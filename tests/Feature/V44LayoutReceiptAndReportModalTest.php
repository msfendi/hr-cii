<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * V44-LAYOUT (2026-11-10) — Perbaikan layout bukti scan + canteen report:
 *
 *  1. Bukti scan kantin: foto wajah TIDAK ditampilkan, minimap 1 baris,
 *     nama kantin dari tabel canteens ditampilkan di kartu highlight.
 *  2. Face camera di canteen transaction: snapshot + kamera langsung off
 *     setelah wajah terbaca.
 *  3. /canteen-report tab Detail Scan: id_ticket bisa diklik -> modal
 *     bukti scan dengan desain sama persis seperti canteen transaction.
 */
class V44LayoutReceiptAndReportModalTest extends TestCase
{
    private function txnViewSource(): string
    {
        return file_get_contents(resource_path('views/canteen_transaction/index.blade.php'));
    }

    private function reportViewSource(): string
    {
        return file_get_contents(resource_path('views/canteen_report/index.blade.php'));
    }

    private function reportControllerSource(): string
    {
        return file_get_contents(app_path('Http/Controllers/CanteenReportController.php'));
    }

    private function routesSource(): string
    {
        return file_get_contents(base_path('routes/web.php'));
    }

    public function test_receipt_menampilkan_foto_wajah_dan_minimap_1_row_kanan_kiri(): void
    {
        $src = $this->txnViewSource();
        // Foto wajah dan minimap harus ditampilkan 1 baris kanan-kiri
        $this->assertStringContainsString('inv.photo_face', $src);
        $this->assertStringContainsString('Foto Wajah', $src);
        $this->assertStringContainsString('Lokasi Scan', $src);
        $this->assertStringContainsString('id="receiptMap"', $src);
    }

    public function test_camera_snapshot_flash_scoped_ke_kotak_kamera(): void
    {
        $src = $this->txnViewSource();
        // Flash snapshot berada di dalam cameraBoxWrapper dengan position-absolute, bukan append ke body
        $this->assertStringContainsString('id="cameraBoxWrapper"', $src);
        $this->assertStringContainsString('id="faceSnapshotFlash"', $src);
        $this->assertStringNotContainsString('document.body.appendChild(overlay)', $src);
    }

    public function test_canteen_report_format_tanggal_tidak_ada_syntax_error(): void
    {
        $src = $this->reportViewSource();
        // Parameter dateStr tidak bentrok dengan day
        $this->assertStringContainsString('function formatTanggalId(dateStr)', $src);
        $this->assertStringNotContainsString('function formatTanggalId(d)', $src);
    }

    public function test_receipt_memakai_nama_kantin_dari_tabel_canteens(): void
    {
        $src = $this->txnViewSource();
        $this->assertStringContainsString(
            "inv.canteen_name || ('Kantin ' + inv.canteen_id)",
            $src,
            'Kartu highlight harus memakai canteen_name dari tabel canteens.'
        );
    }

    public function test_face_snapshot_flash_dan_stop_camera_setelah_terbaca(): void
    {
        $src = $this->txnViewSource();
        // Ada fungsi snapshot flash
        $this->assertStringContainsString('function showFaceSnapshotFlash', $src);
        $this->assertStringContainsString('captureFacePhoto()', $src);
        // stopCamera dipanggil setelah wajah dikenali (setelah snapshot)
        $this->assertStringContainsString('showFaceSnapshotFlash(snapshotDataUrl)', $src);
        $this->assertStringContainsString('stopCamera();', $src);
    }

    public function test_canteen_report_controller_punya_receipt_by_ticket(): void
    {
        $src = $this->reportControllerSource();
        $this->assertStringContainsString('function receiptByTicket', $src);
        $this->assertStringContainsString("where('id_ticket', \$idTicket)", $src);
        // Ambil nama kantin dari tabel canteens
        $this->assertStringContainsString('Canteen::where', $src);
        $this->assertStringContainsString('canteen_name', $src);
    }

    public function test_route_canteen_report_receipt_terdaftar(): void
    {
        $src = $this->routesSource();
        $this->assertStringContainsString(
            "canteen-report.receipt",
            $src,
            'Route canteen-report/receipt harus terdaftar.'
        );
    }

    public function test_detail_scan_id_ticket_bisa_diklik_dan_modal_ada(): void
    {
        $src = $this->reportViewSource();
        // id_ticket dirender sebagai link yang bisa diklik
        $this->assertStringContainsString('id-ticket-link', $src);
        $this->assertStringContainsString("data-id_ticket=", $src);
        // Modal receipt ada
        $this->assertStringContainsString('id="receiptModal"', $src);
        $this->assertStringContainsString('id="receiptModalBody"', $src);
        // Fetch ke endpoint receipt
        $this->assertStringContainsString("canteen-report.receipt", $src);
        // renderReceiptModal & drawReceiptModalMap ada (desain sama dengan canteen transaction)
        $this->assertStringContainsString('function renderReceiptModal', $src);
        $this->assertStringContainsString('function drawReceiptModalMap', $src);
        $this->assertStringContainsString('L.geoJSON', $src);
        $this->assertStringContainsString('circleMarker', $src);
    }

    public function test_receipt_modal_memakai_format_tanggal_indonesia(): void
    {
        $src = $this->reportViewSource();
        $this->assertStringContainsString('function formatTanggalId', $src);
        $this->assertStringContainsString('Januari', $src);
    }

    public function test_receipt_modal_highlight_kantin_tanggal_jam(): void
    {
        $src = $this->reportViewSource();
        $this->assertStringContainsString('background:#e8f0fe', $src);
        $this->assertStringContainsString('font-weight:700', $src);
        $this->assertStringContainsString('inv.canteen_name', $src);
    }

    public function test_no_cjk_in_changed_files(): void
    {
        foreach ([
            app_path('Http/Controllers/CanteenReportController.php'),
            resource_path('views/canteen_report/index.blade.php'),
            resource_path('views/canteen_transaction/index.blade.php'),
            base_path('routes/web.php'),
        ] as $f) {
            $this->assertDoesNotMatchRegularExpression(
                '/[\x{3000}-\x{9fff}]/u',
                file_get_contents($f),
                basename($f) . ' tidak boleh mengandung karakter CJK.'
            );
        }
    }
}
