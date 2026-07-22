<?php

namespace App\Http\Controllers;

use App\Services\MonitoringRekonsiliasiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class MonitoringRekonsiliasiController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['uraian']);
        $service = MonitoringRekonsiliasiService::make($filters);

        return view('monitoring.rekonsiliasi', [
            'filters'       => $filters,
            'cpoOptions'    => $service->cpoOptions(),
            // Kombinasi Buyer (brand) / Style / CPO (uraian) dari mon_orders,
            // dipakai frontend untuk 3 select2 filter yang saling berkaitan.
            'filterOptions' => $service->orderFilterOptions(),
        ]);
    }

    /**
     * Endpoint AJAX tunggal yang mengisi SELURUH widget dashboard (KPI,
     * material achievement, production result, material purchase, work
     * order/BOM, top excess, detail) -- dipanggil ulang tiap kali CPO diganti.
     */
    public function data(Request $request)
    {
        $filters = $request->only(['uraian']);

        // Kalau CPO belum dipilih, JANGAN jalankan query berat (full-scan
        // tanpa scope ke satu uraian bisa menarik seluruh tabel sekaligus
        // untuk banyak widget). Cukup balikan payload kosong; dashboard
        // baru menarik data sesungguhnya setelah user memilih CPO.
        if (empty($filters['uraian'])) {
            return response()->json($this->emptyPayload());
        }

        $service = MonitoringRekonsiliasiService::make($filters);

        return response()->json([
            'header'               => $service->header(),
            'summary'              => $service->summary(),
            'shipmentDates'        => $service->shipmentDates(),
            'fabricQty'            => $service->fabricQty(),
            'fabricUsage'          => $service->fabricUsage(),
            'materialAchievement'  => $service->materialAchievement(),
            'productionPipeline'   => $service->productionPipeline(),
            'productionResultByMaterial' => $service->productionResultByMaterial(),
            'materialPurchase'     => $service->materialPurchasePivot(),
            'workOrder'            => $service->workOrderPivot(),
            'workOrderCount'       => $service->workOrderCount(),
            'topMaterialExcess'    => $service->topMaterialExcess(),
            'detail'               => $service->detail(),
            'shipmentByDate'       => $service->shipmentByDate(),
            'shipmentDetail'       => $service->shipmentDetail(),
            'pipelineLossSteps'    => $service->pipelineLossSteps(),
            'shipmentByCategory'   => $service->shipmentByCategory(),
        ]);
    }

    /**
     * Struktur payload kosong (bentuknya sama persis dengan payload asli)
     * supaya kode render di sisi frontend tidak perlu tahu bedanya --
     * cukup dipakai untuk mengosongkan seluruh widget saat belum ada CPO.
     */
    private function emptyPayload(): array
    {
        return [
            'empty'                => true,
            'header'               => ['cpo' => null, 'brand' => null, 'style' => null],
            'summary'              => [
                'contract_qty'    => 0,
                'shipment_qty'    => 0,
                'balance_qty'     => 0,
                'achievement_pct' => 0,
                'shortage_pct'    => 0,
            ],
            'shipmentDates'        => [],
            'fabricQty'            => ['need' => 0, 'order' => 0, 'received' => 0, 'out_wip' => 0],
            'fabricUsage'          => ['use_for_gmt' => 0, 'scrap_qty' => 0, 'usage_pct' => 0, 'scrap_pct' => 0, 'consumption' => 0],
            'materialAchievement'  => [],
            'productionPipeline'   => [
                'contract'    => 0,
                'departments' => [],
                'shipment'    => 0,
                'total_loss'  => 0,
                'loss_pct'    => 0,
            ],
            'productionResultByMaterial' => [],
            'materialPurchase'     => [],
            'workOrder'            => [],
            'workOrderCount'       => 0,
            'topMaterialExcess'    => [],
            'detail'               => [],
            'shipmentByDate'       => [],
            'shipmentDetail'       => [],
            'pipelineLossSteps'    => [],
            'shipmentByCategory'   => [],
        ];
    }

    /**
     * Tombol "Sync Rekonsiliasi" -> php artisan monitoring:sync-rekonsiliasi --year=xxxx
     */
    public function syncRekonsiliasi(Request $request)
    {
        return $this->runSyncCommand('monitoring:sync-rekonsiliasi', $request);
    }

    /**
     * Tombol "Sync Production Line" -> php artisan monitoring:sync-prod-line --year=xxxx
     */
    public function syncProdLine(Request $request)
    {
        return $this->runSyncCommand('monitoring:sync-prod-line', $request);
    }

    /**
     * Tombol "Sync Shipment" -> php artisan monitoring:sync-shipment --year=xxxx
     */
    public function syncShipment(Request $request)
    {
        return $this->runSyncCommand('monitoring:sync-shipment', $request);
    }

    /**
     * Tombol "Sync Work Order" -> php artisan monitoring:sync-work-order
     * (command ini TIDAK punya opsi --year, sumbernya cuma status='Unfinish'
     * di smartit, bukan filter tanggal -- makanya tidak lewat runSyncCommand()).
     */
    public function syncWorkOrder(Request $request)
    {
        set_time_limit(0);

        try {
            $exitCode = Artisan::call('monitoring:sync-work-order');
            $output = trim(Artisan::output());

            return response()->json([
                'success'   => $exitCode === 0,
                'command'   => 'monitoring:sync-work-order',
                'exit_code' => $exitCode,
                'output'    => $output,
            ], $exitCode === 0 ? 200 : 500);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'command' => 'monitoring:sync-work-order',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function runSyncCommand(string $command, Request $request)
    {
        $year = (int) $request->input('year', now()->year);

        // Sync dari SQL Server (smartit) bisa makan waktu -- longgarkan batas eksekusi PHP
        // supaya request tidak keburu timeout sebelum artisan command selesai.
        set_time_limit(0);

        try {
            $exitCode = Artisan::call($command, ['--year' => $year]);
            $output = trim(Artisan::output());

            return response()->json([
                'success'   => $exitCode === 0,
                'command'   => "{$command} --year={$year}",
                'exit_code' => $exitCode,
                'output'    => $output,
            ], $exitCode === 0 ? 200 : 500);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'command' => "{$command} --year={$year}",
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
