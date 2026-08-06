<?php

namespace App\Http\Controllers;

use App\Events\NotificationEvent;
use App\Jobs\GeneratePayrollExport;
use App\Models\PayrollApprove;
use App\Models\PayrollSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

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
            ->leftJoin('payroll_exports', 'payroll_exports.run_id', '=', 'payroll_runs.id') // 🔥 tambahan
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
        $user = Auth::user();
        $data = PayrollApprove::findOrFail($id);
        $npkLogin = $request->npk;

        $export = DB::table('payroll_exports')->select('payroll_exports.id', 'payroll_periods.name')->leftJoin('payroll_runs', 'payroll_runs.id', '=', 'payroll_exports.run_id')->leftJoin('payroll_periods', 'payroll_periods.id', '=', 'payroll_runs.period_id')->where('run_id', $data->payroll_run_id)->first();
        // dd($export);
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


        event(new NotificationEvent(
            'Payroll Approval!',
            'Users : ' . $user->name . ' has been approve Payroll ' . $export->name . '!',
            'success'
        ));

        // =========================
        // 🔥 AUTO GENERATE BANK
        // =========================
        if ($finalApprove) {
            event(new NotificationEvent(
                'Payroll Approval!',
                'Payroll ' . $export->name . ' has been approved!',
                'success'
            ));

            GeneratePayrollExport::dispatch($export->id, 'approve');
            $this->generateBank($data->payroll_run_id, $export->id); // 🔥 pakai run_id dan export_id
        }

        return response()->json([
            'message' => 'Approval berhasil'
        ]);
    }

    public function generateBank($runId, $exportId)
    {
        /*
    |--------------------------------------------------------------------------
    | VALIDASI APPROVAL
    |--------------------------------------------------------------------------
    */
        $approve = DB::table('payroll_approve')
            ->where('payroll_run_id', $runId)
            ->first();

        if (!$approve || $approve->status !== 'finish') {
            return false;
        }

        /*
    |--------------------------------------------------------------------------
    | PERIODE
    |--------------------------------------------------------------------------
    */
        $period = DB::table('payroll_runs as pr')
            ->join('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->where('pr.id', $runId)
            ->select('pp.name', 'pp.start_date', 'pp.end_date', 'pp.id')
            ->first();

        if (!$period) {
            return false;
        }

        /*
    |--------------------------------------------------------------------------
    | EMPLOYEE UNION (AKTIF + RESIGN) + PKWT
    |--------------------------------------------------------------------------
    */
        $aktif = DB::table('BIODATA as b')
            ->leftJoin('PKWT as p', 'b.NPK', '=', 'p.NPK')
            ->select('b.NPK', 'b.id_dept', 'p.TKK', 'p.TMK', 'b.IS_STAFF', 'p.KETERANGAN');

        $keluar = DB::table('BIODATA_KELUAR as b')
            ->leftJoin('PKWT as p', 'b.NPK', '=', 'p.NPK')
            ->select('b.NPK', 'b.id_dept', 'p.TKK', 'p.TMK', 'b.IS_STAFF', 'p.KETERANGAN');

        $union = $aktif->union($keluar);

        /*
    |--------------------------------------------------------------------------
    | BASE QUERY PAYROLL
    |--------------------------------------------------------------------------
    */
        $baseQuery = DB::table('payroll_run_details as prd')
            ->leftJoinSub($union, 'bio', function ($join) {
                $join->on('bio.NPK', '=', 'prd.employee_npk');
            })
            ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'prd.employee_dept')
            ->leftJoinSub(
                DB::table('payroll_masters')->select('npk', 'bank_name', 'bank_account'),
                'pm',
                function ($join) {
                    $join->on('pm.npk', '=', 'prd.employee_npk')
                        ->whereRaw("LOWER(pm.bank_name) = 'permata bank'");
                }
            )
            ->where('prd.run_id', $runId)
            ->select(
                'prd.employee_npk',
                'prd.employee_name',
                'prd.total_salary',
                'pm.bank_account',
                'd.DEPARTEMENT',
                'd.IS_SEWING',
                'bio.IS_STAFF',
                'bio.TKK',
                'bio.TMK',
                'bio.KETERANGAN'
            )
            ->orderBy('d.DEPARTEMENT')
            ->orderBy('prd.employee_npk');

        /*
    |--------------------------------------------------------------------------
    | ACTIVE
    | isActive = is_null(TKK) || (TKK > end_date) || (TMK between start & end)
    |--------------------------------------------------------------------------
    */
        $activeEmployees = (clone $baseQuery)
            ->where(function ($query) use ($period) {
                $query->whereNull('bio.TKK')
                    ->orWhere('bio.TKK', '>', $period->end_date);
            })
            ->get();

        /*
    |--------------------------------------------------------------------------
    | RESIGN
    | isResign = !is_null(TKK) && TKK between period && KETERANGAN <> 'MA'
    |            && !isTMKInPeriod
    |--------------------------------------------------------------------------
    */
        $resignEmployees = (clone $baseQuery)
            ->where(function ($query) use ($period) {
                $query->whereNotNull('bio.TKK')
                    ->whereBetween('bio.TKK', [$period->start_date, $period->end_date])
                    ->where(function ($q) {
                        $q->whereNull('bio.KETERANGAN')
                            ->orWhereRaw('UPPER(LTRIM(RTRIM(bio.KETERANGAN))) <> ?', ['MA']);
                    });
            })
            ->get();

        /*
    |--------------------------------------------------------------------------
    | MANGKIR
    | sama seperti RESIGN, hanya KETERANGAN = 'MA'
    |--------------------------------------------------------------------------
    */
        $mangkirEmployees = (clone $baseQuery)
            ->where(function ($query) use ($period) {
                $query->whereNotNull('bio.TKK')
                    ->whereBetween('bio.TKK', [$period->start_date, $period->end_date])
                    ->whereRaw('UPPER(LTRIM(RTRIM(bio.KETERANGAN))) = ?', ['MA']);
            })
            ->get();

        if ($activeEmployees->isEmpty() && $resignEmployees->isEmpty() && $mangkirEmployees->isEmpty()) {
            return false;
        }

        $cleanPeriod = str_replace(' ', '_', strtoupper($period->name));

        /*
    |--------------------------------------------------------------------------
    | GENERATE CSV UMUM (SEMUA ROLE DIGABUNG)
    | Disimpan langsung di payroll/{PERIODE}/, TIDAK masuk folder role
    |--------------------------------------------------------------------------
    */
        $activeFileName  = "PERMATA_{$cleanPeriod}_AKTIF.csv";
        $resignFileName  = "PERMATA_{$cleanPeriod}_RESIGN.csv";
        $mangkirFileName = "PERMATA_{$cleanPeriod}_MANGKIR.csv";

        $activePath  = "payroll/{$cleanPeriod}/{$activeFileName}";
        $resignPath  = "payroll/{$cleanPeriod}/{$resignFileName}";
        $mangkirPath = "payroll/{$cleanPeriod}/{$mangkirFileName}";

        $this->createBankCSV($activeEmployees, $period->name, $activePath);
        $this->createBankCSV($resignEmployees, $period->name, $resignPath);
        $this->createBankCSV($mangkirEmployees, $period->name, $mangkirPath);

        // 🔥 Map nama file per status, key 'ALL' = file gabungan semua role.
        // Kalau ada split, value-nya diganti jadi array of filename oleh loop di bawah.
        $bankFilesByStatus = [
            'AKTIF'   => ['ALL' => $activeFileName],
            'RESIGN'  => ['ALL' => $resignFileName],
            'MANGKIR' => ['ALL' => $mangkirFileName],
        ];

        /*
    |--------------------------------------------------------------------------
    | FILTER PER ROLE
    |--------------------------------------------------------------------------
    | STAFF, SEWING, NON SEWING = eksklusif satu sama lain.
    | NON STAFF = union SEWING + NON SEWING (semua karyawan IS_STAFF=0,
    |             tanpa peduli IS_SEWING-nya), dipakai untuk file bank
    |             konsolidasi non-staff. Makanya karyawan bisa muncul di
    |             folder SEWING/NON_SEWING sekaligus di folder NON_STAFF.
    |--------------------------------------------------------------------------
    */
        $roleFilters = [
            'STAFF'      => fn($e) => (int) $e->IS_STAFF === 1,
            'SEWING'     => fn($e) => (int) $e->IS_STAFF === 0 && !is_null($e->IS_SEWING) && (int) $e->IS_SEWING === 0,
            'NON SEWING' => fn($e) => (int) $e->IS_STAFF === 0 && !is_null($e->IS_SEWING) && (int) $e->IS_SEWING === 1,
            'NON STAFF'  => fn($e) => (int) $e->IS_STAFF === 0,
        ];

        $sourceByStatus = [
            'AKTIF'   => $activeEmployees,
            'RESIGN'  => $resignEmployees,
            'MANGKIR' => $mangkirEmployees,
        ];

        /*
    |--------------------------------------------------------------------------
    | GENERATE CSV PER ROLE FOLDER
    | Disimpan di payroll/{PERIODE}/{ROLE}/
    |--------------------------------------------------------------------------
    */
        // 🔥 Role yang wajib di-split berdasarkan maksimum total_salary per file
        $splitRoles      = ['STAFF', 'NON STAFF'];
        $maxTotalPerFile = 3000000000; // 3 Milyar

        foreach ($roleFilters as $role => $filter) {
            $roleFolder = str_replace(' ', '_', $role); // NON_SEWING, NON_STAFF, dst
            $basePath   = "payroll/{$cleanPeriod}/{$roleFolder}";

            foreach ($sourceByStatus as $statusLabel => $source) {
                $employees = $source->filter($filter)->values();

                if ($employees->isEmpty()) {
                    continue; // skip, tidak buat file kosong
                }

                if (in_array($role, $splitRoles)) {
                    // 🔥 SPLIT CSV: total_salary per file maksimum $maxTotalPerFile
                    $chunks    = $this->splitByMaxTotalSalary($employees, $maxTotalPerFile);
                    $partNames = [];

                    foreach ($chunks as $index => $chunkEmployees) {
                        $part = $index + 1;

                        $roleFileName = count($chunks) > 1
                            ? "PERMATA_{$cleanPeriod}_{$roleFolder}_{$statusLabel}_PART{$part}.csv"
                            : "PERMATA_{$cleanPeriod}_{$roleFolder}_{$statusLabel}.csv";

                        $roleFilePath = "{$basePath}/{$roleFileName}";

                        $this->createBankCSV($chunkEmployees, $period->name, $roleFilePath);

                        $partNames[] = $roleFileName;
                    }

                    // 🔥 1 part = simpan sebagai string biasa, lebih dari 1 = simpan sebagai array
                    $bankFilesByStatus[$statusLabel][$roleFolder] = count($partNames) > 1
                        ? $partNames
                        : $partNames[0];
                } else {
                    $roleFileName = "PERMATA_{$cleanPeriod}_{$roleFolder}_{$statusLabel}.csv";
                    $roleFilePath = "{$basePath}/{$roleFileName}";

                    $this->createBankCSV($employees, $period->name, $roleFilePath);

                    $bankFilesByStatus[$statusLabel][$roleFolder] = $roleFileName;
                }
            }
        }

        /*
    |--------------------------------------------------------------------------
    | UPDATE EXPORT TABLE
    |--------------------------------------------------------------------------
    | file_bank_active / file_bank_resign / file_bank_mangkir disimpan sebagai
    | JSON per role (mirip format file_compensation), contoh:
    | {"ALL":"PERMATA_JULI_2026_AKTIF.csv",
    |  "STAFF":"PERMATA_JULI_2026_STAFF_AKTIF.csv",
    |  "NON_STAFF":["PERMATA_JULI_2026_NON_STAFF_AKTIF_PART1.csv","PERMATA_JULI_2026_NON_STAFF_AKTIF_PART2.csv"]}
    |--------------------------------------------------------------------------
    */
        DB::table('payroll_exports')->updateOrInsert(
            ['run_id' => $runId],
            [
                'file_bank_active'  => json_encode($bankFilesByStatus['AKTIF']),
                'file_bank_resign'  => json_encode($bankFilesByStatus['RESIGN']),
                'file_bank_mangkir' => json_encode($bankFilesByStatus['MANGKIR']),
            ]
        );

        DB::table('payroll_periods')->updateOrInsert(
            ['id' => $period->id],
            [
                'is_closed' => 1
            ]
        );

        return true;
    }


    // |--------------------------------------------------------------------------
    // | SPLIT EMPLOYEES BY MAX TOTAL_SALARY PER FILE
    // |--------------------------------------------------------------------------
    // | Karyawan diakumulasi sesuai urutan (dept, npk) ke dalam 1 file selama
    // | total_salary belum melebihi $maxTotal. Begitu penambahan karyawan
    // | berikutnya membuat total melebihi batas, file baru dibuka.
    // | Contoh: total 8.000.000, max 3.000.000 -> otomatis jadi 3 file.
    // |--------------------------------------------------------------------------
    // */
    private function splitByMaxTotalSalary($employees, $maxTotal)
    {
        $chunks       = [];
        $currentChunk = collect();
        $currentTotal = 0;

        foreach ($employees as $emp) {
            $salary = (float) ($emp->total_salary ?? 0);

            // kalau nambahin karyawan ini bikin total melebihi batas,
            // tutup chunk yang sekarang dan mulai chunk baru
            if ($currentChunk->isNotEmpty() && ($currentTotal + $salary) > $maxTotal) {
                $chunks[]     = $currentChunk;
                $currentChunk = collect();
                $currentTotal = 0;
            }

            $currentChunk->push($emp);
            $currentTotal += $salary;
        }

        if ($currentChunk->isNotEmpty()) {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    private function createBankCSV($employees, $periodName, $path)
    {
        $handle = fopen('php://temp', 'r+');

        foreach ($employees as $emp) {

            // if (empty($emp->bank_account) || $emp->total_salary <= 0) {
            //     continue;
            // }

            fputcsv($handle, [
                'PERMATA',                                          // A
                '',                                                 // B
                '',                                                 // C
                '',                                                 // D
                strtoupper(trim($emp->employee_name)),                    // E
                $emp->bank_account ?? '',                           // F
                'IDR',                                               // G
                number_format($emp->total_salary ?? 0, 0, '', ''),   // H
                'GAJI ' . strtoupper($periodName),                  // I
                '',                                                 // J
                '',                                                 // K
                'OVB',                                              // L
                0,                                                  // M
                0,                                                  // N
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('public')->put($path, $content);
    }
}
