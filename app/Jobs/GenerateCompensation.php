<?php

namespace App\Jobs;

use App\Helpers\PdfPassword;
use App\Models\CompensationApprove;
use App\Models\Compensations;
use App\Models\CompensationDetails;
use App\Models\PayrollSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use App\Services\PdfService;

class GenerateCompensation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    public $tries = 1;

    protected $generate_date;
    protected $compensation_id;
    protected $type;

    public function __construct($generate_date, $compensation_id, $type)
    {
        $this->generate_date = $generate_date;
        $this->compensation_id = $compensation_id;
        $this->type = $type;
    }

    public function handle()
    {
        ini_set('memory_limit', '4096M');
        set_time_limit(0);

        $today = Carbon::parse($this->generate_date);
        $day   = $today->day;

        /*
        |--------------------------------------------------------------------------
        | ONLY DATE 7 & 20
        |--------------------------------------------------------------------------
        */

        if (!in_array($day, [7, 20])) {
            return;
        }

        $master = Compensations::find($this->compensation_id);

        $master->update([
            'status' => 'Start Processing',
            'progress' => 5
        ]);

        /*
        |--------------------------------------------------------------------------
        | FORMULA
        |--------------------------------------------------------------------------
        */

        $formula = DB::table('payroll_components')
            ->where('code', 'compensation')
            ->value('formula');

        if (!$formula) return;

        /*
        |--------------------------------------------------------------------------
        | LOAD EMPLOYEE
        |--------------------------------------------------------------------------
        */

        $master->update([
            'status' => 'Collecting Employees',
            'progress' => 15
        ]);

        /*
        |--------------------------------------------------------------------------
        | SIGNATURE CACHE
        |--------------------------------------------------------------------------
        */
        $signatures = DB::table('users as u')
            ->leftJoin('signatures as s', 's.user_id', '=', 'u.id')
            ->select('u.npk', 's.signature_img')
            ->get()
            ->keyBy('npk');

        $employeeUnion = DB::table('BIODATA')
            ->select(
                'NPK',
                'NAMA_KARYAWAN',
                'ID_DEPT',
                'BAG'
            )
            ->unionAll(
                DB::table('BIODATA_KELUAR')
                    ->select(
                        'NPK',
                        'NAMA_KARYAWAN',
                        'ID_DEPT',
                        'BAG'
                    )
            );

        $employees = DB::table('employees_contract as ec')

            ->join('payroll_masters as pm', 'pm.npk', '=', 'ec.npk')

            ->leftJoin('PKWT as p', 'p.NPK', '=', 'ec.npk')

            ->leftJoinSub($employeeUnion, 'bio', function ($join) {
                $join->on('bio.NPK', '=', 'ec.npk');
            })

            ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'bio.ID_DEPT')

            ->whereMonth('ec.end_date', $today->month)
            ->whereYear('ec.end_date', $today->year)

            ->select(
                'ec.*',
                'pm.type',
                'p.TKK',

                'bio.NAMA_KARYAWAN as employee_name',
                'bio.ID_DEPT',
                'd.DEPARTEMENT as department'
            )

            ->get();

        // dd($employees);


        /*
        |--------------------------------------------------------------------------
        | FILTER NEAREST DATE
        |--------------------------------------------------------------------------
        */

        $employees = $employees->filter(function ($emp) use ($today, $day) {

            $endDate = Carbon::parse($emp->end_date);

            $date7  = Carbon::create($today->year, $today->month, 7);
            $date20 = Carbon::create($today->year, $today->month, 20);

            $diff7  = abs($endDate->diffInDays($date7, false));
            $diff20 = abs($endDate->diffInDays($date20, false));

            return ($diff7 <= $diff20 ? 7 : 20) === $day;
        });
        // dd($employees);

        DB::beginTransaction();

        try {
            $master->update([
                'status' => 'Calculating Compensation',
                'progress' => 35
            ]);

            foreach ($employees as $emp) {

                // dd($emp);

                $is_contract = $emp->type === 'Contract';
                $is_daily    = $emp->type === 'Daily';

                $endDate = Carbon::parse($emp->end_date);

                $date7  = Carbon::create($today->year, $today->month, 7);
                $date20 = Carbon::create($today->year, $today->month, 20);

                $nearestDate =
                    abs($date7->diffInDays($endDate, false))
                    <=
                    abs($date20->diffInDays($endDate, false))
                    ? $date7
                    : $date20;

                $difference_days =
                    $nearestDate->diffInDays($endDate, false);

                $count_days = 0;

                if ($is_daily) {
                    $count_days =
                        Carbon::parse($emp->start_date)
                        ->diffInDays(Carbon::parse($emp->end_date)) + 1;
                }

                /*
                |--------------------------------------------------------------------------
                | FORMULA ENGINE
                |--------------------------------------------------------------------------
                */

                $inputVariables = [
                    'is_contract' => $is_contract ? 1 : 0,
                    'is_daily' => $is_daily ? 1 : 0,
                    'basic_salary' => $emp->salary,
                    'allowance' => $emp->allowance ?? 0,
                    'daily_salary' => $emp->salary,
                    'month_duration' => $emp->month_duration,
                    'difference_days' => $difference_days,
                    'count_days' => $count_days,
                ];

                $amount = $this->evaluateFormula(
                    $formula,
                    [],
                    $inputVariables
                );
                /*
                |--------------------------------------------------------------------------
                | STATUS
                |--------------------------------------------------------------------------
                */

                $status = $emp->status_contract;
                $is_active = 1;

                if (!empty($emp->TKK)) {

                    $tkkDate = Carbon::parse($emp->TKK);

                    if ($tkkDate->lt($endDate)) {
                        $status = 'Resigned Before Contract End';
                    }

                    $is_active = 0;
                }

                CompensationDetails::updateOrCreate(
                    [
                        'npk' => $emp->npk,
                        'id_dept' => $emp->ID_DEPT,
                        'contract_id' => $emp->id,
                        'cutoff_date' => $today
                    ],
                    [
                        'amount' => $amount,
                        'status' => $status,
                        'is_active' => $is_active
                    ]
                );
            }

            /*
            |--------------------------------------------------------------------------
            | CREATE APPROVAL PAYROLL
            |--------------------------------------------------------------------------
            */

            $existsApprove = CompensationApprove::where('run_id', $this->compensation_id)->exists();

            if (!$existsApprove) {
                $settings = PayrollSetting::where('component', 'compensation')->get();

                if ($settings->count() > 0) {
                    $approvals = $settings->pluck('approval')->toArray();

                    $progress = collect($approvals)->map(function ($npk) {
                        $npkList = is_array($npk) ? $npk : json_decode($npk, true);
                        if (!is_array($npkList)) $npkList = [$npk];
                        $statusList = array_fill(0, count($npkList), 'waiting');
                        return [
                            'npk' => json_encode($npkList),
                            'status' => json_encode($statusList)
                        ];
                    })->values();

                    CompensationApprove::create([
                        'run_id'         => $this->compensation_id,
                        'approval'       => $approvals,
                        'progress'       => $progress,
                        'approved_at'    => [],
                        'status'         => 'pending'
                    ]);
                }
            }
            // return response()->json($payrollResults);


            /*
            |--------------------------------------------------------------------------
            | SUMMARY
            |--------------------------------------------------------------------------
            */

            $query = CompensationDetails::whereDate('cutoff_date', $today)
                ->where('is_active', 1);

            $totalAmount   = $query->sum('amount');
            $totalEmployee = $query->count();

            $master->update([
                'total_amount' => $totalAmount,
                'total_employee' => $totalEmployee,
                'progress' => 60,
                'status' => 'Building Rekap PDF'
            ]);

            /*
            |--------------------------------------------------------------------------
            | DIRECTORY
            |--------------------------------------------------------------------------
            */

            $period = $today->format('F_Y');
            $folder = "public/compensations/$period";

            Storage::makeDirectory($folder, 0777, true);

            /*
            |--------------------------------------------------------------------------
            | LOAD DATA FOR PDF
            |--------------------------------------------------------------------------
            */

            $rows = DB::table('employees_contract as ec')

                ->join('compensation_details as cd', function ($join) use ($today) {
                    $join->on('cd.contract_id', '=', 'ec.id')
                        ->whereDate('cd.cutoff_date', $today);
                })

                ->leftJoinSub($employeeUnion, 'bio', function ($join) {
                    $join->on('bio.NPK', '=', 'ec.npk');
                })

                ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'bio.ID_DEPT')

                ->select(
                    'cd.amount',
                    'cd.status',
                    'cd.is_active',

                    'ec.npk',
                    'ec.start_date',
                    'ec.end_date',
                    'ec.salary',
                    'ec.month_duration',

                    'bio.NAMA_KARYAWAN as employee_name',
                    'd.DEPARTEMENT as department'
                )

                ->orderBy('d.DEPARTEMENT')
                ->orderBy('ec.npk')

                ->get()

                ->groupBy(fn($row) => $row->department ?? 'NO DEPARTMENT');

            // dd($rows);

            $approvals = [];

            $approve = DB::table('compensation_approve')
                ->where('run_id', $this->compensation_id)
                ->first();

            if ($approve && $approve->progress) {

                foreach (json_decode($approve->progress, true) as $row) {

                    $npks = json_decode($row['npk'], true) ?? [];

                    $statuses = json_decode($row['status'], true);
                    if (!is_array($statuses)) {
                        $statuses = array_fill(0, count($npks), $row['status']);
                    }

                    $empApprove = DB::query()
                        ->fromSub($employeeUnion, 'emp')
                        ->get()
                        ->keyBy('NPK');
                    foreach ($npks as $i => $npk) {
                        $approvals[] = [
                            'npk' => $npk,
                            'nama_karyawan' => $empApprove[$npk]->NAMA_KARYAWAN ?? '-',
                            'bagian' => $empApprove[$npk]->BAG ?? '-',
                            'status' => strtolower($statuses[$i] ?? 'waiting'),
                            'signature_img' => $signatures[$npk]->signature_img ?? null
                        ];
                    }
                }
            }

            // dd($approvals);

            $suffix = $this->type === 'process' ? '' : '_APPROVED';

            $html = View::make(
                'compensation.rekap_pdf',
                [
                    'groups' => $rows,
                    'totalAmount' => $totalAmount,
                    'totalEmployee' => $totalEmployee,
                    'date' => $today,
                    'approvals' => $approvals
                ]
            )->render();

            /*
            |--------------------------------------------------------------------------
            | GENERATE PDF
            |--------------------------------------------------------------------------
            */

            $pdf = App::make('snappy.pdf.wrapper');

            $pdf->loadHTML($html)
                ->setPaper('a4')
                ->setOrientation('landscape')
                ->setOption('enable-local-file-access', true);


            $pdfPath = "$folder/REKAP COMPENSATION_{$period}{$suffix}.pdf";
            $pdfPathTemp = "$folder/REKAP_{$period}{$suffix}_temp.pdf";
            $tempPath = storage_path("app/$pdfPathTemp");
            $finalPath = storage_path("app/$pdfPath");

            if (File::exists($tempPath)) File::delete($tempPath);
            if (File::exists($finalPath)) File::delete($finalPath);

            $pdf->save($tempPath);

            /*
            |--------------------------------------------------------------------------
            | ENCRYPT PDF
            |--------------------------------------------------------------------------
            */

            $master->update([
                'status' => 'Encrypting PDF',
                'progress' => 85
            ]);

            $password = PdfPassword::generate('staff', $today);

            PdfService::protect($tempPath, $finalPath, $password);

            if (File::exists($tempPath)) File::delete($tempPath);

            /*
            |--------------------------------------------------------------------------
            | FINISH
            |--------------------------------------------------------------------------
            */

            $master->update([
                'file_pdf' => str_replace('public/', '', "REKAP COMPENSATION_{$period}{$suffix}.pdf"),
                'status' => 'finished',
                'progress' => 100
            ]);

            DB::commit();
        } catch (\Throwable $e) {

            DB::rollBack();
            $master?->update([
                'status' => 'failed'
            ]);

            throw $e; // <-- WAJIB sementara debugging
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FORMULA ENGINE
    |--------------------------------------------------------------------------
    */
    private function evaluateFormula($formula, $results, $inputVariables)
    {
        $variables = array_merge($inputVariables, $results);

        foreach ($variables as $key => $value) {
            $formula = preg_replace('/\b' . $key . '\b/', $value, $formula);
        }

        try {
            return eval("return $formula;");
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
