<?php

namespace App\Http\Controllers;

use App\Services\AuditRecapService;
use App\Exports\AuditExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class AuditRecapController extends Controller
{
    /**
     * Tampilkan halaman rekap audit.
     * - Dropdown "Generate" hanya berisi payroll_period yang is_closed = 0.
     * - Dropdown "Lihat data" berisi SEMUA payroll_period (termasuk yang
     *   sudah closed), supaya data historis tetap bisa dilihat.
     * - Kalau request AJAX (dipanggil oleh DataTables server-side), return
     *   JSON lewat yajra/laravel-datatables, bukan render view.
     */
    public function index(Request $request)
    {
        $openPeriods = DB::table('payroll_periods')
            ->where('is_closed', 0)
            ->orderByDesc('start_date')
            ->get();

        $allPeriods = DB::table('payroll_periods')
            ->orderByDesc('start_date')
            ->get();

        $periodId = (int) $request->get('period_id', optional($allPeriods->first())->id);
        $selectedPeriod = $allPeriods->firstWhere('id', $periodId);

        if ($request->ajax()) {
            if ($selectedPeriod) {
                $query = DB::table('AUDIT')
                    ->whereBetween('TANGGAL', [
                        Carbon::parse($selectedPeriod->start_date)->format('Y-m-d'),
                        Carbon::parse($selectedPeriod->end_date)->format('Y-m-d'),
                    ]);

                return datatables()->of($query)->make(true);
            }
            return datatables()->of([])->make(true);
        }

        $employeeGroupChutex = DB::connection('cii')->table('AUDIT')
            ->select('SUBDIVISI', DB::raw('MIN(KODE_BAGIAN) AS KODE_BAGIAN'))
            ->groupBy('SUBDIVISI')
            ->orderBy('SUBDIVISI', 'ASC')
            ->limit(10)
            ->get();

        return view('audit_recap.index', compact('openPeriods', 'allPeriods', 'periodId', 'selectedPeriod', 'employeeGroupChutex'));
    }

    /**
     * Proses generate rekap audit untuk 1 payroll_period (dipicu tombol Generate).
     * Overwrite total: row NPK+TANGGAL yang sudah ada akan ditimpa ulang
     * (termasuk jitter random-nya).
     */
    public function generate(Request $request, AuditRecapService $service)
    {
        $validated = $request->validate([
            'period_id' => 'required|integer|exists:payroll_periods,id',
        ]);

        try {
            $result = $service->generate((int) $validated['period_id']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['period_id' => $e->getMessage()]);
        }

        return redirect()
            ->route('audit-recap.index', ['period_id' => $validated['period_id']])
            ->with('success', "Rekap audit berhasil digenerate: {$result['total_rows']} baris untuk periode \"{$result['period_name']}\".");
    }

    /**
     * Endpoint bantu (dipanggil via AJAX dari front-end): terima daftar tanggal
     * libur (format "DD/MM/YYYY", dipisah koma) lewat parameter holiday_date,
     * lalu kembalikan array angka tanggal (day-of-month) saja -- dipakai untuk
     * menandai kolom hari libur di report grid (template.report-final).
     */
    public function export(Request $request)
    {
        $dates = explode(',', $request->holiday_date);
        $days = array_map(function ($dateStr) {
            $parts = explode('/', trim($dateStr));
            return isset($parts[1]) ? $parts[1] : '';
        }, $dates);

        return response()->json(['success' => true, 'days' => $days]);
    }

    /**
     * Tampilkan laporan kehadiran (grid per-hari) untuk 1 DEPT_GROUP dalam
     * rentang tanggal tertentu. View: template.report-final.
     */
    public function report(Request $request)
    {
        $deptGroup = $request->dept_group;

        $employeeGroupChutex = DB::connection('cii')->table('AUDIT')
            ->select('NPK', 'SUBDIVISI')
            ->distinct('NPK', 'SUBDIVISI')
            ->where('DEPT_GROUP', $deptGroup);
        $employeeGroup = $employeeGroupChutex->orderBy('SUBDIVISI', 'ASC')->orderBy('NPK', 'ASC')->get();

        $employeesChutex = DB::connection('cii')->table('AUDIT')
            ->select(
                'AUDIT.NPK',
                'AUDIT.NAMA_KARYAWAN',
                'AUDIT.SUBDIVISI',
                'AUDIT.TANGGAL',
                'AUDIT.JAM_PAGI',
                'AUDIT.JAM_SIANG',
                'AUDIT.JAM_MALAM',
                DB::raw("CASE WHEN overtimes.JUMLAH_JAM_LEMBUR IS NOT NULL AND ISNUMERIC(overtimes.JUMLAH_JAM_LEMBUR) = 0 THEN overtimes.JUMLAH_JAM_LEMBUR ELSE AUDIT.STATUS END AS KETERANGAN"),
                'PKWT.TMK',
                'PKWT.TKK'
            )
            ->leftJoin('PKWT', 'PKWT.NPK', '=', 'AUDIT.NPK')
            ->leftJoin('overtimes', function ($join) {
                $join->on('overtimes.NPK', '=', 'AUDIT.NPK')
                    ->on('overtimes.OVERTIME_DATE', '=', 'AUDIT.TANGGAL');
            })
            ->where('AUDIT.DEPT_GROUP', $deptGroup)
            ->whereBetween('AUDIT.TANGGAL', [$request->fromdate, $request->todate]);
        $employees = $employeesChutex->orderBy('AUDIT.SUBDIVISI', 'ASC')->orderBy('AUDIT.NPK', 'ASC')->orderBy('AUDIT.TANGGAL', 'ASC')->get();

        $days = $request->days;
        return view('template.report-final', compact('employees', 'employeeGroup', 'days'));
    }

    /**
     * Download rekap sebagai Excel (.xlsx) lewat class App\Exports\AuditExport
     * (sudah ada di project, tidak di-generate ulang di sini).
     */
    public function export_view(Request $request)
    {
        return Excel::download(
            new AuditExport(
                $request->fromdate,
                $request->todate,
                $request->dept_group,
                $request->days
            ),
            'cii.xlsx'
        );
    }

    /**
     * Download laporan kehadiran sebagai PDF (landscape A4), pakai view yang
     * sama dengan report() yaitu template.report-final.
     */
    public function export_pdf(Request $request)
    {
        $deptGroup = $request->dept_group;

        $employeeGroup = DB::connection('cii')->table('AUDIT')
            ->select('NPK', 'SUBDIVISI')
            ->distinct('NPK', 'SUBDIVISI')
            ->where('DEPT_GROUP', $deptGroup)
            ->orderBy('SUBDIVISI', 'ASC')
            ->orderBy('NPK', 'ASC')
            ->get();

        $employees = DB::connection('cii')->table('AUDIT')
            ->select(
                'AUDIT.NPK',
                'AUDIT.NAMA_KARYAWAN',
                'AUDIT.SUBDIVISI',
                'AUDIT.TANGGAL',
                'AUDIT.JAM_PAGI',
                'AUDIT.JAM_SIANG',
                'AUDIT.JAM_MALAM',
                DB::raw("CASE WHEN overtimes.JUMLAH_JAM_LEMBUR IS NOT NULL AND ISNUMERIC(overtimes.JUMLAH_JAM_LEMBUR) = 0 THEN overtimes.JUMLAH_JAM_LEMBUR ELSE AUDIT.STATUS END AS KETERANGAN"),
                'PKWT.TMK',
                'PKWT.TKK'
            )
            ->leftJoin('PKWT', 'PKWT.NPK', '=', 'AUDIT.NPK')
            ->leftJoin('overtimes', function ($join) {
                $join->on('overtimes.NPK', '=', 'AUDIT.NPK')
                    ->on('overtimes.OVERTIME_DATE', '=', 'AUDIT.TANGGAL');
            })
            ->where('AUDIT.DEPT_GROUP', $deptGroup)
            ->whereBetween('AUDIT.TANGGAL', [$request->fromdate, $request->todate])
            ->orderBy('AUDIT.SUBDIVISI', 'ASC')
            ->orderBy('AUDIT.NPK', 'ASC')
            ->orderBy('AUDIT.TANGGAL', 'ASC')
            ->limit(1000)
            ->get();

        $days = $request->days;

        $pdf = Pdf::loadView('template.report-final', compact('employees', 'employeeGroup', 'days'))
            ->setPaper('a4', 'landscape');

        $filename = 'laporan-kehadiran-' . $deptGroup . '-' . $request->fromdate . '-' . $request->todate . '.pdf';
        return $pdf->download($filename);
    }
}