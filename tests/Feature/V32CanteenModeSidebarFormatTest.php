<?php

namespace Tests\Feature;

use App\Models\CanteenBreakWindow;
use App\Models\FaceRecognitionSetting;
use Tests\TestCase;

/**
 * PERBAIKAN V32 (2026-11-09) -- MODE CANTEEN, FORMAT JAM, SIDEBAR
 * DOUBLE-LOAD, DAN SPACER CARD.
 *
 * Isu yang diuji:
 *   1. Mode canteen: pilih liveness ATAU random challenge (bukan
 *      keduanya). Disimpan di face_recognition_settings
 *      (canteen_verification_mode), BUKAN canteen_settings.
 *   2. Format jam Window aktif: "12:15:00.0000000" -> "12:15".
 *   3. Sidebar dropdown tidak mau tertutup di /face-attendance/settings
 *      dan /face-attendance/admin/log: akar double-load jQuery/Bootstrap
 *      (footer + footerscript) di layouts/app.
 *   4. Spacer antara card Geomap Transaksi Kantin dan Izin Presensi
 *      Wajah per Karyawan.
 *
 * Semua test murni kontrak kode + unit formatTime, tidak butuh SQL Server.
 */
class V32CanteenModeSidebarFormatTest extends TestCase
{
    private function stripComments(string $code): string
    {
        $code = preg_replace('/\{\{--.*?--\}\}/s', '', $code);
        $code = preg_replace('#/\*.*?\*/#s', '', $code);
        $code = preg_replace('/^\s*\/\/.*$/m', '', $code);
        $code = preg_replace('/<!--.*?-->/s', '', $code);

        return $code;
    }

    private function source(string $rel): string
    {
        $path = base_path($rel);
        $this->assertFileExists($path, "File sumber tidak ditemukan: {$rel}");

        return $this->stripComments(file_get_contents($path));
    }

    // ==========================================================
    // 1. MODE CANTEEN: LIVENESS ATAU RANDOM (BUKAN KEDUANYA)
    // ==========================================================

    public function test_face_recognition_setting_has_canteen_verification_mode()
    {
        $src = $this->source('app/Models/FaceRecognitionSetting.php');

        $this->assertStringContainsString("'canteen_verification_mode'", $src);
        $this->assertStringContainsString('CANTEEN_MODE_LIVENESS', $src);
        $this->assertStringContainsString('CANTEEN_MODE_RANDOM', $src);
        $this->assertStringContainsString('CANTEEN_MODE_NONE', $src);
        $this->assertStringContainsString('function canteenVerificationMode()', $src);
        $this->assertStringContainsString('function normalizeCanteenVerificationMode(', $src);
    }

    public function test_canteen_mode_is_mutually_exclusive_liveness_or_random()
    {
        // Mode hanya satu nilai dalam satu waktu.
        $this->assertSame('liveness', FaceRecognitionSetting::normalizeCanteenVerificationMode('liveness'));
        $this->assertSame('random_challenge', FaceRecognitionSetting::normalizeCanteenVerificationMode('random_challenge'));
        $this->assertSame('none', FaceRecognitionSetting::normalizeCanteenVerificationMode('none'));
        // Nilai tidak dikenal -> fail-safe liveness.
        $this->assertSame('liveness', FaceRecognitionSetting::normalizeCanteenVerificationMode('junk'));
        $this->assertSame('liveness', FaceRecognitionSetting::normalizeCanteenVerificationMode(''));

        // Konstanta berbeda satu sama lain.
        $this->assertNotSame(
            FaceRecognitionSetting::CANTEEN_MODE_LIVENESS,
            FaceRecognitionSetting::CANTEEN_MODE_RANDOM
        );
    }

    public function test_canteen_mode_stored_in_face_recognition_settings_not_canteen_settings()
    {
        // Model FaceRecognitionSetting yang menyimpan mode.
        $frs = $this->source('app/Models/FaceRecognitionSetting.php');
        // PERBAIKAN V33 (2026-11-09): guard memakai cachedHasColumn
        // (1 query listing di-cache 1 jam) supaya tidak hit
        // INFORMATION_SCHEMA tiap request di SQL Server remote.
        $this->assertStringContainsString(
            "cachedHasColumn('face_recognition_settings', 'canteen_verification_mode')",
            $frs
        );

        // CanteenSetting TIDAK lagi menyimpan mode (deprecated).
        // Cek teks mentah (stripComments menghapus komentar /* */).
        $csRaw = file_get_contents(base_path('app/Models/CanteenSetting.php'));
        $this->assertStringContainsString('DEPRECATED', $csRaw);

        // Controller canteen membaca mode dari FaceRecognitionSetting.
        $ctc = $this->source('app/Http/Controllers/CanteenTransactionController.php');
        $this->assertStringContainsString(
            'FaceRecognitionSetting::canteenVerificationMode()',
            $ctc
        );
        $this->assertStringContainsString('CANTEEN_MODE_LIVENESS', $ctc);
        $this->assertStringContainsString('CANTEEN_MODE_RANDOM', $ctc);
    }

