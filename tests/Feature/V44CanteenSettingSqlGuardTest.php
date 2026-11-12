<?php

namespace Tests\Feature;

use App\Models\CanteenSetting;
use App\Models\FaceRecognitionSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * V44 HOTFIX KEDUA (2026-11-11): Bukti nyata bahwa SQL INSERT/UPDATE ke
 * canteen_settings TIDAK lagi mengandung kolom deprecated, meskipun
 * static request-scope cache kolom (FaceRecognitionSetting::$cachedColumnsCache)
 * masih stale (mengira kolom deprecated masih ada).
 *
 * Latar belakang error produksi:
 *   SQLSTATE[42S22] Invalid column name 'liveness_canteen_enabled'
 *   (Connection: cii, SQL: insert into [canteen_settings]
 *   ([is_enabled], [liveness_canteen_enabled], ...) values (1, 1, ...))
 *
 * Penyebab pada kode V44 hotfix pertama: sanitizeAttributes() masih
 * bergantung pada cachedColumns() yang membaca dua lapisan cache; bila
 * keduanya stale, kolom deprecated lolos. Sekarang kolom deprecated SELALU
 * dihapus (fail-closed).
 */
class V44CanteenSettingSqlGuardTest extends TestCase
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

        // Tabel canteen_settings TANPA kolom deprecated (kondisi produksi
        // setelah migration drop kolom).
        Schema::create('canteen_settings', function ($table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('require_geomap')->default(false);
            $table->text('allowed_work_location_ids')->nullable();
            $table->timestamps();
        });

        // Simulasi KEDUA lapisan cache stale: kolom deprecated masih ter-cache
        // meski sudah di-drop di database fisik.
        Cache::put('schema_columns_canteen_settings', [
            'id', 'is_enabled', 'require_geomap', 'allowed_work_location_ids',
            'created_at', 'updated_at',
            'liveness_canteen_enabled',
            'is_random_challenge_enabled',
            'random_challenge_canteen_enabled',
        ], now()->addHour());

        $this->setStaticColumnCache([
            'id', 'is_enabled', 'require_geomap', 'allowed_work_location_ids',
            'created_at', 'updated_at',
            'liveness_canteen_enabled',
            'is_random_challenge_enabled',
            'random_challenge_canteen_enabled',
        ]);
    }

    protected function tearDown(): void
    {
        CanteenSetting::forgetCurrent();
        $this->setStaticColumnCache([]);
        Cache::forget('schema_columns_canteen_settings');
        parent::tearDown();
    }

    /**
     * Set static request-scope cache FaceRecognitionSetting::$cachedColumnsCache
     * via reflection (mensimulasikan cache stale di dalam satu request).
     */
    private function setStaticColumnCache(array $columns): void
    {
        $prop = new \ReflectionProperty(FaceRecognitionSetting::class, 'cachedColumnsCache');
        $prop->setAccessible(true);
        $prop->setValue(null, ['canteen_settings' => $columns]);
    }

    /**
     * Tangkap SEMUA SQL yang dijalankan, kembalikan yang mengandung
     * 'canteen_settings' dan bersifat INSERT/UPDATE.
     */
    private function captureCanteenWrites(callable $fn): array
    {
        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            $sql = strtolower($query->sql);
            if (strpos($sql, 'canteen_settings') !== false
                && (strpos($sql, 'insert') === 0 || strpos($sql, 'update') === 0)) {
                $writes[] = $query->sql;
            }
        });
        $fn();
        return $writes;
    }

    public function test_insert_baru_tidak_mengandung_kolom_deprecated_meski_cache_stale(): void
    {
        $this->assertNotEmpty(Cache::get('schema_columns_canteen_settings', []));

        $writes = $this->captureCanteenWrites(function () {
            $setting = new CanteenSetting();
            $setting->is_enabled = true;
            $setting->require_geomap = false;
            $setting->allowed_work_location_ids = [8];
            // Sengaja set kolom deprecated (meniru request/form lama):
            $setting->setRawAttributes(array_merge($setting->getAttributes(), [
                'liveness_canteen_enabled' => 1,
                'is_random_challenge_enabled' => 0,
                'random_challenge_canteen_enabled' => 0,
            ]));
            $setting->save();
        });

        $this->assertNotEmpty($writes, 'Harus ada SQL INSERT ke canteen_settings.');
        foreach ($writes as $sql) {
            $this->assertStringNotContainsString('liveness_canteen_enabled', strtolower($sql));
            $this->assertStringNotContainsString('is_random_challenge_enabled', strtolower($sql));
            $this->assertStringNotContainsString('random_challenge_canteen_enabled', strtolower($sql));
        }

        // Row tersimpan nyata dan bersih.
        $row = DB::table('canteen_settings')->first();
        $this->assertNotNull($row);
        $this->assertEquals(1, $row->is_enabled);
    }

    public function test_update_geomap_tidak_mengandung_kolom_deprecated_meski_cache_stale(): void
    {
        $existing = CanteenSetting::create([
            'is_enabled' => true,
            'require_geomap' => false,
            'allowed_work_location_ids' => [1],
        ]);
        CanteenSetting::forgetCurrent();

        $writes = $this->captureCanteenWrites(function () use ($existing) {
            $setting = CanteenSetting::find($existing->id);
            $setting->require_geomap = true;
            $setting->allowed_work_location_ids = [8];
            $setting->setRawAttributes(array_merge($setting->getAttributes(), [
                'liveness_canteen_enabled' => 1,
            ]));
            $setting->save();
        });

        $this->assertNotEmpty($writes, 'Harus ada SQL UPDATE ke canteen_settings.');
        foreach ($writes as $sql) {
            $lower = strtolower($sql);
            $this->assertStringNotContainsString('liveness_canteen_enabled', $lower);
            $this->assertStringNotContainsString('is_random_challenge_enabled', $lower);
            $this->assertStringNotContainsString('random_challenge_canteen_enabled', $lower);
        }

        $row = DB::table('canteen_settings')->find($existing->id);
        $this->assertEquals(1, $row->require_geomap);
        $this->assertJsonStringEqualsJsonString(json_encode([8]), $row->allowed_work_location_ids);
    }

    public function test_current_membuat_baris_default_tanpa_kolom_deprecated(): void
    {
        CanteenSetting::forgetCurrent();

        $writes = $this->captureCanteenWrites(function () {
            $setting = CanteenSetting::current();
            $this->assertTrue($setting->exists);
        });

        foreach ($writes as $sql) {
            $lower = strtolower($sql);
            $this->assertStringNotContainsString('liveness_canteen_enabled', $lower);
            $this->assertStringNotContainsString('is_random_challenge_enabled', $lower);
            $this->assertStringNotContainsString('random_challenge_canteen_enabled', $lower);
        }
    }

    public function test_no_cjk_in_modified_files(): void
    {
        $files = [
            app_path('Models/CanteenSetting.php'),
            $this->path('tests/Feature/V44CanteenSettingSqlGuardTest.php'),
        ];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $this->assertSame(
                0,
                preg_match('/[\x{3000}-\x{9fff}\x{3040}-\x{30ff}]/u', $content),
                "File $file mengandung karakter CJK."
            );
        }
    }

    private function path(string $rel): string
    {
        return base_path($rel);
    }
}
