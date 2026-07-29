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
        $filters = $request->only(['uraian', 'brand', 'style', 'ocf']);
        $service = MonitoringDashboardService::make($filters);

        // Keempat dropdown (Brand/Style/Uraian/OCF) di-cascade BOLAK-BALIK
        // dari filter yang aktif saat halaman dibuka (lihat
        // MonitoringDashboardService::cascadedFilterOptions()) -- kalau
        // request index() ini datang dengan query filter (mis. dari link
        // share/bookmark), dropdown lain langsung ikut menyaring sejak awal,
        // bukan cuma nanti setelah user ganti pilihan. Sama seperti
        // MonitoringRekonsiliasiController::index().
        $filterOptions = $service->cascadedFilterOptions();

        return view('monitoring.dashboard', [
            'filters'           => $filters,
            'brandOptions'      => $filterOptions['brand'],
            'styleOptions'      => $filterOptions['style'],
            'uraianOptions'     => $filterOptions['uraian'],
            'ocfOptions'        => $filterOptions['ocf'],
            // Kombinasi uraian/brand/style dari mon_orders TANPA di-scope
            // apapun -- dipertahankan kalau frontend masih butuh daftar
            // lengkap awal (mis. autocomplete/search di client).
            'orderCombos'       => $service->orderCombos(),
            'orderComboOptions' => $service->orderComboOptions(),
        ]);
    }

    /**
     * Endpoint AJAX untuk filter dashboard tanpa reload penuh.
     */
    public function data(Request $request)
    {
        $filters = $request->only(['uraian', 'brand', 'style', 'ocf']);
        $service = MonitoringDashboardService::make($filters);

        // Keempat dropdown (Brand/Style/Uraian/OCF) di-cascade BOLAK-BALIK
        // dari filter yang sedang aktif (lihat
        // MonitoringDashboardService::cascadedFilterOptions()) -- dihitung
        // SELALU di sini supaya keempat dropdown tetap saling menyaring
        // setiap kali user mengganti salah satunya (pilih OCF -> Uraian/
        // Style/Brand ikut menyaring; pilih Style -> Brand/Uraian/OCF ikut
        // menyaring; dst). Sama seperti MonitoringRekonsiliasiController::data().
        $filterOptions = $service->cascadedFilterOptions();

        // Kalau belum ada filter SAMA SEKALI (uraian, brand, style, maupun
        // ocf), JANGAN jalankan query berat (full-scan tanpa scope bisa
        // menarik seluruh tabel sekaligus untuk banyak widget). Cukup
        // balikan payload kosong (dropdown tetap ikut dikirim supaya
        // frontend bisa langsung reset/cascade ulang -- lihat tombol Reset
        // Filter di dashboard.blade.php). Sama seperti
        // MonitoringRekonsiliasiController::data()/emptyPayload().
        if (empty($filters['uraian']) && empty($filters['brand']) && empty($filters['style']) && empty($filters['ocf'])) {
            return response()->json($this->emptyPayload($filterOptions));
        }

        return response()->json([
            'summary'        => $service->summary(),
            'orderPivot'     => $service->orderPivot(),
            'materialPivot'  => $service->materialPurchasePivot(),
            'workOrderPivot' => $service->workOrderPivot(),
            'filterOptions'  => $filterOptions,
            'brandOptions'   => $filterOptions['brand'],
            'styleOptions'   => $filterOptions['style'],
            'uraianOptions'  => $filterOptions['uraian'],
            // `ocfOptions` dipertahankan sebagai key terpisah (selain di
            // dalam `filterOptions`) supaya kompatibel dengan frontend lama
            // yang sudah membaca key ini.
            'ocfOptions'     => $filterOptions['ocf'],
        ]);
    }

    /**
     * Payload kosong untuk endpoint data() saat belum ada filter aktif sama
     * sekali -- dropdown Brand/Style/Uraian/OCF tetap disertakan supaya
     * frontend bisa reset/cascade ulang keempatnya. Meniru
     * MonitoringRekonsiliasiController::emptyPayload().
     */
    private function emptyPayload(array $filterOptions = []): array
    {
        $filterOptions += ['brand' => [], 'style' => [], 'uraian' => [], 'ocf' => []];

        return [
            'summary'        => ['total_qty_order' => 0, 'total_style' => 0, 'total_item_belum_order' => 0],
            'orderPivot'     => [],
            'materialPivot'  => [],
            'workOrderPivot' => [],
            'filterOptions'  => $filterOptions,
            'brandOptions'   => $filterOptions['brand'],
            'styleOptions'   => $filterOptions['style'],
            'uraianOptions'  => $filterOptions['uraian'],
            'ocfOptions'     => $filterOptions['ocf'],
        ];
    }

    /**
     * Data kalender (jumlah order per tanggal) berdasarkan mon_orders.production_delivery,
     * untuk satu bulan tertentu. Query: ?year=2026&month=7
     */
    public function calendar(Request $request)
    {
        $filters = $request->only(['uraian', 'brand', 'style', 'ocf']);
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
        $filters = $request->only(['uraian', 'brand', 'style', 'ocf']);
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
