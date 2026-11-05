<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\EmployeeActiveStatusService;
use App\Models\EmployeeFace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\WithoutMiddleware;

/**
 * TES PERBAIKAN V31.8 - CEK STATUS KARYAWAN (PKWT.TKK)
 * ==========================================================
 *
 * Aturan bisnis yang diuji:
 *   - PKWT.TKK IS NULL     -> karyawan MASIH AKTIF  -> boleh lanjut.
 *   - PKWT.TKK IS NOT NULL -> karyawan SUDAH KELUAR -> DITOLAK.
 *
 * Yang diuji:
 *   1. EmployeeActiveStatusService: mapping TKK -> status aktif.
 *   2. Endpoint self-register step 1: karyawan keluar tidak boleh
 *      lanjut ke step 2 (direspons `inactive_employee`).
 *   3. Endpoint face attendance store: karyawan keluar tidak boleh
 *      tercatat (direspons `inactive_employee`).
 *
 * Keterdependence:
 *   - Data tabel PKWT disuplai lewat subclass service (tidak bergantung
 *     pada SQL Server).
 *   - Tabel `employee_faces` & `face_recognition_settings` dibuat di
 *     SQLite in-memory, sehingga endpoint face attendance bisa diuji
 *     tanpa menyentuh SQL Server sama sekali.
 */
class EmployeeTkkActiveStatusTest extends TestCase
{
    use WithoutMiddleware;
    /** @var array<int,array> */
    protected array $pkwtRows = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->pkwtRows = [];

        $this->useSqliteInMemory();

        EmployeeActiveStatusService::flushCache();
        EmployeeActiveStatusService::swap($this->fakeService());

