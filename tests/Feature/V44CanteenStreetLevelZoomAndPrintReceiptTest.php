<?php

namespace Tests\Feature;

use Tests\TestCase;

class V44CanteenStreetLevelZoomAndPrintReceiptTest extends TestCase
{
    private function txnViewSource(): string
    {
        return file_get_contents(resource_path('views/canteen_transaction/index.blade.php'));
    }

    private function reportViewSource(): string
    {
        return file_get_contents(resource_path('views/canteen_report/index.blade.php'));
    }

    public function test_minimap_street_level_zoom_17_pada_canteen_transaction(): void
    {
        $src = $this->txnViewSource();

        // Inisialisasi map dengan street level zoom 17
        $this->assertStringContainsString('zoom: 17', $src);
        $this->assertStringContainsString('receiptMap.setView([lat, lng], 17)', $src);
        // Proteksi agar zoom tidak turun di bawah level 16
        $this->assertStringContainsString('receiptMap.getZoom() < 16', $src);
    }

    public function test_minimap_street_level_zoom_17_pada_canteen_report_modal(): void
    {
        $src = $this->reportViewSource();

        // Inisialisasi map modal dengan street level zoom 17
        $this->assertStringContainsString('zoom: 17', $src);
        $this->assertStringContainsString('receiptModalMap.setView([lat, lng], 17)', $src);
        // Proteksi saat modal dibuka agar zoom tetap dipertahankan pada 17
        $this->assertStringContainsString('receiptModalMap.getZoom() < 16', $src);
        $this->assertStringContainsString('receiptModalMap.setZoom(17)', $src);
    }

    public function test_layout_cetak_struk_teroptimasi_pada_canteen_report(): void
    {
        $src = $this->reportViewSource();

        // Tombol cetak menggunakan window.print() langsung tanpa window.open popup rusak
        $this->assertStringContainsString("$('#btnPrintReceipt').on('click'", $src);
        $this->assertStringContainsString('window.print()', $src);
        $this->assertStringNotContainsString("window.open('', '_blank')", $src);

        // Aturan @media print terpasang untuk merapikan kartu struk
        $this->assertStringContainsString('@media print', $src);
        $this->assertStringContainsString('#receiptModalBox', $src);
        $this->assertStringContainsString('.leaflet-control-zoom', $src);
        $this->assertStringContainsString('print-color-adjust: exact', $src);
    }

    public function test_layout_cetak_struk_teroptimasi_pada_canteen_transaction(): void
    {
        $src = $this->txnViewSource();

        // Tombol cetak di stepReceipt
        $this->assertStringContainsString('id="btnPrintReceiptTransaction"', $src);
        $this->assertStringContainsString('window.print()', $src);

        // Aturan @media print terpasang untuk merapikan kartu struk
        $this->assertStringContainsString('@media print', $src);
        $this->assertStringContainsString('#receiptBox', $src);
        $this->assertStringContainsString('.leaflet-control-zoom', $src);
    }

    public function test_peniadaan_no_bukti_dan_penggunaan_id_ticket_pada_kedua_view(): void
    {
        $txn = $this->txnViewSource();
        $rep = $this->reportViewSource();

        // Tidak ada baris "No. Bukti" pada kedua struk
        $this->assertStringNotContainsString('<span>No. Bukti</span>', $txn);
        $this->assertStringNotContainsString('<span>No. Bukti</span>', $rep);

        // Baris "ID Tiket" hadir pada kedua struk
        $this->assertStringContainsString('<span>ID Tiket</span>', $txn);
        $this->assertStringContainsString('<span>ID Tiket</span>', $rep);
    }

    public function test_no_cjk_in_both_views(): void
    {
        foreach ([
            resource_path('views/canteen_transaction/index.blade.php'),
            resource_path('views/canteen_report/index.blade.php'),
        ] as $f) {
            $this->assertDoesNotMatchRegularExpression(
                '/[\x{3000}-\x{9fff}]/u',
                file_get_contents($f),
                basename($f) . ' tidak boleh mengandung karakter CJK.'
            );
        }
    }
}
