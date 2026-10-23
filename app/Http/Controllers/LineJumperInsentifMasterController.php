<?php

namespace App\Http\Controllers;

use App\Events\NotificationEvent;
use App\Exports\LineJumperInsentifTemplateExport;
use App\Imports\LineJumperInsentifImport;
use App\Models\InsentifApproval;
use App\Models\InsentifRoleFormula;
use App\Models\LineJumperOperation;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use RealRashid\SweetAlert\Facades\Alert;

class LineJumperInsentifMasterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | KONSTANTA
    |--------------------------------------------------------------------------
    | - payroll_components.code                 : line_jumper_insentif
    |   formula (JSON) = {"3":450000,"4":600000,"5":750000}
    |   key = jumlah operasi (minimal), value = insentif
    | - insentif_role_formulas (role, dept)     : jumper, line_jumper
    |   formula = insentif * ((100-qc_violation)/100)
    */
    private const COMPONENT_CODE = 'line_jumper_insentif';
    private const ROLE           = 'jumper';
    private const DEPT           = 'line_jumper';

    /*
    |--------------------------------------------------------------------------
    | BIODATA (AKTIF + KELUAR)
    |--------------------------------------------------------------------------
    */
    private function biodataUnion()
    {
        return DB::connection('cii')
            ->table('BIODATA')
            ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', 'IS_STAFF', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'))
            ->unionAll(
                DB::connection('cii')
                    ->table('BIODATA_KELUAR')
                    ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', 'IS_STAFF', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'))
            );
    }

    /*
    |--------------------------------------------------------------------------
    | MUTASI BERIKUTNYA (setelah akhir periode)
    |--------------------------------------------------------------------------
    */
    private function nextMutation($periodEnd)
    {
        return DB::table('employee_mutations as em1')
            ->select('em1.npk', 'em1.from_dept', 'em1.to_dept', 'em1.date')
            ->where('em1.date', '>', $periodEnd)
            ->whereRaw('em1.id = (
                SELECT MIN(em2.id)
                FROM employee_mutations em2
                WHERE em2.npk = em1.npk
                AND em2.date > ?
            )', [$periodEnd]);
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $data = DB::table('line_jumper_operations as l')
            ->join('payroll_periods as pp', 'l.period_id', '=', 'pp.id')
            ->leftJoinSub($this->biodataUnion(), 'bio', function ($join) {
                $join->on('l.npk', '=', 'bio.NPK');
            })
            ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'bio.ID_DEPT')
            ->select(
                'l.id',
                'l.npk',
                'bio.NAMA_KARYAWAN as name',
                'd.DEPARTEMENT as dept',
                'pp.name as period',
                'l.operation',
                'l.machine'
            )
            ->where('pp.is_closed', 0)
            ->orderBy('l.npk')
            ->orderBy('l.operation')
            ->get();

        $periods = PayrollPeriod::select('id', 'name')
            ->where('is_closed', 0)
            ->orderBy('id', 'desc')
            ->get();

        return view('line_jumper_insentif_master.index', compact('data', 'periods'));
    }

    /*
    |--------------------------------------------------------------------------
    | DATA MASTER PER PERIODE (AJAX)
    |--------------------------------------------------------------------------
    */
    public function getData($period)
    {
        $periods   = PayrollPeriod::findOrFail($period);
        $periodEnd = $periods->end_date;

        $data = DB::table('line_jumper_operations as l')
            ->join('payroll_periods as pp', 'l.period_id', '=', 'pp.id')
            ->leftJoinSub($this->biodataUnion(), 'bio', function ($join) {
                $join->on('l.npk', '=', 'bio.NPK');
            })
            ->leftJoinSub($this->nextMutation($periodEnd), 'em', function ($join) {
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
                'l.id',
                'l.npk',
                'bio.NAMA_KARYAWAN as name',
                'd.DEPARTEMENT as dept',
                'pp.name as period',
                'l.operation',
                'l.machine'
            )
            ->where('l.period_id', $period)
            ->orderBy('l.npk')
            ->orderBy('l.operation')
            ->get();

        return response()->json($data);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
    public function destroy($id)
    {
        $user = Auth::user();
        $data = LineJumperOperation::findOrFail($id);
        $data->delete();

        event(new NotificationEvent(
            'Line Jumper Insentif!',
            'User : ' . $user->name . ' has deleted Line Jumper Insentif!',
            'danger'
        ));

        Alert::success('Deleted Successfully!', 'Line Jumper Insentif succesfully deleted!');

        return redirect()->route('line-jumper-insentif-master.index')
            ->with('success', 'Data berhasil dihapus');
    }

    /*
    |--------------------------------------------------------------------------
    | TEMPLATE
    |--------------------------------------------------------------------------
    */
    public function template()
    {
        return Excel::download(
            new LineJumperInsentifTemplateExport,
            'template_line_jumper_insentif_master.xlsx'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | IMPORT
    |--------------------------------------------------------------------------
    */
    public function import(Request $request)
    {
        $request->validate([
            'period_id'   => 'required|exists:payroll_periods,id',
            'is_insentif' => 'required|in:0,1',
            'file'        => 'required_if:is_insentif,1|mimes:xlsx,xls'
        ]);

        $user      = Auth::user();
        $period    = PayrollPeriod::findOrFail($request->period_id);
        $component = self::COMPONENT_CODE;
        $message   = 'Process berhasil dijalankan';

        /*
        =====================================
        JIKA INSENTIF -> IMPORT EXCEL
        =====================================
        */
        if ($request->is_insentif == 1) {

            $import = new LineJumperInsentifImport($request->period_id);

            Excel::import($import, $request->file('file'));

            $message = $import->imported . ' baris berhasil diimport'
                . ($import->skipped ? ', ' . $import->skipped . ' baris dilewati (npk/operation kosong)' : '');
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

            $waitingStatus = array_fill(0, count($approvalArray), 'waiting');

            $progress = [
                [
                    'npk'    => json_encode($approvalArray),
                    'status' => json_encode($waitingStatus),
                ]
            ];

            InsentifApproval::updateOrCreate(
                [
                    'period_id'         => $request->period_id,
                    'payroll_component' => $component
                ],
                [
                    'approval'    => $approval,
                    'progress'    => $progress,
                    'approved_at' => null,
                    'status'      => 'pending'
                ]
            );
        }

        event(new NotificationEvent(
            'Line Jumper Insentif!',
            'User : ' . $user->name . ' has imported Line Jumper Insentif ' . $period->name . '!',
            'success'
        ));

        return back()->with('success', $message);
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK / SIMULASI INSENTIF PER PERIODE (AJAX)
    |--------------------------------------------------------------------------
    */
    public function check($period_id)
    {
        $period      = PayrollPeriod::findOrFail($period_id);
        $periodStart = $period->start_date;
        $periodEnd   = $period->end_date;

        /*
        |--------------------------------------------------------------------------
        | FORMULA TIER (payroll_components)
        |--------------------------------------------------------------------------
        */
        $tiers = json_decode(
            PayrollComponent::where('code', self::COMPONENT_CODE)->value('formula'),
            true
        );

        if (empty($tiers) || !is_array($tiers)) {
            return response()->json([
                'message' => 'Formula "' . self::COMPONENT_CODE . '" belum ada / kosong di payroll_components.'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | FORMULA POTONGAN QC (insentif_role_formulas)
        |--------------------------------------------------------------------------
        */
        $roleFormula = InsentifRoleFormula::where('role', self::ROLE)
            ->where('dept', self::DEPT)
            ->value('formula');

        $qcViolation = $this->getQcViolation($period->id);

        /*
        |--------------------------------------------------------------------------
        | JUMLAH OPERASI PER NPK
        |--------------------------------------------------------------------------
        | operation_key unik per (period, npk) -> COUNT(*) = jumlah operasi
        */
        $assignmentCount = DB::table('line_jumper_operations')
            ->where('period_id', $period->id)
            ->select('npk', DB::raw('COUNT(*) as total_operation'))
            ->groupBy('npk');

        /*
        |--------------------------------------------------------------------------
        | EMPLOYEE SOURCE
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
            ->joinSub($assignmentCount, 'anpk', function ($join) {
                $join->on('p.NPK', '=', 'anpk.npk');
            })
            ->leftJoinSub($this->nextMutation($periodEnd), 'em', function ($join) {
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
            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereNull('p.TKK')
                    ->orWhereBetween('p.TKK', [$periodStart, $periodEnd]);
            })
            ->select(
                'p.NPK',
                'emp.NAMA_KARYAWAN',
                'anpk.total_operation',
                'p.TMK',
                'p.TKK as tkk',
                'emp.ID_DEPT',
                'd.DEPARTEMENT as DEPARTEMENT'
            );

        $employees = DB::connection('cii')
            ->query()
            ->fromSub($employeeBase, 'emp')
            ->distinct()
            ->get();

        /*
        |--------------------------------------------------------------------------
        | CALCULATION
        |--------------------------------------------------------------------------
        */
        $results = [];

        foreach ($employees as $employee) {

            $amount = $this->calculateJumper(
                (int) $employee->total_operation,
                $tiers,
                $roleFormula,
                $qcViolation
            );

            // dibulatkan supaya sama dengan job payroll
            $amount = round((float) $amount, 0);

            if ($amount <= 0) continue;

            $results[] = [
                'npk'             => $employee->NPK,
                'name'            => $employee->NAMA_KARYAWAN,
                'dept'            => $employee->DEPARTEMENT,
                'role'            => self::ROLE,
                'total_operation' => (int) $employee->total_operation,
                'jumper_insentif' => $amount,
                'tkk'             => $employee->tkk,
                'status'          => $employee->tkk ? 'Resign' : 'Active',
            ];
        }

        return response()->json([
            'qc_violation' => $qcViolation,
            'data'         => $results,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | QC VIOLATION (%) PER PERIODE
    |--------------------------------------------------------------------------
    | Sumber: tabel qc_violations (period_id, percentage, reason).
    | Kalau ada lebih dari 1 baris di periode yang sama, persentasenya
    | dijumlahkan (maks 100). Samakan dengan cara QcInsentifMasterController
    | mengambilnya kalau berbeda.
    */
    private function getQcViolation($periodId): float
    {
        $percentage = (float) DB::table('qc_violations')
            ->where('period_id', $periodId)
            ->sum('percentage');

        return max(0, min(100, $percentage));
    }

    /*
    |--------------------------------------------------------------------------
    | TIER BERDASARKAN JUMLAH OPERASI
    |--------------------------------------------------------------------------
    | {"3":450000,"4":600000,"5":750000}
    | 5 atau lebih -> 750.000, 4 -> 600.000, 3 -> 450.000, <3 -> 0
    */
    private function getInsentifByOperation(int $totalOperation, array $tiers)
    {
        krsort($tiers, SORT_NUMERIC);

        foreach ($tiers as $threshold => $value) {
            if ($totalOperation >= (int) $threshold) {
                return $value;
            }
        }

        return 0;
    }

    /*
    |--------------------------------------------------------------------------
    | HITUNG INSENTIF JUMPER
    |--------------------------------------------------------------------------
    */
    private function calculateJumper(int $totalOperation, array $tiers, ?string $roleFormula, float $qcViolation)
    {
        $insentif = (float) $this->getInsentifByOperation($totalOperation, $tiers);

        if ($insentif <= 0) {
            return 0;
        }

        // tidak ada formula di insentif_role_formulas -> insentif apa adanya
        if (!$roleFormula) {
            return $insentif;
        }

        $variables = [
            'qc_violation' => $qcViolation,
            'insentif'     => $insentif,
        ];

        $formula = $roleFormula;

        foreach ($variables as $key => $value) {
            $formula = str_replace($key, sprintf('%.4F', $value), $formula);
        }

        try {
            // hanya izinkan karakter matematika
            if (!preg_match('/^[0-9\.\+\-\*\/\(\) ]+$/', $formula)) {
                throw new \Exception('Invalid formula');
            }

            return eval("return {$formula};");
        } catch (\Throwable $e) {
            return $insentif;
        }
    }
}
