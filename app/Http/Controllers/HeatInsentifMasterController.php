<?php

namespace App\Http\Controllers;

use App\Events\NotificationEvent;
use Illuminate\Http\Request;
use App\Models\InsentifMaster;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\HeatInsentifTemplateExport;
use App\Imports\HeatInsentifImport;
use App\Models\HeatEfficiency;
use App\Models\InsentifApproval;
use App\Models\InsentifRoleFormula;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;

class HeatInsentifMasterController extends Controller
{
    public function index()
    {
        $biodataUnion = DB::connection('cii')
            ->table('BIODATA')
            ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', 'IS_STAFF', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'))
            ->unionAll(
                DB::connection('cii')
                    ->table('BIODATA_KELUAR')
                    ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', 'IS_STAFF', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'))
            );
        $data = DB::table('heat_efficiencies as h')
            ->join('payroll_periods as pp', 'h.period_id', '=', 'pp.id')
            ->leftJoinSub($biodataUnion, 'bio', function ($join) {
                $join->on('h.npk', '=', 'bio.NPK');
            })
            ->select(
                'h.id',
                'h.npk',
                'bio.NAMA_KARYAWAN as name',
                'h.role',
                'pp.name as period',
                'h.efficiency',
                'h.piece',
                'h.date',
                'h.tim'
            )
            ->where('pp.is_closed', 0)
            ->orderBy('h.date')
            ->get();
        $periods = PayrollPeriod::select('id', 'name')
            ->where('is_closed', 0)
            ->orderBy('id', 'desc')
            ->get();
        // dd($data);
        return view('heat_insentif_master.index', compact('data', 'periods'));
    }

    public function getData($period)
    {
        $biodataUnion = DB::connection('cii')
            ->table('BIODATA')
            ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', 'IS_STAFF', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'))
            ->unionAll(
                DB::connection('cii')
                    ->table('BIODATA_KELUAR')
                    ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', 'IS_STAFF', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'))
            );
        $data = DB::table('heat_efficiencies as h')
            ->join('payroll_periods as pp', 'h.period_id', '=', 'pp.id')

            ->leftJoinSub($biodataUnion, 'bio', function ($join) {
                $join->on('h.npk', '=', 'bio.NPK');
            })
            ->select(
                'h.id',
                'h.npk',
                'bio.NAMA_KARYAWAN as name',
                'h.role',
                'pp.name as period',
                'h.efficiency',
                'h.piece',
                'h.date',
                'h.tim'
            )
            ->where('h.period_id', $period)
            ->orderBy('h.date')
            ->get();

        return response()->json($data);
    }

    public function create()
    {
        return view('heat_insentif_master.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'npk' => 'required',
            'type' => 'required',
            'efficiency' => 'nullable|numeric',
            'piece' => 'nullable|numeric',
        ]);

        InsentifMaster::create($request->all());

        return redirect()->route('heat-insentif-master.index')
            ->with('success', 'Data berhasil disimpan');
    }

    public function edit($id)
    {
        $data = InsentifMaster::findOrFail($id);
        return view('heat-insentif-master.edit', compact('data'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'npk' => 'required',
            'type' => 'required',
            'efficiency' => 'nullable|numeric',
            'piece' => 'nullable|numeric',
        ]);

        $data = InsentifMaster::findOrFail($id);
        $data->update($request->all());

        return redirect()->route('heat-insentif-master.index')
            ->with('success', 'Data berhasil diupdate');
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $data = HeatEfficiency::findOrFail($id);
        $data->delete();

        event(new NotificationEvent(
            'Heat Seal Insentif!',
            'User : ' . $user->name . ' has deleted Heat Seal Insentif!',
            'danger'
        ));

        Alert::success('Deleted Successfully!', 'Heat Seal Insentif succesfully deleted!');

        return redirect()->route('heat-insentif-master.index')
            ->with('success', 'Data berhasil dihapus');
    }

    public function template()
    {
        return Excel::download(new HeatInsentifTemplateExport, 'template_heat_insentif_master.xlsx');
    }

    public function import(Request $request)
    {
        $user = Auth::user();
        $period = PayrollPeriod::where('id', '=', $request->period_id)->first();
        $request->validate([
            'period_id'   => 'required|exists:payroll_periods,id',
            'is_insentif' => 'required|in:0,1',
            'file'        => 'required_if:is_insentif,1|mimes:xlsx,xls'
        ]);

        $component = 'heat_insentif';

        /*
    =====================================
    JIKA INSENTIF → IMPORT EXCEL
    =====================================
    */
        // HeatEfficiency::where('period_id', $period->id)->delete();
        if ($request->is_insentif == 1) {

            Excel::import(
                new HeatInsentifImport($request->period_id),
                $request->file('file')
            );
        }

        /*
    =====================================
    GET APPROVAL SETTING
    =====================================
    */

        $setting = DB::table('payroll_settings')
            ->where('component', $component)
            ->first();

        if ($setting) {

            $approvalArray = json_decode($setting->approval, true);

            $approval = [
                json_encode($approvalArray)
            ];

            $waitingStatus = array_fill(
                0,
                count($approvalArray),
                'waiting'
            );

            $progress = [
                [
                    'npk' => json_encode($approvalArray),
                    'status' => json_encode($waitingStatus),
                ]
            ];

            /*
        =====================================
        STATUS AUTO FINISH JIKA NO INSENTIF
        =====================================
        */

            // $status = $request->is_insentif == 1
            //     ? 'pending'
            //     : 'finish';

            InsentifApproval::updateOrCreate(
                [
                    'period_id' => $request->period_id,
                    'payroll_component' => $component
                ],
                [
                    'approval'     => $approval,
                    'progress'     => $progress,
                    'approved_at'  => null,
                    'status'       => 'pending'
                ]
            );
        }

        event(new NotificationEvent(
            'Heat Seal Insentif!',
            'User : ' . $user->name . ' has imported Heat Seal Insentif ' . $period->name . '!',
            'success'
        ));

        return back()->with('success', 'Process berhasil dijalankan');
    }

    public function check($period_id)
    {
        $period = PayrollPeriod::findOrFail($period_id);

        $periodStart = $period->start_date;
        $periodEnd   = $period->end_date;

        // dd($periodStart, $periodEnd);


        /*
        |--------------------------------------------------------------------------
        | AMBIL NPK YANG BENAR-BENAR ADA ASSIGNMENT
        |--------------------------------------------------------------------------
        */

        $assignmentNpk = DB::table(DB::raw("
        (
            SELECT * FROM heat_efficiencies
        ) he
    "))->where('he.period_id', $period->id);

        // dd($assignmentNpk->toArray());


        /*
    |--------------------------------------------------------------------------
    | EMPLOYEE SOURCE (TETAP SAMA LOGIC)
    |--------------------------------------------------------------------------
    */

        $employeeBase = DB::connection('cii')
            ->table('PKWT as p')

            ->join(DB::raw("
        (
            SELECT NPK, NAMA_KARYAWAN, ID_DEPT, SECTION FROM BIODATA
            UNION ALL
            SELECT NPK, NAMA_KARYAWAN, ID_DEPT, SECTION FROM BIODATA_KELUAR
        ) emp
    "), 'p.NPK', '=', 'emp.NPK')

            ->leftJoinSub($assignmentNpk, 'anpk', function ($join) {
                $join->on('p.NPK', '=', 'anpk.npk');
            })
            ->leftJoin('DEPT as d', 'emp.ID_DEPT', '=', 'd.ID_DEPT')
            ->joinSub(
                DB::table('insentif_role_formulas')
                    ->select('role')
                    ->distinct(),
                'irf',
                function ($join) {
                    $join->on('anpk.role', '=', 'irf.role');
                }
            )
            // ->where('p.NPK', '=', 'C-00795')
            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereNull('p.TKK')
                    ->orWhereBetween('p.TKK', [$periodStart, $periodEnd]);
            })

            ->select(
                'p.NPK',
                'emp.NAMA_KARYAWAN',
                'p.TMK',
                'p.TKK as tkk',
                'emp.ID_DEPT',
                'd.DEPARTEMENT as DEPARTEMENT',
                'irf.role as role',
                'emp.SECTION as SECTION'
            );

        $employees = DB::connection('cii')
            ->query()
            ->fromSub($employeeBase, 'emp')
            ->distinct()
            ->get();

        // dd($employeeBase->get(), $employees);


        /*
    |--------------------------------------------------------------------------
    | FORMULA (TETAP)
    |--------------------------------------------------------------------------
    */

        $heatInsentifFormula = json_decode(
            PayrollComponent::where('code', 'heat_insentif')
                ->value('formula'),
            true
        );


        /*
    |--------------------------------------------------------------------------
    | CALCULATION (LOGIC TIDAK DIUBAH)
    |--------------------------------------------------------------------------
    */

        $results = [];

        foreach ($employees as $employee) {
            $status = $employee->tkk ? 'Resign' : 'Active';

            $heat = $this->calculateHeat(
                $employee,
                $period,
                $heatInsentifFormula,
                $employee->role,
            );

            // 🔹 PERBAIKAN: bulatkan hasil insentif supaya tidak selisih
            // dengan job (GeneratePayrollProcess/V2 membulatkan tiap
            // komponen sebelum dijumlahkan ke grandTotal).
            $heat = round((float) $heat, 0);

            if ($heat <= 0) continue;

            $results[] = [
                'npk' => $employee->NPK,
                'name' => $employee->NAMA_KARYAWAN,
                'heat_insentif' => $heat,
                'dept' => $employee->DEPARTEMENT,
                'role' => $employee->role,
                'tkk' => $employee->tkk,
                'status' => $status
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | GABUNGKAN PER NPK (FIX: 1 NPK 2 ROLE DALAM 1 BULAN)
        |--------------------------------------------------------------------------
        | Sebelum ini, tiap (NPK, role) jadi baris terpisah di $results, jadi
        | kalau 1 NPK punya 2 role dalam periode yang sama, insentifnya muncul
        | sebagai 2 baris berbeda dan TIDAK dijumlah. Di sini digabung jadi 1
        | baris per NPK, insentif diakumulasi, dan role ditampilkan gabungan.
        |--------------------------------------------------------------------------
        */
        $results = $this->mergeInsentifByNpk($results, 'heat_insentif');

        return response()->json([
            'data' => $results
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GABUNGKAN HASIL INSENTIF PER NPK (SUM LINTAS ROLE)
    |--------------------------------------------------------------------------
    */

    private function mergeInsentifByNpk(array $results, string $amountKey): array
    {
        $merged = [];

        foreach ($results as $row) {
            $npk = $row['npk'];

            if (!isset($merged[$npk])) {
                $merged[$npk] = $row;
                $merged[$npk]['role'] = [$row['role']];
                if (array_key_exists('dept', $row)) {
                    $merged[$npk]['dept'] = [$row['dept']];
                }
                if (array_key_exists('line_info', $row)) {
                    $merged[$npk]['line_info'] = [$row['line_info']];
                }
                continue;
            }

            $merged[$npk][$amountKey] += $row[$amountKey];

            if (!in_array($row['role'], $merged[$npk]['role'], true)) {
                $merged[$npk]['role'][] = $row['role'];
            }

            if (array_key_exists('dept', $row) && !in_array($row['dept'], $merged[$npk]['dept'], true)) {
                $merged[$npk]['dept'][] = $row['dept'];
            }

            if (array_key_exists('line_info', $row) && !in_array($row['line_info'], $merged[$npk]['line_info'], true)) {
                $merged[$npk]['line_info'][] = $row['line_info'];
            }
        }

        return array_values(array_map(function ($row) {
            $row['role'] = implode(', ', array_filter($row['role']));

            if (array_key_exists('dept', $row) && is_array($row['dept'])) {
                $row['dept'] = implode(' | ', array_unique(array_filter($row['dept'])));
            }

            if (array_key_exists('line_info', $row) && is_array($row['line_info'])) {
                $filtered = array_filter($row['line_info'], fn($v) => $v !== null && $v !== '-' && $v !== '');
                $row['line_info'] = $filtered ? implode(' | ', array_unique($filtered)) : '-';
            }

            return $row;
        }, $merged));
    }

    /*
    |--------------------------------------------------------------------------
    | ENGINE (COPY PAYROLL)
    |--------------------------------------------------------------------------
    */

    private function getInsentifByEfficiency($efficiency, $rules)
    {
        krsort($rules);

        foreach ($rules as $threshold => $value) {
            if ($efficiency >= $threshold) {
                return $value;
            }
        }

        return 0;
    }

    /*
    |--------------------------------------------------------------------------
    | HEAT SEAL INSENTIF (COPY PAYROLL)
    |--------------------------------------------------------------------------
    */

    private function calculateHeat($employee, $period, $formula, $role)
    {
        $amount = 0;

        /*
        |--------------------------------------------------------------------------
        | TKK (RESIGN DATE)
        |--------------------------------------------------------------------------
        */
        $tkkDate = !empty($employee->tkk)
            ? Carbon::parse($employee->tkk)->format('Y-m-d')
            : null;

        /*
        |--------------------------------------------------------------------------
        | GET MUTATIONS EMPLOYEE
        |--------------------------------------------------------------------------
        */
        $mutations = DB::table('employee_mutations')
            ->leftJoin('DEPT as d', 'employee_mutations.to_dept', '=', 'd.ID_DEPT')
            ->where('npk', $employee->NPK)
            ->orderBy('date')
            ->get();

        /*
    |--------------------------------------------------------------------------
    | LOAD OVERTIME (ONLY ONCE)
    |--------------------------------------------------------------------------
    */
        $overtimes = DB::table('overtimes')
            ->where('NPK', $employee->NPK)
            ->whereBetween('OVERTIME_DATE', [
                $period->start_date,
                $period->end_date
            ])
            ->get()
            ->keyBy(fn($o) => $o->OVERTIME_DATE);


        /*
    |--------------------------------------------------------------------------
    | VALIDATE OVERTIME
    |--------------------------------------------------------------------------
    */
        $isValidOvertime = function ($date) use ($overtimes) {

            // tidak ada record → tetap dihitung
            if (!isset($overtimes[$date])) {
                return true;
            }

            $lembur = $overtimes[$date]->JUMLAH_JAM_LEMBUR;

            // NULL / kosong → tetap dihitung
            if ($lembur === null || $lembur === '') {
                return true;
            }

            // angka → tetap dihitung
            if (is_numeric($lembur)) {
                return true;
            }

            // MA / CT / BR / S1 dll → skip
            return false;
        };

        /*
    |--------------------------------------------------------------------------
    | LOAD ASSIGNMENT
    |--------------------------------------------------------------------------
    | Difilter berdasarkan $role yang sedang dihitung (sama seperti Pad).
    | Karyawan bisa punya lebih dari satu role dalam 1 periode, dan
    | calculateHeat() dipanggil terpisah per (npk, role) dari check(), jadi
    | assignment yang diambil di sini dibatasi ke role tsb saja.
    |--------------------------------------------------------------------------
    */
        $assignments = DB::table('heat_efficiencies')
            ->where('npk', $employee->NPK)
            ->where('period_id', $period->id)
            ->where('role', $role)
            ->whereBetween('date', [$period->start_date, $period->end_date])
            ->get();

        // dd($assignments);


        /*
            |--------------------------------------------------------------------------
            | OPERATOR
            |--------------------------------------------------------------------------
            */
        if ($role === 'operator') {
            foreach ($assignments as $assignment) {

                /*
                |--------------------------------------------------------------------------
                | CHECK RESIGN (NEW)
                |--------------------------------------------------------------------------
                */
                if ($tkkDate && $assignment->date >= $tkkDate) {
                    continue;
                }

                // dd($rows);

                if (!$isValidOvertime($assignment->npk, $assignment->date)) {
                    continue;
                }

                $rate = $this->getInsentifByEfficiency(
                    $assignment->efficiency,
                    $formula
                );

                $amount += $rate * $assignment->piece;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | NON OPERATOR (SPV / LEADER / HELPER)
        |--------------------------------------------------------------------------
        | Operator pembanding diambil PER TANGGAL, mengikuti kolom "tim" milik
        | employee (non operator) itu sendiri pada tanggal tsb:
        |   - tim = 1  -> hanya operator dengan tim = 1
        |   - tim = 2  -> hanya operator dengan tim = 2
        |   - tim null -> semua operator (tanpa filter tim)
        |--------------------------------------------------------------------------
        */ else {
            // Assignment milik employee (non operator) ini sendiri, dikelompokkan per tanggal
            $employeeAssignmentsByDate = $assignments->groupBy('date');
            // dd($employeeAssignmentsByDate);

            $totalDeptInsentif = 0;
            $operatorNpks = [];

            foreach ($employeeAssignmentsByDate as $date => $rowsForDate) {

                /*
                |--------------------------------------------------------------------------
                | CHECK RESIGN (NEW)
                |--------------------------------------------------------------------------
                */
                if ($tkkDate && $date >= $tkkDate) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | AMBIL TIM EMPLOYEE UNTUK TANGGAL INI
                |--------------------------------------------------------------------------
                */
                $tim = $rowsForDate
                    ->pluck('tim')
                    ->filter(fn($t) => $t !== null && $t !== '')
                    ->first();

                /*
                |----------------------------------
                | TOTAL DEPT INSENTIF (NUMERATOR)
                | ONLY VALID OPERATOR, TIM SAMA (JIKA ADA)
                |----------------------------------
                */
                $operatorQuery = DB::table('heat_efficiencies')
                    ->where('period_id', $period->id)
                    ->where('role', '=', 'operator')
                    ->where('date', $date);

                if (!is_null($tim)) {
                    $operatorQuery->where('tim', $tim);
                }

                $operatorsForDate = $operatorQuery->get();

                // dd($tim, $operatorsForDate);

                foreach ($operatorsForDate as $operator) {
                    // FILTER HANYA NUMERATOR
                    if (!$isValidOvertime($operator->npk, $operator->date)) {
                        continue;
                    }

                    $rate = $this->getInsentifByEfficiency(
                        $operator->efficiency,
                        $formula
                    );

                    $totalDeptInsentif += $rate * $operator->piece;
                    $operatorNpks[] = $operator->npk;

                    // dd($totalDeptInsentif);
                }
            }

            /*
            |----------------------------------
            | DENOMINATOR (OPERATOR UNIK, TIM SAMA PER TANGGAL)
            |----------------------------------
            */
            $jumlahOperator = collect($operatorNpks)->unique()->count();

            // dd($totalDeptInsentif, $jumlahOperator);

            $amount += $this->calculateRoleHeatInsentif(
                $role,
                'heat',
                $totalDeptInsentif,
                $jumlahOperator
            );
        }
        // dd($jumlahOperator, $totalDeptInsentif, $amount, $role);


        return $amount;
    }


    private function calculateRoleHeatInsentif(
        $role,
        $dept,
        $totalDeptInsentif,
        $jumlahOperator
    ) {

        $jumlahOperator = max($jumlahOperator, 1);

        /*
    |--------------------------------------------------------------------------
    | GET FORMULA FROM DB (CACHE)
    |--------------------------------------------------------------------------
    */

        $formula = Cache::remember(
            "insentif_formula_{$dept}_{$role}",
            300,
            function () use ($role, $dept) {

                return InsentifRoleFormula::where('role', $role)
                    ->where('dept', $dept)
                    ->value('formula');
            }
        );

        /*
    |--------------------------------------------------------------------------
    | DEFAULT FALLBACK
    |--------------------------------------------------------------------------
    */

        if (!$formula) {
            return $totalDeptInsentif;
        }

        /*
    |--------------------------------------------------------------------------
    | VARIABLE REPLACEMENT
    |--------------------------------------------------------------------------
    */

        $variables = [
            'totalDeptInsentif' => $totalDeptInsentif,
            'jumlahOperator'    => $jumlahOperator,
        ];

        foreach ($variables as $key => $value) {
            $formula = str_replace($key, $value, $formula);
        }

        /*
    |--------------------------------------------------------------------------
    | SAFE EVALUATION
    |--------------------------------------------------------------------------
    */

        try {

            // hanya izinkan karakter matematika
            if (!preg_match('/^[0-9\.\+\-\*\/\(\) ]+$/', $formula)) {
                throw new \Exception('Invalid formula');
            }

            return eval("return {$formula};");
        } catch (\Throwable $e) {

            return $totalDeptInsentif;
        }
    }
}
