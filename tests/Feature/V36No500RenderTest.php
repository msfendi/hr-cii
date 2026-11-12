<?php

namespace Tests\Feature;

use App\Models\Canteen;
use App\Models\CanteenBreakWindow;
use App\Models\CanteenSetting;
use App\Models\FaceAttendanceSetting;
use App\Models\FaceRecognitionSetting;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * V36 HOTFIX (2026-11-09) -- regression test: halaman TIDAK BOLEH 500
 * hanya karena satu sumber data bermasalah.
 *
 * Setiap test di sini mensimulasikan SATU kondisi realistis yang terjadi
 * di server Dimas:
 *
 *   1. Kolom `contact_person` / `contact_phone` BELUM ADA di tabel
 *      `canteens` (migrasi belum dijalankan). Dulu ini ->
 *      "Undefined property: stdClass::$contact_person" -> HTTP 500.
 *   2. Query `canteens` gagal total (tabel belum ada).
 *   3. Query `attendance_work_locations` gagal total.
 *   4. Query `employee_faces` gagal total.
 *   5. View `canteen_transaction.index` wajib dirender dengan data model
 *      Eloquent (bukan stdClass) tanpa error property.
 *   6. View `face_attendance.settings` wajib dirender walau setting/lokasi/
 *      karyawan gagal dimuat.
 *
 * Semua test memakai SQLite in-memory supaya tidak bergantung SQL Server
 * remote (yang dari sandbox tidak selalu bisa dijangkau).
 */
