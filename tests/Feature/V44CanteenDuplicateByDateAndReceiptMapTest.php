<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * V44 (2026-11-10) — Perbaikan sesuai laporan user:
 *
 *  1. CEK "SUDAH SCAN" BERDASARKAN TANGGAL SAJA (bukan window break).
 *     Sebelumnya findDuplicateAnyCanteen() memakai where('window_name', ...),
 *     sehingga scan kedua di window BERBEDA (mis. Sarapan vs Makan Siang)
 *     pada hari yang sama LOLOS. Sekarang: npk + date saja di kedua tabel
 *     (canteen & canteen_twos) -> kalau ada di salah satu = duplikat.
 *
 *  2. BUKTI SCAN KANTIN: tambah minimap koordinat hasil scan (polygon area
 *     + marker hijau di titik scan), format tanggal "1 Januari 2026", dan
 *     data kantin/tanggal/waktu ditonjolkan.
 */
class V44CanteenDuplicateByDateAndReceiptMapTest extends TestCase
{
    private function controllerSource(): string
    {
        return file_get_contents(app_path('Http/Controllers/CanteenTransactionController.php'));
    }

    private function viewSource(): string
    {
        return file_get_contents(resource_path('views/canteen_transaction/index.blade.php'));
    }

    // ---------------------------------------------------------------
    // 1. Logika duplikat: TANGGAL saja, window_name tidak lagi dipakai
    // ---------------------------------------------------------------

    public function test_duplicate_check_tidak_lagi_memakai_window_name(): void
    {
        $src = $this->controllerSource();

        // PERBAIKAN V44: di dalam method findDuplicateAnyCanteen tidak boleh
        // ada lagi ->where('window_name', ...). Kita ekstrak body method-nya.
        preg_match('/function findDuplicateAnyCanteen\([\s\S]*?\n    \}/', $src, $m);
        $this->assertNotEmpty($m, 'Method findDuplicateAnyCanteen harus ada.');
        $body = $m[0];

        $this->assertStringNotContainsString(
            "where('window_name'",
            $body,
            'V44: findDuplicateAnyCanteen tidak boleh lagi memfilter window_name — cek berdasarkan tanggal saja.'
        );
        $this->assertStringContainsString(
            "where('date', \$date)",
            $body,
            'Cek duplikat harus tetap memfilter berdasarkan tanggal.'
        );
        $this->assertStringContainsString(
            "where('npk', \$npk)",
            $body,
            'Cek duplikat harus tetap memfilter berdasarkan NPK.'
        );
    }

    public function test_signature_findDuplicateAnyCanteen_tanpa_windowName(): void
    {
        $src = $this->controllerSource();

        $this->assertMatchesRegularExpression(
            '/function findDuplicateAnyCanteen\(string \$npk, string \$date\)/',
            $src,
            'V44: signature findDuplicateAnyCanteen harus (string $npk, string $date) tanpa $windowName.'
        );
        $this->assertStringContainsString(
            '$this->findDuplicateAnyCanteen($npk, $today)',
            $src,
            'Pemanggilan di store() harus tanpa argumen window.'
        );
    }

    public function test_pesan_duplikat_menyebut_hari_ini_bukan_window(): void
    {
        $src = $this->controllerSource();

        $this->assertStringContainsString(
            'hari ini',
            $src,
            'Pesan duplikat harus menyebut "hari ini" (bukan window).'
        );
        // Pesan lama yang menyebut window sebagai penyebab harus hilang.
        $this->assertStringNotContainsString(
            'pada window {$window->name}',
            $src,
            'Pesan duplikat tidak boleh lagi menyebut window aktif sebagai alasan.'
        );
    }

    public function test_blade_duplikat_alert_tidak_lagi_memakai_window(): void
    {
        $src = $this->viewSource();

        preg_match('/function showDuplicateAlert\([\s\S]*?\n\}/', $src, $m);
        $this->assertNotEmpty($m, 'showDuplicateAlert harus ada.');
        $body = $m[0];

        $this->assertStringNotContainsString(
            'duplicate_window',
            $body,
            'V44: alert duplikat tidak lagi menampilkan window (cek by tanggal saja).'
        );
        $this->assertStringContainsString(
            'hari ini',
            $body,
            'Alert duplikat harus menyebut "hari ini".'
        );
    }

    // ---------------------------------------------------------------
    // 2. Bukti scan: minimap koordinat hasil scan
    // ---------------------------------------------------------------

