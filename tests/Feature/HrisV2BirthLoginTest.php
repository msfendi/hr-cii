<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureHrisV2Employee;
use App\Services\EmployeeActiveStatusService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Login karyawan portal v2 TANPA kamera: NPK + tanggal lahir (YYMMDD).
 *
 * Aturan yang diuji:
 *   1. NPK + tanggal lahir cocok (PKWT.TGLLAHIR) -> sesi karyawan dibuat.
 *   2. Tanggal lahir salah -> 422 dengan pesan generik, tanpa sesi.
 *   3. NPK tidak dikenal -> pesan generik yang SAMA (tidak membocorkan NPK).
 *   4. Format bukan 6 digit -> 422 validasi field tgl_lahir.
 *   5. Karyawan sudah keluar (PKWT.TKK terisi) -> 403 walau tanggal benar.
 *   6. Tanggal lahir diambil dari BIODATA.TGL_LAHIR bila PKWT tidak punya.
 *
 * Semua data disediakan lewat SQLite in-memory, tidak menyentuh SQL Server.
 */
class HrisV2BirthLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Arahkan koneksi CII (sekaligus koneksi default aplikasi) ke SQLite.
        config([
            'database.connections.cii' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        DB::purge('cii');
        DB::setDefaultConnection('cii');

        Schema::create('PKWT', function ($table) {
            $table->string('NPK', 20)->primary();
            $table->string('NAMA', 100)->nullable();
            $table->string('TGLLAHIR', 30)->nullable();
            $table->string('TMK', 30)->nullable();
            $table->string('TKK', 30)->nullable();
        });

        Schema::create('BIODATA', function ($table) {
            $table->string('NPK', 20)->primary();
            $table->string('NAMA_KARYAWAN', 100)->nullable();
            $table->string('TGL_LAHIR', 30)->nullable();
        });

        EmployeeActiveStatusService::swap(null);
        EmployeeActiveStatusService::flushCache();
    }

    protected function tearDown(): void
    {
        EmployeeActiveStatusService::swap(null);
        EmployeeActiveStatusService::flushCache();

        parent::tearDown();
    }

    protected function isiPkwt(string $npk = 'C-00827', ?string $tgllahir = '1998-05-12', ?string $tkk = null, string $nama = 'DIMAS GALANG'): void
    {
        DB::table('PKWT')->insert([
            'NPK' => $npk,
            'NAMA' => $nama,
            'TGLLAHIR' => $tgllahir,
            'TMK' => '2020-01-01',
            'TKK' => $tkk,
        ]);
    }

    public function test_tanggal_lahir_cocok_membuat_sesi_karyawan(): void
    {
        $this->isiPkwt();

        $res = $this->postJson(route('v2.employee.login-lahir'), [
            'npk' => 'c-00827',
            'tgl_lahir' => '980512',
        ]);

        $res->assertOk()
            ->assertJson(['success' => true, 'npk' => 'C-00827', 'name' => 'DIMAS GALANG']);

        $this->assertStringEndsWith('/v2/karyawan', $res->json('redirect_url'));
        $this->assertSame('C-00827', session(EnsureHrisV2Employee::SESSION_NPK));
        $this->assertSame('DIMAS GALANG', session(EnsureHrisV2Employee::SESSION_NAME));
        // Bridge subsistem lama ikut terisi supaya tidak login dua kali.
        $this->assertSame('C-00827', session('face_attendance_npk'));
        $this->assertSame('C-00827', session('cuti_employee_npk'));
    }

    public function test_tanggal_lahir_salah_ditolak_tanpa_sesi(): void
    {
        $this->isiPkwt();

        $res = $this->postJson(route('v2.employee.login-lahir'), [
            'npk' => 'C-00827',
            'tgl_lahir' => '980513',
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
        $this->assertStringContainsString('do not match our records', $res->json('message'));
        $this->assertNull(session(EnsureHrisV2Employee::SESSION_NPK));
    }

    public function test_npk_tidak_dikenal_memberi_pesan_generik_yang_sama(): void
    {
        $this->isiPkwt();

        $res = $this->postJson(route('v2.employee.login-lahir'), [
            'npk' => 'C-99999',
            'tgl_lahir' => '980512',
        ]);

        $res->assertStatus(422);
        $this->assertSame(
            'NPK and birth date do not match our records. Check the data on your PKWT and try again.',
            $res->json('message')
        );
        $this->assertNull(session(EnsureHrisV2Employee::SESSION_NPK));
    }

    public function test_format_tanggal_bukan_enam_digit_ditolak_validasi(): void
    {
        $this->isiPkwt();

        foreach (['9805', '98-05-12', 'abcdef', '9805123'] as $format) {
            $res = $this->postJson(route('v2.employee.login-lahir'), [
                'npk' => 'C-00827',
                'tgl_lahir' => $format,
            ]);

            $res->assertStatus(422)->assertJsonValidationErrors('tgl_lahir');
        }

        $this->assertNull(session(EnsureHrisV2Employee::SESSION_NPK));
    }

    public function test_karyawan_sudah_keluar_ditolak(): void
    {
        $this->isiPkwt('C-00827', '1998-05-12', '2026-01-31');

        $res = $this->postJson(route('v2.employee.login-lahir'), [
            'npk' => 'C-00827',
            'tgl_lahir' => '980512',
        ]);

        $res->assertStatus(403)->assertJson(['success' => false]);
        $this->assertStringContainsString('not an active employee', $res->json('message'));
        $this->assertNull(session(EnsureHrisV2Employee::SESSION_NPK));
    }

    public function test_tanggal_lahir_fallback_ke_biodata(): void
    {
        // Baris PKWT ada tetapi TGLLAHIR kosong -> pakai BIODATA.TGL_LAHIR.
        $this->isiPkwt('C-00827', null);
        DB::table('BIODATA')->insert([
            'NPK' => 'C-00827',
            'NAMA_KARYAWAN' => 'DIMAS BIODATA',
            'TGL_LAHIR' => '1998-05-12',
        ]);

        $res = $this->postJson(route('v2.employee.login-lahir'), [
            'npk' => 'C-00827',
            'tgl_lahir' => '980512',
        ]);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertSame('C-00827', session(EnsureHrisV2Employee::SESSION_NPK));
    }

    public function test_sesi_aktif_tidak_perlu_login_ulang(): void
    {
        $res = $this->withSession([EnsureHrisV2Employee::SESSION_NPK => 'C-00827'])
            ->postJson(route('v2.employee.login-lahir'), [
                'npk' => 'C-00827',
                'tgl_lahir' => '980512',
            ]);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertStringEndsWith('/v2/karyawan', $res->json('redirect_url'));
    }

    public function test_percobaan_berulang_dibatasi_throttle(): void
    {
        $this->isiPkwt();

        // 10 percobaan pertama masih diproses (gagal karena tanggal salah).
        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('v2.employee.login-lahir'), [
                'npk' => 'C-00827',
                'tgl_lahir' => '000000',
            ])->assertStatus(422);
        }

        // Percobaan ke-11 ditahan rate limiter rute (throttle:10,1).
        $this->postJson(route('v2.employee.login-lahir'), [
            'npk' => 'C-00827',
            'tgl_lahir' => '980512',
        ])->assertStatus(429);
    }

    public function test_halaman_login_menampilkan_tab_tanggal_lahir(): void
    {
        $html = $this->get(route('v2.employee.login'))->assertOk()->getContent();

        $this->assertStringContainsString('id="tabsLoginKaryawan"', $html);
        $this->assertStringContainsString('data-tab="wajah"', $html);
        $this->assertStringContainsString('data-tab="lahir"', $html);
        $this->assertStringContainsString('id="inputTglLahir"', $html);
        $this->assertStringContainsString('id="btnMasukLahir"', $html);
        $this->assertStringContainsString('YYMMDD', $html);
        // Alamat endpoint login tanggal lahir tersedia untuk JavaScript.
        // @json() meng-escape garis miring, jadi cukup cek nama rutenya.
        $this->assertStringContainsString('login-tanggal-lahir', $html);
    }
}