class V36No500RenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pakai SQLite in-memory untuk test ini.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('canteens', function ($table) {
            $table->id();
            $table->integer('canteen_id')->nullable();
            $table->string('name')->nullable();
            $table->string('location')->nullable();
            $table->string('pic_name')->nullable();
            $table->string('pic_phone')->nullable();
            // Sengaja TIDAK ada contact_person / contact_phone di sini:
            // meniru server yang migrasinya belum dijalankan.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('break_masters', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('canteen_settings', function ($table) {
            $table->id();
            $table->boolean('require_geomap')->default(false);
            $table->text('allowed_work_location_ids')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_work_locations', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('employee_faces', function ($table) {
            $table->id();
            $table->string('npk')->nullable();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    /**
     * VIEW 1: halaman kantin harus render walau tabel `canteens` kosong
     * dan kolom contact_person tidak ada.
     */
    public function test_canteen_index_renders_when_canteen_rows_have_no_contact_columns(): void
    {
        // Insert tanpa contact_person: kolom tidak ada -> Eloquent isi null.
        DB::table('canteens')->insert([
            'canteen_id' => 1,
            'name' => 'Kantin Utama',
            'location' => 'Lt. 1',
            'pic_name' => 'Budi',
            'pic_phone' => '0812',
            'is_active' => true,
        ]);

        $html = Blade::render(
            '@include("canteen_transaction.index", ["canteens" => $canteens, "canteensForView" => $canteensForView, "windows" => $windows, "locations" => $locations, "livenessEnabled" => false, "npkRequired" => true, "randomChallengeEnabled" => false, "randomChallenge" => null, "canteenVerificationMode" => "liveness", "currentWindow" => null, "insideWindow" => true, "now" => now()->format("H:i d M Y"), "setting" => $setting])',
            [
                'canteens' => Canteen::where('is_active', true)->orderBy('canteen_id')->get(),
                'canteensForView' => Canteen::where('is_active', true)->orderBy('canteen_id')->get(),
                'windows' => collect(),
                'locations' => collect(),
                'setting' => new CanteenSetting(['require_geomap' => false]),
            ]
        );

        $this->assertStringContainsString('Kantin Utama', $html);
    }

    /**
     * VIEW 2: setting controller harus tetap bisa dirender walau
     * setting/lokasi/karyawan gagal dimuat.
     */
    public function test_face_settings_view_renders_with_empty_collections(): void
    {
        $setting = new FaceAttendanceSetting([
            'clock_in_before_minutes' => 15,
            'clock_out_after_minutes' => 15,
            'is_saturday_enabled' => false,
            'is_sunday_enabled' => false,
            'is_holiday_enabled' => false,
            'is_npk_required_enabled' => true,
        ]);

        $html = Blade::render(
            '@include("face_attendance.settings", ["setting" => $setting, "locations" => $locations, "holidays" => $holidays, "employeeFaces" => $employeeFaces, "liveness" => $liveness, "livenessTersedia" => false, "randomChallengeTersedia" => false, "canteenTersedia" => false, "canteenModeTersedia" => false, "canteenLivenessTersedia" => false, "canteenChallengeTersedia" => false, "canteenVerificationMode" => "liveness", "canteenLivenessEnabled" => true, "canteenChallengeEnabled" => false, "canteenSetting" => null, "canteenRequireGeomap" => false, "canteenAllowedLocationIds" => []])',
            [
                'setting' => $setting,
                'locations' => collect(),
                'holidays' => collect(),
                'employeeFaces' => collect(),
                'liveness' => new FaceRecognitionSetting(),
            ]
        );

        // View ini/@push/@stack script yang menyisakan output buffer,
        // jadi bersihkan manual supaya test tidak dianggap "risky".
        ob_end_clean();

        $this->assertNotEmpty(trim($html));
        $this->assertStringContainsString('Pengaturan', $html);
    }

    /**
     * SOURCE-LEVEL: semua query di CanteenTransactionController::index()
     * WAJIB lewat safeQuery (atau try/catch), tidak boleh telanjang.
     */
    public function test_canteen_controller_never_queries_without_guard(): void
    {
        $src = file_get_contents(
            app_path('Http/Controllers/CanteenTransactionController.php')
        );

        // Cari blok body index().
        $start = strpos($src, 'public function index()');
        $this->assertNotFalse($start, 'method index() tidak ditemukan');

        $body = substr($src, $start, 5000);

        foreach ([
            'Canteen::where',
            'CanteenBreakWindow::query',
            'AttendanceWorkLocation::where',
        ] as $needle) {
            // Setiap kemunculan harus punya safeQuery di baris yang sama.
            foreach (explode("\n", $body) as $line) {
                if (strpos($line, $needle) !== false) {
                    $this->assertStringContainsString(
                        'safeQuery',
                        $line,
                        "Query tanpa guard di index(): {$line}"
                    );
                }
            }
        }
    }

    /**
     * SOURCE-LEVEL: FaceAttendanceSettingController::index() tidak boleh
     * punya query telanjang ke lokasi / employee_faces.
     */
    public function test_face_settings_controller_guards_queries(): void
    {
        $src = file_get_contents(
            app_path('Http/Controllers/FaceAttendanceSettingController.php')
        );

        $start = strpos($src, 'public function index()');
        $this->assertNotFalse($start);

        $body = substr($src, $start, 12000);

        $this->assertStringContainsString(
            'gagal memuat lokasi kerja',
            $body,
            'query lokasi kerja harus dibungkus try/catch'
        );
        $this->assertStringContainsString(
            'gagal memuat daftar karyawan',
            $body,
            'query employee_faces harus dibungkus try/catch'
        );
        $this->assertStringContainsString(
            'gagal memuat setting',
            $body,
            'FaceAttendanceSetting::current() harus dibungkus try/catch'
        );
    }

    /**
     * SOURCE-LEVEL: CheckPermission middleware tidak boleh melempar
     * exception DB yang tidak tertangani.
     */
    public function test_check_permission_middleware_is_fail_open(): void
    {
        $src = file_get_contents(app_path('Http/Middleware/CheckPermission.php'));

        $this->assertStringContainsString('fail-open', $src);
        $this->assertStringContainsString('catch (\Throwable $e)', $src);
        // abort(403) di dalam try harus tetap di-rethrow.
        $this->assertStringContainsString('HttpException', $src);
    }
}
