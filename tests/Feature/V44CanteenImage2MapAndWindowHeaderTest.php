<?php

namespace Tests\Feature;

use App\Http\Controllers\CanteenReportController;
use App\Http\Controllers\CanteenTransactionController;
use Tests\TestCase;

class V44CanteenImage2MapAndWindowHeaderTest extends TestCase
{
    private function transactionViewSource(): string
    {
        return file_get_contents(resource_path('views/canteen_transaction/index.blade.php'));
    }

    private function reportViewSource(): string
    {
        return file_get_contents(resource_path('views/canteen_report/index.blade.php'));
    }

    public function test_photo_face_format_anti_414(): void
    {
        // Raw base64 tanpa prefix harus diberi prefix data URI
        $raw = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
        $formatted = CanteenTransactionController::formatPhotoFaceForResponse($raw);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $formatted);

        // Yang sudah ada data:image/ tetap utuh
        $already = 'data:image/png;base64,' . $raw;
        $this->assertSame($already, CanteenTransactionController::formatPhotoFaceForResponse($already));

        // Null atau kosong tetap null
        $this->assertNull(CanteenTransactionController::formatPhotoFaceForResponse(null));
        $this->assertNull(CanteenTransactionController::formatPhotoFaceForResponse('   '));
    }

    public function test_window_aktif_header_sinkron_dari_transaksi_canteen(): void
    {
        $src = $this->transactionViewSource();

        // Elemen header window aktif memiliki ID untuk update dinamis
        $this->assertStringContainsString('id="activeWindowAlert"', $src);
        $this->assertStringContainsString('id="activeWindowText"', $src);

        // renderReceipt mengupdate header dari inv.window_name
        $this->assertStringContainsString('inv.window_name', $src);
        $this->assertStringContainsString('activeWindowText', $src);
        $this->assertStringContainsString('textBox.textContent = `${inv.window_name}', $src);

        // Selesai (btnDone) mengembalikan teks awal
        $this->assertStringContainsString('initialWindowText', $src);
    }

    public function test_peta_bukti_scan_sesuai_referensi_gambar_2(): void
    {
        $tSrc = $this->transactionViewSource();
        $rSrc = $this->reportViewSource();

        // Keduanya memiliki icon custom avatar siluet sesuai Gambar 2
        $this->assertStringContainsString('createScanAvatarIcon', $tSrc);
        $this->assertStringContainsString('createScanAvatarIcon', $rSrc);
        $this->assertStringContainsString('#00c08b', $tSrc); // background toska
        $this->assertStringContainsString('#3a206e', $tSrc); // avatar silhouette dark purple

        // Polygon styling hijau toska (#00c08b) dan noClip: true
        $this->assertStringContainsString("color: '#00c08b'", $tSrc);
        $this->assertStringContainsString("noClip: true", $tSrc);
        $this->assertStringContainsString("color: '#00c08b'", $rSrc);
        $this->assertStringContainsString("noClip: true", $rSrc);

        // Zoom control aktif (+ / -) di top-left
        $this->assertStringContainsString('zoomControl: true', $tSrc);
        $this->assertStringContainsString('zoomControl: true', $rSrc);
    }

    public function test_photo_face_helper_in_blade_anti_414(): void
    {
        $tSrc = $this->transactionViewSource();
        $rSrc = $this->reportViewSource();

        $this->assertStringContainsString('function formatPhotoFaceSrc', $tSrc);
        $this->assertStringContainsString('function formatPhotoFaceSrc', $rSrc);
        $this->assertStringContainsString('formatPhotoFaceSrc(inv.photo_face)', $tSrc);
        $this->assertStringContainsString('formatPhotoFaceSrc(inv.photo_face)', $rSrc);
    }

    public function test_no_cjk_in_all_modified_files(): void
    {
        foreach ([
            app_path('Http/Controllers/CanteenTransactionController.php'),
            app_path('Http/Controllers/CanteenReportController.php'),
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
