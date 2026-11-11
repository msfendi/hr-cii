<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * PERBAIKAN V32 (2026-11-09) -- DUA WAJAH LIVENESS DI /canteen-transaction
 *                              + SWEETALERT SIMPAN GEOMAP KANTIN
 *
 * Isu 1: DUA WAJAH LIVENESS
 * --------------------------
 * Halaman /canteen-transaction meng-include DUA partial wajah sekaligus:
 *   - canteen_transaction._liveness   (baris lama)
 *   - face_attendance._liveness_face   (baris baru V32)
 * Kedua file itu DUPLIKAT PERSIS satu sama lain, sehingga halaman
 * menampilkan dua wajah + dua blok <style> .lv-* yang bertabrakan.
 *
 * Isu 2: SWEETALERT SIMPAN GEOMAP
 * -------------------------------
 * Permintaan user: "saat simpan Geomap Transaksi Kantin tampilkan
 * sweet alert confirmation dan berhasilnya".
 *
 * Test ini murni kontrak kode -- tidak butuh database SQL Server.
 */
class V32DuplicateFaceAndGeomapAlertTest extends TestCase
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
    // 1. DUA WAJAH LIVENESS
    // ==========================================================

    public function test_canteen_view_does_not_include_duplicate_liveness_partial()
    {
        $src = $this->source('resources/views/canteen_transaction/index.blade.php');

        // Partial lokal yang duplikat TIDAK BOLEH di-include lagi.
        $this->assertDoesNotMatchRegularExpression(
            "/@include\(\s*'canteen_transaction\._liveness'/",
            $src,
            'View canteen masih meng-include partial wajah duplikat canteen_transaction._liveness.'
        );
    }

    public function test_canteen_view_includes_only_canonical_liveness_partial()
    {
        $src = $this->source('resources/views/canteen_transaction/index.blade.php');

        // Hanya partial kanonik yang boleh dipakai.
        $this->assertStringContainsString(
            "@include('face_attendance._liveness_face'",
            $src,
            'View canteen harus memakai partial kanonik face_attendance._liveness_face.'
        );

        // Dan TIDAK BOLEH ada include partial wajah lain.
        $this->assertSame(
            1,
            preg_match_all("/@include\('[^']*_liveness[^']*'/", $src),
            'View canteen harus punya tepat satu include partial wajah liveness.'
        );
    }

    public function test_duplicate_liveness_partial_file_has_been_removed()
    {
        $this->assertFileDoesNotExist(
            base_path('resources/views/canteen_transaction/_liveness.blade.php'),
            'Partial duplikat canteen_transaction/_liveness.blade.php masih ada.'
        );
    }

    public function test_canonical_liveness_partial_is_the_only_liveness_face_source()
    {
        // Partial kanonik harus tetap ada dan dipakai halaman lain.
        $this->assertFileExists(
            base_path('resources/views/face_attendance/_liveness_face.blade.php'),
            'Partial kanonik face_attendance/_liveness_face.blade.php hilang.'
        );

        // Tidak boleh ada partial wajah lain di folder canteen_transaction.
        $files = glob(base_path('resources/views/canteen_transaction/*liveness*'));

        $this->assertSame(
            [],
            $files,
            'Masih ada file liveness di folder canteen_transaction: '
                . implode(', ', array_map('basename', $files))
        );
    }

    public function test_canteen_view_renders_single_liveness_face_element()
    {
        $view = base_path('resources/views/canteen_transaction/index.blade.php');
        $src = $this->source('resources/views/canteen_transaction/index.blade.php');

        // Face guide di panel kamera bukan wajah liveness.
        // Yang dihitung adalah elemen .lv-face-wrap dari partial.
        // Karena partial di-include satu kali, markup view hanya boleh
        // punya SATU titik include -> satu elemen wajah.
        $includeCount = preg_match_all("/@include\('face_attendance\._liveness_face'/", $src);
        $this->assertSame(1, $includeCount, 'Partial wajah harus di-include tepat sekali.');

        // Pastikan view tidak menyalin SVG wajah secara manual
        // (yang akan menambah wajah kedua di luar partial).
        $this->assertDoesNotMatchRegularExpression(
            '/class="lv-face-svg"/',
            $src,
            'View canteen menyalin SVG wajah secara manual -> ada wajah tambahan.'
        );

        $this->assertFileExists($view);
    }

    public function test_other_pages_still_use_canonical_liveness_partial()
    {
        // Halaman lain tidak boleh rusak karena partial kanonik dipakai bersama.
        $pemakai = [
            'resources/views/auth/login.blade.php',
            'resources/views/cuti/login.blade.php',
            'resources/views/face_recognition/self_register.blade.php',
            'resources/views/face_attendance/login.blade.php',
            'resources/views/payroll/employee_payroll.blade.php',
        ];

        foreach ($pemakai as $rel) {
            $src = $this->source($rel);
            $this->assertStringContainsString(
                "@include('face_attendance._liveness_face'",
                $src,
                "Halaman {$rel} harus tetap memakai partial kanonik."
            );
        }
    }

    // ==========================================================
    // 2. SWEETALERT SIMPAN GEOMAP KANTIN
    // ==========================================================

    public function test_geomap_form_has_id_for_js_binding()
    {
        $src = $this->source('resources/views/face_attendance/settings.blade.php');

        $this->assertStringContainsString(
            'id="canteenGeomapForm"',
            $src,
            'Form geomap kantin harus punya id="canteenGeomapForm" agar bisa di-bind JS.'
        );
    }

    public function test_geomap_form_shows_confirmation_before_save()
    {
        $src = $this->source('resources/views/face_attendance/settings.blade.php');

        // Submit harus dicegat, lalu tampilkan konfirmasi.
        $this->assertStringContainsString(
            "addEventListener('submit'",
            $src,
            'Form geomap harus dicegat event submit untuk konfirmasi.'
        );
        $this->assertStringContainsString('preventDefault', $src);
        $this->assertStringContainsString('showCancelButton: true', $src);
        $this->assertStringContainsString('Ya, Simpan', $src);
        $this->assertStringContainsString('Batal', $src);
    }

    public function test_geomap_form_shows_success_and_error_sweetalert()
    {
        $src = $this->source('resources/views/face_attendance/settings.blade.php');

        // Notifikasi hasil: sukses dan gagal.
        $this->assertStringContainsString('Berhasil Disimpan', $src);
        $this->assertStringContainsString('Gagal Menyimpan', $src);
        $this->assertStringContainsString("icon: ikon", $src);
        $this->assertStringContainsString('showResult(true', $src);
        $this->assertStringContainsString('showResult(false', $src);
    }

    public function test_geomap_form_loads_sweetalert2_and_sends_json()
    {
        $src = $this->source('resources/views/face_attendance/settings.blade.php');

        // JS SweetAlert2 harus dimuat eksplisit di halaman ini.
        $this->assertStringContainsString('sweetalert2', $src);

        // Permintaan dikirim sebagai JSON supaya backend bisa balas JSON.
        $this->assertStringContainsString("'Accept': 'application/json'", $src);
        $this->assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $src);
    }

    public function test_geomap_form_escapes_location_names_in_alert()
    {
        $src = $this->source('resources/views/face_attendance/settings.blade.php');

        // Nama lokasi berasal dari DB -> harus di-escape sebelum masuk HTML alert.
        $this->assertStringContainsString('function escapeHtml', $src);
        $this->assertStringContainsString('escapeHtml(n)', $src);
        $this->assertStringContainsString('escapeHtml(message)', $src);
        $this->assertStringContainsString('escapeHtml(warning)', $src);
    }

    public function test_controller_returns_json_for_geomap_save()
    {
        $src = $this->source('app/Http/Controllers/FaceAttendanceSettingController.php');

        // Controller harus mendukung respons JSON.
        $this->assertStringContainsString('expectsJson()', $src);
        $this->assertStringContainsString("'ok' => true", $src);
        $this->assertStringContainsString("'ok' => false", $src);
        $this->assertStringContainsString("'message' =>", $src);
    }

    public function test_controller_shows_sweetalert_on_non_json_save()
    {
        $src = $this->source('app/Http/Controllers/FaceAttendanceSettingController.php');

        // Fallback non-JSON: SweetAlert sukses + gagal.
        $this->assertStringContainsString('Alert::success(', $src);
        $this->assertStringContainsString('Alert::error(', $src);
        $this->assertStringContainsString(
            'use RealRashid\\SweetAlert\\Facades\\Alert;',
            $src,
            'Facade Alert belum di-import di controller.'
        );
    }

    public function test_controller_geomap_save_still_redirects_with_flash()
    {
        $src = $this->source('app/Http/Controllers/FaceAttendanceSettingController.php');

        // Flash lama harus tetap ada supaya halaman non-JS tetap informatif.
        $this->assertStringContainsString("->with('success', \$pesan)", $src);
        $this->assertStringContainsString("->with('error', \$pesan)", $src);
    }

    public function test_geomap_fail_closed_warning_preserved()
    {
        $src = $this->source('app/Http/Controllers/FaceAttendanceSettingController.php');

        // Peringatan fail-closed harus tetap communicates ke user.
        $this->assertStringContainsString('fail-closed', $src);
        $this->assertStringContainsString("'warning' => \$peringatan", $src);
    }
}
