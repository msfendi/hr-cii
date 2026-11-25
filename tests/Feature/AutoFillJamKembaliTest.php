<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Worker `izin:auto-fill-jam-kembali` — penutup hari 17:00.
 *
 * Diuji dengan koneksi `cii` diarahkan ke SQLite in-memory supaya logika
 * seleksi baris (approved + tanggal acuan + jam_kembali kosong) dan efek
 * samping sinkronisasi ke tabel absensi lama benar-benar dieksekusi, bukan
 * hanya di-mock. Di server nyata `cii` menunjuk ke SQL Server.
 */
class AutoFillJamKembaliTest extends TestCase
{
    private const DATE = '2026-11-25';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'cii');
        config()->set('database.connections.cii', [
            'driver'                  => 'sqlite',
            'database'                => ':memory:',
            'prefix'                  => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('cii');

        $this->createSchema();
    }

    private function createSchema(): void
    {
        Schema::connection('cii')->create('work_assignment_requests', function (Blueprint $t) {
            $t->increments('id');
            $t->string('npk')->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->string('jam_keluar')->nullable();
            $t->string('rencana_kembali')->nullable();
            $t->string('jam_kembali')->nullable();
            $t->string('status')->default('pending');
            $t->string('void')->nullable();
            $t->integer('current_level')->default(1);
            $t->timestamps();
        });

        Schema::connection('cii')->create('ijin_meninggalkan_requests', function (Blueprint $t) {
            $t->increments('id');
            $t->string('token')->nullable();
            $t->string('npk')->nullable();
            $t->date('tanggal')->nullable();
            $t->string('jam_keluar')->nullable();
            $t->string('rencana_kembali')->nullable();
            $t->string('jam_kembali')->nullable();
            $t->text('alasan')->nullable();
            $t->string('status')->default('pending');
            $t->integer('current_level')->default(1);
            $t->text('approval_chain')->nullable();
            $t->text('answers')->nullable();
            $t->text('security_check')->nullable();
            $t->integer('legacy_ijin_id')->nullable();
            $t->string('void')->nullable();
            $t->string('submitted_by')->nullable();
            $t->timestamps();
        });

        Schema::connection('cii')->create('ijin_meninggalkan_pekerjaans', function (Blueprint $t) {
            $t->increments('id');
            $t->string('npk')->nullable();
            $t->date('tanggal')->nullable();
            $t->string('jam_keluar')->nullable();
            $t->string('rencana_kembali')->nullable();
            $t->string('jam_kembali')->nullable();
            $t->string('reason')->nullable();
            $t->integer('id_break')->nullable();
            $t->boolean('is_deduction')->default(false);
            $t->timestamps();
        });

        Schema::connection('cii')->create('BIODATA', function (Blueprint $t) {
            $t->string('NPK')->nullable();
            $t->string('ID_DEPT')->nullable();
            $t->string('NAMA_KARYAWAN')->nullable();
        });

        Schema::connection('cii')->create('dept_breaktimes', function (Blueprint $t) {
            $t->string('id_dept')->nullable();
            $t->integer('id_break')->nullable();
        });

        Schema::connection('cii')->create('PKWT', function (Blueprint $t) {
            $t->string('NPK')->nullable();
            $t->string('NAMA')->nullable();
        });
    }

    private function seedIzinDinas(): void
    {
        $rows = [
            // 1 — approved hari ini (dinas sehari), belum kembali → HARUS diisi
            ['id' => 1, 'npk' => 'C-1', 'start_date' => self::DATE, 'end_date' => self::DATE, 'jam_kembali' => null, 'status' => 'approved', 'void' => 'false'],
            // 2 — sudah kembali oleh security → JANGAN ditimpa
            ['id' => 2, 'npk' => 'C-2', 'start_date' => self::DATE, 'end_date' => self::DATE, 'jam_kembali' => '15:30:00', 'status' => 'approved', 'void' => 'false'],
            // 3 — belum approved → JANGAN diisi
            ['id' => 3, 'npk' => 'C-3', 'start_date' => self::DATE, 'end_date' => self::DATE, 'jam_kembali' => null, 'status' => 'pending', 'void' => 'false'],
            // 4 — approved tapi rentangnya hari lain → JANGAN diisi
            ['id' => 4, 'npk' => 'C-4', 'start_date' => '2026-11-20', 'end_date' => '2026-11-21', 'jam_kembali' => null, 'status' => 'approved', 'void' => 'false'],
            // 5 — approved hari ini tapi sudah void → JANGAN diisi
            ['id' => 5, 'npk' => 'C-5', 'start_date' => self::DATE, 'end_date' => self::DATE, 'jam_kembali' => null, 'status' => 'approved', 'void' => 'true'],
            // 6 — dinas beberapa hari yang mencakup tanggal acuan → HARUS diisi
            ['id' => 6, 'npk' => 'C-6', 'start_date' => '2026-11-24', 'end_date' => '2026-11-26', 'jam_kembali' => null, 'status' => 'approved', 'void' => 'false'],
            // 7 — dinas tanpa tanggal selesai (masih berjalan) → HARUS diisi
            ['id' => 7, 'npk' => 'C-7', 'start_date' => '2026-11-24', 'end_date' => null, 'jam_kembali' => null, 'status' => 'approved', 'void' => 'false'],
        ];

        foreach ($rows as $row) {
            DB::connection('cii')->table('work_assignment_requests')->insert(
                $row + ['jam_keluar' => '08:00:00', 'rencana_kembali' => '12:00:00']
            );
        }
    }

    public function test_izin_dinas_hanya_mengisi_baris_yang_belum_kembali(): void
    {
        $this->seedIzinDinas();

        $this->artisan('izin:auto-fill-jam-kembali', [
            '--doc'  => 'izin-dinas',
            '--date' => self::DATE,
            '--time' => '17:00',
        ])->assertExitCode(0);

        $rows = DB::connection('cii')->table('work_assignment_requests')
            ->orderBy('id')->pluck('jam_kembali', 'id')->all();

        $this->assertSame('17:00:00', $rows[1], 'dinas sehari tanpa jam_kembali harus diisi 17:00');
        $this->assertSame('15:30:00', $rows[2], 'jam_kembali yang sudah ada tidak boleh ditimpa');
        $this->assertNull($rows[3], 'baris belum approved tidak boleh diisi');
        $this->assertNull($rows[4], 'rentang tanggal di luar acuan tidak boleh diisi');
        $this->assertNull($rows[5], 'baris void tidak boleh diisi');
        $this->assertSame('17:00:00', $rows[6], 'dinas multi-hari yang mencakup acuan harus diisi');
        $this->assertSame('17:00:00', $rows[7], 'dinas tanpa end_date (masih berjalan) harus diisi');
    }

    public function test_dry_run_tidak_menyimpan_apa_pun(): void
    {
        $this->seedIzinDinas();

        $this->artisan('izin:auto-fill-jam-kembali', [
            '--doc'     => 'izin-dinas',
            '--date'    => self::DATE,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertNull(
            DB::connection('cii')->table('work_assignment_requests')->where('id', 1)->value('jam_kembali')
        );
    }

    public function test_izin_meninggalkan_diisi_dan_disalin_ke_tabel_absensi_lama(): void
    {
        DB::connection('cii')->table('BIODATA')->insert([
            ['NPK' => 'C-9', 'ID_DEPT' => 'D1', 'NAMA_KARYAWAN' => 'Budi'],
        ]);
        DB::connection('cii')->table('dept_breaktimes')->insert([
            ['id_dept' => 'D1', 'id_break' => 7],
        ]);
        DB::connection('cii')->table('PKWT')->insert([
            ['NPK' => 'C-9', 'NAMA' => 'Budi'],
        ]);

        DB::connection('cii')->table('ijin_meninggalkan_requests')->insert([
            'id'              => 10,
            'token'           => 'tok-10',
            'npk'             => 'C-9',
            'tanggal'         => self::DATE,
            'jam_keluar'      => '09:00:00',
            'rencana_kembali' => '12:00:00',
            'jam_kembali'     => null,
            'alasan'          => 'urusan keluarga',
            'status'          => 'approved',
            'current_level'   => 2,
            'void'            => 'false',
        ]);

        $this->artisan('izin:auto-fill-jam-kembali', [
            '--doc'  => 'izin-meninggalkan',
            '--date' => self::DATE,
            '--time' => '17:00',
        ])->assertExitCode(0);

        $req = DB::connection('cii')->table('ijin_meninggalkan_requests')->where('id', 10)->first();

        $this->assertSame('17:00:00', $req->jam_kembali);
        $this->assertNotNull($req->legacy_ijin_id, 'harus ada salinan ke tabel absensi lama');

        $legacy = DB::connection('cii')->table('ijin_meninggalkan_pekerjaans')->where('id', $req->legacy_ijin_id)->first();

        $this->assertNotNull($legacy, 'baris tabel absensi lama harus dibuat');
        $this->assertSame('C-9', $legacy->npk);
        $this->assertSame('17:00:00', $legacy->jam_kembali);
        $this->assertSame(7, (int) $legacy->id_break);
        $this->assertTrue((bool) $legacy->is_deduction, 'is_deduction selalu true (keputusan bisnis)');
    }

    public function test_opsi_doc_tidak_dikenal_ditolak(): void
    {
        $this->artisan('izin:auto-fill-jam-kembali', ['--doc' => 'ngawur'])
            ->assertExitCode(2);
    }
}
