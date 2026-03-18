<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PayrollComponent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    public function calculate(Request $request)
    {
        $employeesalary = DB::connection('cii')
            ->table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'SALARY', 'ALLOWANCE')
            ->where('NPK', '=', 'C-00827')
            ->get();

        $components = PayrollComponent::where('is_active', 1)
            ->orderByDesc('priority')
            ->get();

        $payrollResults = [];

        foreach ($employeesalary as $employee) {

            $inputVariables = [
                'overtime_hours' => $request->overtime_hours ?? 0,
                'absence_days'   => $request->absence_days ?? 0,
                'basic_salary'   => $request->salary ?? 0,
                'working_years'   => $request->working_years ?? 0,
                'allowance'   => $request->allowance ?? 0,
                'special_overtime_hours'  => $request->special_overtime_hours,
                'absence_days'  => $request->absence_days,
            ];

            $results = [];
            $grandTotal = 0;

            foreach ($components as $component) {

                if ($component->calculation_method === 'fixed') {

                    // jika komponen basic_salary gunakan dari BIODATA
                    if ($component->code === 'basic_salary') {
                        $amount = $employee->SALARY ?? 0;
                    } else {
                        $amount = $component->value;
                    }
                } else {

                    $amount = $this->evaluateFormula(
                        $component->formula,
                        $results,
                        $inputVariables
                    );
                }

                $results[$component->code] = $amount;

                if ($component->type === 'earning') {
                    $grandTotal += $amount;
                } else {
                    $grandTotal -= $amount;
                }
            }

            $payrollResults[] = [
                'npk' => $employee->NPK,
                'name' => $employee->NAMA_KARYAWAN,
                'components' => $results,
                'total_salary' => $grandTotal
            ];
        }

        return response()->json($payrollResults);
    }

    private function evaluateFormula($formula, $calculatedComponents, $inputVariables)
    {
        $variables = array_merge($calculatedComponents, $inputVariables);

        foreach ($variables as $key => $value) {
            $formula = preg_replace('/\b' . $key . '\b/', $value ?? 0, $formula);
        }

        // if (!preg_match('/^[0-9\.\+\-\*\/\(\)\s]+$/', $formula)) {
        //     throw new \Exception("Invalid formula");
        // }

        return eval("return $formula;");
    }
}