    public function test_canteen_controller_does_not_save_mode_to_canteen_settings()
    {
        $ctc = $this->source('app/Http/Controllers/CanteenTransactionController.php');

        // adminUpdateSetting tidak lagi menulis kolom verifikasi canteen.
        $this->assertDoesNotMatchRegularExpression(
            "/'liveness_canteen_enabled'\\s*=>\\s*\\\$request/",
            $ctc,
            'adminUpdateSetting masih menulis liveness_canteen_enabled ke canteen_settings.'
        );
        $this->assertDoesNotMatchRegularExpression(
            "/'random_challenge_canteen_enabled'\\s*=>\\s*\\\$request/",
            $ctc,
            'adminUpdateSetting masih menulis random_challenge_canteen_enabled ke canteen_settings.'
        );
    }

    public function test_settings_view_has_mode_select_not_two_separate_toggles()
    {
        $src = $this->source('resources/views/face_attendance/settings.blade.php');

        // Mode select ada.
        $this->assertStringContainsString('name="canteen_verification_mode"', $src);
        $this->assertStringContainsString('value="liveness"', $src);
        $this->assertStringContainsString('value="random_challenge"', $src);
        $this->assertStringContainsString('value="none"', $src);

        // Dua sakelar terpisah lama TIDAK lagi ada di view settings.
        $this->assertDoesNotMatchRegularExpression(
            "/id=\"liveness_canteen_enabled\"/",
            $src,
            'Sakelar liveness_canteen_enabled lama masih ada di settings (harus mode select).'
        );
        $this->assertDoesNotMatchRegularExpression(
            "/id=\"random_challenge_canteen_enabled\"/",
            $src,
            'Sakelar random_challenge_canteen_enabled lama masih ada di settings (harus mode select).'
        );
    }

    public function test_canteen_view_uses_single_mode_gate()
    {
        $src = $this->source('resources/views/canteen_transaction/index.blade.php');

        $this->assertStringContainsString('CANTEEN_VERIFICATION_MODE', $src);
        $this->assertStringContainsString("=== 'liveness'", $src);
        $this->assertStringContainsString("=== 'random_challenge'", $src);

        // Banner ganda lama tidak lagi ada: hanya satu banner per mode.
        $this->assertStringContainsString('id="livenessAlertBox"', $src);
        $this->assertStringContainsString('id="challengeAlertBox"', $src);
        $this->assertStringContainsString('id="noVerifyAlertBox"', $src);
    }

    public function test_migration_adds_mode_and_drops_canteen_settings_columns()
    {
        $src = $this->source(
            'database/migrations/2026_11_09_143000_add_canteen_verification_mode_to_face_recognition_settings.php'
        );

        $this->assertStringContainsString('canteen_verification_mode', $src);
        $this->assertStringContainsString('face_recognition_settings', $src);
        $this->assertStringContainsString('canteen_settings', $src);
        $this->assertStringContainsString('liveness_canteen_enabled', $src);
        $this->assertStringContainsString('random_challenge_canteen_enabled', $src);
        $this->assertStringContainsString('dropColumn', $src);
    }

    // ==========================================================
    // 2. FORMAT JAM: HILANGKAN .0000000
    // ==========================================================

    public function test_break_window_format_time_strips_fractional_seconds()
    {
        $this->assertSame('12:15', CanteenBreakWindow::formatTime('12:15:00.0000000'));
        $this->assertSame('17:15', CanteenBreakWindow::formatTime('17:15:00.0000000'));
        $this->assertSame('09:00', CanteenBreakWindow::formatTime('09:00:00'));
        $this->assertSame('09:00', CanteenBreakWindow::formatTime('9:00'));
        $this->assertSame('12:15', CanteenBreakWindow::formatTime('12:15'));
        $this->assertNull(CanteenBreakWindow::formatTime(null));
        $this->assertNull(CanteenBreakWindow::formatTime(''));
    }

