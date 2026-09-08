<?php

namespace App\Http\Controllers;

use App\Events\NotificationEvent;
use Illuminate\Http\Request;
use App\Models\InsentifMaster;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\QcInsentifTemplateExport;
use App\Imports\QcInsentifImport;
use App\Models\InsentifApproval;
use App\Models\InsentifRoleFormula;
use App\Models\QcEfficiency;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;

class QcInsentifMasterController extends Controller
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


        $periods = PayrollPeriod::select('id', 'name')
            ->where('is_closed', 0)
            ->orderBy('id', 'desc')
            ->get();

        $data = DB::table('employee_qc_assignments as ela')

            ->leftJoin('qc_efficiencies as l', function ($join) {
                $join->on('ela.period_id', '=', 'l.period_id')
                    ->on('ela.line_number', '=', 'l.line_number')
                    ->whereNotNull('ela.line_number')
                    ->whereColumn('ela.start_date', 'l.date');
            })

            ->leftJoinSub($biodataUnion, 'bio', function ($join) {
                $join->on('ela.NPK', '=', 'bio.NPK');
            })

            ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'bio.ID_DEPT')

            ->join('payroll_periods as pp', 'l.period_id', '=', 'pp.id')
            ->select(
                'ela.id',
                'pp.name as period',
                'ela.npk',
                'bio.NAMA_KARYAWAN as nama',
                'd.DEPARTEMENT as dept',
                'l.efficiency',
                'l.line_number',
                'l.date'
            )
            ->where('pp.is_closed', 0)
            ->orderBy('l.date')
            ->get();
        return view('qc_insentif_master.index', compact('data', 'periods'));
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

        $data = DB::table('employee_qc_assignments as ela')

            ->leftJoin('qc_efficiencies as l', function ($join) {
                $join->on('ela.period_id', '=', 'l.period_id')
                    ->on('ela.line_number', '=', 'l.line_number')
                    ->whereNotNull('ela.line_number')
                    ->whereColumn('ela.start_date', 'l.date');
            })

            ->leftJoinSub($biodataUnion, 'bio', function ($join) {
                $join->on('ela.NPK', '=', 'bio.NPK');
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

            ->join('payroll_periods as pp', 'l.period_id', '=', 'pp.id')
            ->select(
                'ela.id',
                'pp.name as period',
                'ela.npk',
                'bio.NAMA_KARYAWAN as nama',
                'd.DEPARTEMENT as dept',
                'l.efficiency',
                'l.line_number',
                'l.date'
            )
            ->where('l.period_id', $period)
            ->orderBy('l.date')
            ->get();

        return response()->json($data);
    }

    public function create()
    {
        return view('qc_insentif_master.create');
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

        return redirect()->route('qc-insentif-master.index')
            ->with('success', 'Data berhasil disimpan');
    }

    public function edit($id)
    {
        $data = InsentifMaster::findOrFail($id);
        return view('qc-insentif-master.edit', compact('data'));
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

        return redirect()->route('qc-insentif-master.index')
            ->with('success', 'Data berhasil diupdate');
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $data = QcEfficiency::findOrFail($id);
        $data->delete();

        event(new NotificationEvent(
            'Qc Insentif!',
            'User : ' . $user->name . ' has deleted Qc Insentif!',
            'danger'
        ));

        Alert::success('Deleted Successfully!', 'Qc Insentif succesfully deleted!');

        return redirect()->route('qc-insentif-master.index')
            ->with('success', 'Data berhasil dihapus');
    }

    public function template()
    {
        return Excel::download(new QcInsentifTemplateExport, 'template_qc_insentif_master.xlsx');
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

        $component = 'qc_insentif';

        /*
    =====================================
    JIKA INSENTIF → IMPORT EXCEL
    =====================================
    */
        // QcEfficiency::where('period_id', $period->id)->delete();
        if ($request->is_insentif == 1) {

            Excel::import(
                new QcInsentifImport($request->period_id),
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
            'Qc Insentif!',
            'User : ' . $user->name . ' has imported Qc Insentif ' . $period->name . '!',
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
    | AMBIL NPK YANG ADA ASSIGNMENT
    |--------------------------------------------------------------------------
    */

        $assignmentNpk = DB::table(DB::raw("
        (
            SELECT * FROM employee_qc_assignments
        ) ela
    "))->where('ela.period_id', $period->id);

        // dd($assignmentNpk->get());

        /*
    |--------------------------------------------------------------------------
    | EMPLOYEE SOURCE
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


        $employeeViolationSummary = DB::table('employee_violations')
            ->select(
                'npk',
                DB::raw('SUM(percentage) as violation_percentage')
            )
            ->where('period_id', $period->id)
            ->groupBy('npk');

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
            ->leftJoinSub($employeeViolationSummary, 'ev', function ($join) {
                $join->on('emp.NPK', '=', 'ev.npk');
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
            ->leftJoin('sections as s', function ($join) {
                $join->on(
                    DB::raw('TRY_CAST(emp.SECTION AS BIGINT)'),
                    '=',
                    's.id'
                )->where('s.id', '!=', 109);
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
            ->leftJoin('qc_efficiencies as le', function ($join) {
                $join->on('le.period_id', '=', 'anpk.period_id')
                    ->on('le.line_number', '=', 'anpk.line_number')
                    ->on('le.date', '=', 'anpk.start_date');
            })
            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereNull('p.TKK')
                    ->orWhereBetween('p.TKK', [$periodStart, $periodEnd]);
            })

            // ->where('emp.NPK', '=', 'C-00796')

            ->select(
                'p.NPK',
                'emp.NAMA_KARYAWAN',
                'anpk.role',
                'anpk.line_number as assignment_line_number',
                'p.TMK',
                'p.TKK as tkk',
                'emp.ID_DEPT',
                'd.DEPARTEMENT as DEPARTEMENT',
                'emp.SECTION as SECTION',
                's.line_start',
                's.line_end',
                DB::raw('COALESCE(ev.violation_percentage, 0) as violation_percentage'),
                DB::raw("
                CASE
                    WHEN em.from_dept IS NOT NULL
                    THEN em.from_dept
                    ELSE emp.ID_DEPT
                END as payroll_dept
                "),
            );

        $employees = DB::connection('cii')
            ->query()
            ->fromSub($employeeBase, 'emp')
            ->distinct()
            ->get();

        // dd($employees);

        /*
    |--------------------------------------------------------------------------
    | GROUP PER NPK + ROLE (FIX DUPLICATE ROWS)
    |--------------------------------------------------------------------------
    | 1 NPK bisa punya beberapa row employee_qc_assignments dengan role yang
    | SAMA tapi line_number berbeda (mis. dibantu di line 2 & 3 di tanggal
    | yang sama). calculateQc() sendiri sudah menjumlahkan SEMUA assignment
    | milik NPK tsb untuk role itu (query di dalamnya hanya filter npk +
    | period_id, tidak filter per baris/line_number), jadi kalau baris
    | duplikat ini dibiarkan lolos ke loop di bawah, calculateQc() akan
    | dipanggil & dihitung ulang beberapa kali dengan hasil yang identik →
    | makanya di tabel "Detail Insentif Karyawan" NPK yang sama + role yang
    | sama muncul 2x dengan nominal yang sama persis.
    |
    | Fix: group dulu per (NPK, role) SEBELUM dihitung, supaya calculateQc()
    | hanya dipanggil SEKALI per kombinasi NPK+role (bukan di-sum setelah
    | dihitung 2x, karena itu justru akan melipatgandakan nominalnya).
    | Line number dari assignment yang ke-collapse tetap dikumpulkan supaya
    | info "Line ..." di kolom line_info tidak hilang.
    |--------------------------------------------------------------------------
    */
        $employees = $employees
            ->groupBy(function ($employee) {
                return $employee->NPK . '|' . strtolower($employee->role ?? '');
            })
            ->map(function ($group) {
                $employee = $group->first();

                $employee->assignment_lines = $group
                    ->pluck('assignment_line_number')
                    ->filter(fn($line) => $line !== null && $line !== '')
                    ->unique()
                    ->sort()
                    ->values();

                return $employee;
            })
            ->values();

        /*
    |--------------------------------------------------------------------------
    | FORMULA
    |--------------------------------------------------------------------------
    */

        $qcInsentifFormula = json_decode(
            PayrollComponent::where('code', 'qc_insentif')->value('formula'),
            true
        );

        /*
    |--------------------------------------------------------------------------
    | CALCULATION
    |--------------------------------------------------------------------------
    */

        $results = [];
        // dd(
        //     $employees->groupBy('NPK')
        //         ->map(fn($x) => $x->count())
        //         ->filter(fn($x) => $x > 1)
        // );
        foreach ($employees as $employee) {

            $status = $employee->tkk ? 'Resign' : 'Active';

            $qc = $this->calculateQc(
                $employee,
                $period,
                $qcInsentifFormula,
                $employee->role
            );

            // dd(
            //     $employee,
            //     $period,
            //     $qcInsentifFormula,
            //     $employee->role
            // );

            // dd($qc);

            if ($qc <= 0) continue;

            $dept = $employee->DEPARTEMENT;

            if ($employee->line_start !== null && $employee->line_end !== null) {
                $dept .= " ({$employee->line_start}-{$employee->line_end})";
            }

            // =========================
            // KETERANGAN LINE
            // - Operator & Spv         : line spesifik dari employee_qc_assignments
            // - Role lain (chief/qa)   : range line dari section (sections.line_start - line_end)
            // =========================
            $roleLower = strtolower($employee->role ?? '');

            if (in_array($roleLower, ['operator', 'spv'])) {
                $lineInfo = (isset($employee->assignment_lines) && $employee->assignment_lines->isNotEmpty())
                    ? 'Line ' . $employee->assignment_lines->implode(', ')
                    : '-';
            } else {
                $lineInfo = ($employee->line_start !== null && $employee->line_end !== null)
                    ? 'Line ' . $employee->line_start . '-' . $employee->line_end
                    : '-';
            }

            $results[] = [
                'npk' => $employee->NPK,
                'name' => $employee->NAMA_KARYAWAN,
                'dept' => $dept,
                'role' => $employee->role,
                'line_info' => $lineInfo,
                'qc_insentif' => $qc,
                'tkk' => $employee->tkk,
                'status' => $status
            ];
        }

        return response()->json([
            'data' => $results
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | QC INSENTIF (COPY 1:1 LINE INSENTIF)
    |--------------------------------------------------------------------------
    */

    private function calculateQc($employee, $period, $formula, $role)
    {
        // dd($role);
        $amount = 0;

        $collectionLinesTest = collect([]);

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
        | LOAD THRESHOLD
        |--------------------------------------------------------------------------
        */
        $thresholds = DB::table('insentif_thresholds')
            ->where('insentif_type', 'QC')
            ->where('type', 'Percentage')
            ->pluck('minimum', 'days');

        $getMinEfficiency = function ($dayIndex) use ($thresholds) {

            if (isset($thresholds[$dayIndex])) {
                return $thresholds[$dayIndex];
            }

            return $thresholds->max();
        };

        /*
    |--------------------------------------------------------------------------
    | LOAD OVERTIME (ONCE)
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
    | FUNCTION VALIDATE OVERTIME
    |--------------------------------------------------------------------------
    */
        $isValidOvertime = function ($date) use ($overtimes) {

            if (!isset($overtimes[$date])) {
                return true; // tidak ada overtime → tetap dihitung
            }

            $lembur = $overtimes[$date]->JUMLAH_JAM_LEMBUR;

            // NULL → tetap dihitung
            if ($lembur === null || $lembur === '') {
                return true;
            }

            // numeric → tetap dihitung
            if (is_numeric($lembur)) {
                return true;
            }

            // karakter (MA, CT, BR, S1, dll)
            return false;
        };


        /*
        |--------------------------------------------------------------------------
        | OPERATOR
        |--------------------------------------------------------------------------
        | SPV dipindahkan ke cabang CHIEF/QA (section-based) karena formula QC SPV
        | = (Total QC Incentive 1 Section / Total Line) * 50%, bukan per-line
        | seperti Operator.
        |--------------------------------------------------------------------------
        */
        $lineViolations = 0;
        if ($role == 'operator') {

            /*
            |--------------------------------------------------------------------------
            | GET INITIAL LINE
            |--------------------------------------------------------------------------
            */
            preg_match('/\d+/', $employee->DEPARTEMENT, $matches);
            $defaultLine = $matches[0] ?? null;

            /*
            |--------------------------------------------------------------------------
            | GET ALL LINE EFFICIENCIES
            |--------------------------------------------------------------------------
            */
            $lineefficiencies = DB::table('employee_qc_assignments as ela')
                ->leftJoin('qc_efficiencies as le', function ($join) {
                    $join->on('le.period_id', '=', 'ela.period_id')
                        ->on('le.line_number', '=', 'ela.line_number')
                        ->on('le.date', '=', 'ela.start_date');
                })

                ->leftJoinSub(
                    DB::table('employee_qc_assignments')
                        ->select(
                            'period_id',
                            'line_number',
                            'start_date',
                            DB::raw('MAX(work_hours) as max_work_hours')
                        )
                        ->groupBy(
                            'period_id',
                            'line_number',
                            'start_date'
                        ),
                    'max_wh',
                    function ($join) {
                        $join->on('max_wh.period_id', '=', 'ela.period_id')
                            ->on('max_wh.line_number', '=', 'ela.line_number')
                            ->on('max_wh.start_date', '=', 'ela.start_date');
                    }
                )

                ->where('ela.period_id', $period->id)
                ->where('ela.npk', $employee->NPK)
                ->whereBetween('le.date', [$period->start_date, $period->end_date])

                ->select(
                    'ela.npk',
                    'le.line_number',
                    'le.efficiency',
                    'le.date',

                    // work hours employee
                    'ela.work_hours',

                    // max work hours pada line & tanggal yang sama
                    'max_wh.max_work_hours'
                )

                ->orderBy('le.date')
                ->get();

            // dd($lineefficiencies);

            // NOTE: reuses `sewing_violations` (same as Line Insentif) since no
            // dedicated QC violations table was specified. Swap the table name
            // here if QC should count against a separate violations source.
            // Cabang ini sekarang hanya menangani role 'operator' (SPV sudah
            // dipindahkan ke cabang CHIEF/QA di bawah).
            if (strtolower($role) == 'operator') {

                $lineViolations = DB::table('sewing_violations')
                    ->whereBetween('tanggal', [
                        $period->start_date,
                        $period->end_date
                    ])
                    ->where('id_dept', $employee->ID_DEPT)
                    ->count();
            } else {

                $lineViolations = 0;
            }

            // dd($employee, $lineViolations);

            foreach ($lineefficiencies as $row) {

                /*
                |--------------------------------------------------------------------------
                | CHECK RESIGN (NEW)
                |--------------------------------------------------------------------------
                */
                if ($tkkDate && $row->date >= $tkkDate) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | CHECK OVERTIME
                |--------------------------------------------------------------------------
                */
                if (!$isValidOvertime($row->date)) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | CALCULATE INSENTIF
                |--------------------------------------------------------------------------
                | CUTOFF: jika QC efficiency line/hari ini > 2%, insentif untuk
                | line/hari tersebut 0 (bukan dihitung dari tabel tier seperti
                | biasa).
                |--------------------------------------------------------------------------
                */
                $lineInsentif =
                    $this->getInsentifByDefectRate($row->efficiency, $formula) * $row->work_hours / $row->max_work_hours;

                $amount += $this->calculateRoleQcInsentif(
                    $role,
                    'qc',
                    $lineInsentif,
                    1, //karena hanya 1 line
                    $lineViolations,
                    $employee->violation_percentage
                );
            }
        } else {

            /*
            |--------------------------------------------------------------------------
            | CHIEF / QA / SPV (SECTION-BASED)
            |--------------------------------------------------------------------------
            | SPV masuk ke sini karena formula QC SPV berbasis Total QC Incentive
            | dalam 1 Section dibagi Total Line (sama seperti Chief & QA), bukan
            | per-line individual.
            |--------------------------------------------------------------------------
            */
            $validRoles = ['chief', 'qa', 'spv'];

            if (!in_array($role, $validRoles)) {
                return $amount;
            }


            if ($role === 'qa') {

                /*
                |--------------------------------------------------------------------------
                | QA: line ditentukan dari kolom BUYER di employee_qc_assignments
                | (bukan lagi dari line_setup CSV).
                |
                | Tiap row assignment qa (1 row = 1 tanggal) punya buyer sendiri,
                | mis. tanggal 1 buyer = "muji". Line & jumlahLine untuk tanggal
                | itu diambil dari qc_efficiencies pada tanggal yang sama dengan
                | buyer yang sama (bukan dari daftar line manual).
                |--------------------------------------------------------------------------
                */
                $qaAssignments = DB::table('employee_qc_assignments')
                    ->where('npk', $employee->NPK)
                    ->where('period_id', $period->id)
                    ->where('role', 'qa')
                    ->whereBetween('start_date', [
                        $period->start_date,
                        $period->end_date
                    ])
                    ->select('start_date as date', 'buyer')
                    ->orderBy('start_date')
                    ->get();

                if ($qaAssignments->isEmpty()) {
                    return $amount;
                }

                // Untuk tiap tanggal, ambil line-line di qc_efficiencies yang
                // buyer-nya sama dengan buyer assignment tanggal itu. Sekalian
                // kumpulkan union line_number (dipakai untuk lineViolations,
                // sama seperti sebelumnya).
                $qaByDate = collect([]);
                $allLineNumbers = collect([]);

                foreach ($qaAssignments as $assignment) {

                    if (empty($assignment->buyer)) {
                        continue;
                    }

                    $linesOfDay = DB::table('qc_efficiencies')
                        ->where('period_id', $period->id)
                        ->where('date', $assignment->date)
                        ->where('buyer', $assignment->buyer)
                        ->get();

                    if ($linesOfDay->isEmpty()) {
                        continue;
                    }

                    $qaByDate->push((object) [
                        'date'  => $assignment->date,
                        'lines' => $linesOfDay,
                    ]);

                    $allLineNumbers = $allLineNumbers->merge($linesOfDay->pluck('line_number'));
                }

                if ($qaByDate->isEmpty()) {
                    return $amount;
                }

                $allLineNumbers = $allLineNumbers->unique()->values()->all();

                // NOTE: reuses `sewing_violations`, sama seperti chief/spv,
                // cuma filter line-nya pakai gabungan seluruh line hasil
                // pencarian buyer sepanjang periode (IN), bukan range section
                // (BETWEEN).
                $lineViolations = DB::table('sewing_violations')
                    ->leftJoin('DEPT as d', 'sewing_violations.id_dept', '=', 'd.ID_DEPT')
                    ->whereBetween('sewing_violations.tanggal', [
                        $period->start_date,
                        $period->end_date
                    ])
                    ->where('d.DEPARTEMENT', 'like', 'LINE %')
                    ->whereIn(
                        DB::raw("CAST(REPLACE(d.DEPARTEMENT,'LINE ','') AS INT)"),
                        $allLineNumbers
                    )
                    ->count();

                $collectionDay = collect([]);
                $collectionTotalLines = collect([]);
                $collectionLines = collect([]);

                // dd($qaByDate, $allLineNumbers, $lineViolations);

                foreach ($qaByDate as $day) {

                    if ($tkkDate && $day->date >= $tkkDate) {
                        continue;
                    }

                    if (!$isValidOvertime($day->date)) {
                        continue;
                    }

                    $totalLineInsentif = 0;

                    foreach ($day->lines as $line) {

                        $totalLineInsentif +=
                            $this->getInsentifByDefectRate($line->efficiency, $formula);

                        if ($totalLineInsentif <= 0) {
                            continue;
                        }

                        $collectionLines->push($totalLineInsentif);
                    }

                    // jumlahLine dihitung PER TANGGAL, dari jumlah line di
                    // qc_efficiencies yang buyer-nya sama dengan buyer
                    // assignment tanggal itu.
                    $jumlahLine = $day->lines->count();

                    $amount += $this->calculateRoleQcInsentif(
                        $role,
                        'qc',
                        $totalLineInsentif,
                        $jumlahLine,
                        $lineViolations,
                        $employee->violation_percentage
                    );

                    $collectionDay->push($amount);
                    $collectionTotalLines->push($jumlahLine);
                }
                // dd($collectionDay->values()->toJson(), $collectionTotalLines->values()->toJson(), $collectionLines->values()->toJson());
            } else {

                $section = DB::table('sections')
                    ->whereRaw('id = ?', [(int) $employee->SECTION])
                    ->select('line_start', 'line_end')
                    ->first();

                // dd($employee->SECTION, $section);

                if (!$section) {
                    return $amount;
                }

                $lineStart = $section->line_start;
                $lineEnd   = $section->line_end;

                $grouped = DB::table('employee_qc_assignments as ela')
                    ->join('qc_efficiencies as le', function ($join) {
                        $join->on('le.period_id', '=', 'ela.period_id')
                            ->on('le.date', '=', 'ela.start_date');
                    })

                    ->where('ela.npk', $employee->NPK)
                    ->where('ela.period_id', $period->id)

                    ->whereBetween('ela.start_date', [
                        $period->start_date,
                        $period->end_date
                    ])

                    ->whereBetween('le.line_number', [
                        $lineStart,
                        $lineEnd
                    ])

                    ->select(
                        // 'le.line_number',
                        'le.date'
                    )

                    ->groupBy(
                        'le.date',
                        // 'le.line_number'
                    )

                    ->orderBy('le.date')
                    ->get();

                // dd($grouped);


                // NOTE: reuses `sewing_violations` filtered to the chief/qa's line
                // range, same mechanism as Line Insentif. Swap if QC needs its own
                // violations source.
                $lineViolations = DB::table('sewing_violations')
                    ->leftJoin('DEPT as d', 'sewing_violations.id_dept', '=', 'd.ID_DEPT')
                    ->whereBetween('sewing_violations.tanggal', [
                        $period->start_date,
                        $period->end_date
                    ])
                    ->where('d.DEPARTEMENT', 'like', 'LINE %')
                    ->whereRaw("
                        CAST(REPLACE(d.DEPARTEMENT,'LINE ','') AS INT)
                        BETWEEN ? AND ?
                    ", [$lineStart, $lineEnd])
                    ->count();

                // dd($lineViolations);

                $collectionDay = collect([]);
                $collectionTotalLines = collect([]);
                $collectionLines = collect([]);

                // $jumlahLine = DB::table('qc_efficiencies')
                //     ->where('period_id', $period->id)
                //     ->whereBetween('date', [$period->start_date, $period->end_date])
                //     ->whereBetween('line_number', [$lineStart, $lineEnd])
                //     ->selectRaw('COUNT(DISTINCT line_number) as jumlah_line')
                //     ->get();

                $jumlahLine = $lineEnd - $lineStart + 1;

                foreach ($grouped as $day) {
                    /*
                |--------------------------------------------------------------------------
                | CHECK RESIGN (NEW)
                |--------------------------------------------------------------------------
                */
                    if ($tkkDate && $day->date >= $tkkDate) {
                        continue;
                    }
                    /*
                |----------------------------------
                | CHECK OVERTIME
                |----------------------------------
                */
                    if (!$isValidOvertime($day->date)) {
                        continue;
                    }

                    $lines = DB::table('qc_efficiencies')
                        ->where('period_id', $period->id)
                        ->where('date', $day->date)
                        ->whereBetween('line_number', [$lineStart, $lineEnd])
                        ->get();

                    $totalLineInsentif = 0;

                    foreach ($lines as $line) {

                        $totalLineInsentif +=
                            $this->getInsentifByDefectRate($line->efficiency, $formula);

                        if ($totalLineInsentif <= 0) {
                            continue;
                        }

                        $collectionLines->push($totalLineInsentif);

                        // dd($grouped, $lines, $totalLineInsentif, $amount);
                    }

                    // dd($grouped, $collectionLines);

                    $amount += $this->calculateRoleQcInsentif(
                        $role,
                        'qc',
                        $totalLineInsentif,
                        // $jumlahLine->first()->jumlah_line,
                        $jumlahLine,
                        $lineViolations,
                        $employee->violation_percentage
                    );

                    $collectionDay->push($amount);
                    $collectionTotalLines->push($jumlahLine);
                }
                // dd($collectionDay->values()->toJson(), $collectionTotalLines->values()->toJson(), $collectionLines->values()->toJson());
            }
        }
        // dd($collectionLinesTest->values()->toJson());
        return $amount;
    }


    /*
    |--------------------------------------------------------------------------
    | ENGINE (COPY LINE INSENTIF)
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
    | QC: arah tier terbalik dari sewing — makin KECIL defect rate makin
    | BESAR insentif. Formula qc_insentif ({"1.0":10000,"1.5":8000,"2.0":6000})
    | dibaca sebagai upper-bound tiap tier, jadi harus ascending + "<=".
    |--------------------------------------------------------------------------
    */
    private function getInsentifByDefectRate($efficiency, $rules)
    {
        ksort($rules);

        foreach ($rules as $threshold => $value) {
            if ($efficiency <= $threshold) {
                return $value;
            }
        }

        return 0;
    }

    private function calculateRoleQcInsentif(
        $role,
        $dept,
        $totalLineInsentif,
        $jumlahLine,
        $violationsCount,
        $employeeViolations,
    ) {

        $jumlahLine = max($jumlahLine, 1);
        // dd($violationsCount);

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
            return $totalLineInsentif;
        }

        /*
    |--------------------------------------------------------------------------
    | VARIABLE REPLACEMENT
    |--------------------------------------------------------------------------
    */

        $variables = [
            'totalLineInsentif' => $totalLineInsentif,
            'jumlahLine'        => $jumlahLine,
            'violationsCount'   => $violationsCount ?? 0,
            'violation_percentage' => $employeeViolations ?? 0
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

            if (!preg_match('/^[0-9\.\+\-\*\/\(\) ]+$/', $formula)) {
                throw new \Exception('Invalid formula');
            }

            return eval("return {$formula};");
        } catch (\Throwable $e) {

            return $totalLineInsentif;
        }
    }
}
