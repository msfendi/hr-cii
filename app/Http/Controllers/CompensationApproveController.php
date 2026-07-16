<?php

namespace App\Http\Controllers;

use App\Events\NotificationEvent;
use App\Jobs\GenerateCompensation;
use App\Jobs\GenerateCompensationExport;
use App\Models\CompensationApprove;
use App\Models\CompensationSetting;
use App\Models\PayrollSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use RealRashid\SweetAlert\Facades\Alert;
use App\Services\PayrollRoleFilterService;

class CompensationApproveController extends Controller
{
    /**
     * Ambil payroll_role user login LANGSUNG dari tabel role_payrolls
     * (bukan dari role auth/spatie). Jika user tidak terdaftar di
     * role_payrolls, return null.
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

    public function index(Request $request)
    {
        $user = Auth::user();
        $role = $this->getUserPayrollRole($user);

        // =========================
        // FILTER STATUS PERIOD
        // =========================
        $filter = $request->get('status', 'open');

        $query = CompensationApprove::query()
            ->join('compensations', 'compensations.id', '=', 'compensation_approve.run_id')
            ->select(
                'compensations.*',
                'compensation_approve.approval',
                'compensation_approve.progress',
                'compensation_approve.approved_at',
                'compensation_approve.status as approval_status'
            );


        if ($filter === 'open') {
            $query->where('compensations.is_closed', false);
        }

        if ($filter === 'closed') {
            $query->where('compensations.is_closed', true);
        }

        $data = $query
            ->latest('compensation_approve.id')
            ->get();


        // =========================
        // UNION BIODATA + BIODATA_KELUAR
        // =========================
        $employees = collect(DB::select("
        SELECT NPK, NAMA_KARYAWAN FROM BIODATA
        UNION
        SELECT NPK, NAMA_KARYAWAN FROM BIODATA_KELUAR
    "))->keyBy('NPK');

        // =========================
        // MAPPING NAMA + FLAG EXPORT
        // =========================
        // dd($data);
        $data->transform(function ($row) use ($employees) {

            // 🔥 FLAG EXPORT
            $row->is_exported = !empty($row->file_excel) && !empty($row->file_pdf);

            $progress = collect($row->progress)->map(function ($p) use ($employees) {

                $npkList = is_array($p['npk'])
                    ? $p['npk']
                    : json_decode($p['npk'], true);

                if (!is_array($npkList)) $npkList = [];

                $names = collect($npkList)->map(function ($npk) use ($employees) {
                    return [
                        'npk' => $npk,
                        'name' => $employees[$npk]->NAMA_KARYAWAN ?? '-'
                    ];
                });

                $p['users'] = $names;

                return $p;
            });

            $row->progress = $progress;

            return $row;
        });

        // Admin -> null (lihat semua file/role). Role lain (termasuk Payroll_ALL)
        // di-scope ke role key-nya masing-masing, supaya Payroll_ALL cuma lihat
        // file berkategori Payroll_ALL saja (bukan semua kategori).
        $fileRoleKey = $role;

        return view('compensation_approve.index', compact('data', 'filter', 'fileRoleKey'));
    }
    // 🔹 Create approval dari setting
    public function store($run_id)
    {
        $settings = PayrollSetting::orderBy('level')->get();

        $approvals = $settings->pluck('approval')->toArray();

        $progress = collect($approvals)->map(function ($npk) {
            return [
                'npk' => $npk,
                'status' => 'pending'
            ];
        });

        return CompensationApprove::create([
            'run_id' => $run_id,
            'approval' => $approvals,
            'progress' => $progress,
            'status' => 'pending'
        ]);
    }

    // 🔹 Approve by NPK
    public function approve($id)
    {
        $user = Auth::user();
        $data = CompensationApprove::where('run_id', $id)->first();
        if (!$data) {
            return response()->json([
                'message' => 'Data approval tidak ditemukan'
            ], 404);
        }
        $npkLogin = $user->npk;

        $export = DB::table('compensations')->select('compensations.id', 'compensations.cutoff_date')->where('id', $data->run_id)->first();
        $periodName = Carbon::parse($export->cutoff_date)->translatedFormat('F_Y');
        $progress = collect($data->progress);
        $approvedAt = collect($data->approved_at ?? []);

        $currentIndex = $progress->search(function ($item) {
            return $item['status'] === 'pending' || str_contains($item['status'], 'waiting');
        });

        if ($currentIndex === false) {
            return response()->json(['message' => 'Semua sudah approve'], 400);
        }

        $row = $progress[$currentIndex];

        $npkList = is_array($row['npk']) ? $row['npk'] : json_decode($row['npk'], true);

        if (!is_array($npkList)) {
            return response()->json(['message' => 'Format NPK invalid'], 500);
        }

        // status decode
        if ($row['status'] === 'pending') {
            $statusList = array_fill(0, count($npkList), 'waiting');
        } else {
            $statusList = json_decode($row['status'], true);
        }

        $userIndex = array_search($npkLogin, $npkList);

        // dd($user->npk, $data, $export, $progress, $approvedAt, $currentIndex, $row, $npkLogin, $userIndex);

        if ($userIndex === false) {
            return response()->json(['message' => 'Anda bukan approver'], 403);
        }

        if ($statusList[$userIndex] === 'approve') {
            return response()->json(['message' => 'Sudah approve'], 400);
        }

        // update status
        $statusList[$userIndex] = 'approve';

        $approvedAtArr = $approvedAt->toArray();
        $approvedAtArr[$currentIndex][$userIndex] = now();

        // cek level selesai
        $allApproved = collect($statusList)->every(fn($s) => $s === 'approve');

        $progressArr = $progress->toArray();

        if ($allApproved) {
            $progressArr[$currentIndex]['status'] = 'approve';
        } else {
            $progressArr[$currentIndex]['status'] = json_encode($statusList);
        }

        $progress = collect($progressArr);
        $approvedAt = collect($approvedAtArr);

        // cek final
        $finalApprove = $progress->every(fn($item) => $item['status'] === 'approve');

        $data->update([
            'progress' => $progress->values(),
            'approved_at' => $approvedAt->values(),
            'status' => $finalApprove ? 'finish' : 'pending'
        ]);


        event(new NotificationEvent(
            'Compensation Approval!',
            'Users : ' . $user->name . ' has been approve Compensation ' . $periodName . '!',
            'success'
        ));

        // =========================
        // 🔥 AUTO GENERATE BANK
        // =========================
        if ($finalApprove) {
            event(new NotificationEvent(
                'Compensation Approval!',
                'Compensation ' . $periodName . ' has been approved!',
                'success'
            ));

            GenerateCompensation::dispatch($export->cutoff_date, $export->id, 'approve');
            $this->createCompensationCSV($export->cutoff_date);
        }

        return response()->json([
            'message' => 'Approval berhasil'
        ]);
    }

    public function createCompensationCSV($generate_date)
    {
        if (!$generate_date) {
            Alert::warning('Warning', 'Date Must be Filled!');
            return back();
        }

        $cutoff     = Carbon::parse($generate_date);
        $day        = $cutoff->day; // 7 atau 20
        $period     = $cutoff->format('F_Y'); // contoh: July_2026
        $periodName = $cutoff->translatedFormat('F_Y');
        $folder     = "public/compensations/$period";

        /*
    |--------------------------------------------------------------------------
    | UNION BIODATA + BIODATA_KELUAR (nama + IS_STAFF utk role mapping)
    |--------------------------------------------------------------------------
    */
        $bioUnion = DB::table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'IS_STAFF')
            ->unionAll(
                DB::table('BIODATA_KELUAR')
                    ->select('NPK', 'NAMA_KARYAWAN', 'IS_STAFF')
            );

        /*
    |--------------------------------------------------------------------------
    | QUERY DATA KOMPENSASI + REKENING DARI payroll_masters
    |--------------------------------------------------------------------------
    */
        $data = DB::table('compensation_details as cd')
            ->leftJoinSub($bioUnion, 'bio', fn($j) => $j->on('bio.NPK', '=', 'cd.npk'))
            ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'cd.id_dept')
            ->leftJoin('payroll_masters as pm', 'pm.npk', '=', 'cd.npk')
            ->whereDate('cd.cutoff_date', $cutoff->toDateString())
            ->where('cd.is_active', '=', '1')
            ->select(
                'cd.npk',
                'cd.amount',
                'pm.bank_account',
                'bio.NAMA_KARYAWAN',
                'bio.IS_STAFF',
                'd.IS_SEWING'
            )
            ->orderBy('bio.NAMA_KARYAWAN')
            ->get();

        if ($data->isEmpty()) {
            Alert::warning('Warning', 'No compensation data to export!');
            return back();
        }

        /*
    |--------------------------------------------------------------------------
    | GROUP BY ROLE_PAYROLL
    | Sama seperti split PDF/Excel di GenerateCompensation, supaya file_csv
    | punya struktur JSON {ROLE_KEY: filename} & folder yang konsisten dgn
    | file_pdf / file_excel (.../{period}/{ROLE}/{day}/...).
    |--------------------------------------------------------------------------
    */
        $categories = [
            PayrollRoleFilterService::ROLE_ALL       => fn($r) => true,
            PayrollRoleFilterService::ROLE_STAFF     => fn($r) => ($r->IS_STAFF ?? 0) == 1,
            PayrollRoleFilterService::ROLE_NONSTAFF  => fn($r) => ($r->IS_STAFF ?? 0) == 0,
            PayrollRoleFilterService::ROLE_SEWING    => fn($r) => ($r->IS_STAFF ?? 0) == 0 && ($r->IS_SEWING ?? 0) == 0,
            PayrollRoleFilterService::ROLE_NONSEWING => fn($r) => ($r->IS_STAFF ?? 0) == 0 && ($r->IS_SEWING ?? 0) == 1,
        ];

        $csvFileMap = [];

        foreach ($categories as $roleKey => $filter) {

            $rows = $data->filter($filter)->values();

            if ($rows->isEmpty()) {
                continue; // skip, tidak buat file kosong
            }

            $roleSlug  = strtolower(str_replace('Payroll_', '', $roleKey));
            $subFolder = PayrollRoleFilterService::folder($roleKey); // '' utk ALL, 'STAFF/' dst
            $roleBase  = $subFolder ? rtrim("$folder/$subFolder", '/') : $folder;
            $roleFolder = "$roleBase/$day";

            $fileName = "COMPENSATION_{$period}_{$roleSlug}.csv";
            $path     = "$roleFolder/$fileName";

            $this->createCompensationBankCSV($rows, $periodName, $path);

            $csvFileMap[$roleKey] = $fileName;
        }

        DB::table('compensations')->updateOrInsert(
            ['cutoff_date' => $generate_date],
            [
                'file_csv'  => json_encode($csvFileMap),
                'is_closed' => 1,
            ]
        );

        Alert::success('Success', 'Compensations Recap Succesfully Generated!');
        return back();
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATE CSV BANK PER ROLE
    | Format kolom disamakan persis dengan createBankCSV() di
    | PayrollApproveController supaya bisa langsung dipakai upload bank.
    |--------------------------------------------------------------------------
    */
    private function createCompensationBankCSV($rows, $periodName, $path)
    {
        $handle = fopen('php://temp', 'r+');

        foreach ($rows as $row) {

            if (empty($row->bank_account) || $row->amount <= 0) {
                continue;
            }

            fputcsv($handle, [
                'PERMATA',                                              // A
                '',                                                     // B
                '',                                                     // C
                '',                                                     // D
                strtoupper(trim($row->NAMA_KARYAWAN ?? '-')),            // E
                $row->bank_account ?? '',                                // F
                'IDR',                                                   // G
                number_format($row->amount ?? 0, 0, '', ''),             // H
                'KOMPENSASI ' . strtoupper($periodName),                 // I
                '',                                                     // J
                '',                                                     // K
                'OVB',                                                   // L
                0,                                                      // M
                0,                                                      // N
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        Storage::put($path, $content);
    }
}
