<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * PERBAIKAN V37 (2026-11-09)
 *
 * Dua permintaan user:
 *   1. "Liveness dan Tantangan di hide saja cukup tampilkan gps dan mode"
 *      pada halaman /canteen-transaction.
 *   2. "Mode Verifikasi Transaksi Kantin" harus punya KARTU SENDIRI di
 *      /face-attendance/settings (tidak lagi di dalam kartu Liveness
 *      Check / Random Challenge).
 *
 * Test ini bersifat KONTRAK (contract test) -- tidak menyentuh database
 * sehingga tidak terkena timeout SQL Server remote.
 */
class V37CanteenBadgeAndVerificationModeTest extends TestCase
{
    private function source(string $relative): string
    {
        $path = resource_path('views/' . $relative);
        $this->assertFileExists($path, "View tidak ditemukan: {$relative}");
        return (string) file_get_contents($path);
    }

    // ---------------------------------------------------------------
    // BAGIAN 1 -- badge di /canteen-transaction
    // ---------------------------------------------------------------

    public function test_canteen_hides_liveness_and_challenge_badges_but_keeps_gps_and_mode()
    {
        $src = $this->source('canteen_transaction/index.blade.php');

        // Badge Liveness & Tantangan HARUS ada di DOM (JS menulis status
        // ke elemen itu) tapi HARUS disembunyikan lewat class CSS.
        $this->assertMatchesRegularExpression(
            '/id="livenessBadge"[^>]*d-hide-canteen-verify-badge|d-hide-canteen-verify-badge[^>]*id="livenessBadge"/',
            $src,
            'Badge #livenessBadge harus memakai class d-hide-canteen-verify-badge'
        );
        $this->assertMatchesRegularExpression(
            '/id="challengeBadge"[^>]*d-hide-canteen-verify-badge|d-hide-canteen-verify-badge[^>]*id="challengeBadge"/',
            $src,
            'Badge #challengeBadge harus memakai class d-hide-canteen-verify-badge'
        );

        // Badge GPS & Mode HARUS tetap tampil (tanpa class hide).
        $this->assertMatchesRegularExpression(
            '/id="gpsBadge"(?![^>]*d-hide-canteen-verify-badge)/',
            $src,
            'Badge #gpsBadge harus tetap terlihat'
        );
        $this->assertMatchesRegularExpression(
            '/id="modeBadge"(?![^>]*d-hide-canteen-verify-badge)/',
            $src,
            'Badge #modeBadge harus tetap terlihat'
        );

        // CSS class hide wajib ada.
        $this->assertStringContainsString(
            '.d-hide-canteen-verify-badge { display: none !important; }',
            $src,
            'Aturan CSS .d-hide-canteen-verify-badge wajib ada'
        );
    }

    public function test_canteen_gps_badge_shows_real_coordinates_not_only_wajib_opsional()
    {
        $src = $this->source('canteen_transaction/index.blade.php');

        // JS harus menulis koordinat + akurasi ke badge GPS.
        $this->assertStringContainsString(
            "gpsBadge.textContent = 'GPS: ' + geoCoords.latitude.toFixed(5)",
            $src,
            'Badge GPS harus menampilkan koordinat sebenarnya'
        );

        // Harus ada status negatif (di luar area / tidak tersedia).
        $this->assertStringContainsString("'GPS: Di luar Area Kerja'", $src);
        $this->assertStringContainsString("'GPS: Tidak Tersedia'", $src);
    }

    public function test_canteen_status_message_no_longer_says_lingkaran()
    {
        $src = $this->source('canteen_transaction/index.blade.php');

        // Pesan "Posisikan wajah di dalam lingkaran" membuat user merasa
        // STUCK -- harus diganti dengan "tengah area kamera".
        $this->assertStringNotContainsString(
            'Posisikan wajah di dalam lingkaran',
            $src,
            'Pesan "dalam lingkaran" harus dihapus'
        );
        $this->assertStringContainsString(
            'Posisikan wajah di tengah area kamera',
            $src,
            'Pesan harus menyebut "tengah area kamera"'
        );
    }

    // ---------------------------------------------------------------
    // BAGIAN 2 -- face-api.js hanya boleh dimuat sekali
    // ---------------------------------------------------------------

    public function test_face_api_script_tag_appears_only_once()
    {
        $src = $this->source('canteen_transaction/index.blade.php');

        // Hitung <script src=...> yang menunjuk face-api.
        preg_match_all('/<script[^>]*src=[\'"][^\'"]*face-api[^\'"]*[\'"][^>]*>/i', $src, $m);
        $this->assertLessThanOrEqual(
            1,
            count($m[0]),
            'face-api.js harus dimuat via SATU <script src>. Tag: ' . implode(' | ', $m[0])
        );

        // Fallback CDN hanya boleh disisipkan lewat JS (document.createElement),
        // BUKAN lewat <script src> kedua di HTML.
        $this->assertSame(
            0,
            preg_match_all('/<script[^>]*src=[\'"][^\'"]*(jsdelivr|unpkg|cdn)[^\'"]*face-api[^\'"]*[\'"]/i', $src),
            'Tidak boleh ada <script src> CDN face-api kedua'
        );
    }

    // ---------------------------------------------------------------
    // BAGIAN 3 -- kartu Mode Verifikasi Transaksi Kantin
    // ---------------------------------------------------------------

    public function test_settings_has_dedicated_card_for_canteen_verification_mode()
    {
        $src = $this->source('face_attendance/settings.blade.php');

        $this->assertStringContainsString(
            'Mode Verifikasi Transaksi Kantin',
            $src,
            'Judul kartu Mode Verifikasi Transaksi Kantin wajib ada'
        );
        $this->assertStringContainsString(
            'settings.update-canteen-verification-mode',
            $src,
            'Form harus POST ke route settings.update-canteen-verification-mode'
        );
    }

    public function test_canteen_verification_mode_select_exists_only_in_dedicated_card()
    {
        $src = $this->source('face_attendance/settings.blade.php');

        // Select mode harus ada.
        $this->assertStringContainsString(
            'name="canteen_verification_mode"',
            $src,
            'Select canteen_verification_mode wajib ada'
        );

        // Hitung kemunculan select HARUSNYA tepat 1 (hanya di kartu baru).
        $this->assertSame(
            1,
            substr_count($src, 'name="canteen_verification_mode"'),
            'Select canteen_verification_mode harus HANYA di kartu Mode Verifikasi Transaksi Kantin'
        );
    }

    public function test_route_and_controller_method_exist()
    {
        $this->assertTrue(
            Route::has('face.attendance.settings.update-canteen-verification-mode'),
            'Route settings.update-canteen-verification-mode belum terdaftar'
        );

        $controller = new \App\Http\Controllers\FaceAttendanceSettingController();
        $this->assertTrue(
            method_exists($controller, 'updateCanteenVerificationMode'),
            'Method updateCanteenVerificationMode belum ada di controller'
        );
    }
}
