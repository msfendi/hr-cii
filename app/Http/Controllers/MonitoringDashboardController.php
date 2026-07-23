<?php

namespace App\Http\Controllers;

use App\Services\MonitoringDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class MonitoringDashboardController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['uraian', 'brand', 'style']);
        $service = MonitoringDashboardService::make($filters);

        return view('monitoring.dashboard', [
            'filters'        => $filters,
            'filterOptions'  => $service->filterOptions(),
            'orderCombos'    => $service->orderCombos(), // ringan untuk cascading
        ]);
    }

    /**
     * Endpoint AJAX untuk filter dashboard tanpa reload penuh.
     */
    public function data(Request $request)
    {
        $filters = $request->only(['uraian', 'brand', 'style']);
        $service = MonitoringDashboardService::make($filters);

        return response()->json([
            'summary'        => $service->summary(),
            'orderPivot'     => $service->orderPivot(),
            'materialPivot'  => $service->materialPurchasePivot(),
            'workOrderPivot' => $service->workOrderPivot(),
        ]);
    }

    /**
     * Data kalender (jumlah order per tanggal) berdasarkan mon_orders.production_delivery,
     * untuk satu bulan tertentu. Query: ?year=2026&month=7
     */
    public function calendar(Request $request)
    {
        $filters = $request->only(['uraian', 'brand', 'style']);
        $year  = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $service = MonitoringDashboardService::make($filters);

        return response()->json([
            'year'  => $year,
            'month' => $month,
            'days'  => $service->productionDeliveryCalendar($year, $month),
        ]);
    }

    /**
     * Detail order untuk satu tanggal production_delivery (dipanggil saat tanggal
     * di kalender diklik). Query: ?date=2026-07-15
     */
    public function calendarDetail(Request $request)
    {
        $filters = $request->only(['uraian', 'brand', 'style']);
        $date = $request->input('date');

        if (!$date) {
            return response()->json(['message' => 'Parameter date wajib diisi.'], 422);
        }

        $service = MonitoringDashboardService::make($filters);

        return response()->json([
            'date' => $date,
            'rows' => $service->productionDeliveryDetail($date),
        ]);
    }

    /**
     * Tombol "Sync BOM" di dashboard -> php artisan monitoring:sync-bom --year=xxxx
     */
    public function syncBom(Request $request)
    {
        return $this->runSyncCommand('monitoring:sync-bom', $request);
    }

    /**
     * Tombol "Sync PO" di dashboard -> php artisan monitoring:sync-po --year=xxxx
     */
    public function syncPo(Request $request)
    {
        return $this->runSyncCommand('monitoring:sync-po', $request);
    }

    /**
     * Jalankan artisan command sync (BOM/PO) untuk tahun tertentu dan kembalikan
     * output-nya sebagai JSON, supaya bisa ditampilkan di SweetAlert frontend.
     * Default tahun = tahun berjalan, tapi bisa dioverride lewat query/body `year`.
     */
    private function runSyncCommand(string $command, Request $request)
    {
        $year = (int) $request->input('year', now()->year);

        // Sync dari SQL Server (smartit) bisa makan waktu -- longgarkan batas eksekusi PHP
        // supaya request tidak keburu timeout sebelum artisan command selesai.
        set_time_limit(0);

        try {
            // $exitCode = Artisan::call($command, ['--year' => $year]);
            $exitCode = Artisan::call($command);
            $output = trim(Artisan::output());

            return response()->json([
                'success'   => $exitCode === 0,
                // 'command'   => "{$command} --year={$year}",
                'command'   => "{$command}",
                'exit_code' => $exitCode,
                'output'    => $output,
            ], $exitCode === 0 ? 200 : 500);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                // 'command' => "{$command} --year={$year}",
                'command' => "{$command}",
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
