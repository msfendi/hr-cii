<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * PERBAIKAN V32 (2026-11-09) -- SETTING GEOMAP KANTIN DI /face-attendance/settings
 *
 * Permintaan user:
 *   "di /face-attendance/settings tambahkan untuk setting geomap yang
 *    diperbolehkan untuk canteen transaction
 *    dan di canteen transaction hanya boleh mengijinkan dari geomap yang
 *    perlu disetting. perlukah membuat migrations?"
 *
 * JAWABAN MIGRATION: TIDAK PERLU.
 * Tabel `canteen_settings` sudah punya `require_geomap` dan
 * `allowed_work_location_ids` sejak migration 2026_11_09_130000.
 *
 * Test ini TIDAK butuh database SQL Server -- murni kontrak kode.
 */
class CanteenGeomapSettingContractTest extends TestCase
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
    // 1. TIDAK PERLU MIGRATION BARU
    // ==========================================================

    public function test_canteen_settings_table_already_has_geomap_columns()
    {
        $src = $this->source(
            'database/migrations/2026_11_09_130000_create_canteen_transaction_and_satisfaction_tables.php'
        );

        // Kolom ini sudah ada, jadi TIDAK perlu migration baru.
        $this->assertStringContainsString("require_geomap", $src);
        $this->assertStringContainsString("allowed_work_location_ids", $src);
    }

    public function test_model_canteen_setting_exposes_geomap_fields()
    {
        $src = $this->source('app/Models/CanteenSetting.php');

        $this->assertStringContainsString("'require_geomap'", $src);
        $this->assertStringContainsString("'allowed_work_location_ids'", $src);
        $this->assertStringContainsString("'require_geomap' => 'boolean'", $src);
        $this->assertStringContainsString("'allowed_work_location_ids' => 'array'", $src);
    }

    // ==========================================================
    // 2. FORM DI /face-attendance/settings
    // ==========================================================

    public function test_settings_view_has_canteen_geomap_form()
    {
        $src = $this->source('resources/views/face_attendance/settings.blade.php');

        $this->assertStringContainsString(
            'route(\'face.attendance.settings.update-canteen-geomap\')',
            $src,
            'Form geomap kantin belum ada di /face-attendance/settings.'
        );
        $this->assertStringContainsString('name="require_geomap"', $src);
        $this->assertStringContainsString('name="allowed_work_location_ids[]"', $src);
        $this->assertStringContainsString('@csrf', $src);
    }

    public function test_settings_view_uses_controller_values_for_geomap()
    {
        $src = $this->source('resources/views/face_attendance/settings.blade.php');

        $this->assertStringContainsString('$canteenRequireGeomap', $src);
        $this->assertStringContainsString('$canteenAllowedLocationIds', $src);
        $this->assertStringContainsString('$canteenSetting', $src);
    }

    public function test_settings_view_escapes_location_name()
    {
        $src = $this->source('resources/views/face_attendance/settings.blade.php');

        // Nama lokasi WAJIB pakai {{ }} (Blade escape otomatis).
        // Yang berbahaya adalah {!! $loc->name !!} (raw output).
        $this->assertStringContainsString('{{ $loc->name }}', $src);
        $this->assertDoesNotMatchRegularExpression(
            '/\{!![^}]*\$loc->name[^}]*!!\}/',
            $src,
            'Nama lokasi geomap memakai raw output -> XSS.'
        );
    }

    // ==========================================================
    // 3. CONTROLLER
    // ==========================================================

    public function test_controller_has_update_canteen_geomap_method()
    {
        $src = $this->source('app/Http/Controllers/FaceAttendanceSettingController.php');

        $this->assertStringContainsString('function updateCanteenGeomap', $src);
        $this->assertStringContainsString('CanteenSetting::current()', $src);
        $this->assertStringContainsString("'require_geomap'", $src);
        $this->assertStringContainsString("'allowed_work_location_ids'", $src);
    }

    public function test_controller_index_loads_canteen_geomap_values()
    {
        $src = $this->source('app/Http/Controllers/FaceAttendanceSettingController.php');

        $this->assertStringContainsString('$canteenRequireGeomap', $src);
        $this->assertStringContainsString('$canteenAllowedLocationIds', $src);
        // PERBAIKAN V34 HOTFIX (2026-11-09): guard TIDAK lagi memakai
        // cachedHasTable() karena getTables() di SQL Server query berat
        // (join sys.partitions + allocation_units) dan timeout -> 500.
        // Sekarang pakai CanteenSetting::current() langsung (sudah punya
        // try/catch + fallback) dan cachedColumns() untuk write-path.
        // Kontrak ini memastikan tidak ada guard berat yang kembali.
        $this->assertStringContainsString('CanteenSetting::current()', $src);
        $this->assertDoesNotMatchRegularExpression(
            "/cachedHasTable\\('canteen_settings'\\)/",
            $src,
            'V34 HOTFIX: jangan pakai cachedHasTable() di controller settings '
            . '(getTables berat -> 500). Pakai CanteenSetting::current() / cachedColumns().'
        );
    }

    public function test_controller_canteen_geomap_validates_input()
    {
        $src = $this->source('app/Http/Controllers/FaceAttendanceSettingController.php');

        $this->assertStringContainsString("'require_geomap' => 'nullable|boolean'", $src);
        $this->assertStringContainsString("'allowed_work_location_ids' => 'nullable|array'", $src);
        $this->assertStringContainsString("'allowed_work_location_ids.*' => 'integer|min:1'", $src);
    }

    // ==========================================================
    // 4. ROUTE
    // ==========================================================

    public function test_route_exists_for_canteen_geomap_setting()
    {
        $src = $this->source('routes/web.php');

        $this->assertStringContainsString(
            "Route::post('/settings/canteen-geomap'",
            $src
        );
        $this->assertStringContainsString('updateCanteenGeomap', $src);
    }

    // ==========================================================
    // 5. ENFORCEMENT DI /canteen-transaction
    // ==========================================================

    public function test_canteen_transaction_enforces_geomap_whitelist()
    {
        $src = $this->source('app/Http/Controllers/CanteenTransactionController.php');

        // Setting dibaca dari canteen_settings (sumber kebenaran yang sama).
        $this->assertStringContainsString("CanteenSetting::allowedLocationIds()", $src);
        // Filter berdasarkan id yang diizinkan.
        $this->assertStringContainsString(
            "AttendanceWorkLocation::whereIn('id', \$allowedIds)",
            $src
        );
        // Wajib di dalam geomap -> tolak kalau di luar.
        $this->assertStringContainsString('$requireGeomap', $src);
        $this->assertStringContainsString("'geomap_failed' => true", $src);
    }

    public function test_canteen_transaction_is_fail_closed_when_whitelist_empty()
    {
        $src = $this->source('app/Http/Controllers/CanteenTransactionController.php');

        // Daftar kosong + require_geomap aktif harus DITOLAK, bukan lolos.
        $this->assertMatchesRegularExpression(
            '/empty\(\$allowedIds\).*?inside.*?=>.*?false/s',
            $src,
            'Daftar geomap kosong harus menghasilkan inside=false (fail-closed).'
        );
    }

    public function test_canteen_transaction_requires_gps_when_geomap_mandatory()
    {
        $src = $this->source('app/Http/Controllers/CanteenTransactionController.php');

        // Tanpa koordinat -> tolak (tidak boleh fallback diam-diam).
        $this->assertStringContainsString(
            "'message' => 'Lokasi GPS wajib diizinkan untuk transaksi kantin.",
            $src
        );
    }
}
