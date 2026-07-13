<?php

namespace App\Http\Controllers;

use App\Events\NotificationEvent;
use App\Jobs\GenerateCompensation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\CompensationDetails;
use App\Models\Compensations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use RealRashid\SweetAlert\Facades\Alert;
use App\Services\PayrollRoleFilterService;
use App\Models\RolePayroll;

class CompensationsController extends Controller
{
    /**
     * Ambil payroll_role user login LANGSUNG dari tabel role_payrolls
     * (bukan dari role auth/spatie). Jika user tidak terdaftar di
     * role_payrolls, return null (nanti akan dianggap "no role assigned"
     * oleh PayrollRoleFilterService::isRegistered()).
     *
     * Catatan: jika suatu saat 1 user bisa punya >1 payroll_role, ambil
     * baris terbaru berdasarkan id/created_at.
     */
    private function getUserPayrollRole($user): ?string
    {
        if (!$user) {
            return null;
        }

        return DB::table('role_payrolls')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->value('payroll_role');
    }

    /**
     * Batasi & hitung ulang total_amount / total_employee per compensation
     * sesuai payroll_role user login (persis pola scopePeriodsByRole di
     * PayrollProcessController).
     */
    private function scopeCompensationsByRole($compensations, ?string $role)
    {
        if (PayrollRoleFilterService::isAll($role)) {
            return $compensations;
        }

        if (!PayrollRoleFilterService::isRegistered($role)) {
            return collect(); // belum terdaftar di role_payrolls -> kosong
        }

        $bioUnion = DB::table('BIODATA')
            ->select('NPK', 'IS_STAFF')
            ->unionAll(
                DB::table('BIODATA_KELUAR')->select('NPK', 'IS_STAFF')
            );

        return $compensations->map(function ($comp) use ($role, $bioUnion) {

            $query = DB::table('compensation_details as cd')
                ->leftJoinSub($bioUnion, 'bio', fn($j) => $j->on('bio.NPK', '=', 'cd.npk'))
                ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'cd.id_dept')
                ->whereDate('cd.cutoff_date', $comp->cutoff_date)
                ->where('cd.is_active', 1);

            PayrollRoleFilterService::applyToQuery($query, $role, 'bio.IS_STAFF', 'd.IS_SEWING');

            $scoped = $query
                ->selectRaw('COUNT(DISTINCT cd.npk) as total_employee')
                ->selectRaw('COALESCE(SUM(cd.amount), 0) as total_amount')
                ->first();

            $comp->total_employee = $scoped->total_employee ?? 0;
            $comp->total_amount   = $scoped->total_amount ?? 0;

            return $comp;
        })->values();
    }

    /**
     * Map role_payroll milik user login ke key JSON file di kolom
     * file_pdf / file_excel / file_csv tabel compensations.
     *
     * GenerateCompensation job menyimpan file per role menggunakan key yang
     * PERSIS SAMA dengan PayrollRoleFilterService::ROLE_* (mis. "Payroll_STAFF"),
     * jadi di sini tidak perlu mapping/tebak-tebakan lagi -> tinggal pakai $role
     * apa adanya.
     *
     * null artinya user boleh melihat SEMUA file (Admin & role Payroll_ALL).
     */
    private function roleToFileKey(?string $role): ?string
    {
        // PENTING: role "Payroll_ALL" TETAP di-scope ke key "Payroll_ALL" saja
        // (bukan dianggap "lihat semua kategori"). null (lihat semua file/role)
        // hanya untuk Admin, ditangani terpisah di index().
        return $role;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $role = $this->getUserPayrollRole($user);

        // =========================
        // FILTER STATUS PERIOD
        // =========================
        $filter = $request->get('status', 'open');

        $query = DB::table('compensations as c')
            ->leftJoin('compensation_approve as ca', 'ca.run_id', '=', 'c.id')
            ->select(
                'c.*',
                'ca.status as approve_status'
            );

        if ($filter === 'open') {
            $query->where('c.is_closed', false);
        }

        if ($filter === 'closed') {
            $query->where('c.is_closed', true);
        }

        $compensations = $query
            ->latest('c.id')
            ->get();

        // Batasi/hitung ulang sesuai payroll_role user login
        $compensations = $this->scopeCompensationsByRole($compensations, $role);

        $noRoleAssigned   = !PayrollRoleFilterService::isRegistered($role);
        $payrollRoleLabel = $role ? (RolePayroll::ROLES[$role] ?? $role) : null;

        // Admin & role Payroll_ALL -> null (lihat semua file), selain itu di-scope
        // ke role masing-masing supaya hanya file yang relevan yang tampil.
        $fileRoleKey = $this->roleToFileKey($role);

        // dd($user, $fileRoleKey, $this->roleToFileKey($role));

        return view('compensation.index', compact(
            'compensations',
            'filter',
            'noRoleAssigned',
            'payrollRoleLabel',
            'fileRoleKey'
        ));
    }

    public function destroy($id)
    {
        $comp = Compensations::find($id);

        if (!$comp) {
            return response()->json([
                'message' => 'Data compensation tidak ditemukan.'
            ], 404);
        }

        DB::transaction(function () use ($comp) {
            // Hapus detail berdasarkan cutoff_date
            CompensationDetails::whereDate('cutoff_date', $comp->cutoff_date)->delete();

            // Hapus approval berdasarkan run_id = id compensations
            DB::table('compensation_approve')->where('run_id', $comp->id)->delete();

            // Hapus master compensations
            $comp->delete();
        });

        return response()->json([
            'message' => 'Compensation data has been deleted successfully.'
        ]);
    }

    public function details($date)
    {
        $user = Auth::user();
        $role = $this->getUserPayrollRole($user);

        $bioUnion = DB::table('BIODATA')
            ->select('NPK', 'IS_STAFF')
            ->unionAll(
                DB::table('BIODATA_KELUAR')->select('NPK', 'IS_STAFF')
            );

        $query = DB::table('compensation_details as cd')
            ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'cd.id_dept')
            ->leftJoin('employees_contract as ec', 'ec.id', '=', 'cd.contract_id')
            ->leftJoin('PKWT as p', 'p.NPK', '=', 'cd.npk')
            ->leftJoinSub($bioUnion, 'bio', fn($j) => $j->on('bio.NPK', '=', 'cd.npk'))
            ->whereDate('cd.cutoff_date', $date)
            ->select(
                'cd.id',
                'cd.npk',
                'd.DEPARTEMENT as dept',
                'p.TMK',
                'cd.month_duration',
                'cd.day_duration',
                'cd.end_date',
                'ec.salary',
                'cd.amount',
                'cd.status',
                'cd.is_active'
            );

        if (!($user && $user->hasRole('Admin'))) {
            PayrollRoleFilterService::applyToQuery($query, $role, 'bio.IS_STAFF', 'd.IS_SEWING');
        }

        $data = $query->get();

        return response()->json(['data' => $data]);
    }


    /*
    |--------------------------------------------------------------------------
    | GENERATE COMPENSATION
    |--------------------------------------------------------------------------
    */



    public function generate(Request $request)
    {
        $user = Auth::user();
        $today = Carbon::parse($request->generate_date);
        $periodName = Carbon::parse($today)->translatedFormat('F_Y');

        $exists = Compensations::where('cutoff_date', $today)->exists();

        if ($exists) {
            Alert::error('Gagal', 'Compensation for this period has been generated previously.');
            return redirect()->back();
        }

        $master = Compensations::create([
            'cutoff_date' => $today,
            'status' => 'Waiting Queue',
            'progress' => 0,
            'is_closed' => 0
        ]);

        GenerateCompensation::dispatch(
            $today,
            $master->id,
            'process'
        );

        Alert::success('Success', 'Compensation successfully processed!');
        event(new NotificationEvent(
            'Process Compensations!',
            'Users : ' . $user->name . ' has been process Compensations ' . $periodName . '!',
            'success'
        ));

        return back();
    }

    public function check(Request $request)
    {
        $today = Carbon::parse($request->generate_date);

        $service = new GenerateCompensation(
            $today,
            0,
            'check'
        );

        $data = $service->simulation();

        return $data;
    }

    // public function check($date)
    // {
    //     $today = Carbon::parse($date);

    //     $job = new GenerateCompensation(
    //         $today,
    //         0,
    //         'check'
    //     );

    //     return $job->simulation();
    // }
}
