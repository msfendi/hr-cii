<?php

namespace App\Http\Controllers;

use App\Events\NotificationEvent;
use App\Jobs\GeneratePayrollExport;
use App\Models\PayrollApprove;
use App\Models\PayrollSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayrollApproveController extends Controller
{
    public function index(Request $request)
    {
        // =========================
        // FILTER STATUS PERIOD
        // =========================
        $filter = $request->get('status', 'open');

        // =========================
        // JOIN PAYROLL RUN + PERIOD + EXPORT
        // =========================
        $query = PayrollApprove::query()
            ->join('payroll_runs', 'payroll_approve.payroll_run_id', '=', 'payroll_runs.id')
            ->join('payroll_periods', 'payroll_runs.period_id', '=', 'payroll_periods.id')
            ->leftJoin('payroll_exports', 'payroll_exports.run_id', '=', 'payroll_runs.id')
            ->select(
                'payroll_approve.*',
                'payroll_periods.name as period_name',
                'payroll_exports.file_bank_active',
                'payroll_exports.file_bank_resign',
                'payroll_exports.file_bank_mangkir',
                'payroll_exports.file_excel',
                'payroll_exports.file_pdf',
                'payroll_exports.file_peng',
                'payroll_exports.status as export_status'
            );

        if ($filter === 'open') {
            $query->where('payroll_periods.is_closed', false);
        }

        if ($filter === 'closed') {
            $query->where('payroll_periods.is_closed', true);
        }

        $data = $query
            ->latest('payroll_approve.id')
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
        $data->transform(function ($row) use ($employees) {

            $row->is_exported = !empty($row->file_excel) && !empty($row->file_pdf);

            $row->progress = collect($row->progress)->map(function ($p) use ($employees) {

                $npkList = is_array($p['npk'])
                    ? $p['npk']
                    : json_decode($p['npk'], true);

                if (!is_array($npkList)) $npkList = [];

                $p['users'] = collect($npkList)->map(fn($npk) => [
                    'npk' => $npk,
                    'name' => $employees[$npk]->NAMA_KARYAWAN ?? '-',
                ]);

                return $p;
            });

            return $row;
        });

        return view('payroll_approve.index', compact('data', 'filter'));
    }

    // 🔹 Create approval dari setting
    public function store($payroll_run_id)
    {
        $settings = PayrollSetting::orderBy('level')->get();

        $approvals = $settings->pluck('approval')->toArray();

        $progress = collect($approvals)->map(function ($npk) {
            return [
                'npk' => $npk,
                'status' => 'pending',
            ];
        });

        return PayrollApprove::create([
            'payroll_run_id' => $payroll_run_id,
            'approval' => $approvals,
            'progress' => $progress,
            'status' => 'pending',
        ]);
    }

    // 🔹 Approve oleh user yang sedang login
    public function approve(Request $request, $id)
    {
        $user = Auth::user();

        // NPK diambil dari user login (bukan dari request) supaya tidak bisa approve atas nama orang lain
        $npkLogin = $user->npk ?? null;

        if (empty($npkLogin)) {
            return response()->json(['message' => 'NPK user tidak ditemukan'], 403);
        }

        $outcome = DB::transaction(function () use ($id, $npkLogin) {

            // lock row supaya double klik / dua approver bersamaan tidak saling timpa
            $data = PayrollApprove::lockForUpdate()->findOrFail($id);

            $export = DB::table('payroll_exports')
                ->leftJoin('payroll_runs', 'payroll_runs.id', '=', 'payroll_exports.run_id')
                ->leftJoin('payroll_periods', 'payroll_periods.id', '=', 'payroll_runs.period_id')
                ->where('payroll_exports.run_id', $data->payroll_run_id)
                ->select('payroll_exports.id', 'payroll_periods.name')
                ->first();

            if (!$export) {
                return ['error' => ['Export payroll belum dibuat, generate export terlebih dahulu', 422]];
            }

            $progress = collect($data->progress);
            $approvedAt = collect($data->approved_at ?? []);

            $currentIndex = $progress->search(function ($item) {
                return $item['status'] === 'pending' || str_contains($item['status'], 'waiting');
            });

            if ($currentIndex === false) {
                return ['error' => ['Semua sudah approve', 400]];
            }

            $row = $progress[$currentIndex];

            $npkList = is_array($row['npk']) ? $row['npk'] : json_decode($row['npk'], true);

            if (!is_array($npkList)) {
                return ['error' => ['Format NPK invalid', 500]];
            }

            // decode status per approver
            if ($row['status'] === 'pending') {
                $statusList = array_fill(0, count($npkList), 'waiting');
            } else {
                $statusList = json_decode($row['status'], true);
            }

            $userIndex = array_search($npkLogin, $npkList);

            if ($userIndex === false) {
                return ['error' => ['Anda bukan approver', 403]];
            }

            if ($statusList[$userIndex] === 'approve') {
                return ['error' => ['Sudah approve', 400]];
            }

            // update status
            $statusList[$userIndex] = 'approve';

            $approvedAtArr = $approvedAt->toArray();
            $approvedAtArr[$currentIndex][$userIndex] = now();

            // cek level selesai
            $allApproved = collect($statusList)->every(fn($s) => $s === 'approve');

            $progressArr = $progress->toArray();
            $progressArr[$currentIndex]['status'] = $allApproved
                ? 'approve'
                : json_encode($statusList);

            $progress = collect($progressArr);

            // cek final
            $finalApprove = $progress->every(fn($item) => $item['status'] === 'approve');

            $data->update([
                'progress' => $progress->values(),
                'approved_at' => collect($approvedAtArr)->values(),
                'status' => $finalApprove ? 'finish' : 'pending',
            ]);

            return [
                'export' => $export,
                'final' => $finalApprove,
            ];
        });

        if (isset($outcome['error'])) {
            return response()->json(['message' => $outcome['error'][0]], $outcome['error'][1]);
        }

        $export = $outcome['export'];

        event(new NotificationEvent(
            'Payroll Approval!',
            'Users : ' . $user->name . ' has been approve Payroll ' . $export->name . '!',
            'success'
        ));

        // =========================
        // FINAL APPROVE -> job export (PDF + Excel + CSV bank) dijalankan di queue
        // File bank & penutupan periode sekarang ditangani GeneratePayrollExport
        // =========================
        if ($outcome['final']) {
            event(new NotificationEvent(
                'Payroll Approval!',
                'Payroll ' . $export->name . ' has been approved!',
                'success'
            ));

            GeneratePayrollExport::dispatch($export->id, 'approve');
        }

        return response()->json([
            'message' => 'Approval berhasil',
        ]);
    }
}