        Log::spy();
    }

    protected function tearDown(): void
    {
        EmployeeActiveStatusService::swap(null);
        EmployeeActiveStatusService::flushCache();
        parent::tearDown();
    }

    /**
     * Arahkan koneksi default ke SQLite in-memory dan buat tabel minimal
     * yang dibutuhkan FaceRecognitionService.
     */
    protected function useSqliteInMemory(): void
    {
        config([
            'database.default'   => 'sqlite_test',
            'database.connections.sqlite_test' => [
                'driver'   => 'sqlite',
                'database' => ':memory:',
                'prefix'   => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        DB::purge('sqlite_test');
        DB::setDefaultConnection('sqlite_test');

        Schema::dropIfExists('employee_faces');
        Schema::dropIfExists('face_recognition_settings');

        Schema::create('employee_faces', function ($table) {
            $table->id();
            $table->string('npk', 50)->nullable();
            $table->string('name', 255)->nullable();
            $table->text('face_img')->nullable();
            $table->text('descriptor')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('registered_by')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->boolean('can_login')->default(true);
            $table->boolean('is_attendance_enabled')->default(true);
            $table->string('location_scope', 50)->default('all');
            $table->text('allowed_location_ids')->nullable();
            $table->timestamps();
        });

        Schema::create('face_recognition_settings', function ($table) {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->float('match_threshold')->default(0.500);
            $table->boolean('is_qr_login_enabled')->default(true);
            $table->boolean('is_qr_payroll_enabled')->default(true);
            $table->boolean('is_qr_thr_enabled')->default(true);
            $table->boolean('is_qr_cuti_enabled')->default(true);
            $table->boolean('is_liveness_enabled')->default(true);
            $table->boolean('liveness_presensi_enabled')->default(true);
            $table->boolean('liveness_login_enabled')->default(true);
            $table->boolean('liveness_slip_enabled')->default(true);
            $table->boolean('liveness_cuti_enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Daftarkan wajah C-00002 dengan descriptor tertentu supaya
     * FaceRecognitionService::match() bisa mencocokkan.
     */
    protected function registerFace(string $npk, array $descriptor): void
    {
        EmployeeFace::query()->create([
            'npk'        => $npk,
            'name'       => 'CHAN MAY GIN',
            'descriptor' => json_encode($descriptor),
            'is_active'  => true,
        ]);
    }

    /**
     * Service tiruan: data PKWT diambil dari array, bukan dari database.
     */
    protected function fakeService(): EmployeeActiveStatusService
    {
        $rows = &$this->pkwtRows;

        return new class($rows) extends EmployeeActiveStatusService {
            private array $rows;

            public function __construct(array &$rows)
            {
                $this->rows = &$rows;
            }

            protected function fetchPkwtRow(string $npk)
            {
                foreach ($this->rows as $r) {
                    if (strtoupper((string) $r['NPK']) === strtoupper($npk)) {
                        return (object) $r;
                    }
                }

                return null;
            }
        };
    }

    /**
     * Set baris PKWT untuk tes.
     */
    protected function setPkwt(array $rows): void
    {
        $this->pkwtRows = $rows;
        EmployeeActiveStatusService::flushCache();
    }

    // ==============================================================
    // 1. SERVICE
    // ==============================================================

    /** @test */
    public function tkk_null_artinya_karyawan_masih_aktif()
    {
        $this->setPkwt([
            ['NPK' => 'C-00001', 'NAMA' => 'ANTHONY CHIN', 'TKK' => null],
        ]);

        $res = EmployeeActiveStatusService::check('C-00001');

        $this->assertTrue($res['found'], 'Karyawan harus ditemukan di PKWT');
        $this->assertTrue($res['active'], 'TKK NULL => karyawan MASIH AKTIF');
        $this->assertNull($res['tkk']);
        $this->assertTrue(EmployeeActiveStatusService::isActive('C-00001'));
    }

    /** @test */
    public function tkk_terisi_artinya_karyawan_sudah_keluar()
    {
        $this->setPkwt([
            ['NPK' => 'C-00002', 'NAMA' => 'CHAN MAY GIN', 'TKK' => '2026-02-02'],
        ]);

        $res = EmployeeActiveStatusService::check('C-00002');

        $this->assertTrue($res['found']);
        $this->assertFalse($res['active'], 'TKK terisi => karyawan SUDAH KELUAR');
        $this->assertSame('2026-02-02', $res['tkk']);
        $this->assertFalse(EmployeeActiveStatusService::isActive('C-00002'));
    }

    /** @test */
    public function npk_tidak_ditemukan_tidak_memblokir_alur()
    {
        $this->setPkwt([]);

        $res = EmployeeActiveStatusService::check('TIDAK-ADA');

        $this->assertFalse($res['found']);
        // Tidak ditemukan = tidak boleh dianggap "bukan karyawan aktif".
        $this->assertTrue($res['active'], 'NPK tidak ditemukan tidak boleh dianggap keluar');
    }

    /** @test */
    public function npk_kosong_ditolak_aman()
    {
        $res = EmployeeActiveStatusService::check('   ');

        $this->assertFalse($res['found']);
        $this->assertFalse($res['active']);
    }

    /** @test */
    public function pesan_alert_menyebut_nama_dan_tanggal_keluar()
    {
        $msg = EmployeeActiveStatusService::inactiveMessage('CHAN MAY GIN', '2026-02-02');

        $this->assertStringContainsString('CHAN MAY GIN', $msg);
        $this->assertStringContainsString('bukan karyawan aktif', strtolower($msg));
        $this->assertStringContainsString('2 Februari 2026', $msg);
    }

    /** @test */
    public function hasil_cek_di_cache_per_npk()
    {
        $this->setPkwt([
            ['NPK' => 'C-00001', 'NAMA' => 'ANTHONY CHIN', 'TKK' => null],
        ]);

        $a = EmployeeActiveStatusService::check('C-00001');
        $b = EmployeeActiveStatusService::check('c-00001'); // huruf kecil

        $this->assertSame($a, $b, 'Cache harus memakai NPK ternormalisasi');
    }

    // ==============================================================
    // 2. SELF REGISTER STEP 1
    // ==============================================================

    /** @test */
    public function self_register_step1_menolak_karyawan_yang_sudah_keluar()
    {
        $this->setPkwt([
            ['NPK' => 'C-00002', 'NAMA' => 'CHAN MAY GIN', 'TKK' => '2026-02-02'],
        ]);

        $response = $this->postJson(route('self-register-face.verify'), [
            'npk'       => 'C-00002',
            'tgl_lahir' => '980512',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status'            => false,
            'inactive_employee' => true,
        ]);
        $this->assertStringContainsString('bukan karyawan aktif', strtolower((string) $response->json('message')));
        // Tidak boleh ada token yang diberikan (tidak boleh lanjut ke step 2).
        $this->assertNull($response->json('token'));
    }

    // ==============================================================
    // 3. FACE ATTENDANCE STORE
    // ==============================================================

    /** @test */
    public function face_attendance_menolak_karyawan_yang_sudah_keluar()
    {
        $this->setPkwt([
            ['NPK' => 'C-00002', 'NAMA' => 'CHAN MAY GIN', 'TKK' => '2026-02-02'],
        ]);

        // Wajah C-00002 terdaftar, descriptor-nya sama persis dengan yang dikirim.
        $descriptor = array_fill(0, 128, 0.5);
        $this->registerFace('C-00002', $descriptor);

        Session::put('face_attendance_npk', 'C-00002');

        $response = $this->postJson(route('face.attendance.store'), [
            'descriptor' => $descriptor,
            'latitude'   => -6.2,
            'longitude'  => 106.8,
            'accuracy'   => 10,
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success'           => false,
            'inactive_employee' => true,
        ]);
        $this->assertStringContainsString('bukan karyawan aktif', strtolower((string) $response->json('message')));
    }
}
