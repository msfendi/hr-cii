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
        // ocf = kode yang DIEKSTRAK dari mon_boms.code_prod, bukan nilai mentahnya
        // (lihat MonitoringRekonsiliasiService::extractOcfCode()).
        $filters = $request->only(['uraian', 'brand', 'style', 'negara', 'ocf', 'sub_ref']);
        $service = MonitoringRekonsiliasiService::make($filters);

        // Kelima dropdown (Buyer/Style/CPO/OCF/Negara) di-cascade BOLAK-BALIK
        // dari filter yang aktif saat halaman dibuka (lihat
        // MonitoringRekonsiliasiService::cascadedFilterOptions()) -- kalau
        // request index() ini datang dengan query filter (mis. dari link
        // share/bookmark), dropdown lain langsung ikut menyaring sejak awal,
        // bukan cuma nanti setelah user ganti pilihan.
        $filterOptions = $service->cascadedFilterOptions();

        return view('monitoring.rekonsiliasi', [
            'filters'       => $filters,
            'cpoOptions'    => $filterOptions['uraian'],
            'buyerOptions'  => $filterOptions['buyer'],
            'styleOptions'  => $filterOptions['style'],
            // Kombinasi Buyer (brand) / Style / CPO (uraian) dari mon_orders
            // TANPA di-scope apapun -- tetap disediakan kalau frontend masih
            // butuh daftar lengkap awal (mis. autocomplete/search di client).
            // Untuk cascade dua arah yang sesungguhnya, pakai buyerOptions/
            // styleOptions/cpoOptions/ocfOptions/negaraOptions di atas & bawah.
            'filterOptions' => $service->orderFilterOptions(),
            // Dropdown filter Negara, sudah di-cascade sesuai Buyer/Style/CPO/OCF aktif.
            'negaraOptions' => $filterOptions['negara'],
            // Dropdown filter OCF, sudah di-cascade sesuai Buyer/Style/CPO/Negara aktif.
            'ocfOptions'    => $filterOptions['ocf'],
            // Dropdown filter Sub Ref, sudah di-cascade sesuai Buyer/Style/CPO/OCF/Negara aktif.
            'subRefOptions' => $filterOptions['sub_ref'],
        ]);
    }

    public function indexocf(Request $request)
    {
        // uraian = CPO spesifik; brand/style dipakai untuk search tanpa harus
        // memilih 1 CPO (lihat MonitoringRekonsiliasiService::filterUraianList()).
        // negara = filter tambahan berdasarkan negara supplier shipment (lihat
        // MonitoringRekonsiliasiService::cpoListForNegara()).
        // ocf = kode yang DIEKSTRAK dari mon_boms.code_prod, bukan nilai mentahnya
        // (lihat MonitoringRekonsiliasiService::extractOcfCode()).
        $filters = $request->only(['uraian', 'brand', 'style', 'negara', 'ocf', 'sub_ref']);
        $service = MonitoringRekonsiliasiService::make($filters);

        // Kelima dropdown (Buyer/Style/CPO/OCF/Negara) di-cascade BOLAK-BALIK
        // dari filter yang aktif saat halaman dibuka (lihat
        // MonitoringRekonsiliasiService::cascadedFilterOptions()) -- kalau
        // request index() ini datang dengan query filter (mis. dari link
        // share/bookmark), dropdown lain langsung ikut menyaring sejak awal,
        // bukan cuma nanti setelah user ganti pilihan.
        $filterOptions = $service->cascadedFilterOptions();

        return view('monitoring.rekonsiliasi_ocf', [
            'filters'       => $filters,
            'cpoOptions'    => $filterOptions['uraian'],
            'buyerOptions'  => $filterOptions['buyer'],
            'styleOptions'  => $filterOptions['style'],
            // Kombinasi Buyer (brand) / Style / CPO (uraian) dari mon_orders
            // TANPA di-scope apapun -- tetap disediakan kalau frontend masih
            // butuh daftar lengkap awal (mis. autocomplete/search di client).
            // Untuk cascade dua arah yang sesungguhnya, pakai buyerOptions/
            // styleOptions/cpoOptions/ocfOptions/negaraOptions di atas & bawah.
            'filterOptions' => $service->orderFilterOptions(),
            // Dropdown filter Negara, sudah di-cascade sesuai Buyer/Style/CPO/OCF aktif.
            'negaraOptions' => $filterOptions['negara'],
            // Dropdown filter OCF, sudah di-cascade sesuai Buyer/Style/CPO/Negara aktif.
            'ocfOptions'    => $filterOptions['ocf'],
            // Dropdown filter Sub Ref, sudah di-cascade sesuai Buyer/Style/CPO/OCF/Negara aktif.
            'subRefOptions' => $filterOptions['sub_ref'],
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
        // ocf = filter tambahan/berdiri sendiri berdasarkan kode OCF hasil
        // ekstraksi dari mon_boms.code_prod -- boleh dipakai sendirian atau
        // dikombinasikan dengan Buyer/Style/CPO/Negara (di-intersect).
        $filters = $request->only(['uraian', 'brand', 'style', 'negara', 'ocf', 'sub_ref']);
        $service = MonitoringRekonsiliasiService::make($filters);

        // Kelima dropdown (Buyer/Style/CPO(Uraian)/OCF/Negara) di-cascade
        // BOLAK-BALIK dari filter yang sedang aktif (lihat
        // MonitoringRekonsiliasiService::cascadedFilterOptions()) -- dihitung
        // SELALU di sini, terlepas dari filter kosong atau tidak, supaya
        // kelima dropdown tetap saling menyaring setiap kali user mengganti
        // salah satunya (pilih OCF -> Uraian/Style/Buyer/Negara ikut
        // menyaring; pilih Style -> Buyer/CPO/OCF/Negara ikut menyaring; dst).
        $filterOptions = $service->cascadedFilterOptions();

        // Kalau belum ada filter SAMA SEKALI (uraian, brand, style, negara,
        // maupun ocf), JANGAN jalankan query berat (full-scan tanpa scope bisa
        // menarik seluruh tabel sekaligus untuk banyak widget). Cukup balikan
        // payload kosong (dropdown tetap ikut dikirim supaya frontend bisa
        // langsung render pilihan awal); dashboard baru menarik data
        // sesungguhnya setelah user memilih minimal satu dari Buyer / Style /
        // CPO / Negara / OCF.
        if (empty($filters['uraian']) && empty($filters['brand']) && empty($filters['style']) && empty($filters['negara']) && empty($filters['ocf']) && empty($filters['sub_ref'])) {
            return response()->json($this->emptyPayload($filterOptions));
        }

        // dd($service->shipmentByDate());

        return response()->json([
            'header'               => $service->header(),
            'summary'              => $service->summary(),
            'shipmentDates'        => $service->shipmentDates(),
            'fabricQty'            => $service->fabricQty(),
            'fabricUsage'          => $service->fabricUsage(),
            'materialAchievement'  => $service->materialAchievement(),
            'productionPipeline'   => $service->productionPipeline(),
            'productionResultByMaterial' => $service->productionResultByMaterial(),
            // 'topMaterialExcess'    => $service->topMaterialExcess(),
            'detail'               => $service->detail(),
            'shipmentByDate'       => $service->shipmentByDate(),
            'shipmentPlanVsActual' => $service->shipmentPlanVsActual(),
            'shipmentDetail'       => $service->shipmentDetail(),
            'pipelineLossSteps'    => $service->pipelineLossSteps(),
            'shipmentByCategory'   => $service->shipmentByCategory(),
            // Dropdown Buyer/Style/CPO(Uraian)/OCF/Sub Ref/Negara, sudah
            // di-cascade dua arah mengikuti filter yang sedang aktif.
            // `ocfOptions`/`subRefOptions` dipertahankan sebagai key
            // terpisah (selain di dalam `filterOptions`) supaya kompatibel
            // dengan frontend lama yang sudah membaca key ini.
            'filterOptions'        => $filterOptions,
            'buyerOptions'         => $filterOptions['buyer'],
            'styleOptions'         => $filterOptions['style'],
            'cpoOptions'           => $filterOptions['uraian'],
            'negaraOptions'        => $filterOptions['negara'],
            'ocfOptions'           => $filterOptions['ocf'],
            'subRefOptions'        => $filterOptions['sub_ref'],
        ]);
    }

    /**
     * Data kalender (jumlah dokumen shipment per tanggal `tgl_bukti`) dari
     * mon_shipments, untuk satu bulan tertentu -- dipakai widget "Shipment
     * Date". Query: ?year=2026&month=7 (plus filter uraian/brand/style/negara).
     */
    public function calendar(Request $request)
    {
        $filters = $request->only(['uraian', 'brand', 'style', 'negara', 'ocf', 'sub_ref']);
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
        $filters = $request->only(['uraian', 'brand', 'style', 'negara', 'ocf', 'sub_ref']);
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
    private function emptyPayload(array $filterOptions = []): array
    {
        $filterOptions += ['buyer' => [], 'style' => [], 'uraian' => [], 'ocf' => [], 'sub_ref' => [], 'negara' => []];

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
            'shipmentPlanVsActual' => ['mode' => 'date', 'labels' => [], 'subRefs' => [], 'plan' => [], 'actual' => []],
            'shipmentDetail'       => [],
            'pipelineLossSteps'    => [],
            'shipmentByCategory'   => [],
            // Dropdown tetap dikirim meski widget lain kosong, supaya
            // Buyer/Style/CPO/OCF/Sub Ref/Negara tetap ter-render dengan
            // cascade yang benar walau belum ada filter yang match apapun.
            'filterOptions'        => $filterOptions,
            'buyerOptions'         => $filterOptions['buyer'],
            'styleOptions'         => $filterOptions['style'],
            'cpoOptions'           => $filterOptions['uraian'],
            'negaraOptions'        => $filterOptions['negara'],
            'ocfOptions'           => $filterOptions['ocf'],
            'subRefOptions'        => $filterOptions['sub_ref'],
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
     * Tombol "Sync Subkon" -> php artisan monitoring:sync-subkon
     * (tidak ada opsi --year, query get_subkon menarik SEMUA transaksi
     * Kirim Subkon (prd_po_hd) & Terima Subkon (prd_so_hd) sekaligus,
     * sama seperti sync-rekonsiliasi -- selalu truncate + insert ulang).
     */
    public function syncSubkon(Request $request)
    {
        return $this->runSyncCommandNoYear('monitoring:sync-subkon');
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

    /**
     * Endpoint ringan khusus untuk refresh KELIMA dropdown filter (Buyer/
     * Style/CPO(Uraian)/OCF/Negara) sekaligus, TANPA menjalankan query
     * widget dashboard yang berat (beda dengan data()). Cocok dipanggil
     * setiap kali salah satu select berubah, sebelum user submit ke
     * dashboard utama -- semua dropdown saling menyaring dua arah lewat
     * MonitoringRekonsiliasiService::cascadedFilterOptions().
     */
    public function filterOptions(Request $request)
    {
        $filters = $request->only(['uraian', 'brand', 'style', 'negara', 'ocf', 'sub_ref']);
        $service = MonitoringRekonsiliasiService::make($filters);

        return response()->json($service->cascadedFilterOptions());
    }

    public function negaraOptions(Request $request)
    {
        $filters = $request->only(['uraian', 'brand', 'style', 'negara', 'ocf', 'sub_ref']);
        $service = MonitoringRekonsiliasiService::make($filters);
        $options = $service->filteredNegaraOptions($filters);

        return response()->json($options);
    }

    /**
     * Hapus 1 baris remark manual (mon_stage_remarks) lewat ikon "hapus"
     * kecil di tiap baris remark pada box pipeline reconciliation
     * (rekon-pipe-remark-delete). Endpoint SENGAJA dipisah dari
     * stage-remark.import supaya bisa diberi permission sendiri di
     * route (lihat monitoring.rekonsiliasi.stage-remark.destroy),
     * jadi role yang boleh import Excel belum tentu boleh hapus manual.
     *
     * CATATAN: saya tidak punya akses ke Model/Controller stage-remark
     * import yang asli (tidak ikut di-upload), jadi nama Model & connection
     * di bawah ini ASUMSI mengikuti konvensi project (App\Models\MonStageRemark,
     * connection default 'cii'). Sesuaikan kalau ternyata beda -- yang penting
     * response JSON-nya tetap {success, message} supaya cocok dengan JS di
     * blade (rekonsiliasi_blade.php / rekonsiliasi_ocf_blade.php).
     */
    public function destroyStageRemark($id)
    {
        try {
            $remark = \App\Models\MonStageRemark::findOrFail($id);
            $remark->delete();

            return response()->json([
                'success' => true,
                'message' => 'Remark berhasil dihapus.',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Remark tidak ditemukan (mungkin sudah dihapus sebelumnya).',
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
