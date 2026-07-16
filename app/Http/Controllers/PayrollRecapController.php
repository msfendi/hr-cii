<?php

namespace App\Http\Controllers;

use App\Jobs\GeneratePayrollProcess;
use App\Models\RolePayroll;
use App\Services\PayrollRoleFilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PayrollRecapController extends Controller
{
    private array $components = [
        'earning' => [
            'basic_salary'           => 'Gaji Pokok',
            'overtime_pay'           => 'Lembur',
            'special_overtime_pay'   => 'Lembur Khusus',
            'monthly_premi'          => 'Premi Bulanan',
            'long_service_allowance' => 'Tunjangan Masa Kerja',
            'allowance'              => 'Tunjangan',
            'sewing_insentif'        => 'Insentif Sewing',
            'pad_insentif'           => 'Insentif PAD',
            'cutting_insentif'       => 'Insentif Cutting',
            'heat_insentif'          => 'Insentif Heat',
            'sixs_insentif'          => 'Insentif 6S',
            'adjusment'              => 'Penyesuaian (Adjustment)',
            'pph_21'                 => 'PPh 21 (Earning)',
        ],
        'deduction' => [
            'work_leave_deduction' => 'Potongan Cuti',
            'late_deduction'       => 'Potongan Keterlambatan',
            'bpjs_kesehatan'       => 'BPJS Kesehatan',
            'bpjs_ketenagakerjaan' => 'BPJS Ketenagakerjaan',
            'pph_21_deduction'     => 'PPh 21 (Potongan)',
            'absence_deduction'    => 'Potongan Absensi',
        ],
    ];

    private function getRole(): ?string
    {
        return PayrollRoleFilterService::getRole(Auth::user());
    }

    private function getRoleLabel(?string $role): ?string
    {
        if ($role === null) {
            return null;
        }
        return RolePayroll::ROLES[$role] ?? $role;
    }

    private function getPeriods()
    {
        return DB::table('payroll_periods')
            ->select('id', 'name', 'start_date', 'end_date', 'is_closed')
            ->orderByDesc('start_date')
            ->get();
    }

    private function resolvePeriod(?string $periodId)
    {
        $query = DB::table('payroll_periods')->orderByDesc('start_date');

        return $periodId
            ? $query->where('id', $periodId)->first()
            : $query->first();
    }

    public function index()
    {
        $role = $this->getRole();

        $bioUnion = DB::table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT', 'IS_STAFF')
            ->unionAll(
                DB::table('BIODATA_KELUAR')->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT', 'IS_STAFF')
            );

        $departmentsQuery = DB::table('payroll_run_details as prd')
            ->join('DEPT', 'DEPT.ID_DEPT', '=', 'prd.employee_dept')
            ->leftJoinSub($bioUnion, 'bio', fn($join) => $join->on('bio.NPK', '=', 'prd.employee_npk'));

        $departmentsQuery = PayrollRoleFilterService::applyToQuery(
            $departmentsQuery,
            $role,
            'bio.IS_STAFF',
            'DEPT.IS_SEWING'
        );

        $departments = $departmentsQuery
            ->select('DEPT.ID_DEPT as id', 'DEPT.DEPARTEMENT as name')
            ->distinct()
            ->orderBy('DEPT.DEPARTEMENT')
            ->get();

        return view('payroll_recap.index', [
            'departments'      => $departments,
            'components'       => $this->components,
            'periods'          => $this->getPeriods(),
            'payrollRole'      => $role,
            'payrollRoleLabel' => $this->getRoleLabel($role),
        ]);
    }

    public function searchEmployee(Request $request)
    {
        $role = $this->getRole();
        $q    = trim((string) $request->get('q'));

        $bioUnion = DB::table('BIODATA')
            ->select('NPK', 'IS_STAFF')
            ->unionAll(
                DB::table('BIODATA_KELUAR')->select('NPK', 'IS_STAFF')
            );

        $query = DB::table('payroll_run_details as prd')
            ->leftJoinSub($bioUnion, 'bio', fn($join) => $join->on('bio.NPK', '=', 'prd.employee_npk'))
            ->leftJoin('DEPT as dept_main', 'dept_main.ID_DEPT', '=', 'prd.employee_dept')
            ->select('prd.employee_npk', 'prd.employee_name')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('prd.employee_npk', 'like', "%{$q}%")
                        ->orWhere('prd.employee_name', 'like', "%{$q}%");
                });
            });

        $query = PayrollRoleFilterService::applyToQuery(
            $query,
            $role,
            'bio.IS_STAFF',
            'dept_main.IS_SEWING'
        );

        $employees = $query
            ->distinct()
            ->orderBy('prd.employee_name')
            ->limit(20)
            ->get()
            ->map(fn($row) => [
                'id'   => $row->employee_npk,
                'text' => "{$row->employee_npk} - {$row->employee_name}",
            ]);

        return response()->json(['results' => $employees]);
    }

    private function classifyEmploymentStatus(?string $tkk, ?string $keterangan, string $periodStart, string $periodEnd): string
    {
        if (empty($tkk) || $tkk > $periodEnd) {
            return 'aktif';
        }
        if ($tkk >= $periodStart && $tkk <= $periodEnd) {
            return strtoupper(trim($keterangan ?? '')) === 'MA' ? 'mangkir' : 'keluar';
        }
        return 'keluar';
    }

    private function parseJam($value): float
    {
        if ($value === null) return 0.0;
        $value = trim((string) $value);
        if ($value === '') return 0.0;
        $value = str_replace(',', '.', $value);
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function evaluateFormula($formula, array $variables)
    {
        if (!$formula) {
            return 0;
        }

        foreach ($variables as $key => $value) {
            $formula = preg_replace('/\b' . $key . '\b/', $value, $formula);
        }

        try {
            return eval("return $formula;");
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function getOvertimeFormulas(): array
    {
        return [
            'regular' => DB::table('payroll_components')->where('code', 'overtime_pay')->value('formula'),
            'special' => DB::table('payroll_components')->where('code', 'special_overtime_pay')->value('formula'),
        ];
    }

    private function latestContractQuery($periodStart, $periodEnd)
    {
        return DB::table('employees_contract as ec1')
            ->select('ec1.npk', 'ec1.salary', 'ec1.allowance', 'ec1.daily_salary', 'ec1.type')
            ->whereDate('ec1.start_date', '<=', $periodEnd)
            ->whereDate('ec1.end_date', '>=', $periodStart)
            ->whereRaw("
                ec1.id = (
                    SELECT TOP 1 ec2.id
                    FROM employees_contract ec2
                    WHERE ec2.npk = ec1.npk
                      AND ec2.start_date <= ?
                      AND ec2.end_date >= ?
                    ORDER BY ec2.contract_ke DESC, ec2.start_date DESC
                )
            ", [$periodEnd, $periodStart]);
    }

    private function evaluateOvertimeFormula(?string $formula, $emp, float $hours, string $hoursKey, float $countDays): float
    {
        if (!$formula || !$emp || $hours <= 0) {
            return 0.0;
        }

        $variables = [
            'basic_salary' => (float) $emp->salary,
            'allowance'    => (float) $emp->allowance,
            'daily_salary' => (float) $emp->daily_salary,
            'count_days'   => $countDays,
            'is_contract'  => Str::ucfirst(Str::lower((string) $emp->type)) === 'Contract' ? 1 : 0,
            'is_daily'     => Str::ucfirst(Str::lower((string) $emp->type)) === 'Daily' ? 1 : 0,
            $hoursKey      => $hours,
        ];

        return (float) $this->evaluateFormula($formula, $variables);
    }

    public function chartData(Request $request)
    {
        $role = $this->getRole();

        $endMonth = $request->filled('end_month')
            ? Carbon::createFromFormat('Y-m', $request->end_month)->endOfMonth()
            : Carbon::now()->endOfMonth();

        $startMonth = (clone $endMonth)->subMonths(11)->startOfMonth();

        $npk       = $request->get('npk');
        $dept      = $request->get('dept');
        $component = $request->get('component', 'total_salary');

        $flatComponents = array_merge($this->components['earning'], $this->components['deduction']);

        if ($component !== 'total_salary' && !array_key_exists($component, $flatComponents)) {
            return response()->json(['message' => 'Component tidak valid.'], 422);
        }

        $componentLabel = $component === 'total_salary'
            ? 'Total Take Home Pay'
            : $flatComponents[$component];

        $bioUnion = DB::table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT', 'IS_STAFF')
            ->unionAll(
                DB::table('BIODATA_KELUAR')->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT', 'IS_STAFF')
            );

        $rowsQuery = DB::table('payroll_run_details as prd')
            ->join('payroll_runs as pr', 'prd.run_id', '=', 'pr.id')
            ->join('payroll_periods as pp', 'pr.period_id', '=', 'pp.id')
            ->leftJoinSub($bioUnion, 'bio', fn($join) => $join->on('bio.NPK', '=', 'prd.employee_npk'))
            ->leftJoin('PKWT as pkwt', 'pkwt.NPK', '=', 'prd.employee_npk')
            ->leftJoin('DEPT as dept_main', 'dept_main.ID_DEPT', '=', 'prd.employee_dept')
            ->whereBetween('pp.start_date', [$startMonth->toDateString(), $endMonth->toDateString()])
            ->when($npk, fn($q) => $q->where('prd.employee_npk', $npk))
            ->when($dept, fn($q) => $q->where('prd.employee_dept', $dept));

        $rowsQuery = PayrollRoleFilterService::applyToQuery(
            $rowsQuery,
            $role,
            'bio.IS_STAFF',
            'dept_main.IS_SEWING'
        );

        $rows = $rowsQuery
            ->select(
                'pp.start_date as period_start',
                'pp.end_date as period_end',
                'prd.employee_npk',
                'prd.total_salary',
                'prd.components',
                'pkwt.TKK as tkk',
                'pkwt.KETERANGAN as keterangan'
            )
            ->orderBy('pp.start_date')
            ->get();

        $months = collect();
        $cursor = (clone $startMonth);
        while ($cursor->lte($endMonth)) {
            $months->put($cursor->format('Y-m'), [
                'label'             => $cursor->translatedFormat('M Y'),
                'aktif_total'       => 0.0,
                'aktif_employees'   => [],
                'keluar_total'      => 0.0,
                'keluar_employees'  => [],
                'mangkir_total'     => 0.0,
                'mangkir_employees' => [],
            ]);
            $cursor->addMonth();
        }

        foreach ($rows as $row) {
            $periodKey = Carbon::parse($row->period_start)->format('Y-m');
            if (!$months->has($periodKey)) {
                continue;
            }

            $comp = json_decode($row->components, true) ?? [];

            $value = $component === 'total_salary'
                ? (float) $row->total_salary
                : (float) ($comp[$component]['amount'] ?? 0);

            $periodStartStr = Carbon::parse($row->period_start)->format('Y-m-d');
            $periodEndStr   = $row->period_end
                ? Carbon::parse($row->period_end)->format('Y-m-d')
                : Carbon::parse($row->period_start)->endOfMonth()->format('Y-m-d');
            $tkkStr = $row->tkk ? Carbon::parse($row->tkk)->format('Y-m-d') : null;

            $status = $this->classifyEmploymentStatus($tkkStr, $row->keterangan, $periodStartStr, $periodEndStr);

            $bucket = $months[$periodKey];
            if ($status === 'mangkir') {
                $bucket['mangkir_total'] += $value;
                $bucket['mangkir_employees'][$row->employee_npk] = true;
            } elseif ($status === 'keluar') {
                $bucket['keluar_total'] += $value;
                $bucket['keluar_employees'][$row->employee_npk] = true;
            } else {
                $bucket['aktif_total'] += $value;
                $bucket['aktif_employees'][$row->employee_npk] = true;
            }
            $months[$periodKey] = $bucket;
        }

        $labels        = $months->pluck('label')->values();
        $aktifValues   = $months->map(fn($m) => round($m['aktif_total'], 2))->values();
        $keluarValues  = $months->map(fn($m) => round($m['keluar_total'], 2))->values();
        $mangkirValues = $months->map(fn($m) => round($m['mangkir_total'], 2))->values();
        $values        = $months->map(fn($m) => round($m['aktif_total'] + $m['keluar_total'] + $m['mangkir_total'], 2))->values();
        $aktifCounts   = $months->map(fn($m) => count($m['aktif_employees']))->values();
        $keluarCounts  = $months->map(fn($m) => count($m['keluar_employees']))->values();
        $mangkirCounts = $months->map(fn($m) => count($m['mangkir_employees']))->values();
        $employeeCounts = $months->map(
            fn($m) =>
            count($m['aktif_employees']) + count($m['keluar_employees']) + count($m['mangkir_employees'])
        )->values();

        $grandTotal   = $values->sum();
        $activeMonths = $values->filter(fn($v) => $v > 0)->count();
        $avgPerMonth  = $activeMonths > 0 ? $grandTotal / $activeMonths : 0;
        $maxEmployees = $employeeCounts->max() ?? 0;

        return response()->json([
            'labels'          => $labels,
            'values'          => $values,
            'aktif_values'    => $aktifValues,
            'keluar_values'   => $keluarValues,
            'mangkir_values'  => $mangkirValues,
            'aktif_counts'    => $aktifCounts,
            'keluar_counts'   => $keluarCounts,
            'mangkir_counts'  => $mangkirCounts,
            'employee_counts' => $employeeCounts,
            'component_label' => $componentLabel,
            'grand_total'     => $grandTotal,
            'avg_per_month'   => $avgPerMonth,
            'max_employees'   => $maxEmployees,
            'range' => [
                'start' => $startMonth->format('Y-m'),
                'end'   => $endMonth->format('Y-m'),
            ],
        ]);
    }

    public function detailData(Request $request)
    {
        $role = $this->getRole();

        $period = $this->resolvePeriod($request->get('period_id'));
        if (!$period) {
            return response()->json(['message' => 'Periode payroll tidak ditemukan.'], 422);
        }

        $npk       = $request->get('npk');
        $dept      = $request->get('dept');
        $component = $request->get('component', 'total_salary');

        $flatComponents = array_merge($this->components['earning'], $this->components['deduction']);

        if ($component !== 'total_salary' && !array_key_exists($component, $flatComponents)) {
            return response()->json(['message' => 'Component tidak valid.'], 422);
        }

        $componentLabel = $component === 'total_salary'
            ? 'Total Take Home Pay'
            : $flatComponents[$component];

        $isClosed = (bool) $period->is_closed;

        $rows = $isClosed
            ? $this->getClosedPayrollRows($period, $role, $npk, $dept)
            : $this->getLivePayrollRows($period, $npk, $dept);

        $periodStart = Carbon::parse($period->start_date)->format('Y-m-d');
        $periodEnd   = !empty($period->end_date)
            ? Carbon::parse($period->end_date)->format('Y-m-d')
            : Carbon::parse($period->start_date)->endOfMonth()->format('Y-m-d');

        $employees          = [];
        $deptRecap          = [];
        $deptEmployeeDetail = [];

        foreach ($rows as $row) {
            $comp = json_decode($row->components, true) ?? [];

            $value = $component === 'total_salary'
                ? (float) $row->total_salary
                : (float) ($comp[$component]['amount'] ?? 0);

            $tkkStr = $row->tkk ? Carbon::parse($row->tkk)->format('Y-m-d') : null;
            $status = $this->classifyEmploymentStatus($tkkStr, $row->keterangan, $periodStart, $periodEnd);

            $employees[$row->employee_npk] = [
                'npk'           => $row->employee_npk,
                'nama'          => $row->nama,
                'bagian'        => $row->dept_name,
                'status'        => $status,
                'tkk_formatted' => $row->tkk ? Carbon::parse($row->tkk)->format('d-m-Y') : null,
                'months_count'  => 1,
                'total'         => round($value, 2),
            ];

            $deptKey = $row->dept_id ?? '-';
            if (!isset($deptRecap[$deptKey])) {
                $deptRecap[$deptKey] = [
                    'dept_id'   => $deptKey,
                    'dept_name' => $row->dept_name,
                    'total'     => 0.0,
                    'employees' => [],
                ];
            }
            $deptRecap[$deptKey]['total'] += $value;
            $deptRecap[$deptKey]['employees'][$row->employee_npk] = true;

            if (!isset($deptEmployeeDetail[$deptKey][$row->employee_npk])) {
                $components = [];
                foreach ($comp as $ck => $cv) {
                    if (!array_key_exists($ck, $flatComponents)) {
                        continue;
                    }
                    $components[$ck] = round(is_array($cv) ? (float) ($cv['amount'] ?? 0) : (float) $cv, 2);
                }

                $deptEmployeeDetail[$deptKey][$row->employee_npk] = [
                    'npk'          => $row->employee_npk,
                    'nama'         => $row->nama,
                    'status'       => $status,
                    'total_salary' => round((float) $row->total_salary, 2),
                    'components'   => $components,
                ];
            }
        }

        $employeeList = collect($employees)->sortByDesc('total')->values();

        $payrollByDept = collect($deptRecap)
            ->map(fn($d) => [
                'dept_id'        => $d['dept_id'],
                'dept_name'      => $d['dept_name'],
                'employee_count' => count($d['employees']),
                'total'          => round($d['total'], 2),
            ])
            ->sortBy('dept_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $deptEmployeeDetails = collect($deptEmployeeDetail)
            ->map(fn($employeesInDept) => collect($employeesInDept)->sortByDesc('total_salary')->values())
            ->toArray();
        $deptEmployeeDetails = (object) $deptEmployeeDetails;

        return response()->json([
            'period' => [
                'id'         => $period->id,
                'name'       => $period->name,
                'start_date' => Carbon::parse($period->start_date)->format('Y-m-d'),
                'is_closed'  => $isClosed,
            ],
            'component_label'       => $componentLabel,
            'employees'             => $employeeList,
            'payroll_by_dept'       => $payrollByDept,
            'dept_employee_details' => $deptEmployeeDetails,
        ]);
    }

    private function getClosedPayrollRows($period, ?string $role, $npk, $dept)
    {
        $bioUnion = DB::table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT', 'IS_STAFF')
            ->unionAll(
                DB::table('BIODATA_KELUAR')->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT', 'IS_STAFF')
            );

        $rowsQuery = DB::table('payroll_run_details as prd')
            ->join('payroll_runs as pr', 'prd.run_id', '=', 'pr.id')
            ->where('pr.period_id', $period->id)
            ->leftJoinSub($bioUnion, 'bio', fn($join) => $join->on('bio.NPK', '=', 'prd.employee_npk'))
            ->leftJoin('PKWT as pkwt', 'pkwt.NPK', '=', 'prd.employee_npk')
            ->leftJoin('DEPT as dept_main', 'dept_main.ID_DEPT', '=', 'prd.employee_dept')
            ->when($npk, fn($q) => $q->where('prd.employee_npk', $npk))
            ->when($dept, fn($q) => $q->where('prd.employee_dept', $dept));

        $rowsQuery = PayrollRoleFilterService::applyToQuery(
            $rowsQuery,
            $role,
            'bio.IS_STAFF',
            'dept_main.IS_SEWING'
        );

        return $rowsQuery
            ->select(
                'prd.employee_npk',
                'prd.employee_dept as dept_id',
                'prd.total_salary',
                'prd.components',
                DB::raw('COALESCE(bio.NAMA_KARYAWAN, prd.employee_name) as nama'),
                DB::raw('COALESCE(dept_main.DEPARTEMENT, \'Tanpa Dept\') as dept_name'),
                'pkwt.TKK as tkk',
                'pkwt.KETERANGAN as keterangan'
            )
            ->get();
    }

    private function getLivePayrollRows($period, $npk, $dept)
    {
        $results = (new GeneratePayrollProcess($period->id))->simulation();

        return collect($results)
            ->when($npk, fn($c) => $c->where('employee_npk', $npk))
            ->when($dept, fn($c) => $c->where('employee_dept', $dept))
            ->map(function ($row) {
                return (object) [
                    'employee_npk' => $row['employee_npk'],
                    'dept_id'      => $row['employee_dept'],
                    'total_salary' => $row['total_salary'],
                    'components'   => json_encode($row['components']),
                    'nama'         => $row['employee_name'],
                    'dept_name'    => $row['dept'] ?: 'Tanpa Dept',
                    'tkk'          => $row['tkk'],
                    'keterangan'   => $row['keterangan'],
                ];
            })
            ->values();
    }

    public function overtimeData(Request $request)
    {
        $role = $this->getRole();

        $period = $this->resolvePeriod($request->get('period_id'));
        if (!$period) {
            return response()->json(['message' => 'Periode payroll tidak ditemukan.'], 422);
        }

        $isClosed    = (bool) $period->is_closed;
        $sourceTable = $isClosed ? 'overtimes_payroll' : 'overtimes';

        $periodStart = Carbon::parse($period->start_date)->startOfDay();
        $periodEnd   = !empty($period->end_date)
            ? Carbon::parse($period->end_date)->endOfDay()
            : (clone $periodStart)->endOfMonth()->endOfDay();

        $totalDaysInPeriod  = $periodStart->diffInDays($periodEnd) + 1;
        $totalWeeksInPeriod = max($totalDaysInPeriod / 7, 0.1);

        $dept = $request->get('dept');

        $formulas      = $this->getOvertimeFormulas();
        $employeeComp  = $this->latestContractQuery($periodStart->toDateString(), $periodEnd->toDateString())
            ->get()
            ->keyBy('npk');

        $bioUnion = DB::table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT', 'IS_STAFF')
            ->unionAll(
                DB::table('BIODATA_KELUAR')->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT', 'IS_STAFF')
            );

        $holidaySet = DB::table('holidays')
            ->whereBetween('holiday_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->where('is_national', 1)
            ->pluck('holiday_date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->flip();

        $overtimeRowsQuery = DB::table($sourceTable . ' as ot')
            ->leftJoinSub($bioUnion, 'bio_ot', fn($join) => $join->on('bio_ot.NPK', '=', 'ot.NPK'));

        if ($isClosed) {
            $deptPerNpkThisPeriod = DB::table('payroll_run_details as prd')
                ->join('payroll_runs as pr', 'prd.run_id', '=', 'pr.id')
                ->where('pr.period_id', $period->id)
                ->select('prd.employee_npk', 'prd.employee_dept')
                ->whereRaw('prd.id = (
            SELECT MAX(prd2.id) FROM payroll_run_details prd2
            JOIN payroll_runs pr2 ON pr2.id = prd2.run_id
            WHERE prd2.employee_npk = prd.employee_npk
              AND pr2.period_id = ?
        )', [$period->id]);

            $overtimeRowsQuery = $overtimeRowsQuery
                ->leftJoinSub($deptPerNpkThisPeriod, 'ndept', fn($join) => $join->on('ndept.employee_npk', '=', 'ot.NPK'))
                ->leftJoin('DEPT', 'DEPT.ID_DEPT', '=', 'ndept.employee_dept');
        } else {
            $overtimeRowsQuery = $overtimeRowsQuery
                ->leftJoin('DEPT', 'DEPT.ID_DEPT', '=', 'bio_ot.ID_DEPT');
        }

        $overtimeRowsQuery = $overtimeRowsQuery
            ->whereDate('ot.OVERTIME_DATE', '>=', $periodStart->toDateString())
            ->whereDate('ot.OVERTIME_DATE', '<=', $periodEnd->toDateString())
            ->when($dept, fn($q) => $q->where('DEPT.ID_DEPT', $dept));

        $overtimeRowsQuery = PayrollRoleFilterService::applyToQuery(
            $overtimeRowsQuery,
            $role,
            'bio_ot.IS_STAFF',
            'DEPT.IS_SEWING'
        );

        $overtimeRows = $overtimeRowsQuery
            ->select(
                'ot.NPK',
                'ot.NAMA_KARYAWAN',
                'ot.OVERTIME_DATE',
                'ot.JUMLAH_JAM_LEMBUR',
                DB::raw('COALESCE(DEPT.ID_DEPT, \'-\') as dept_key'),
                DB::raw('COALESCE(DEPT.DEPARTEMENT, \'Tanpa Dept\') as dept_name')
            )
            ->orderBy('ot.OVERTIME_DATE')
            ->get();

        $overtimeByDept    = [];
        $overtimeEmployees = [];
        $overtimeMatrix    = [];
        $overtimeDateKeys  = [];
        $overtimeByDate    = [];

        // =====================================================================
        // FIX: akumulasi cost mentah (float, belum dibulatkan) PER NPK, meniru
        // persis pola GeneratePayrollProcess -- di Job, overtime_pay &
        // special_overtime_pay dijumlahkan dulu untuk SEMUA hari lembur milik
        // satu karyawan, baru round(..., 0) SEKALI per karyawan per komponen.
        // Sebelumnya recap membulatkan cost per BARIS TANGGAL ke 2 desimal lalu
        // menjumlahkannya -- beda titik & beda presisi pembulatan inilah yang
        // menyebabkan selisih kecil (mis. Rp71) dibanding angka payroll asli.
        // =====================================================================
        $employeeOvertimeCost = [];

        foreach ($overtimeRows as $row) {
            $date      = Carbon::parse($row->OVERTIME_DATE);
            $dateKey   = $date->format('Y-m-d');
            $isWeekend = $date->isWeekend();
            $isHoliday = $holidaySet->has($dateKey);
            $isSpecial = $isWeekend || $isHoliday;

            $jam = $this->parseJam($row->JUMLAH_JAM_LEMBUR);

            $deptKey  = $row->dept_key;
            $deptName = $row->dept_name;

            if (!isset($overtimeByDept[$deptKey])) {
                $overtimeByDept[$deptKey] = [
                    'dept_id'              => $deptKey,
                    'dept_name'            => $deptName,
                    'overtime_jam'         => 0.0,
                    'special_overtime_jam' => 0.0,
                ];
            }

            if (!isset($overtimeByDate[$dateKey])) {
                $overtimeByDate[$dateKey] = [
                    'is_weekend'   => $isWeekend,
                    'is_holiday'   => $isHoliday,
                    'regular_jam'  => 0.0,
                    'special_jam'  => 0.0,
                    'regular_cost' => 0.0,
                    'special_cost' => 0.0,
                ];
            }

            if ($isSpecial) {
                $overtimeByDept[$deptKey]['special_overtime_jam'] += $jam;
                $overtimeByDate[$dateKey]['special_jam'] += $jam;
            } else {
                $overtimeByDept[$deptKey]['overtime_jam'] += $jam;
                $overtimeByDate[$dateKey]['regular_jam'] += $jam;
            }

            $emp = $employeeComp->get($row->NPK);

            $regularHours = 0.0;
            $specialHours = 0.0;

            if ($jam > 0) {
                if ($isSpecial) {
                    $overThreshold = false;
                    if ($emp) {
                        $salaryPlusAllowance = (float) $emp->salary + (float) $emp->allowance;
                        $dailyBasis = ((float) $emp->daily_salary * $totalDaysInPeriod) + (float) $emp->allowance;
                        $overThreshold = $salaryPlusAllowance >= 3800000 || $dailyBasis >= 3800000;
                    }
                    $specialHours = $overThreshold ? min($jam, 8) : $jam;
                } else {
                    $regularHours = $jam;
                }
            }

            $regularCost = $this->evaluateOvertimeFormula(
                $formulas['regular'],
                $emp,
                $regularHours,
                'overtime_hours',
                $totalDaysInPeriod
            );
            $specialCost = $this->evaluateOvertimeFormula(
                $formulas['special'],
                $emp,
                $specialHours,
                'special_overtime_hours',
                $totalDaysInPeriod
            );

            $overtimeByDate[$dateKey]['regular_cost'] += $regularCost;
            $overtimeByDate[$dateKey]['special_cost'] += $specialCost;

            if (!isset($employeeOvertimeCost[$row->NPK])) {
                $employeeOvertimeCost[$row->NPK] = ['regular' => 0.0, 'special' => 0.0];
            }
            $employeeOvertimeCost[$row->NPK]['regular'] += $regularCost;
            $employeeOvertimeCost[$row->NPK]['special'] += $specialCost;

            $overtimeMatrix[$deptKey][$dateKey] = ($overtimeMatrix[$deptKey][$dateKey] ?? 0) + $jam;
            $overtimeDateKeys[$dateKey] = true;

            $overtimeEmployees[] = [
                'dept_id'           => $deptKey,
                'dept_name'         => $deptName,
                'npk'               => $row->NPK,
                'nama'              => $row->NAMA_KARYAWAN,
                'tanggal'           => $dateKey,
                'tanggal_formatted' => $date->format('d-m-Y'),
                'jam'               => round($jam, 2),
                'jenis'             => $isSpecial ? 'special_overtime' : 'overtime',
                'is_weekend'        => $isWeekend,
                'is_holiday'        => $isHoliday,
                'regular_cost'      => round($regularCost, 2),
                'special_cost'      => round($specialCost, 2),
            ];
        }

        $overtimeByDept = collect($overtimeByDept)
            ->map(function ($d) {
                $d['overtime_jam']         = round($d['overtime_jam'], 2);
                $d['special_overtime_jam'] = round($d['special_overtime_jam'], 2);
                $d['total_jam']            = round($d['overtime_jam'] + $d['special_overtime_jam'], 2);
                return $d;
            })
            ->sortBy('dept_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        foreach ($overtimeMatrix as $deptKey => $dates) {
            foreach ($dates as $dateKey => $jam) {
                $overtimeMatrix[$deptKey][$dateKey] = round($jam, 2);
            }
        }

        $overtimeDates = collect(array_keys($overtimeDateKeys))
            ->sort()
            ->values()
            ->map(function ($dateKey) use ($holidaySet) {
                $date = Carbon::parse($dateKey);
                return [
                    'key'        => $dateKey,
                    'label'      => $date->format('d/m'),
                    'is_weekend' => $date->isWeekend(),
                    'is_holiday' => $holidaySet->has($dateKey),
                ];
            });

        $overtimeByDateChart = collect(array_keys($overtimeDateKeys))
            ->sort()
            ->values()
            ->map(function ($dateKey) use ($overtimeByDate) {
                $d = $overtimeByDate[$dateKey];
                return [
                    'date'         => $dateKey,
                    'label'        => Carbon::parse($dateKey)->format('d/m'),
                    'is_weekend'   => $d['is_weekend'],
                    'is_holiday'   => $d['is_holiday'],
                    'regular_jam'  => round($d['regular_jam'], 2),
                    'special_jam'  => round($d['special_jam'], 2),
                    'regular_cost' => round($d['regular_cost'], 0),
                    'special_cost' => round($d['special_cost'], 0),
                ];
            });

        $top5Dept = $overtimeByDept->sortByDesc('total_jam')->take(5)->values();

        $totalRegularJam = round(collect($overtimeByDate)->sum('regular_jam'), 2);
        $totalSpecialJam = round(collect($overtimeByDate)->sum('special_jam'), 2);

        $overtimeByWeek = [];
        $weekCursor = $periodStart->copy();
        $weekNumber = 1;

        while ($weekCursor->lte($periodEnd)) {
            $weekEndCursor = (clone $weekCursor)->addDays(6);
            if ($weekEndCursor->gt($periodEnd)) {
                $weekEndCursor = $periodEnd->copy();
            }

            $overtimeByWeek[] = [
                'week'         => $weekNumber,
                'start_date'   => $weekCursor->format('Y-m-d'),
                'end_date'     => $weekEndCursor->format('Y-m-d'),
                'regular_jam'  => 0.0,
                'special_jam'  => 0.0,
                'regular_cost' => 0.0,
                'special_cost' => 0.0,
            ];

            $weekCursor->addDays(7);
            $weekNumber++;
        }

        foreach ($overtimeByDate as $dateKey => $d) {
            $daysFromStart = $periodStart->diffInDays(Carbon::parse($dateKey));
            $weekIndex     = (int) floor($daysFromStart / 7);

            if (isset($overtimeByWeek[$weekIndex])) {
                $overtimeByWeek[$weekIndex]['regular_jam']  += $d['regular_jam'];
                $overtimeByWeek[$weekIndex]['special_jam']  += $d['special_jam'];
                $overtimeByWeek[$weekIndex]['regular_cost'] += $d['regular_cost'];
                $overtimeByWeek[$weekIndex]['special_cost'] += $d['special_cost'];
            }
        }

        $overtimeByWeek = collect($overtimeByWeek)->map(function ($w) {
            $w['regular_jam']   = round($w['regular_jam'], 2);
            $w['special_jam']   = round($w['special_jam'], 2);
            $w['total_jam']     = round($w['regular_jam'] + $w['special_jam'], 2);
            $w['regular_cost']  = round($w['regular_cost'], 0);
            $w['special_cost']  = round($w['special_cost'], 0);
            $w['total_cost']    = round($w['regular_cost'] + $w['special_cost'], 0);
            return $w;
        })->values();

        // =====================================================================
        // TOTAL RESMI -- dihitung PERSIS seperti Job: bulatkan ke 0 desimal
        // PER KARYAWAN PER KOMPONEN dulu (regular & special terpisah), baru
        // dijumlahkan semua karyawan. Ini yang membuat angka di recap sama
        // persis dengan angka hasil Generate Payroll asli.
        // =====================================================================
        $totalCostRegular = 0;
        $totalCostSpecial = 0;
        foreach ($employeeOvertimeCost as $cost) {
            $totalCostRegular += round($cost['regular'], 0);
            $totalCostSpecial += round($cost['special'], 0);
        }

        return response()->json([
            'period' => [
                'id'         => $period->id,
                'name'       => $period->name,
                'start_date' => $periodStart->format('Y-m-d'),
                'end_date'   => $periodEnd->format('Y-m-d'),
                'is_closed'  => $isClosed,
            ],
            'overtime_by_dept'         => $overtimeByDept,
            'overtime_employees'       => $overtimeEmployees,
            'overtime_dates'           => $overtimeDates,
            'overtime_matrix'          => $overtimeMatrix,
            'overtime_by_date'         => $overtimeByDateChart,
            'top5_dept'                => $top5Dept,
            'total_reguler'            => $totalRegularJam,
            'total_special'            => $totalSpecialJam,
            'overtime_by_week'         => $overtimeByWeek,
            'overtime_total_regular'   => $totalRegularJam,
            'overtime_total_special'   => $totalSpecialJam,
            'overtime_total_cost_regular' => $totalCostRegular,
            'overtime_total_cost_special' => $totalCostSpecial,
            'overtime_total_cost'         => $totalCostRegular + $totalCostSpecial,
        ]);
    }
}
