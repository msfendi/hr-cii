<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * PERBAIKAN V35 HOTFIX (2026-11-09)
 *
 * Mengunci penyebab 500 di /canteen-transaction:
 *   Controller::index() punya 3 query LANGSUNG ke database yang TIDAK
 *   dilindungi try/catch:
 *     - Canteen::where('is_active', true)->...->get()
 *     - CanteenBreakWindow::query()->orderBy('id')->get()
 *     - AttendanceWorkLocation::where('is_active', true)->...->get()
 *
 *   Kalau salah satu timeout (SQL Server remote HYT00), halaman 500.
 *
 * V35 membungkus ketiganya dengan helper safeQuery() yang fallback ke
 * koleksi kosong + catat ke log.
 */
class V35CanteenIndexNoFatalTest extends TestCase
{
    private function controllerSource(): string
    {
        return File::get(base_path('app/Http/Controllers/CanteenTransactionController.php'));
    }

    public function test_index_uses_safe_query_helper_for_all_three_queries(): void
    {
        $src = $this->controllerSource();

        $this->assertStringContainsString(
            'private function safeQuery(callable $query, string $label)',
            $src,
            'Helper safeQuery() harus ada di controller.'
        );

        // Tiga query utama index() WAJIB lewat safeQuery.
        $this->assertMatchesRegularExpression(
            '/safeQuery\(\s*fn\s*\(\)\s*=>\s*Canteen::where\(/',
            $src,
            'Query daftar kantin harus lewat safeQuery().'
        );
        $this->assertMatchesRegularExpression(
            '/safeQuery\(\s*fn\s*\(\)\s*=>\s*CanteenBreakWindow::query\(/',
            $src,
            'Query break_masters harus lewat safeQuery().'
        );
        $this->assertMatchesRegularExpression(
            '/safeQuery\(\s*fn\s*\(\)\s*=>\s*AttendanceWorkLocation::where\(/',
            $src,
            'Query attendance_work_locations harus lewat safeQuery().'
        );
    }

    public function test_safe_query_catches_throwable_and_logs(): void
    {
        $src = $this->controllerSource();

        $this->assertStringContainsString(
            'catch (\Throwable $e)',
            $src,
            'safeQuery() harus menangkap Throwable.'
        );
        $this->assertStringContainsString(
            "Log::warning('Canteen index: gagal memuat '",
            $src,
            'safeQuery() harus mencatat kegagalan ke log.'
        );
        $this->assertStringContainsString(
            'return collect();',
            $src,
            'safeQuery() harus fallback ke koleksi kosong.'
        );
    }

    public function test_index_still_returns_view_with_all_expected_variables(): void
    {
        $src = $this->controllerSource();

        foreach ([
            'canteens', 'canteensForView', 'windows', 'locations',
            'livenessEnabled', 'npkRequired', 'randomChallengeEnabled',
            'randomChallenge', 'canteenVerificationMode', 'currentWindow',
            'insideWindow', 'now', 'setting',
        ] as $var) {
            $this->assertStringContainsString(
                "'" . $var . "'",
                $src,
                "Variabel view '" . $var . "' harus diteruskan ke view."
            );
        }
    }

    public function test_all_controller_methods_still_present(): void
    {
        $src = $this->controllerSource();

        // Method yang wajib ada (regresi tertangkap di sini kalau ada
        // yang hilang saat refactor).
        foreach ([
            'index', 'validateNpk', 'store', 'latestReceipt',
            'adminIndex', 'adminStoreWindow', 'adminUpdateWindow',
            'adminDeleteWindow', 'adminUpdateSetting',
            'saveToCanteenDb', 'findDuplicateAnyCanteen',
            'resolveDepartmentName', 'sanitizePhotoFace',
            'checkGeomap', 'pointInPolygon',
        ] as $method) {
            $this->assertMatchesRegularExpression(
                '/function\s+' . preg_quote($method, '/') . '\s*\(/',
                $src,
                "Method '" . $method . "' harus ada di controller."
            );
        }
    }

    public function test_flow_karyawan_resign_tkk_masih_ada(): void
    {
        $src = $this->controllerSource();

        // Cek TKK (PKWT.TKK) sebelum kamera -> employee sudah resign.
        $this->assertStringContainsString(
            'EmployeeActiveStatusService::check($npk)',
            $src,
            'Pengecekan status resigned (PKWT.TKK) harus ada di store().'
        );
        $this->assertStringContainsString(
            "'inactive_employee' => true",
            $src,
            'Response 403 dengan flag inactive_employee harus ada.'
        );
    }

    public function test_flow_duplikat_lintas_kantin_masih_ada(): void
        {
            $src = $this->controllerSource();

            $this->assertStringContainsString(
                "'duplicate' => true",
                $src,
                'Response duplikat harus ada.'
            );
            // PERBAIKAN V44 (2026-11-10): cek duplikat sekarang berdasarkan
            // TANGGAL saja, window tidak lagi jadi syarat -> response tidak
            // lagi perlu menyebut window yang bentrok. Test ini hanya
            // memastikan response duplikat masih ada.
            $this->assertStringContainsString(
                "'duplicate_window' =>",
                $src,
                'Response duplikat masih menyertakan field duplicate_window (isi dari DB, bukan $window->name).'
            );
            // Cek melintAS kedua tabel (canteen + canteen_twos).
            $this->assertStringContainsString(
                '\\App\\Models\\CanteenReport::class',
                $src,
                'Cek duplikat harus membaca tabel canteen (Kantin 1).'
            );
            $this->assertStringContainsString(
                '\\App\\Models\\CanteenTwoReport::class',
                $src,
                'Cek duplikat harus membaca tabel canteen_twos (Kantin 2).'
            );
        }

    public function test_geomap_whitelist_masih_dijalankan(): void
    {
        $src = $this->controllerSource();

        $this->assertStringContainsString(
            'CanteenSetting::allowedLocationIds()',
            $src,
            'Geomap harus memakai whitelist allowed_work_location_ids.'
        );
        $this->assertStringContainsString(
            "'geomap_failed' => true",
            $src,
            'Response geomap gagal harus ada.'
        );
    }

    public function test_no_heavy_schema_calls_in_controller(): void
    {
        $src = $this->controllerSource();

        // Schema::getTables() / hasTable() = query berat (join
        // sys.partitions + allocation_units) -> sering timeout -> 500.
        $this->assertDoesNotMatchRegularExpression(
            '/Schema::getTables\s*\(/',
            $src,
            'Controller tidak boleh memanggil Schema::getTables().'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/Schema::hasTable\s*\(/',
            $src,
            'Controller tidak boleh memanggil Schema::hasTable().'
        );
    }

    public function test_no_cjk_characters_in_controller(): void
    {
        $src = $this->controllerSource();

        $this->assertDoesNotMatchRegularExpression(
            '/[\x{3000}-\x{9fff}]/u',
            $src,
            'Controller tidak boleh mengandung karakter CJK.'
        );
    }
}
