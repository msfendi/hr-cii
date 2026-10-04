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
use Illuminate\Support\Facades\Schema;
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

            // 🔹 PERBAIKAN: bulatkan hasil insentif supaya tidak selisih
            // dengan job (GeneratePayrollProcess/V2 membulatkan tiap
            // komponen sebelum dijumlahkan ke grandTotal).
            $qc = round((float) $qc, 0);

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

            if (in_array($roleLower, ['inline', 'endline', 'fqc', 'spv'], true)) {
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

        /*
        |--------------------------------------------------------------------------
        | GABUNGKAN PER NPK (FIX: 1 NPK 2 ROLE DALAM 1 BULAN)
        |--------------------------------------------------------------------------
        | Dedup per (NPK, role) di atas hanya menghilangkan duplikat baris
        | employee_qc_assignments untuk role yang SAMA. Kalau 1 NPK punya 2
        | role berbeda dalam periode yang sama (mis. operator lalu jadi
        | chief), masing-masing role tetap jadi baris terpisah di $results
        | dan TIDAK dijumlah. Di sini digabung jadi 1 baris per NPK, insentif
        | diakumulasi, dan role/line_info ditampilkan gabungan.
        |--------------------------------------------------------------------------
        */
        $results = $this->mergeInsentifByNpk($results, 'qc_insentif');

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
        | OPERATOR (INLINE / ENDLINE / FQC)
        |--------------------------------------------------------------------------
        | SPV dipindahkan ke cabang CHIEF/QA (section-based) karena formula QC SPV
        | = (Total QC Incentive 1 Section / Total Line) * 50%, bukan per-line
        | seperti Operator.
        |--------------------------------------------------------------------------
        */
        $lineViolations = 0;
        $operatorRoles = ['inline', 'endline', 'fqc']; // semua role operator QC
        if (in_array(strtolower((string) $role), $operatorRoles, true)) {

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
                        ->on('le.date', '=', 'ela.start_date')
                        ->where('le.dept', '=', 'qc');
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
            // Cabang ini sekarang hanya menangani role operator QC:
            // inline, endline, fqc (SPV sudah dipindahkan ke cabang CHIEF/QA di bawah).
            if (in_array(strtolower((string) $role), $operatorRoles, true)) {

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
                    $employee->violation_percentage,
                    $this->qcViolationPercentage($period)
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
            $validRoles = ['chief', 'qa', 'spv', 'qa_leader'];

            if (!in_array($role, $validRoles)) {
                return $amount;
            }


            if (($role === 'qa') || ($role === 'qa_leader')) {

                /*
                |--------------------------------------------------------------------------
                | QA / QA LEADER: sumber qc_efficiencies ditentukan dari assignment
                | (employee_qc_assignments yang line_number-nya NULL), DISTINCT per tanggal.
                | Acuan: sheet "Summary Incentive" (QA INCENTIVE SEPTEMBER 2026).
                |
                | - third_party terisi : qc_efficiencies = tanggal + buyer + third_party tsb
                |     (baris third party, line_number NULL). Contoh: TENTAC / PQC.
                | - third_party kosong : qc_efficiencies = tanggal + buyer saja.
                |     * NPK yang memegang third_party di buyer yang sama => assignment kosong
                |       ikut third_party tsb (tidak dobel di tanggal yang sama).
                |     * Buyer yang punya baris third party (MUJI): hanya baris line_number NULL
                |       (PQC + TENTAC) => total insentif keduanya x 50% (= rata-rata).
                |     * Buyer tanpa third party (GAP, SUKO): baris buyer di-distinct per tanggal
                |       (1 defect rate per buyer per tanggal) => x 50%.
                |
                | Nominal per entry = TOTAL insentif baris yang di-distinct, lalu formula role QC
                | (qa x5/10, qa_leader x7/10, dipotong qc_violation).
                | QA LEADER dibagi jumlah third_party di tanggal tsb.
                |--------------------------------------------------------------------------
                */
                $qaRaw = DB::table('employee_qc_assignments')
                    ->where('npk', $employee->NPK)
                    ->where('period_id', $period->id)
                    ->where('role', $role)
                    ->whereNull('line_number') // QA / QA leader: hanya assignment tanpa line_number
                    ->whereBetween('start_date', [
                        $period->start_date,
                        $period->end_date
                    ])
                    ->select('start_date as date', 'buyer', 'third_party')
                    ->orderBy('start_date')
                    ->get()
                    ->map(function ($row) {
                        $thirdParty = trim((string) ($row->third_party ?? ''));
                        $row->date        = substr((string) $row->date, 0, 10);
                        $row->third_party = $thirdParty !== '' ? $thirdParty : null;
                        $row->buyer       = trim((string) ($row->buyer ?? ''));

                        return $row;
                    })
                    ->filter(fn($row) => $row->buyer !== '')
                    ->values();

                // third_party yang dipegang NPK ini per buyer.
                $qaThirdPartyByBuyer = $qaRaw
                    ->filter(fn($row) => $row->third_party !== null)
                    ->groupBy(fn($row) => strtoupper($row->buyer))
                    ->map(fn($rows) => $rows->pluck('third_party')
                        ->unique(fn($tp) => strtoupper($tp))
                        ->values());

                // Assignment kosong ikut third_party NPK (jika ada), lalu DISTINCT per tanggal + buyer + third_party.
                $qaAssignments = $qaRaw
                    ->flatMap(function ($row) use ($qaThirdPartyByBuyer) {
                        if ($row->third_party !== null) {
                            return [$row];
                        }

                        $tps = $qaThirdPartyByBuyer->get(strtoupper($row->buyer));

                        if ($tps && $tps->isNotEmpty()) {
                            return $tps->map(fn($tp) => (object) [
                                'date'        => $row->date,
                                'buyer'       => $row->buyer,
                                'third_party' => $tp,
                            ])->all();
                        }

                        return [$row];
                    })
                    ->unique(fn($row) => $row->date . '|' . strtoupper($row->buyer) . '|' . strtoupper((string) $row->third_party))
                    ->values();

                if ($qaAssignments->isEmpty()) {
                    return $amount;
                }

                // Ambil semua qc_efficiencies yang dibutuhkan sekali query, group per tanggal + buyer.
                $qaEffByKey = DB::table('qc_efficiencies')
                    ->where('period_id', $period->id)
                    ->where('dept', 'qa')
                    ->whereIn('date', $qaAssignments->pluck('date')->unique()->all())
                    ->whereIn('buyer', $qaAssignments->pluck('buyer')->unique()->all())
                    ->get()
                    ->groupBy(fn($row) => substr((string) $row->date, 0, 10) . '|' . strtoupper(trim((string) $row->buyer)));

                // Buyer yang punya baris third party di periode ini (mis. MUJI: PQC + TENTAC).
                $qaBuyerHasThirdParty = DB::table('qc_efficiencies')
                    ->where('period_id', $period->id)
                    ->where('dept', 'qa')
                    ->whereIn('buyer', $qaAssignments->pluck('buyer')->unique()->all())
                    ->whereNotNull('third_party')
                    ->where('third_party', '!=', '')
                    ->pluck('buyer')
                    ->mapWithKeys(fn($buyer) => [strtoupper(trim((string) $buyer)) => true]);

                $qaByDate = collect([]);

                foreach ($qaAssignments as $qaAssignment) {

                    $buyerHasThirdParty = $qaBuyerHasThirdParty->has(strtoupper($qaAssignment->buyer));

                    $effRows = $qaEffByKey->get(
                        $qaAssignment->date . '|' . strtoupper($qaAssignment->buyer),
                        collect()
                    );

                    $linesOfDay = $effRows
                        ->filter(function ($row) use ($qaAssignment, $buyerHasThirdParty) {
                            // third_party terisi => baris buyer + third_party tsb
                            if ($qaAssignment->third_party !== null) {
                                return strcasecmp(trim((string) ($row->third_party ?? '')), $qaAssignment->third_party) === 0;
                            }

                            // third_party kosong, buyer punya third party (MUJI) => hanya line_number NULL
                            if ($buyerHasThirdParty) {
                                return $row->line_number === null || $row->line_number === '';
                            }

                            // third_party kosong, buyer tanpa third party (GAP, SUKO) => semua baris buyer
                            return true;
                        })
                        // DISTINCT per tanggal (+ third_party): 1 defect rate = 1 baris.
                        ->unique(fn($row) => strtoupper(trim((string) ($row->third_party ?? ''))) . '|' . (float) $row->efficiency)
                        ->values();

                    if ($linesOfDay->isEmpty()) {
                        continue;
                    }

                    $qaByDate->push((object) [
                        'date'        => $qaAssignment->date,
                        'buyer'       => $qaAssignment->buyer,
                        'third_party' => $qaAssignment->third_party,
                        'lines'       => $linesOfDay,
                    ]);
                }

                if ($qaByDate->isEmpty()) {
                    return $amount;
                }

                // Jumlah third_party per tanggal (pembagi QA LEADER).
                $qaThirdPartyCountByDate = [];

                foreach ($qaByDate as $row) {
                    if ($row->third_party !== null) {
                        $qaThirdPartyCountByDate[(string) $row->date][strtoupper($row->third_party)] = true;
                    }
                }

                // Proses PER TANGGAL (1 tanggal bisa >1 entry: beda third_party).
                foreach ($qaByDate->groupBy(fn($entry) => (string) $entry->date) as $entries) {

                    $date = $entries->first()->date;

                    if ($tkkDate && $date >= $tkkDate) {
                        continue;
                    }

                    if (!$isValidOvertime($date)) {
                        continue;
                    }

                    $dayAmount = 0;

                    foreach ($entries as $entry) {

                        $totalLineInsentif = 0;

                        foreach ($entry->lines as $line) {
                            // Cutoff di luar tier tertinggi sudah ditangani getInsentifByDefectRate().
                            $totalLineInsentif += $this->getInsentifByDefectRate($line->efficiency, $formula);
                        }

                        $dayAmount += $this->calculateQaInsentif(
                            $role,
                            $totalLineInsentif,
                            $this->qcViolationPercentageByBuyer($period, $entry->buyer)
                        );
                    }

                    // QA LEADER: dibagi jumlah third_party di tanggal tsb (tanpa third_party => tidak dibagi).
                    if ($role === 'qa_leader') {
                        $jumlahThirdParty = max(count($qaThirdPartyCountByDate[(string) $date] ?? []), 1);
                        $dayAmount = $dayAmount / $jumlahThirdParty;
                    }

                    $amount += $dayAmount;
                }
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
                            ->on('le.date', '=', 'ela.start_date')
                            ->where('le.dept', '=', 'qc');
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

                // Pembagi = jumlah line yang benar-benar ada di section (tabel DEPT 'LINE n'),
                // bukan selisih range, mis. section 1-12 tanpa LINE 1 => 11 (acuan: FTY 2).
                $jumlahLine = $this->qcSectionLineCount($lineStart, $lineEnd);

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
                        ->where('dept', 'qc')
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

                    // CHIEF / SPV: (total insentif semua line di section / jumlah line) x faktor role
                    // (chief 7/10, spv 5/10), lalu dipotong qc_violation. Acuan: Summary Incentive FTY 1 & 2.
                    $amount += $this->calculateChiefSpvQcInsentif(
                        $role,
                        $totalLineInsentif,
                        $jumlahLine,
                        $this->qcViolationPercentage($period)
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

    /*
    |--------------------------------------------------------------------------
    | QC VIOLATION (qc_violations) PER PERIODE
    |--------------------------------------------------------------------------
    | Dikirim ke formula role QC sebagai variable `qc_violation`.
    | Di-cache per request supaya tidak query ulang untuk tiap karyawan/hari.
    */
    private array $qcViolationCache = [];

    private function qcViolationPercentage($period): float
    {
        $periodId = is_object($period) ? $period->id : $period;

        return $this->qcViolationCache[$periodId] ??= min(100, max(0, (float) DB::table('qc_violations')
            ->where('period_id', $periodId)
            ->sum('percentage')));
    }

    /*
    |--------------------------------------------------------------------------
    | QC VIOLATION PER BUYER (qc_violations)
    |--------------------------------------------------------------------------
    | Jika tabel qc_violations punya kolom `buyer`, baris dengan buyer kosong
    | berlaku untuk semua buyer dan baris ber-buyer hanya untuk buyer tsb.
    | Tanpa kolom `buyer` => total percentage periode (perilaku lama).
    */
    private array $qcViolationBuyerCache = [];

    private function qcViolationPercentageByBuyer($period, $buyer = null): float
    {
        $periodId = is_object($period) ? $period->id : $period;
        $buyerKey = strtoupper(trim((string) $buyer));
        $cacheKey = $periodId . '|' . $buyerKey;

        if (isset($this->qcViolationBuyerCache[$cacheKey])) {
            return $this->qcViolationBuyerCache[$cacheKey];
        }

        $query = DB::table('qc_violations')->where('period_id', $periodId);

        if (Schema::hasColumn('qc_violations', 'buyer')) {
            $query->where(function ($q) use ($buyerKey) {
                $q->whereNull('buyer')
                    ->orWhere('buyer', '')
                    ->orWhereRaw('UPPER(buyer) = ?', [$buyerKey]);
            });
        }

        return $this->qcViolationBuyerCache[$cacheKey] = min(100, max(0, (float) $query->sum('percentage')));
    }

    /*
    |--------------------------------------------------------------------------
    | INSENTIF QA / QA LEADER (formula insentif_role_formulas dept 'qc')
    |--------------------------------------------------------------------------
    | $avgInsentif (nama lama) = TOTAL insentif baris efficiency yang di-distinct
    | pada tanggal tsb (totalLineInsentif di formula).
    | qa        : (total * 5 / 10) * ((100 - qc_violation) / 100)
    | qa_leader : (total * 7 / 10) * ((100 - qc_violation) / 100)
    */
    private function calculateQaInsentif($role, $avgInsentif, $qcViolation = 0)
    {
        $qcViolation = (float) ($qcViolation ?? 0);

        // $fallback = fn() => $avgInsentif
        //     * ($role === 'qa_leader' ? 0.7 : 0.5)
        //     * ((100 - $qcViolation) / 100);

        $formula = Cache::remember(
            "insentif_formula_qc_{$role}",
            300,
            function () use ($role) {
                return InsentifRoleFormula::where('role', $role)
                    ->where('dept', 'qc')
                    ->value('formula');
            }
        );

        if (!$formula) {
            return $fallback();
        }

        $variables = [
            'totalLineInsentif'    => $avgInsentif,
            'jumlahLine'           => 1,
            'violationsCount'      => 0,
            'violation_percentage' => 0,
            'qc_violation'         => $qcViolation,
            'insentif'             => $avgInsentif,
        ];

        $formula = strtr($formula, array_map(fn($v) => (string) $v, $variables));

        try {
            if (!preg_match('/^[0-9\.\+\-\*\/\(\) ]+$/', $formula)) {
                throw new \Exception('Invalid formula');
            }

            return eval("return {$formula};");
        } catch (\Throwable $e) {
            return $fallback();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | JUMLAH LINE SECTION (pembagi CHIEF / SPV QC)
    |--------------------------------------------------------------------------
    | Hitung line yang ada di tabel DEPT ('LINE n') dalam range section.
    | Fallback ke selisih range jika DEPT tidak punya line di range tsb.
    */
    private array $qcSectionLineCountCache = [];

    private function qcSectionLineCount($lineStart, $lineEnd): int
    {
        $lineStart = (int) $lineStart;
        $lineEnd   = (int) $lineEnd;
        $cacheKey  = $lineStart . '-' . $lineEnd;

        if (isset($this->qcSectionLineCountCache[$cacheKey])) {
            return $this->qcSectionLineCountCache[$cacheKey];
        }

        $count = (int) DB::table('DEPT')
            ->where('DEPARTEMENT', 'like', 'LINE %')
            ->whereRaw("TRY_CAST(REPLACE(DEPARTEMENT, 'LINE ', '') AS INT) BETWEEN ? AND ?", [$lineStart, $lineEnd])
            ->distinct()
            ->count('DEPARTEMENT');

        if ($count <= 0) {
            $count = max($lineEnd - $lineStart + 1, 1);
        }

        return $this->qcSectionLineCountCache[$cacheKey] = $count;
    }

    /*
    |--------------------------------------------------------------------------
    | INSENTIF CHIEF / SPV QC (formula insentif_role_formulas dept 'qc')
    |--------------------------------------------------------------------------
    | chief : ((total / jumlahLine) * 7 / 10) * ((100 - qc_violation) / 100)
    | spv   : ((total / jumlahLine) * 5 / 10) * ((100 - qc_violation) / 100)
    | total = jumlah insentif semua line section pada tanggal tsb.
    */
    private function calculateChiefSpvQcInsentif($role, $totalLineInsentif, $jumlahLine, $qcViolation = 0)
    {
        $jumlahLine  = max((int) $jumlahLine, 1);
        $qcViolation = (float) ($qcViolation ?? 0);

        $fallback = fn() => ($totalLineInsentif / $jumlahLine)
            * ($role === 'chief' ? 0.7 : 0.5)
            * ((100 - $qcViolation) / 100);

        $formula = Cache::remember(
            "insentif_formula_qc_{$role}",
            300,
            function () use ($role) {
                return InsentifRoleFormula::where('role', $role)
                    ->where('dept', 'qc')
                    ->value('formula');
            }
        );

        if (!$formula) {
            return $fallback();
        }

        $formula = strtr($formula, array_map(fn($v) => (string) $v, [
            'totalLineInsentif' => $totalLineInsentif,
            'jumlahLine'        => $jumlahLine,
            'qc_violation'      => $qcViolation,
            'insentif'          => $totalLineInsentif,
        ]));

        try {
            if (!preg_match('/^[0-9\.\+\-\*\/\(\) ]+$/', $formula)) {
                throw new \Exception('Invalid formula');
            }

            return eval("return {$formula};");
        } catch (\Throwable $e) {
            return $fallback();
        }
    }

    private function calculateRoleQcInsentif(
        $role,
        $dept,
        $totalLineInsentif,
        $jumlahLine,
        $violationsCount,
        $employeeViolations,
        $qcViolation = 0,
    ) {

        $jumlahLine = max($jumlahLine, 1);

        // QC: nominal dasar langsung dipotong qc_violations (persen), rumus role
        // di insentif_role_formulas (dept 'qc') tidak dievaluasi.
        if ($dept === 'qc') {
            return $totalLineInsentif * ((100 - ($qcViolation ?? 0)) / 100);
        }
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
            'violation_percentage' => $employeeViolations ?? 0,
            // Variable khusus formula role QC (dept 'qc'):
            // - qc_violation : persentase potongan dari tabel qc_violations
            // - insentif     : alias totalLineInsentif (dipakai role operator QC)
            'qc_violation'      => $qcViolation ?? 0,
            'insentif'          => $totalLineInsentif,
        ];

        // strtr() mengganti dari key terpanjang dan tidak me-scan ulang hasil
        // penggantian, jadi 'totalLineInsentif' tidak bentrok dengan 'insentif'.
        $formula = strtr($formula, array_map(fn($v) => (string) $v, $variables));

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
