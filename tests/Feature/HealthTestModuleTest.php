<?php

namespace Tests\Feature;

use App\Http\Controllers\HealthTestController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Uji modul Health Test (index + detail).
 *
 * Latar belakang bug 2026-11-23:
 *   - Daftar menampilkan baris ganda (ID 284 dan 354 masing-masing 3x) karena
 *     join langsung ke `pelamar` + `pelamar_details` mengalikan baris: NIK tidak
 *     unik di `pelamar` dan satu pelamar bisa punya beberapa `pelamar_details`.
 *   - Tombol view membuka modal "Detail Health Test" tanpa data karena
 *     pengisian modal berada di dalam $(document).ready milik DataTable,
 *     sehingga mati bila inisialisasi DataTable melempar galat.
 *
 * Semua data berada di file SQLite sementara; tidak ada koneksi ke CII.
 */
class HealthTestModuleTest extends TestCase
{
    private string $db;

    private string $nik = '3312126108050001';

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = database_path('health_test_module_test.sqlite');
        if (file_exists($this->db)) {
            unlink($this->db);
        }
        touch($this->db);

        config([
            'database.default' => 'cii',
            'database.connections.cii' => [
                'driver'   => 'sqlite',
                'database' => $this->db,
                'prefix'   => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        DB::purge('cii');

        Schema::create('health_tests', function (Blueprint $table) {
            $table->id();
            $table->string('nik')->nullable();
            $table->integer('cacat')->nullable();
            $table->integer('buta_warna')->nullable();
            $table->string('visus_mata')->nullable();
            $table->string('visus_mata_od')->nullable();
            $table->string('visus_mata_os')->nullable();
            $table->integer('tinggi')->nullable();
            $table->integer('berat')->nullable();
            $table->string('abdoment')->nullable();
            $table->string('gigi')->nullable();
            $table->string('cor_pulmo')->nullable();
            $table->string('tht')->nullable();
            $table->string('extreme')->nullable();
            $table->string('tekanan_darah')->nullable();
            $table->string('respirasi')->nullable();
            $table->string('denyut')->nullable();
            $table->string('suhu')->nullable();
            $table->integer('paru')->nullable();
            $table->integer('hepatitis')->nullable();
            $table->integer('jantung')->nullable();
            $table->integer('thypoid')->nullable();
            $table->integer('alergi')->nullable();
            $table->integer('ashma')->nullable();
            $table->text('lain')->nullable();
            $table->integer('kesimpulan')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::create('pelamar', function (Blueprint $table) {
            $table->integer('ID')->primary();
            $table->string('NIK')->nullable();
            $table->string('NAMA')->nullable();
        });

        Schema::create('pelamar_details', function (Blueprint $table) {
            $table->id();
            $table->string('id_pelamar')->nullable();
            $table->string('file_surat_sehat')->nullable();
        });

        $this->seedData();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->db)) {
            unlink($this->db);
        }

        parent::tearDown();
    }

    /** Data tiruan yang sengaja menggandakan pelamar dan pelamar_details. */
    private function seedData(): void
    {
        // Tiga baris pelamar dengan NIK sama (orang sama, tiga kali melamar)
        foreach ([1, 2, 3] as $id) {
            DB::table('pelamar')->insert([
                'ID'   => $id,
                'NIK'  => $this->nik,
                'NAMA' => 'REGINA TITA NUR AURELIA',
            ]);

            DB::table('pelamar_details')->insert([
                'id_pelamar'       => (string) $id,
                'file_surat_sehat' => 'pelamar/surat_sehat/surat_sehat_regina_' . $id . '.pdf',
            ]);
        }

        // Pelamar lain, tanpa surat sehat
        DB::table('pelamar')->insert([
            'ID'   => 9,
            'NIK'  => '3312144104060002',
            'NAMA' => 'REGINA APRILIA PRAMESTHI',
        ]);
        DB::table('pelamar_details')->insert([
            'id_pelamar'       => '9',
            'file_surat_sehat' => null,
        ]);

        // Dua health test untuk NIK yang sama (pemeriksaan berbeda tanggal)
        DB::table('health_tests')->insert([
            'id' => 284, 'nik' => $this->nik, 'cacat' => 0, 'buta_warna' => 0,
            'visus_mata_od' => '1.0', 'visus_mata_os' => '1.0',
            'tinggi' => 160, 'berat' => 55, 'suhu' => '36.5', 'gigi' => 'baik',
            'tekanan_darah' => '120/80', 'respirasi' => '18', 'denyut' => '80',
            'abdoment' => 'normal', 'cor_pulmo' => 'normal', 'tht' => 'normal',
            'extreme' => 'normal', 'paru' => 1, 'hepatitis' => 0, 'jantung' => 0,
            'thypoid' => 0, 'alergi' => 0, 'ashma' => 0,
            'lain' => null, 'kesimpulan' => 1, 'remark' => null,
            'created_at' => '2026-07-29 08:00:00', 'updated_at' => '2026-07-29 08:00:00',
        ]);

        DB::table('health_tests')->insert([
            'id' => 354, 'nik' => $this->nik, 'cacat' => 0, 'buta_warna' => 0,
            'visus_mata_od' => '0.8', 'visus_mata_os' => '0.8',
            'tinggi' => 160, 'berat' => 57, 'suhu' => '37.1', 'gigi' => 'karies',
            'tekanan_darah' => '130/85', 'respirasi' => '20', 'denyut' => '88',
            'abdoment' => 'normal', 'cor_pulmo' => 'normal', 'tht' => 'normal',
            'extreme' => 'normal', 'paru' => 1, 'hepatitis' => 0, 'jantung' => 0,
            'thypoid' => 0, 'alergi' => 0, 'ashma' => 1,
            'lain' => null, 'kesimpulan' => 0, 'remark' => 'Perlu kontrol ulang',
            'created_at' => '2026-08-03 08:00:00', 'updated_at' => '2026-08-03 08:00:00',
        ]);

        // Health test milik pelamar tanpa surat sehat (harus tetap tersaring keluar)
        DB::table('health_tests')->insert([
            'id' => 1578, 'nik' => '3312144104060002', 'cacat' => 0,
            'kesimpulan' => 1, 'created_at' => '2026-10-22 08:00:00',
            'updated_at' => '2026-10-22 08:00:00',
        ]);
    }

    /** Query LAMA (join langsung) sebagai pembanding: membuktikan sumber baris ganda. */
    private function daftarCaraLama()
    {
        return DB::table('health_tests')
            ->join('pelamar', 'pelamar.NIK', '=', 'health_tests.nik')
            ->leftJoin('pelamar_details', 'pelamar_details.id_pelamar', '=', 'pelamar.ID')
            ->select('health_tests.*', 'pelamar.NAMA as nama', 'pelamar_details.file_surat_sehat')
            ->whereNotNull('pelamar_details.file_surat_sehat')
            ->orderByDesc('health_tests.created_at')
            ->get();
    }

    /** Query BARU lewat controller: satu baris health_tests = satu baris tabel. */
    private function daftarCaraBaru()
    {
        $view = app(HealthTestController::class)->index();

        return $view->getData()['data'];
    }

    public function test_cara_lama_menggandakan_setiap_baris_health_test(): void
    {
        $lama = $this->daftarCaraLama();

        // 1 health test x 3 baris pelamar = 3 baris; ini penyebab baris ganda di layar
        $this->assertCount(6, $lama, 'query lama memang menghasilkan baris ganda');

        $id284 = $lama->where('id', 284);
        $id354 = $lama->where('id', 354);

        $this->assertCount(3, $id284, 'ID 284 muncul 3 kali pada query lama');
        $this->assertCount(3, $id354, 'ID 354 muncul 3 kali pada query lama');
    }

    public function test_daftar_baru_tidak_lagi_menggandakan_baris(): void
    {
        $baru = $this->daftarCaraBaru();

        $this->assertCount(2, $baru, 'hanya 2 health test yang punya surat sehat');
        $this->assertCount(1, $baru->where('id', 284), 'ID 284 tampil tepat satu kali');
        $this->assertCount(1, $baru->where('id', 354), 'ID 354 tampil tepat satu kali');
        $this->assertCount(2, $baru->pluck('id')->unique(), 'tidak ada id kembar');
    }

    public function test_urutan_terbaru_di_atas_dan_nama_terisi(): void
    {
        $baru = $this->daftarCaraBaru();

        $this->assertSame(354, (int) $baru[0]->id, 'tanggal terbaru (03 Aug) di urutan pertama');
        $this->assertSame(284, (int) $baru[1]->id);

        $this->assertSame('REGINA TITA NUR AURELIA', $baru[0]->nama);
        $this->assertSame('REGINA TITA NUR AURELIA', $baru[1]->nama);
        $this->assertStringContainsString('surat_sehat_regina', (string) $baru[0]->file_surat_sehat);
    }

    public function test_health_test_tanpa_surat_sehat_tetap_tersaring(): void
    {
        $baru = $this->daftarCaraBaru();

        $this->assertNotContains(1578, $baru->pluck('id')->map(fn ($v) => (int) $v)->all());
    }

    public function test_detail_mengembalikan_data_lengkap(): void
    {
        $res = app(HealthTestController::class)->show(284);

        $this->assertSame(200, $res->getStatusCode());

        $payload = $res->getData(true);

        $this->assertSame(284, $payload['id']);
        $this->assertSame($this->nik, $payload['nik']);
        $this->assertSame('REGINA TITA NUR AURELIA', $payload['nama']);
        $this->assertSame(1, $payload['kesimpulan']);
        $this->assertSame('29 Jul 2026', $payload['tanggal_test']);
        $this->assertSame('1.0', $payload['visus_mata_od']);
        $this->assertSame(160, (int) $payload['tinggi']);
        $this->assertSame(55, (int) $payload['berat']);
        $this->assertSame('120/80', $payload['tekanan_darah']);
        $this->assertStringContainsString('surat_sehat_regina', (string) $payload['file_surat_sehat']);
    }

    public function test_detail_kesimpulan_kurang_sehat_ikut_terbaca(): void
    {
        $payload = app(HealthTestController::class)->show(354)->getData(true);

        $this->assertSame(0, $payload['kesimpulan']);
        $this->assertSame('Perlu kontrol ulang', $payload['remark']);
        $this->assertSame(1, $payload['ashma']);
        $this->assertSame('03 Aug 2026', $payload['tanggal_test']);
    }

    public function test_detail_tidak_ditemukan_mengembalikan_404(): void
    {
        $res = app(HealthTestController::class)->show(999999);

        $this->assertSame(404, $res->getStatusCode());
        $this->assertSame('Data Health Test tidak ditemukan.', $res->getData(true)['message']);
    }

    /** Subquery ber-limit harus dikompilasi sebagai TOP 1 agar sah di SQL Server. */
    public function test_sql_server_mengompilasi_subquery_dengan_top(): void
    {
        config([
            'database.connections.sqlsrv_compile' => [
                'driver' => 'sqlsrv',
                'host' => 'localhost',
                'database' => 'contoh',
                'username' => 'contoh',
                'password' => 'contoh',
                'charset' => 'utf8',
                'prefix' => '',
            ],
        ]);

        try {
            $conn = DB::connection('sqlsrv_compile');

            $sub = $conn->table('pelamar as p')
                ->select('p.NAMA as nama')
                ->whereColumn('p.NIK', 'health_tests.nik')
                ->orderBy('p.ID')
                ->limit(1);

            $sql = $conn->table('health_tests')
                ->select('health_tests.*')
                ->addSelect(['nama' => $sub])
                ->toSql();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Grammar SQL Server tidak tersedia: ' . $e->getMessage());
        }

        $this->assertStringContainsString('top 1', strtolower($sql));
        $this->assertStringNotContainsString('limit 1', strtolower($sql));
    }
}
