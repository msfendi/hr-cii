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
            ['label' => 'DEPARTEMEN', 'nilai' => 'PERSENTASE_TURNOVER', 'satuan' => '%', 'waktu' => false],
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

    public function test_kolom_status_seragam_tidak_dipilih_sebagai_label(): void
    {
        // Kasus screenshot: tabel rekap 5 periode dengan kolom STATUS konstan 'Payroll calculation completed'
        $rows = [
            (object) ['Periode' => 'Juni 2026', 'Jumlah_Karyawan' => 3192, 'Total_Payroll' => 8281972070, 'Status' => 'Payroll calculation completed'],
            (object) ['Periode' => 'Juli 2026', 'Jumlah_Karyawan' => 3334, 'Total_Payroll' => 8698223390, 'Status' => 'Payroll calculation completed'],
            (object) ['Periode' => 'Agustus 2026', 'Jumlah_Karyawan' => 3505, 'Total_Payroll' => 9073286694, 'Status' => 'Payroll calculation completed'],
            (object) ['Periode' => 'September 2026', 'Jumlah_Karyawan' => 3612, 'Total_Payroll' => 9642893966, 'Status' => 'Payroll calculation completed'],
            (object) ['Periode' => 'Oktober 2026', 'Jumlah_Karyawan' => 3730, 'Total_Payroll' => 10212615492, 'Status' => 'Payroll calculation completed'],
        ];

        $hasil = $this->panggil('pilihKolomChart', $rows, 'analisis payroll dari bulan januari sampai desember 2026 tampilkan dalam chart');

        $this->assertNotNull($hasil);
        $this->assertSame('Periode', $hasil['label'], 'Kolom label harus Periode, bukan Status yang nilainya seragam');
        $this->assertSame('Total_Payroll', $hasil['nilai'], 'Kolom nilai harus Total_Payroll');
        $this->assertTrue($hasil['waktu'], 'Periode harus dikenali sebagai kolom deret waktu');
    }

    public function test_urutan_grafik_waktu_adalah_kronologis_bukan_descending_nilai(): void
    {
        // 5 bulan: nilai Oktober tertinggi, tapi grafik waktu harus urut Juni -> Juli -> ... -> Oktober
        $rows = [
            (object) ['Periode' => 'Oktober 2026', 'Total_Payroll' => 10212615492],
            (object) ['Periode' => 'Juni 2026', 'Total_Payroll' => 8281972070],
            (object) ['Periode' => 'September 2026', 'Total_Payroll' => 9642893966],
            (object) ['Periode' => 'Juli 2026', 'Total_Payroll' => 8698223390],
            (object) ['Periode' => 'Agustus 2026', 'Total_Payroll' => 9073286694],
        ];

        // Tes fungsi urutKronologis via reflection
        $labels = ['Oktober 2026', 'Juni 2026', 'September 2026', 'Juli 2026', 'Agustus 2026'];
        usort($labels, fn ($a, $b) => $this->panggil('urutKronologis', $a, $b));

        $this->assertSame(
            ['Juni 2026', 'Juli 2026', 'Agustus 2026', 'September 2026', 'Oktober 2026'],
            $labels,
            'Label waktu harus terurut kronologis dari bulan terawal ke terbaru'
        );
    }

    public function test_grafik_kurang_dari_dua_kategori_tidak_dibuat(): void
    {
        $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        // Jika data hanya 1 baris atau hanya 1 kategori setelah difilter, tidak dibuat grafik
        $rows = [
            (object) ['Status' => 'Payroll calculation completed', 'Total' => 45000000],
        ];

        $png = $this->panggil('renderChartPng', $rows, 'Status', 'Total', 'Rp', 'Judul');
        $this->assertNull($png, 'Grafik dengan kategori < 2 harus return null');
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