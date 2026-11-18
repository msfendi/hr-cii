<?php

namespace Tests\Unit;

use App\Services\AiChatService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Chatbot: pemilihan data untuk grafik.
 *
 * Aturan yang dijaga:
 *  - permintaan chart/grafik dikenali dari bahasa user (Indonesia & Inggris);
 *  - pertanyaan data biasa TIDAK dianggap permintaan chart;
 *  - kolom nilai dipilih yang benar-benar angka dan bukan kolom identitas;
 *  - kolom persentase diprioritaskan sebagai nilai (mis. PERSENTASE_TURNOVER);
 *  - hasil query tanpa pasangan label+angka tidak dipaksa jadi grafik.
 */
class ChatbotChartTest extends TestCase
{
    private AiChatService $svc;
    private ReflectionClass $ref;

    protected function setUp(): void
    {
        parent::setUp();

        // Tidak memakai state instance / container: objek dibuat tanpa
        // konstruktor supaya test tidak butuh koneksi database.
        $this->ref = new ReflectionClass(AiChatService::class);
        $this->svc = $this->ref->newInstanceWithoutConstructor();
    }

    private function panggil(string $metode, ...$argumen)
    {
        $m = $this->ref->getMethod($metode);
        $m->setAccessible(true);

        return $m->invoke($this->svc, ...$argumen);
    }

    /**
     * @dataProvider providerPermintaan
     */
    public function test_deteksi_permintaan_chart(string $pesan, bool $harus): void
    {
        $this->assertSame($harus, $this->panggil('mintaChart', $pesan), $pesan);
    }

    public static function providerPermintaan(): array
    {
        return [
            'bahasa Indonesia' => ['tampilkan dalam chart', true],
            'kata grafik' => ['tolong buat grafik turnover per departemen', true],
            'visualisasi' => ['visualisasikan data ini', true],
            'diagram batang' => ['buat diagram batang absensi', true],
            'pie chart' => ['sajikan sebagai pie chart', true],
            'pertanyaan data biasa' => ['berapa jumlah karyawan aktif per departemen?', false],
            'daftar karyawan' => ['tampilkan 5 karyawan bagian IT', false],
            'sapaan' => ['halo, selamat pagi', false],
        ];
    }

    public function test_kolom_persen_dipilih_sebagai_nilai(): void
    {
        $rows = [
            (object) ['DEPARTEMEN' => 'Factory 1', 'MASUK' => 138, 'KELUAR' => 67, 'PERSENTASE_TURNOVER' => 8.19],
            (object) ['DEPARTEMEN' => 'IRONING', 'MASUK' => 20, 'KELUAR' => 12, 'PERSENTASE_TURNOVER' => 7.06],
        ];

        $this->assertSame(
            ['label' => 'DEPARTEMEN', 'nilai' => 'PERSENTASE_TURNOVER', 'satuan' => '%'],
            $this->panggil('pilihKolomChart', $rows)
        );
    }

    public function test_kolom_identitas_tidak_dijadikan_nilai(): void
    {
        $rows = [
            (object) ['NPK' => 1001, 'DEPARTEMEN' => 'Produksi', 'JUMLAH' => 12],
            (object) ['NPK' => 1002, 'DEPARTEMEN' => 'QC', 'JUMLAH' => 8],
        ];

        $hasil = $this->panggil('pilihKolomChart', $rows);

        $this->assertSame('JUMLAH', $hasil['nilai'], 'NPK adalah identitas, bukan nilai grafik');
        $this->assertSame('DEPARTEMEN', $hasil['label']);
    }

    public function test_tanpa_kolom_angka_tidak_dibuat_grafik(): void
    {
        $rows = [
            (object) ['DEPARTEMEN' => 'Produksi', 'STATUS' => 'Aktif'],
            (object) ['DEPARTEMEN' => 'QC', 'STATUS' => 'Kontrak'],
        ];

        $this->assertNull($this->panggil('pilihKolomChart', $rows));
    }

    public function test_hasil_kosong_tidak_dibuat_grafik(): void
    {
        $this->assertNull($this->panggil('pilihKolomChart', []));
    }

    public function test_judul_grafik_memakai_nama_kolom_bukan_pertanyaan(): void
    {
        $judul = $this->panggil('judulChart', [
            'label' => 'DEPARTEMEN',
            'nilai' => 'PERSENTASE_TURNOVER',
            'satuan' => '%',
        ], 'tampilkan dalam chart');

        $this->assertSame('Persentase Turnover per Departemen', $judul);
    }

    public function test_kolom_tahun_dan_kalender_tidak_dipilih_sebagai_nilai(): void
    {
        $rows = [
            (object) [
                'NAMA_KARYAWAN' => 'Joko Karyanto',
                'TAHUN' => 2026,
                'BULAN' => 2,
                'TANGGAL' => '2026-02-14',
                'JAM_LEMBUR' => 8.0,
                'LEWAT_4_JAM' => 'Ya',
            ],
            (object) [
                'NAMA_KARYAWAN' => 'Eka Zanuar',
                'TAHUN' => 2026,
                'BULAN' => 2,
                'TANGGAL' => '2026-02-14',
                'JAM_LEMBUR' => 7.5,
                'LEWAT_4_JAM' => 'Ya',
            ],
        ];

        $hasil = $this->panggil('pilihKolomChart', $rows, 'tampilkan pelanggaran lembur');
        $this->assertSame('JAM_LEMBUR', $hasil['nilai'], 'Kolom JAM_LEMBUR harus dipilih, bukan TAHUN atau BULAN');
        $this->assertSame('NAMA_KARYAWAN', $hasil['label']);
        $this->assertSame('jam', $hasil['satuan']);
    }

    public function test_agregasi_label_duplikat_dalam_chart(): void
    {
        $rows = [
            (object) ['NAMA_KARYAWAN' => 'Joko', 'JAM_LEMBUR' => 8.0],
            (object) ['NAMA_KARYAWAN' => 'Joko', 'JAM_LEMBUR' => 4.0],
            (object) ['NAMA_KARYAWAN' => 'Budi', 'JAM_LEMBUR' => 5.0],
        ];

        $kolom = $this->panggil('pilihKolomChart', $rows);
        $this->assertSame('JAM_LEMBUR', $kolom['nilai']);
        $this->assertSame('NAMA_KARYAWAN', $kolom['label']);
    }
}