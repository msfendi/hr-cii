<?php

namespace Tests\Unit;

use App\Services\AiChatService;
use PHPUnit\Framework\TestCase;

/**
 * Pemilihan kolom grafik (R34).
 *
 * Bug nyata yang dijaga di sini: pertanyaan "gaji" dijawab tabel berisi
 * `salary`, tetapi grafik memplot `contract_ke` sehingga semua batang bernilai
 * 10 — angka itu artinya "kontrak ke-10", bukan gaji. Penyebabnya: skor semua
 * kolom angka seri, lalu kolom yang muncul lebih dulu di hasil query menang.
 */
class ChartColumnSelectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $container = \Illuminate\Container\Container::getInstance();

        if ($container === null || ! $container->bound('config')) {
            $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        }

        config(['cache.default' => 'array']);
    }

    private function layanan(): AiChatService
    {
        return new class extends AiChatService {
            public function __construct() {}

            public function pilih(array $rows, ?string $pesan): ?array
            {
                return $this->pilihKolomChart($rows, $pesan);
            }

            public function judul(array $kolom, string $pesan): string
            {
                return $this->judulChart($kolom, $pesan);
            }

            public function teknis(string $nama): bool
            {
                return $this->kolomPenandaTeknis($nama);
            }
        };
    }

    /** Data persis seperti kasus di layar: contract_ke muncul sebelum salary. */
    private function barisGaji(): array
    {
        return [
            ['NPK' => 'C-00505', 'NAMA_KARYAWAN' => 'AGUS SETIAWAN', 'DEPARTEMEN' => 'PACKING', 'contract_ke' => 2, 'salary' => 2500000],
            ['NPK' => 'C-00936', 'NAMA_KARYAWAN' => 'SISILIA ENING PRIAS MOTE', 'DEPARTEMEN' => 'PACKING', 'contract_ke' => 3, 'salary' => 2500000],
            ['NPK' => 'C-01033', 'NAMA_KARYAWAN' => 'OKTAVIA SUCI RAHMAWATI', 'DEPARTEMEN' => 'PACKING', 'contract_ke' => 4, 'salary' => 2500000],
            ['NPK' => 'C-01563', 'NAMA_KARYAWAN' => 'MUHAMMAD BAYU PRASTOWO', 'DEPARTEMEN' => 'LEADER PACKING', 'contract_ke' => 10, 'salary' => 2500000],
            ['NPK' => 'C-01951', 'NAMA_KARYAWAN' => 'AMANDA NILAM MELANI', 'DEPARTEMEN' => 'LEADER PACKING', 'contract_ke' => 10, 'salary' => 2600000],
        ];
    }

    public function test_pertanyaan_gaji_memilih_kolom_salary_bukan_contract_ke(): void
    {
        $svc = $this->layanan();
        $kolom = $svc->pilih($this->barisGaji(), 'tampilkan gaji karyawan packing dalam bentuk chart');

        $this->assertNotNull($kolom);
        $this->assertSame('salary', $kolom['nilai']);
        $this->assertSame('Rp', $kolom['satuan']);
        $this->assertFalse($kolom['waktu']);
        // Label tetap kolom nama, bukan NPK.
        $this->assertNotSame('npk', mb_strtolower($kolom['label']));
    }

    public function test_judul_grafik_menyebut_gaji_bukan_nama_kolom_teknis(): void
    {
        $svc = $this->layanan();
        $kolom = $svc->pilih($this->barisGaji(), 'gaji karyawan packing chart');
        $judul = $svc->judul($kolom, 'gaji karyawan packing chart');

        $this->assertStringContainsString('Gaji', $judul);
        $this->assertStringNotContainsString('Contract Ke', $judul);
    }

    public function test_kolom_penanda_teknis_dikenali(): void
    {
        $svc = $this->layanan();

        foreach (['contract_ke', 'kontrak_ke', 'id', 'npk_id', 'row_no', 'seq', 'level'] as $nama) {
            $this->assertTrue($svc->teknis($nama), "{$nama} harus dianggap penanda teknis");
        }

        foreach (['salary', 'allowance', 'total_gaji', 'jam_lembur', 'jumlah_hari_kerja'] as $nama) {
            $this->assertFalse($svc->teknis($nama), "{$nama} bukan penanda teknis");
        }
    }

    public function test_tanpa_kolom_metrik_grafik_tidak_dibuat(): void
    {
        $svc = $this->layanan();

        // Hanya ada kolom penanda teknis + nama: jangan memplot apa pun.
        $rows = [
            ['NAMA_KARYAWAN' => 'AGUS SETIAWAN', 'contract_ke' => 10],
            ['NAMA_KARYAWAN' => 'SISILIA ENING PRIAS MOTE', 'contract_ke' => 10],
            ['NAMA_KARYAWAN' => 'OKTAVIA SUCI RAHMAWATI', 'contract_ke' => 9],
        ];

        $this->assertNull($svc->pilih($rows, 'tampilkan data karyawan packing dalam bentuk chart'));
    }

    public function test_pertanyaan_tunjangan_memilih_allowance(): void
    {
        $svc = $this->layanan();

        $rows = $this->barisGaji();
        foreach ($rows as $i => $r) {
            $rows[$i]['allowance'] = 300000 + ($i * 50000);
        }

        $kolom = $svc->pilih($rows, 'berapa tunjangan karyawan packing? buat chart');

        $this->assertNotNull($kolom);
        $this->assertSame('allowance', $kolom['nilai']);
        $this->assertSame('Rp', $kolom['satuan']);
        $this->assertFalse($kolom['waktu']);
    }
}