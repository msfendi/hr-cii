<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * V44 HOTFIX (2026-11-11): Uji sanitasi kolom deprecated di canteen_settings
 * supaya tidak memicu SQLSTATE[42S22] Invalid column name.
 *
 * Latar belakang:
 *   - Error produksi: SQLSTATE[42S22] Invalid column name
 *     'liveness_canteen_enabled' saat INSERT ke canteen_settings.
 *   - Akar masalah: cache schema (schema_columns_canteen_settings,
 *     TTL 1 jam) stale -> sanitizeAttributes() mengira kolom deprecated
 *     masih ada padahal sudah di-drop.
 *   - Solusi: bersihkan cache schema sebelum sanitasi/save.
 */
class V44CanteenSettingDeprecatedColsSanitizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pakai SQLite in-memory untuk test ini (cepat, tidak tergantung SQL Server).
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        // Buat tabel canteen_settings TANPA kolom deprecated
        // (simulasi kondisi produksi pasca-migrasi drop kolom).
        Schema::create('canteen_settings', function ($table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('require_geomap')->default(false);
            $table->text('allowed_work_location_ids')->nullable();
            $table->timestamps();
        });

        // Simulasi cache schema stale: kolom deprecated masih ter-cache
        // meski sudah di-drop di database.
        Cache::put('schema_columns_canteen_settings', [
            'id', 'is_enabled', 'require_geomap', 'allowed_work_location_ids',
            'created_at', 'updated_at',
            // Kolom deprecated yang TIDAK ADA di tabel fisik:
            'liveness_canteen_enabled',
            'is_random_challenge_enabled',
            'random_challenge_canteen_enabled',
        ], now()->addHour());
    }

    protected function tearDown(): void
    {
        \App\Models\CanteenSetting::forgetCurrent();
        Cache::forget('schema_columns_canteen_settings');
        parent::tearDown();
    }

    public function test_sanitize_attributes_menghapus_kolom_deprecated_meski_cache_stale(): void
    {
        // Input dengan kolom deprecated (dari form lama / request).
        $dirty = [
            'is_enabled' => true,
            'require_geomap' => true,
            'allowed_work_location_ids' => json_encode([1, 2, 3]),
            // Kolom deprecated yang TIDAK BOLEH disimpan:
            'liveness_canteen_enabled' => true,
            'is_random_challenge_enabled' => false,
            'random_challenge_canteen_enabled' => false,
        ];

        $clean = \App\Models\CanteenSetting::sanitizeAttributes($dirty);

        // Pastikan kolom deprecated sudah dihapus.
        $this->assertArrayNotHasKey('liveness_canteen_enabled', $clean);
        $this->assertArrayNotHasKey('is_random_challenge_enabled', $clean);
        $this->assertArrayNotHasKey('random_challenge_canteen_enabled', $clean);

        // Pastikan kolom valid tetap ada.
        $this->assertArrayHasKey('is_enabled', $clean);
        $this->assertArrayHasKey('require_geomap', $clean);
        $this->assertArrayHasKey('allowed_work_location_ids', $clean);
    }

    public function test_cache_schema_dibersihkan_oleh_sanitize_attributes(): void
    {
        // Pastikan cache stale ada sebelum sanitasi.
        $this->assertNotEmpty(Cache::get('schema_columns_canteen_settings', []));

        // Panggil sanitizeAttributes (akan membersihkan cache).
        $dirty = ['liveness_canteen_enabled' => true];
        \App\Models\CanteenSetting::sanitizeAttributes($dirty);

        // Pastikan cache sudah dibersihkan.
        $this->assertNull(Cache::get('schema_columns_canteen_settings'));
    }

    public function test_save_model_tidak_menyimpan_kolom_deprecated_meski_cache_stale(): void
    {
        // Simulasi cache stale.
        Cache::put('schema_columns_canteen_settings', [
            'id', 'is_enabled', 'require_geomap', 'allowed_work_location_ids',
            'created_at', 'updated_at',
            'liveness_canteen_enabled',
            'is_random_challenge_enabled',
            'random_challenge_canteen_enabled',
        ], now()->addHour());

        // Buat model dengan kolom deprecated.
        $setting = new \App\Models\CanteenSetting();
        $setting->is_enabled = true;
        $setting->require_geomap = true;
        $setting->allowed_work_location_ids = json_encode([1, 2, 3]);
        // Kolom deprecated (TIDAK BOLEH disimpan):
        $setting->liveness_canteen_enabled = true;
        $setting->is_random_challenge_enabled = false;
        $setting->random_challenge_canteen_enabled = false;

        // Simpan model (akan memanggil sanitizeAttributes via saving event).
        $setting->save();

        // Pastikan model tersimpan di database.
        $this->assertDatabaseHas('canteen_settings', [
            'id' => $setting->id,
            'is_enabled' => true,
            'require_geomap' => true,
        ]);

        // Pastikan kolom deprecated TIDAK ADA di database.
        $row = DB::table('canteen_settings')->find($setting->id);
        $this->assertFalse(property_exists($row, 'liveness_canteen_enabled'));
        $this->assertFalse(property_exists($row, 'is_random_challenge_enabled'));
        $this->assertFalse(property_exists($row, 'random_challenge_canteen_enabled'));
    }

    public function test_admin_update_setting_tidak_menyimpan_kolom_deprecated(): void
    {
        // Simulasi cache stale.
        Cache::put('schema_columns_canteen_settings', [
            'id', 'is_enabled', 'require_geomap', 'allowed_work_location_ids',
            'created_at', 'updated_at',
            'liveness_canteen_enabled',
        ], now()->addHour());

        // Buat setting awal.
        $setting = \App\Models\CanteenSetting::create([
            'is_enabled' => true,
            'require_geomap' => false,
            'allowed_work_location_ids' => json_encode([]),
        ]);

        // Panggil adminUpdateSetting dengan request yang mengandung
        // kolom deprecated (simulasi form lama).
        $request = new \Illuminate\Http\Request([
            'is_enabled' => true,
            'require_geomap' => true,
            'allowed_work_location_ids' => [1, 2, 3],
            // Kolom deprecated (TIDAK BOLEH disimpan):
            'liveness_canteen_enabled' => true,
        ]);

        $controller = new \App\Http\Controllers\CanteenTransactionController();
        $controller->adminUpdateSetting($request);

        // Pastikan setting terupdate tanpa kolom deprecated.
        $row = DB::table('canteen_settings')->find($setting->id);
        $this->assertEquals(1, $row->is_enabled);
        $this->assertEquals(1, $row->require_geomap);
        $this->assertJsonStringEqualsJsonString(json_encode([1, 2, 3]), $row->allowed_work_location_ids);

        // Pastikan kolom deprecated TIDAK ADA di database.
        $this->assertFalse(property_exists($row, 'liveness_canteen_enabled'));
    }

    public function test_update_canteen_geomap_tidak_menyimpan_kolom_deprecated(): void
    {
        // Simulasi cache stale.
        Cache::put('schema_columns_canteen_settings', [
            'id', 'is_enabled', 'require_geomap', 'allowed_work_location_ids',
            'created_at', 'updated_at',
            'liveness_canteen_enabled',
        ], now()->addHour());

        // Buat setting awal.
        $setting = \App\Models\CanteenSetting::create([
            'is_enabled' => true,
            'require_geomap' => false,
            'allowed_work_location_ids' => json_encode([]),
        ]);

        // Panggil updateCanteenGeomap dengan request yang mengandung
        // kolom deprecated.
        $request = new \Illuminate\Http\Request([
            'require_geomap' => true,
            'allowed_work_location_ids' => [1, 2, 3],
            // Kolom deprecated (TIDAK BOLEH disimpan):
            'liveness_canteen_enabled' => true,
        ]);

        $controller = new \App\Http\Controllers\FaceAttendanceSettingController();
        $controller->updateCanteenGeomap($request);

        // Pastikan setting terupdate tanpa kolom deprecated.
        $row = DB::table('canteen_settings')->find($setting->id);
        $this->assertEquals(1, $row->require_geomap);
        $this->assertJsonStringEqualsJsonString(json_encode([1, 2, 3]), $row->allowed_work_location_ids);

        // Pastikan kolom deprecated TIDAK ADA di database.
        $this->assertFalse(property_exists($row, 'liveness_canteen_enabled'));
    }

    public function test_no_cjk_in_modified_files(): void
    {
        $files = [
            app_path('Models/CanteenSetting.php'),
            app_path('Http/Controllers/FaceAttendanceSettingController.php'),
            app_path('Http/Controllers/CanteenTransactionController.php'),
            database_path('migrations/2026_11_11_000000_v44_ensure_canteen_settings_deprecated_cols_dropped.php'),
        ];

        $cjkPattern = '/[\x{3000}-\x{9fff}]/u';

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->assertStringNotMatchesFormat($cjkPattern, $content,
                "File $file mengandung karakter CJK.");
        }
    }
}
