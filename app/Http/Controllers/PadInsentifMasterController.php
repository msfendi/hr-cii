<?php

namespace App\Http\Controllers;

use App\Events\NotificationEvent;
use Illuminate\Http\Request;
use App\Models\InsentifMaster;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PadInsentifTemplateExport;
use App\Imports\PadInsentifImport;
use App\Models\InsentifApproval;
use App\Models\InsentifRoleFormula;
use App\Models\PadEfficiency;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;

class PadInsentifMasterController extends Controller
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

        $data = DB::table('pad_efficiencies as p')
            ->join('payroll_periods as pp', 'p.period_id', '=', 'pp.id')

            ->leftJoinSub($biodataUnion, 'bio', function ($join) {
                $join->on('p.NPK', '=', 'bio.NPK');
            })

            ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'bio.ID_DEPT')
            ->select(
                'p.id',
                'p.npk',
                'bio.NAMA_KARYAWAN as name',
                'd.DEPARTEMENT as dept',
                'p.role',
                'pp.name as period',
                'p.efficiency',
                'p.piece',
                'p.date'
            )
            ->where('pp.is_closed', 0)
            ->orderBy('p.date')
            ->get();
        $periods = PayrollPeriod::select('id', 'name')
            ->where('is_closed', 0)
            ->orderBy('id', 'desc')
            ->get();
        // dd($data);
        return view('pad_insentif_master.index', compact('data', 'periods'));
    }

    public function getData($period)
    {
        $periods = PayrollPeriod::findOrFail($period);
        $periodEnd = $periods->end_date;
        $biodataUnion = DB::connection('cii')
            ->table('BIODATA')
            ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', 'IS_STAFF', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'))
            ->unionAll(
                DB::connection('cii')
                    ->table('BIODATA_KELUAR')
                    ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', 'IS_STAFF', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'))
            );
        $nextMutation = DB::table('employee_mutations as em1')
            ->select(
                'em1.npk',
                'em1.from_dept',
                'em1.to_dept',
                'em1.date'
            )
            ->where('em1.date', '>', $periodEnd)
            ->whereRaw('em1.id = (
        SELECT MIN(em2.id)
        FROM employee_mutations em2
        WHERE em2.npk = em1.npk
        AND em2.date > ?
    )', [$periodEnd]);

        $data = DB::table('pad_efficiencies as p')
            ->join('payroll_periods as pp', 'p.period_id', '=', 'pp.id')

            ->leftJoinSub($biodataUnion, 'bio', function ($join) {
                $join->on('p.NPK', '=', 'bio.NPK');
            })
            ->leftJoinSub($nextMutation, 'em', function ($join) {
                $join->on('bio.NPK', '=', 'em.npk');
            })

            ->leftJoin('DEPT as d', function ($join) {
                $join->on(
                    'd.ID_DEPT',
                    '=',
                    DB::raw("
            CASE
                WHEN em.from_dept IS NOT NULL
                THEN em.from_dept
                ELSE bio.ID_DEPT
            END
        ")
                );
            })

            ->select(
                'p.id',
                'p.npk',
                'bio.NAMA_KARYAWAN as name',
                'd.DEPARTEMENT as dept',
                'p.role',
                'pp.name as period',
                'p.efficiency',
                'p.piece',
                'p.date'
            )
            ->where('p.period_id', $period)
            ->orderBy('p.date')
            ->get();

        return response()->json($data);
    }

    public function create()
    {
        return view('pad_insentif_master.create');
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

        return redirect()->route('pad-insentif-master.index')
            ->with('success', 'Data berhasil disimpan');
    }

    public function edit($id)
    {
        $data = InsentifMaster::findOrFail($id);
        return view('pad-insentif-master.edit', compact('data'));
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

        return redirect()->route('pad-insentif-master.index')
            ->with('success', 'Data berhasil diupdate');
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $data = PadEfficiency::findOrFail($id);
        $data->delete();

        event(new NotificationEvent(
            'Pad Print Insentif!',
            'User : ' . $user->name . ' has deleted Pad Print Insentif!',
            'danger'
        ));

        Alert::success('Deleted Successfully!', 'Pad Print Insentif succesfully deleted!');

        return redirect()->route('pad-insentif-master.index')
            ->with('success', 'Data berhasil dihapus');
    }

    public function template()
    {
        return Excel::download(new PadInsentifTemplateExport, 'template_pad_insentif_master.xlsx');
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

        $component = 'pad_insentif';

        /*
    =====================================
    JIKA INSENTIF → IMPORT EXCEL
    =====================================
    */
        // PadEfficiency::where('period_id', $period->id)->delete();
        if ($request->is_insentif == 1) {

            Excel::import(
                new PadInsentifImport($request->period_id),
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
            'Pad Print Insentif!',
            'User : ' . $user->name . ' has imported Pad Print Insentif ' . $period->name . '!',
            'success'
        ));

        return back()->with('success', 'Process berhasil dijalankan');
    }

    public function check($period_id)
    {
        $period = PayrollPeriod::findOrFail($period_id);

        $periodStart = $period->start_date;
        $periodEnd   = $period->end_date;


        /*
        |--------------------------------------------------------------------------
        | AMBIL NPK YANG BENAR-BENAR ADA ASSIGNMENT
        |--------------------------------------------------------------------------
        */

        $assignmentNpk = DB::table(DB::raw("
        (
            SELECT * FROM pad_efficiencies
        ) pe
    "))->where('pe.period_id', $period->id);

        // dd($assignmentNpk->get());


        /*
    |--------------------------------------------------------------------------
    | EMPLOYEE SOURCE (TETAP SAMA LOGIC)
    |--------------------------------------------------------------------------
    */
        $nextMutation = DB::table('employee_mutations as em1')
            ->select(
                'em1.npk',
                'em1.from_dept',
                'em1.to_dept',
                'em1.date'
            )
            ->where('em1.date', '>', $periodEnd)
            ->whereRaw('em1.id = (
        SELECT MIN(em2.id)
        FROM employee_mutations em2
        WHERE em2.npk = em1.npk
        AND em2.date > ?
    )', [$periodEnd]);

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
            ->leftJoinSub($nextMutation, 'em', function ($join) {
                $join->on('emp.NPK', '=', 'em.npk');
            })

            ->leftJoin('DEPT as d', function ($join) {
                $join->on(
                    'd.ID_DEPT',
                    '=',
                    DB::raw("
            CASE
                WHEN em.from_dept IS NOT NULL
                THEN em.from_dept
                ELSE emp.ID_DEPT
            END
        ")
                );
            })
            ->joinSub(
                DB::table('insentif_role_formulas')
                    ->select('role')
                    ->distinct(),
                'irf',
                function ($join) {
                    $join->on('anpk.role', '=', 'irf.role');
                }
            )
            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereNull('p.TKK')
                    ->orWhereBetween('p.TKK', [$periodStart, $periodEnd]);
            })

            ->select(
                'p.NPK',
                'emp.NAMA_KARYAWAN',
                'anpk.role',
                'p.TMK',
                'p.TKK as tkk',
                'emp.ID_DEPT',
                'd.DEPARTEMENT as DEPARTEMENT',
            );

        $employees = DB::connection('cii')
            ->query()
            ->fromSub($employeeBase, 'emp')
            ->distinct()
            ->get();

        // dd($employees); 


        /*
    |--------------------------------------------------------------------------
    | FORMULA (TETAP)
    |--------------------------------------------------------------------------
    */

        $padInsentifFormula = json_decode(
            PayrollComponent::where('code', 'pad_insentif')
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
            $pad = $this->calculatePad(
                $employee,
                $period,
                $padInsentifFormula,
                $employee->role,
            );

            // 🔹 PERBAIKAN: bulatkan hasil insentif supaya tidak selisih
            // dengan job (GeneratePayrollProcess/V2 membulatkan tiap
            // komponen sebelum dijumlahkan ke grandTotal).
            $pad = round((float) $pad, 0);

            if ($pad <= 0) continue;

            $results[] = [
                'npk' => $employee->NPK,
                'name' => $employee->NAMA_KARYAWAN,
                'pad_insentif' => $pad,
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
        | kalau 1 NPK punya 2 role dalam periode yang sama (mis. operator lalu
        | jadi leader), insentifnya muncul sebagai 2 baris berbeda dan TIDAK
        | dijumlah. Di sini digabung jadi 1 baris per NPK, insentif diakumulasi,
        | dan role ditampilkan gabungan (mis. "operator, leader").
        |--------------------------------------------------------------------------
        */
        $results = $this->mergeInsentifByNpk($results, 'pad_insentif');

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
    | PAD PRINT INSENTIF (COPY PAYROLL)
    |--------------------------------------------------------------------------
    */

    private function calculatePad($employee, $period, $formula, $role)
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
        // Ambil overtime semua NPK agar validasi operator pembanding akurat.
        $overtimes = DB::table('overtimes')
            ->whereBetween('OVERTIME_DATE', [$period->start_date, $period->end_date])
            ->get()
            ->groupBy(fn($o) => Carbon::parse($o->OVERTIME_DATE)->format('Y-m-d'));


        /*
    |--------------------------------------------------------------------------
    | VALIDATE OVERTIME
    |--------------------------------------------------------------------------
    */
        $isValidOvertime = function ($npk, $date) use ($overtimes) {
            $dateKey = Carbon::parse($date)->format('Y-m-d');
            $record = $overtimes->get($dateKey, collect())
                ->first(fn($row) => (string) $row->NPK === (string) $npk);

            if (!$record) {
                return true;
            }

            $lembur = $record->JUMLAH_JAM_LEMBUR;
            return $lembur === null || $lembur === '' || is_numeric($lembur);
        };

        /*
    |--------------------------------------------------------------------------
    | LOAD ASSIGNMENT
    |--------------------------------------------------------------------------
    | Difilter berdasarkan $role yang sedang dihitung. Karyawan bisa punya
    | lebih dari satu role dalam 1 periode (mis. operator tgl 3-4 Agustus,
    | lalu jadi leader mulai tgl 5) — calculatePad() dipanggil terpisah per
    | (npk, role) dari check(), jadi assignment yang diambil di sini juga
    | harus dibatasi ke role tsb saja supaya tanggal role lain tidak ikut
    | tercampur ke perhitungan.
    |--------------------------------------------------------------------------
    */
        $assignments = DB::table('pad_efficiencies')
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
        } else {
            // Mengikuti sheet INSENTIF PER PERSON:
            // supervisor/admin = rata-rata total operator sebulan;
            // inkmaking/helper = rata-rata tanggal 1-7 semua tim ditambah
            // rata-rata tanggal 8-akhir untuk tim terkait.
            $employeeAssignments = $assignments->sortBy('date')->values();
            $firstAssignmentDate = $employeeAssignments->first()
                ? Carbon::parse($employeeAssignments->first()->date)->format('Y-m-d')
                : null;

            $averageForWindow = function ($startDate, $endDate, $team = null) use (
                $period,
                $formula,
                $isValidOvertime,
                $tkkDate
            ) {
                $operatorRows = DB::table('pad_efficiencies')
                    ->where('period_id', $period->id)
                    ->where('role', 'operator')
                    ->whereBetween('date', [$startDate, $endDate])
                    ->when($team !== null, fn($q) => $q->where('tim', $team))
                    ->get();

                $perOperator = [];
                foreach ($operatorRows as $operator) {
                    $date = Carbon::parse($operator->date)->format('Y-m-d');
                    if (($tkkDate && $date >= $tkkDate) ||
                        !$isValidOvertime($operator->npk, $date)
                    ) {
                        continue;
                    }

                    $rate = $this->getInsentifByEfficiency($operator->efficiency, $formula);
                    $perOperator[$operator->npk] = ($perOperator[$operator->npk] ?? 0)
                        + ($rate * (float) $operator->piece);
                }

                return count($perOperator) ? array_sum($perOperator) / count($perOperator) : 0;
            };

            if (in_array($role, ['supervisor', 'admin'], true)) {
                $amount = $averageForWindow($period->start_date, $period->end_date);
            } else {
                $team = $employeeAssignments->pluck('tim')
                    ->first(fn($value) => $value !== null && $value !== '');

                $periodStart = Carbon::parse($period->start_date);
                $periodEnd = Carbon::parse($period->end_date);
                $firstWeekEnd = $periodStart->copy()->day(7);
                if ($firstWeekEnd->gt($periodEnd)) {
                    $firstWeekEnd = $periodEnd->copy();
                }

                // Helper yang baru mulai tanggal 16 mengikuti rumus khusus Excel
                // (hanya rata-rata periode 16-akhir).
                $lateOnly = $role === 'helper'
                    && $firstAssignmentDate !== null
                    && Carbon::parse($firstAssignmentDate)->day >= 16;

                if (!$lateOnly && $periodStart->lte($firstWeekEnd)) {
                    $amount += $averageForWindow(
                        $periodStart->format('Y-m-d'),
                        $firstWeekEnd->format('Y-m-d')
                    );
                }

                $secondStart = $periodStart->copy()->day($lateOnly ? 16 : 8);
                if ($secondStart->lt($periodStart)) {
                    $secondStart = $periodStart->copy();
                }

                if ($secondStart->lte($periodEnd)) {
                    $amount += $averageForWindow(
                        $secondStart->format('Y-m-d'),
                        $periodEnd->format('Y-m-d'),
                        $team
                    );
                }
            }

            // Formula DB tetap menjadi sumber faktor role, misalnya Helper 0.75.
            $amount = $this->calculateRolePadInsentif($role, 'pad', $amount, 1);
        }

        return $amount;
    }


    private function calculateRolePadInsentif(
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
