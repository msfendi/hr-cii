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
        // uraian = CPO spesifik; brand/style dipakai untuk search tanpa harus
        // memilih 1 CPO (lihat MonitoringRekonsiliasiService::filterUraianList()).
        // negara = filter tambahan berdasarkan negara supplier shipment (lihat
        // MonitoringRekonsiliasiService::cpoListForNegara()).
        $filters = $request->only(['uraian', 'brand', 'style', 'negara']);
        $service = MonitoringRekonsiliasiService::make($filters);

        return view('monitoring.rekonsiliasi', [
            'filters'       => $filters,
            'cpoOptions'    => $service->cpoOptions(),
            // Kombinasi Buyer (brand) / Style / CPO (uraian) dari mon_orders,
            // dipakai frontend untuk 3 select2 filter yang saling berkaitan.
            'filterOptions' => $service->orderFilterOptions(),
            // Dropdown filter Negara, diambil dari mon_ms_suppliers + mon_ms_negaras.
            'negaraOptions' => $service->negaraOptions(),
        ]);
    }

    /**
     * Endpoint AJAX tunggal yang mengisi SELURUH widget dashboard (KPI,
     * material achievement, production result, top excess, detail) --
     * dipanggil ulang tiap kali CPO diganti.
     */
    public function data(Request $request)
    {
        // uraian = CPO spesifik. brand/style boleh dipakai sendiri-sendiri
        // atau dikombinasikan sebagai pengganti uraian -- service akan
        // me-resolve semua CPO yang match lalu menggabungkan datanya.
        // negara = filter tambahan/berdiri sendiri berdasarkan negara supplier
        // shipment -- boleh dipakai sendirian tanpa Buyer/Style/CPO.
        $filters = $request->only(['uraian', 'brand', 'style', 'negara']);

        // Kalau belum ada filter SAMA SEKALI (uraian, brand, style, maupun
        // negara), JANGAN jalankan query berat (full-scan tanpa scope bisa
        // menarik seluruh tabel sekaligus untuk banyak widget). Cukup balikan
        // payload kosong; dashboard baru menarik data sesungguhnya setelah
        // user memilih minimal satu dari Buyer / Style / CPO / Negara.
        if (empty($filters['uraian']) && empty($filters['brand']) && empty($filters['style']) && empty($filters['negara'])) {
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
            'topMaterialExcess'    => $service->topMaterialExcess(),
            'detail'               => $service->detail(),
            'shipmentByDate'       => $service->shipmentByDate(),
            'shipmentDetail'       => $service->shipmentDetail(),
            'pipelineLossSteps'    => $service->pipelineLossSteps(),
            'shipmentByCategory'   => $service->shipmentByCategory(),
        ]);
    }

    /**
     * Data kalender (jumlah dokumen shipment per tanggal `tgl_bukti`) dari
     * mon_shipments, untuk satu bulan tertentu -- dipakai widget "Shipment
     * Date". Query: ?year=2026&month=7 (plus filter uraian/brand/style/negara).
     */
    public function calendar(Request $request)
    {
        $filters = $request->only(['uraian', 'brand', 'style', 'negara']);
        $year  = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $service = MonitoringRekonsiliasiService::make($filters);

        return response()->json([
            'year'  => $year,
            'month' => $month,
            'days'  => $service->shipmentCalendar($year, $month),
        ]);
    }

    /**
     * Detail dokumen shipment untuk satu tanggal `tgl_bukti` spesifik
     * (dipanggil saat tanggal di kalender Shipment Date diklik).
     * Query: ?date=2026-07-15
     */
    public function calendarDetail(Request $request)
    {
        $filters = $request->only(['uraian', 'brand', 'style', 'negara']);
        $date = $request->input('date');

        if (!$date) {
            return response()->json(['message' => 'Parameter date wajib diisi.'], 422);
        }

        $service = MonitoringRekonsiliasiService::make($filters);

        return response()->json([
            'date' => $date,
            'rows' => $service->shipmentCalendarDetail($date),
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
            'fabricQty'            => ['need' => 0, 'order' => 0, 'received' => 0, 'out_wip' => 0, 'stock' => 0],
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
        return $this->runSyncCommand('monitoring:sync-work-order', $request);
    }

    /**
     * Tombol "Sync Master Barang" -> php artisan monitoring:sync-ms-barang
     * (master data, tidak ada opsi --year, selalu upsert semua barang_code).
     */
    public function syncMsBarang(Request $request)
    {
        return $this->runSyncCommandNoYear('monitoring:sync-ms-barang');
    }

    /**
     * Tombol "Sync Master Negara" -> php artisan monitoring:sync-ms-negara
     * (master data, tidak ada opsi --year, selalu kosongkan tabel lalu insert
     * ulang semua negara_code -- bukan upsert).
     */
    public function syncMsNegara(Request $request)
    {
        return $this->runSyncCommandNoYear('monitoring:sync-ms-negara');
    }

    /**
     * Tombol "Sync Master Supplier" -> php artisan monitoring:sync-ms-supplier
     * (master data, tidak ada opsi --year, selalu kosongkan tabel lalu insert
     * ulang semua supplier_code -- bukan upsert). Sebaiknya dijalankan
     * SETELAH sync-ms-negara supaya mapping negara_id sudah lengkap.
     */
    public function syncMsSupplier(Request $request)
    {
        return $this->runSyncCommandNoYear('monitoring:sync-ms-supplier');
    }

    /**
     * Variant runSyncCommand() untuk command master data yang TIDAK punya
     * opsi --year (mis. sync-ms-barang, sync-ms-negara, sync-ms-supplier).
     */
    private function runSyncCommandNoYear(string $command)
    {
        set_time_limit(0);

        try {
            $exitCode = Artisan::call($command);
            $output = trim(Artisan::output());

            return response()->json([
                'success'   => $exitCode === 0,
                'command'   => $command,
                'exit_code' => $exitCode,
                'output'    => $output,
            ], $exitCode === 0 ? 200 : 500);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'command' => $command,
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

    public function negaraOptions(Request $request)
    {
        $filters = $request->only(['uraian', 'brand', 'style', 'negara']);
        $service = MonitoringRekonsiliasiService::make($filters);
        $options = $service->filteredNegaraOptions($filters);

        return response()->json($options);
    }
}