    public function test_receipt_step_punya_container_minimap(): void
    {
        $src = $this->viewSource();

        $this->assertStringContainsString('id="receiptMap"', $src, 'Harus ada container #receiptMap di step bukti scan.');
        $this->assertStringContainsString('id="receiptMapCaption"', $src, 'Harus ada caption peta bukti.');
    }

    public function test_ada_fungsi_drawReceiptMap_dengan_marker_hijau(): void
    {
        $src = $this->viewSource();

        $this->assertMatchesRegularExpression(
            '/function drawReceiptMap\(lat, lng\)/',
            $src,
            'Harus ada fungsi drawReceiptMap(lat, lng).'
        );
        // Marker hijau seperti attachment: fillColor hijau + border putih.
        $this->assertStringContainsString("fillColor: '#1cc88a'", $src, 'Marker hasil scan harus hijau (#1cc88a).');
        $this->assertStringContainsString("color: '#ffffff'", $src, 'Marker hasil scan harus ber-border putih.');
        // Polygon area digambar via L.geoJSON (konvensi [lng,lat] benar).
        preg_match('/function drawReceiptMap\([\s\S]*?\n\}/', $src, $m);
        $this->assertNotEmpty($m);
        $this->assertStringContainsString('window.L.geoJSON(geoJson', $m[0], 'Polygon di peta bukti harus digambar via L.geoJSON.');
        $this->assertStringContainsString('L.circleMarker([lat, lng]', $m[0], 'Marker harus ditempatkan di koordinat hasil scan.');
    }

    public function test_renderReceipt_memanggil_drawReceiptMap_bila_koordinat_ada(): void
    {
        $src = $this->viewSource();

        preg_match('/function renderReceipt\(inv\)\{[\s\S]*?\n\}/', $src, $m);
        $this->assertNotEmpty($m, 'renderReceipt harus ada.');
        $this->assertStringContainsString('drawReceiptMap(lat, lng)', $m[0], 'renderReceipt harus menggambar peta saat koordinat tersedia.');
    }

    // ---------------------------------------------------------------
    // 3. Format tanggal "1 Januari 2026" + data penting ditonjolkan
    // ---------------------------------------------------------------

    public function test_ada_format_tanggal_indonesia_panjang(): void
    {
        $src = $this->viewSource();

        $this->assertMatchesRegularExpression('/function formatTanggalId\(/', $src, 'Harus ada helper formatTanggalId.');
        $this->assertStringContainsString("'Januari'", $src, 'Nama bulan Indonesia harus ada.');
        $this->assertStringContainsString("'Desember'", $src, 'Nama bulan Indonesia harus lengkap.');
        // Contoh keluaran: 1 Januari 2026
        $this->assertMatchesRegularExpression(
            "/return d \+ ' ' \+ monthName \+ ' ' \+ y/",
            $src,
            'formatTanggalId harus menghasilkan "1 Januari 2026".'
        );
    }

    public function test_renderReceipt_memakai_format_tanggal_dan_kartu_tebal(): void
    {
        $src = $this->viewSource();

        preg_match('/function renderReceipt\(inv\)\{[\s\S]*?\n\}/', $src, $m);
        $this->assertNotEmpty($m);
        $body = $m[0];

        $this->assertStringContainsString('formatTanggalId(inv.date)', $body, 'Tanggal di bukti harus diformat Indonesia panjang.');
        // Kartu besar untuk kantin/tanggal/waktu: latar biru muda + teks tebal.
        $this->assertStringContainsString('background:#e8f0fe', $body, 'Data penting harus ditonjolkan dengan latar berwarna.');
        $this->assertStringContainsString('font-weight:700', $body, 'Data penting harus teks tebal.');
        $this->assertStringContainsString('inv.canteen_name', $body, 'Nama kantin dari tabel canteens harus ada di kartu yang ditonjolkan.');
    }

    // ---------------------------------------------------------------
    // 4. Tidak ada karakter CJK di file yang diubah
    // ---------------------------------------------------------------

    public function test_no_cjk_in_changed_files(): void
    {
        foreach ([app_path('Http/Controllers/CanteenTransactionController.php'),
                  resource_path('views/canteen_transaction/index.blade.php')] as $f) {
            $this->assertDoesNotMatchRegularExpression(
                '/[\x{3000}-\x{9fff}]/u',
                file_get_contents($f),
                basename($f) . ' tidak boleh mengandung karakter CJK.'
            );
        }
    }
}
