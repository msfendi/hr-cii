<?php

namespace App\Http\Controllers;

use App\Services\AuditRecapService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditRecapController extends Controller
{
    /**
     * Tampilkan halaman rekap audit.
     * - Dropdown "Generate" hanya berisi payroll_period yang is_closed = 0.
     * - Dropdown "Lihat data" berisi SEMUA payroll_period (termasuk yang
     *   sudah closed), supaya data historis tetap bisa dilihat.
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

        return view('audit_recap.index', compact('openPeriods', 'allPeriods', 'periodId', 'selectedPeriod'));
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
}