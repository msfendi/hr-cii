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

class CompensationApproveController extends Controller
{

    public function index(Request $request)
    {

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
            )
            ->where('compensations.is_closed', '=', '0');


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

        return view('compensation_approve.index', compact('data', 'filter'));
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
        } else {
            $cutoff = Carbon::parse($generate_date);

            $folderTarget = $cutoff->format('F_Y'); // contoh: May_2026
            $periodName   = $cutoff->translatedFormat('F_Y');
            $fileName     = 'Compensations ' . $cutoff->format('d M Y') . '.csv';

            $path = "public/compensations/$folderTarget/$fileName";

            /*
    |--------------------------------------------------------------------------
    | UNION BIODATA + BIODATA_KELUAR
    |--------------------------------------------------------------------------
    */
            $employeeUnion = DB::table('BIODATA')
                ->select('NPK', 'NAMA_KARYAWAN')
                ->unionAll(
                    DB::table('BIODATA_KELUAR')
                        ->select('NPK', 'NAMA_KARYAWAN')
                );

            /*
    |--------------------------------------------------------------------------
    | QUERY DATA KOMPENSASI
    |--------------------------------------------------------------------------
    */
            $data = DB::table('compensation_details as cd')
                ->join('payroll_masters as pm', 'pm.npk', '=', 'cd.npk')

                ->joinSub($employeeUnion, 'emp', function ($join) {
                    $join->on('emp.NPK', '=', 'cd.npk');
                })

                ->whereDate('cd.cutoff_date', $cutoff->toDateString())

                ->select(
                    'cd.npk',
                    'cd.amount',
                    'pm.bank_name',
                    'pm.bank_account',
                    'emp.NAMA_KARYAWAN',
                    'cd.is_active'
                )
                ->where('cd.is_active', '=', '1')
                ->orderBy('emp.NAMA_KARYAWAN')
                ->get();

            /*
    |--------------------------------------------------------------------------
    | CREATE CSV STREAM
    |--------------------------------------------------------------------------
    */
            $handle = fopen('php://temp', 'r+');

            /*
    |--------------------------------------------------------------------------
    | HEADER
    |--------------------------------------------------------------------------
    */
            fputcsv($handle, [
                'No Rekening Tujuan',
                'Nama Penerima',
                'Bank',
                'Kode Bank',
                'Nominal',
                'Keterangan'
            ]);

            /*
    |--------------------------------------------------------------------------
    | DATA
    |--------------------------------------------------------------------------
    */
            foreach ($data as $row) {

                if (empty($row->bank_account) || $row->amount <= 0) {
                    continue;
                }

                fputcsv($handle, [
                    $row->bank_account,
                    strtoupper($row->NAMA_KARYAWAN),
                    strtoupper($row->bank_name ?? 'PERMATA'),
                    '013',
                    number_format($row->amount, 0, '', ''),
                    'KOMPENSASI ' . strtoupper($periodName)
                ]);
            }

            /*
    |--------------------------------------------------------------------------
    | SAVE FILE
    |--------------------------------------------------------------------------
    */
            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            Storage::put($path, $content);

            DB::table('compensations')->updateOrInsert(
                ['cutoff_date' => $generate_date],
                [
                    'file_csv' => $fileName,
                    'is_closed' => 1,
                ]
            );

            Alert::success('Success', 'Compensations Recap Succesfully Generated!');
            return back();
        }
    }
}
