<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\CanteenSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class V44CanteenSettingGeomapSanitizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        // Meniru tabel canteen_settings setelah migration 2026_11_09_143000
        // (kolom liveness_canteen_enabled / random_challenge_canteen_enabled SUDAH DI-DROP).
        if (!Schema::hasTable('canteen_settings')) {
            Schema::create('canteen_settings', function ($table) {
                $table->id();
                $table->boolean('is_enabled')->default(true);
                $table->boolean('require_geomap')->default(false);
                $table->text('allowed_work_location_ids')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function tearDown(): void
    {
        CanteenSetting::forgetCurrent();
        parent::tearDown();
    }

    protected function source(string $path): string
    {
        return file_get_contents(base_path($path));
    }

    public function test_canteen_setting_fillable_and_casts_exclude_deprecated_columns()
    {
        $setting = new CanteenSetting();

        $fillable = $setting->getFillable();
        $this->assertNotContains('liveness_canteen_enabled', $fillable);
        $this->assertNotContains('is_random_challenge_enabled', $fillable);
        $this->assertNotContains('random_challenge_canteen_enabled', $fillable);
        $this->assertContains('require_geomap', $fillable);
        $this->assertContains('allowed_work_location_ids', $fillable);

        $casts = $setting->getCasts();
        $this->assertArrayNotHasKey('liveness_canteen_enabled', $casts);
        $this->assertArrayNotHasKey('is_random_challenge_enabled', $casts);
        $this->assertArrayNotHasKey('random_challenge_canteen_enabled', $casts);
        $this->assertArrayHasKey('require_geomap', $casts);
        $this->assertArrayHasKey('allowed_work_location_ids', $casts);
    }

    public function test_canteen_setting_sanitize_attributes_removes_deprecated_columns()
    {
        $dirty = [
            'is_enabled' => 1,
            'liveness_canteen_enabled' => 1,
            'is_random_challenge_enabled' => 0,
            'random_challenge_canteen_enabled' => 0,
            'require_geomap' => 1,
            'allowed_work_location_ids' => [8],
        ];

        $clean = CanteenSetting::sanitizeAttributes($dirty);

        $this->assertArrayNotHasKey('liveness_canteen_enabled', $clean);
        $this->assertArrayNotHasKey('is_random_challenge_enabled', $clean);
        $this->assertArrayNotHasKey('random_challenge_canteen_enabled', $clean);
        $this->assertSame(1, $clean['is_enabled']);
        $this->assertSame(1, $clean['require_geomap']);
        $this->assertSame([8], $clean['allowed_work_location_ids']);
    }

    public function test_canteen_setting_current_does_not_set_deprecated_attributes_and_saves_cleanly()
    {
        CanteenSetting::forgetCurrent();
        $setting = CanteenSetting::current();

        $this->assertNotNull($setting);
        $this->assertTrue($setting->exists);
        $this->assertNotNull($setting->id);

        $attributes = $setting->getAttributes();
        $this->assertArrayNotHasKey('liveness_canteen_enabled', $attributes);
        $this->assertArrayNotHasKey('is_random_challenge_enabled', $attributes);
        $this->assertArrayNotHasKey('random_challenge_canteen_enabled', $attributes);

        // Verifikasi row tersimpan nyata di database tanpa error kolom
        $dbRow = DB::table('canteen_settings')->find($setting->id);
        $this->assertNotNull($dbRow);
        $this->assertEquals(1, $dbRow->is_enabled);
    }

    public function test_canteen_setting_mutators_ignore_deprecated_properties()
    {
        $setting = new CanteenSetting();
        $setting->liveness_canteen_enabled = true;
        $setting->is_random_challenge_enabled = true;
        $setting->random_challenge_canteen_enabled = true;

        $attributes = $setting->getAttributes();
        $this->assertArrayNotHasKey('liveness_canteen_enabled', $attributes);
        $this->assertArrayNotHasKey('is_random_challenge_enabled', $attributes);
        $this->assertArrayNotHasKey('random_challenge_canteen_enabled', $attributes);
    }

    public function test_canteen_setting_accessors_return_boolean_values()
    {
        $setting = new CanteenSetting();

        $this->assertIsBool($setting->liveness_canteen_enabled);
        $this->assertIsBool($setting->is_random_challenge_enabled);
        $this->assertIsBool($setting->random_challenge_canteen_enabled);
    }

    public function test_saving_canteen_geomap_setting_succeeds_without_column_error()
    {
        CanteenSetting::forgetCurrent();

        $setting = CanteenSetting::current();
        $setting->require_geomap = true;
        $setting->allowed_work_location_ids = [8, 12];
        $this->assertTrue($setting->save());

        $reloaded = CanteenSetting::find($setting->id);
        $this->assertTrue((bool) $reloaded->require_geomap);
        $this->assertEquals([8, 12], $reloaded->allowed_work_location_ids);
    }

    public function test_controller_geomap_save_safely_binds_existing_record()
    {
        $src = $this->source('app/Http/Controllers/FaceAttendanceSettingController.php');

        $this->assertStringContainsString('CanteenSetting::current()', $src);
        $this->assertStringContainsString('require_geomap', $src);
        $this->assertStringContainsString('allowed_work_location_ids', $src);
        $this->assertStringContainsString('$setting->save()', $src);
    }

    public function test_no_cjk_in_patched_files()
    {
        $files = [
            'app/Models/CanteenSetting.php',
            'app/Http/Controllers/FaceAttendanceSettingController.php',
            'app/Http/Controllers/CanteenTransactionController.php',
            'tests/Feature/V44CanteenSettingGeomapSanitizationTest.php',
        ];

        foreach ($files as $rel) {
            $content = $this->source($rel);
            $this->assertSame(
                0,
                preg_match('/[\x{4e00}-\x{9fa5}\x{3040}-\x{309f}\x{30a0}-\x{30ff}\x{ac00}-\x{d7af}]/u', $content),
                "Berkas {$rel} mengandung karakter CJK."
            );
        }
    }
}