    public function test_break_window_accessors_use_format_time()
    {
        $src = $this->source('app/Models/CanteenBreakWindow.php');

        $this->assertStringContainsString('function formatTime(', $src);
        $this->assertStringContainsString('getTimeStartAttribute', $src);
        $this->assertStringContainsString('getTimeEndAttribute', $src);
    }

    public function test_canteen_view_window_active_uses_formatted_time()
    {
        // View memakai $currentWindow->time_start yang kini sudah
        // berformat HH:MM dari accessor model.
        $src = $this->source('resources/views/canteen_transaction/index.blade.php');

        $this->assertStringContainsString('{{ $currentWindow->time_start }}', $src);
        $this->assertStringContainsString('{{ $currentWindow->time_end }}', $src);
    }

    // ==========================================================
    // 3. SIDEBAR: DOUBLE-LOAD JQUERY/BOOTSTRAP DI LAYOUTS/APP
    // ==========================================================

    public function test_vendor_js_wrapped_with_once_named_block()
    {
        $footer = $this->source('resources/views/layout/footer.blade.php');
        $footerscript = $this->source('resources/views/layout/footerscript.blade.php');

        // Kedua file memakai nama @once yang SAMA supaya hanya
        // dirender sekali per request.
        $this->assertStringContainsString("@once('vendor-js')", $footer);
        $this->assertStringContainsString("@once('vendor-js')", $footerscript);
    }

    public function test_layouts_app_includes_both_footer_and_footerscript()
    {
        // Konteks bug: layouts/app meng-include keduanya, sehingga
        // tanpa @once vendor JS dimuat dua kali.
        $src = $this->source('resources/views/layouts/app.blade.php');

        $this->assertStringContainsString("@include('layout.footer')", $src);
        $this->assertStringContainsString("@include('layout.footerscript')", $src);
    }

    public function test_navbar_sidebar_collapse_toggle_bound_once()
    {
        $src = $this->source('resources/views/layout/navbar.blade.php');

        $this->assertStringContainsString('function bindSidebarCollapseToggle()', $src);
        $this->assertStringContainsString('__collapseToggleBound', $src);
        $this->assertStringContainsString('[data-toggle="collapse"][data-target]', $src);
        $this->assertStringContainsString('stopImmediatePropagation', $src);
    }

    public function test_navbar_syncs_collapse_trigger()
    {
        $src = $this->source('resources/views/layout/navbar.blade.php');

        $this->assertStringContainsString('function syncCollapseTrigger(', $src);
        $this->assertStringContainsString(
            "trigger.classList.toggle('collapsed', !shouldBeOpen)",
            $src
        );
    }

    // ==========================================================
    // 4. SPACER ANTARA CARD GEOMAP DAN IZIN PER KARYAWAN
    // ==========================================================

    public function test_settings_view_has_spacer_between_geomap_and_employee_cards()
    {
        $src = $this->source('resources/views/face_attendance/settings.blade.php');

        // Spacer eksplisit di antara dua card col-12.
        $this->assertStringContainsString(
            'aria-hidden="true" style="height: 1.5rem;"',
            $src,
            'Spacer antara Geomap Transaksi Kantin dan Izin Presensi tidak ditemukan.'
        );

        // Card geomap punya margin bawah.
        $this->assertStringContainsString('card shadow mb-4', $src);
    }

    public function test_no_cjk_characters_in_patched_files()
    {
        $files = [
            'app/Models/FaceRecognitionSetting.php',
            'app/Models/CanteenBreakWindow.php',
            'app/Models/CanteenSetting.php',
            'app/Http/Controllers/CanteenTransactionController.php',
            'app/Http/Controllers/FaceAttendanceSettingController.php',
            'resources/views/face_attendance/settings.blade.php',
            'resources/views/canteen_transaction/index.blade.php',
            'resources/views/canteen_transaction/admin.blade.php',
            'resources/views/layout/navbar.blade.php',
            'resources/views/layout/footer.blade.php',
            'resources/views/layout/footerscript.blade.php',
            'database/migrations/2026_11_09_143000_add_canteen_verification_mode_to_face_recognition_settings.php',
        ];

        foreach ($files as $rel) {
            $path = base_path($rel);
            $this->assertFileExists($path);

            $content = file_get_contents($path);
            $cjk = preg_match_all('/[\x{3000}-\x{9FFF}\x{FF00}-\x{FFEF}]/u', $content);

            $this->assertSame(
                0,
                $cjk,
                "File {$rel} mengandung karakter CJK."
            );
        }
    }
}
