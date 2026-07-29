<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BiExchangeRateService
{
    /**
     * Endpoint SOAP lama (wsBI.asmx/getSubwsKursBankIndonesia3) sudah MATI.
     *
     * Sumber pengganti: halaman resmi "Kurs Transaksi BI". Halaman ini di-render
     * SharePoint tapi tabel kursnya server-side rendered (bukan hasil JS), jadi
     * masih bisa di-scrape pakai HTTP client biasa (sudah dicek: GET biasa
     * langsung mengembalikan HTML berisi tabel Kurs Jual/Beli semua mata uang).
     *
     * KETERBATASAN PENTING:
     * - Halaman ini hanya menampilkan kurs TERBARU yang sudah dipublikasikan
     *   (biasanya sekali per hari kerja, sekitar pukul 16.15 WIB), BUKAN data
     *   historis per tanggal. Jadi service ini tidak bisa "backfill" tanggal
     *   lampau — ia hanya bisa mengambil rilis terbaru yang sedang tayang.
     * - Untuk membangun histori harian, jadwalkan sync ini jalan tiap hari
     *   kerja sore (lihat SyncExchangeRateCommand + scheduler di README).
     * - Karena ini scraping (bukan API resmi terdokumentasi), struktur HTML
     *   bisa berubah sewaktu-waktu tanpa pemberitahuan dari BI. Selalu cek log
     *   kalau sync tiba-tiba berhenti menghasilkan data.
     */
    protected string $sourceUrl = 'https://www.bi.go.id/id/statistik/informasi-kurs/transaksi-bi/default.aspx';

    protected string $currencyFrom = 'USD';
    protected string $currencyTo = 'IDR';

    protected array $indonesianMonths = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
        'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
        'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
    ];

    /**
     * Ambil rilis kurs USD -> IDR terbaru dari halaman BI, lalu simpan (updateOrCreate
     * berdasarkan tanggal rilisnya) supaya aman dijalankan berkali-kali.
     */
    public function syncLatest(): ?ExchangeRate
    {
        $row = $this->fetchLatest();

        if (! $row) {
            return null;
        }

        return ExchangeRate::updateOrCreate(
            [
                'rate_date'     => $row['rate_date'],
                'currency_from' => $this->currencyFrom,
                'currency_to'   => $this->currencyTo,
            ],
            [
                'kurs_jual'    => $row['kurs_jual'],
                'kurs_beli'    => $row['kurs_beli'],
                'kurs_tengah'  => $row['kurs_tengah'],
                'source'       => 'bi.go.id (kurs-transaksi-bi)',
                'raw_response' => $row['raw'],
            ]
        );
    }

    /**
     * Ambil & parse HTML halaman "Kurs Transaksi BI".
     *
     * @return array{rate_date: string, kurs_jual: float, kurs_beli: float, kurs_tengah: float, raw: array}|null
     */
    protected function fetchLatest(): ?array
    {
        try {
            $response = Http::withHeaders([
                // beberapa situs pemerintah menolak request tanpa User-Agent browser
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                    . '(KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
            ])->timeout(20)->get($this->sourceUrl);

            if (! $response->successful()) {
                Log::warning('Gagal akses halaman Kurs Transaksi BI', ['status' => $response->status()]);
                return null;
            }

            return $this->parseHtml($response->body());
        } catch (Throwable $e) {
            Log::error('Error sync kurs BI: ' . $e->getMessage());
            return null;
        }
    }

    protected function parseHtml(string $html): ?array
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $rateDate = $this->extractUpdateDate($dom->textContent);

        foreach ($xpath->query('//table') as $table) {
            $firstRow = $xpath->query('.//tr', $table)->item(0);

            if (! $firstRow) {
                continue;
            }

            $headerCells = [];
            foreach ($xpath->query('.//th|.//td', $firstRow) as $i => $cell) {
                $headerCells[$i] = mb_strtolower(trim($cell->textContent));
            }

            $jualIndex = $this->findColumnIndex($headerCells, 'kurs jual');
            $beliIndex = $this->findColumnIndex($headerCells, 'kurs beli');

            if ($jualIndex === null || $beliIndex === null) {
                continue; // bukan tabel kurs, skip
            }

            foreach ($xpath->query('.//tr', $table) as $row) {
                $cells = $xpath->query('.//td', $row);

                if ($cells->length === 0) {
                    continue; // baris header
                }

                $currencyCode = strtoupper(trim($cells->item(0)->textContent ?? ''));

                if ($currencyCode !== $this->currencyFrom) {
                    continue;
                }

                $jualText = $cells->item($jualIndex)?->textContent ?? '';
                $beliText = $cells->item($beliIndex)?->textContent ?? '';

                $kursJual = $this->toFloat($jualText);
                $kursBeli = $this->toFloat($beliText);

                if ($kursJual <= 0 || $kursBeli <= 0) {
                    continue;
                }

                return [
                    'rate_date'   => $rateDate,
                    'kurs_jual'   => $kursJual,
                    'kurs_beli'   => $kursBeli,
                    'kurs_tengah' => round(($kursJual + $kursBeli) / 2, 2),
                    'raw'         => [
                        'kurs_jual_text' => trim($jualText),
                        'kurs_beli_text' => trim($beliText),
                        'update_terakhir' => $rateDate,
                    ],
                ];
            }
        }

        Log::warning('Baris kurs USD tidak ditemukan di halaman Kurs Transaksi BI. Kemungkinan struktur HTML berubah.');

        return null;
    }

    /**
     * Cari index kolom berdasarkan potongan teks header (case-insensitive).
     */
    protected function findColumnIndex(array $headerCells, string $needle): ?int
    {
        foreach ($headerCells as $index => $text) {
            if (str_contains($text, $needle)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Format angka Indonesia "17.998,54" -> 17998.54
     */
    protected function toFloat(string $value): float
    {
        $value = trim($value);
        $value = str_replace('.', '', $value);   // hapus pemisah ribuan
        $value = str_replace(',', '.', $value);  // koma jadi titik desimal
        $value = preg_replace('/[^0-9.\-]/', '', $value);

        return (float) $value;
    }

    /**
     * Cari teks "Update Terakhir dd Month yyyy" di halaman dan ubah ke Y-m-d.
     * Kalau tidak ketemu, fallback ke tanggal hari ini.
     */
    protected function extractUpdateDate(string $pageText): string
    {
        if (preg_match('/Update\s+Terakhir\s+(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/iu', $pageText, $m)) {
            $day = (int) $m[1];
            $monthName = mb_strtolower($m[2]);
            $year = (int) $m[3];

            if (isset($this->indonesianMonths[$monthName])) {
                return Carbon::create($year, $this->indonesianMonths[$monthName], $day)->format('Y-m-d');
            }
        }

        return Carbon::today()->format('Y-m-d');
    }
}
