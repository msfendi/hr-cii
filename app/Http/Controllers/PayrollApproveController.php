<?php

namespace App\Http\Controllers;

use App\Models\PayrollApprove;
use App\Models\PayrollSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

class PayrollApproveController extends Controller
{

    public function index()
    {
        // =========================
        // JOIN PAYROLL RUN + PERIOD + EXPORT
        // =========================
        $data = PayrollApprove::query()
            ->join('payroll_runs', 'payroll_approve.payroll_run_id', '=', 'payroll_runs.id')
            ->join('payroll_periods', 'payroll_runs.period_id', '=', 'payroll_periods.id')
            ->leftJoin('payroll_exports', 'payroll_exports.run_id', '=', 'payroll_runs.id') // 🔥 tambahan
            ->select(
                'payroll_approve.*',
                'payroll_periods.name as period_name',
                'payroll_exports.file_bank',
                'payroll_exports.file_excel',
                'payroll_exports.file_pdf'
            )
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

        return view('payroll_approve.index', compact('data'));
    }
    // 🔹 Create approval dari setting
    public function store($payroll_run_id)
    {
        $settings = PayrollSetting::orderBy('level')->get();

        $approvals = $settings->pluck('approval')->toArray();

        $progress = collect($approvals)->map(function ($npk) {
            return [
                'npk' => $npk,
                'status' => 'pending'
            ];
        });

        return PayrollApprove::create([
            'payroll_run_id' => $payroll_run_id,
            'approval' => $approvals,
            'progress' => $progress,
            'status' => 'pending'
        ]);
    }

    // 🔹 Approve by NPK
    public function approve(Request $request, $id)
    {
        $data = PayrollApprove::findOrFail($id);
        $npkLogin = $request->npk;

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

        // =========================
        // 🔥 AUTO GENERATE BANK
        // =========================
        if ($finalApprove) {
            $this->generateBank($data->payroll_run_id); // 🔥 pakai run_id
        }

        return response()->json([
            'message' => 'Approval berhasil'
        ]);
    }

    public function generateBank($runId)
    {
        // =========================
        // VALIDASI APPROVAL
        // =========================
        $approve = DB::table('payroll_approve')
            ->where('payroll_run_id', $runId)
            ->first();

        if (!$approve || $approve->status !== 'finish') {
            return false;
        }

        // =========================
        // AMBIL NAMA PERIODE
        // =========================
        $period = DB::table('payroll_runs')
            ->join('payroll_periods', 'payroll_runs.period_id', '=', 'payroll_periods.id')
            ->where('payroll_runs.id', $runId)
            ->value('payroll_periods.name');

        // =========================
        // AMBIL DATA GAJI
        // =========================
        $data = DB::table('payroll_run_details as pd')
            ->leftJoin('payroll_masters as pm', 'pm.npk', '=', 'pd.employee_npk')
            ->where('pd.run_id', $runId)
            ->whereRaw("LOWER(pm.bank_name) = 'permata'")
            ->select(
                'pd.employee_npk',
                'pd.employee_name',
                'pm.bank_account',
                'pd.total_salary'
            )
            ->get();

        // =========================
        // CSV CONTENT
        // =========================
        $rows = [];

        $rows[] = [
            'No Rekening Tujuan',
            'Nama Penerima',
            'Bank',
            'Kode Bank',
            'Nominal',
            'Keterangan'
        ];

        foreach ($data as $d) {

            if (empty($d->bank_account) || $d->total_salary <= 0) continue;

            $rows[] = [
                $d->bank_account,
                strtoupper($d->employee_name),
                'PERMATA',
                '013',
                number_format($d->total_salary, 0, '', ''),
                'GAJI ' . strtoupper($period)
            ];
        }

        // =========================
        // NAMA FILE
        // =========================
        $cleanPeriod = str_replace(' ', '_', $period);
        $filename = "PERMATA_{$cleanPeriod}.csv";
        $path = "payroll/{$filename}";

        // =========================
        // SIMPAN FILE
        // =========================
        $handle = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('public')->put($path, $content);

        // =========================
        // UPDATE payroll_exports
        // =========================
        DB::table('payroll_exports')
            ->updateOrInsert(
                ['run_id' => $runId],
                ['file_bank' => $path]
            );

        return true;
    }
}
