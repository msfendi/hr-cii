<?php

namespace App\Http\Controllers;

use App\Jobs\GeneratePayrollExport;
use App\Jobs\GeneratePayrollRekap;
use App\Models\PayrollComponent;
use App\Models\PayrollExport;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunDetail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;
use Yajra\DataTables\DataTables;

class PayrollProcessController extends Controller
{

    public function index()
    {
        $periods = PayrollRun::leftJoin('payroll_periods', 'payroll_runs.period_id', '=', 'payroll_periods.id')
            ->leftJoin('payroll_exports', 'payroll_exports.run_id', '=', 'payroll_runs.id')
            ->select(
                'payroll_runs.*',
                'payroll_periods.name as period_name',
                'payroll_exports.status as export_status',
                'payroll_exports.file_excel as file_excel',
                'payroll_exports.file_pdf as file_pdf',
            )
            ->orderByDesc('payroll_runs.processed_at')
            ->get();

        return view('payroll.index', compact('periods'));
    }

    public function generate()
    {
        $periods = PayrollPeriod::all();
        return view('payroll.process', compact('periods'));
    }

    public function process(Request $request)
    {
        $period = PayrollPeriod::findOrFail($request->period_id);

        $endDate = $period->end_date;

        $employees = DB::connection('cii')
            ->table('BIODATA as b')
            ->leftJoin('PKWT as p', 'b.NPK', '=', 'p.NPK')
            ->leftJoin('overtimes as o', 'b.NPK', '=', 'o.NPK')
            ->leftJoin('payroll_masters as pm', 'b.NPK', '=', 'pm.npk')
            ->select(
                'b.NPK',
                'b.NAMA_KARYAWAN',
                'pm.salary',
                'pm.allowance',
                DB::raw("
            COALESCE(SUM(
                CASE 
                    WHEN o.DAY NOT IN ('Sabtu','Minggu') 
                    THEN TRY_CAST(o.JUMLAH_JAM_LEMBUR as FLOAT) 
                    ELSE 0 
                END
            ),0) as overtime_hours
        "),
                DB::raw("
            COALESCE(SUM(
                CASE 
                    WHEN o.DAY IN ('Sabtu','Minggu') 
                    THEN TRY_CAST(o.JUMLAH_JAM_LEMBUR as FLOAT) 
                    ELSE 0 
                END
            ),0) as special_overtime_hours
        "),
                DB::raw("DATEDIFF(YEAR, p.TMK, '$endDate') as working_years")
            )
            ->groupBy(
                'b.NPK',
                'b.NAMA_KARYAWAN',
                'pm.salary',
                'pm.allowance',
                'p.TMK'
            )
            ->get();

        $components = PayrollComponent::where('is_active', 1)
            ->orderByDesc('priority')
            ->get();

        $run = PayrollRun::create([
            'period_id' => $period->id,
            'processed_at' => now(),
        ]);

        $totalPayroll = 0;

        foreach ($employees as $employee) {

            $inputVariables = [
                'basic_salary'   => (float) $employee->salary,
                'allowance'      => (float) $employee->allowance,
                'overtime_hours' => (float) $employee->overtime_hours,
                'absence_days'   => (float) ($request->absence_days ?? 0),
                'special_overtime_hours'  => (float) $employee->special_overtime_hours,
                'working_years'  => (float) $employee->working_years,
            ];

            $results = [];
            $grandTotal = 0;

            foreach ($components as $component) {

                if ($component->calculation_method === 'fixed') {
                    $amount = $component->value;
                } else {

                    $amount = $this->evaluateFormula(
                        $component->formula,
                        $results,
                        $inputVariables
                    );
                }

                $amount = (float) $amount;

                $results[$component->code] = $amount;

                if ($component->type === 'earning') {
                    $grandTotal += $amount;
                } else {
                    $grandTotal -= $amount;
                }
            }

            PayrollRunDetail::create([
                'run_id'        => $run->id,
                'employee_npk'  => $employee->NPK,
                'employee_name' => $employee->NAMA_KARYAWAN,
                'components'    => $results,
                'total_salary'  => $grandTotal
            ]);

            $totalPayroll += $grandTotal;
        }

        $run->update([
            'employee_count' => $employees->count(),
            'total_payroll'  => $totalPayroll
        ]);

        Alert::success('Payroll processed successfully!');
        return redirect('payroll-process/index');
    }

    public function details($id)
    {
        $data = DB::table('payroll_run_details')
            ->where('run_id', $id)
            ->select(
                'run_id',
                'employee_npk',
                'employee_name',
                'components',
                'total_salary'
            )
            ->orderBy('employee_npk')
            ->get();

        $data->transform(function ($item) {

            $components = json_decode($item->components, true);

            foreach ($components as $key => $value) {
                $item->$key = $value;
            }

            return $item;
        });

        return response()->json([
            'data' => $data
        ]);
    }

    public function destroy($period_id)
    {
        DB::beginTransaction();

        try {

            // ambil semua run id dari period
            $runIds = PayrollRun::where('id', $period_id)->pluck('id');
            if ($runIds->count() > 0) {

                // hapus detail payroll
                PayrollRunDetail::whereIn('run_id', $runIds)->delete();

                // hapus run payroll
                PayrollRun::whereIn('id', $runIds)->delete();
            }

            DB::commit();

            return redirect()->back()->with('success', 'Payroll deleted successfully');
        } catch (\Exception $e) {

            DB::rollBack();

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function slip($run_id, $npk)
    {
        $employee = DB::table('payroll_run_details')
            ->leftJoin('payroll_runs as pr', 'pr.id', '=', 'payroll_run_details.run_id')
            ->leftJoin('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
            ->where('run_id', $run_id)
            ->where('employee_npk', $npk)
            ->first();

        $components = json_decode($employee->components, true);

        $componentTypes = DB::table('payroll_components')
            ->pluck('type', 'code');

        $earnings = [];
        $deductions = [];

        foreach ($components as $code => $value) {

            $type = $componentTypes[$code] ?? 'earning';

            if ($type == 'earning') {
                $earnings[$code] = $value;
            } else {
                $deductions[$code] = $value;
            }
        }

        $data = [
            'employee' => $employee,
            'earnings' => $earnings,
            'deductions' => $deductions
        ];

        $pdf = Pdf::loadView('payroll.slip', $data)
            ->setPaper('A4', 'portrait');

        return $pdf->download('slip-gaji-' . $employee->employee_npk . '.pdf');
    }

    public function export($run_id)
    {

        $export = PayrollExport::create([
            'run_id' => $run_id,
            'status' => 'processing',
            'progress' => 0
        ]);

        GeneratePayrollExport::dispatch($export->id);

        Alert::success('Sukses', 'Export payroll selesai diproses!');
        return redirect('payroll-process/index');
        // return response()->json([
        //     'message' => 'Export started',
        //     'export_id' => $export->id
        // ]);
    }

    public function progress($id)
    {

        $export = PayrollExport::findOrFail($id);

        return response()->json([
            'progress' => $export->progress,
            'status' => $export->status
        ]);
    }

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
