<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sinkronisasi kurs USD -> IDR dari halaman publik resmi Bank Indonesia
 * ("Kurs Transaksi BI"), mengikuti implementasi BankIndonesiaService di exchange_rate_bi_sync:
 *   https://www.bi.go.id/id/statistik/informasi-kurs/transaksi-bi/default.aspx
 *
 * Halaman BI hanya menampilkan rilis TERBARU (bukan historis).
 *  - kurs_jual   = kurs jual BI
 *  - kurs_beli   = kurs beli BI
 *  - kurs_tengah = rata-rata jual & beli
 * Kalau tanggal yang sama sudah ada, datanya di-update (updateOrCreate), sama seperti referensi.
 */
class BiExchangeRateService
{
    public const SOURCE = 'BI';

    public const URL = 'https://www.bi.go.id/id/statistik/informasi-kurs/transaksi-bi/default.aspx';

    protected const MONTHS = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
        'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
        'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
    ];

    protected ?string $lastError = null;

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Ambil rilis terbaru dari BI dan simpan.
     */
    public function syncLatest(): ?ExchangeRate
    {
        $this->lastError = null;

        $rate = $this->fetchUsdRate();

        if (! $rate) {
            return null;
        }

        $model = ExchangeRate::query()
            ->usdToIdr()
            ->whereDate('rate_date', $rate['rate_date']->format('Y-m-d'))
            ->first() ?? new ExchangeRate();

        $model->fill([
            'rate_date'     => $rate['rate_date']->format('Y-m-d'),
            'currency_from' => 'USD',
            'currency_to'   => 'IDR',
            'kurs_jual'     => $rate['sell'],
            'kurs_beli'     => $rate['buy'],
            'kurs_tengah'   => $rate['usd_to_idr'],
            'source'        => self::SOURCE,
            'raw_response'  => [
                'provider'   => 'bank_indonesia',
                'url'        => self::URL,
                'sell'       => $rate['sell'],
                'buy'        => $rate['buy'],
                'date_found' => $rate['date_found'],
            ],
        ]);
        $model->save();

        return $model;
    }

    /**
     * @return array{rate_date:Carbon,usd_to_idr:float,sell:float,buy:float,date_found:bool}|null
     */
    public function fetchUsdRate(?Carbon $date = null): ?array
    {
        $date = $date ?? Carbon::today();

        try {
            $response = Http::withHeaders([
                'User-Agent'      => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept'          => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'id-ID,id;q=0.9,en;q=0.8',
            ])->timeout(30)->get(self::URL);

            if (! $response->successful()) {
                $this->lastError = 'BI membalas HTTP ' . $response->status();
                Log::warning('[BI Kurs] HTTP gagal', [
                    'status' => $response->status(),
                    'body'   => mb_substr($this->toText($response->body()), 0, 800),
                ]);
                return null;
            }

            $html   = $response->body();
            $usdRow = $this->extractUsdRow($html);

            if (! $usdRow) {
                $this->lastError = 'Baris USD tidak ditemukan di halaman BI (struktur halaman berubah?).';
                Log::warning('[BI Kurs] baris USD tidak ditemukan', [
                    'snippet' => mb_substr($this->toText($html), 0, 1500),
                ]);
                return null;
            }

            // kurs tengah = rata-rata jual & beli
            $usdToIdr = round(($usdRow['sell'] + $usdRow['buy']) / 2, 2);

            // Tanggal kurs dari "Update Terakhir"; kalau tidak ketemu, pakai hari ini (seperti referensi)
            $rateDate  = $this->extractUpdateDate($html);
            $dateFound = $rateDate !== null;

            if (! $dateFound) {
                Log::warning('[BI Kurs] "Update Terakhir" tidak ditemukan, pakai tanggal hari ini');
            }

            return [
                'rate_date'  => $rateDate ?? $date,
                'usd_to_idr' => $usdToIdr,
                'sell'       => $usdRow['sell'],
                'buy'        => $usdRow['buy'],
                'date_found' => $dateFound,
            ];
        } catch (Throwable $e) {
            $this->lastError = 'Koneksi ke BI gagal: ' . $e->getMessage();
            Log::error('[BI Kurs] exception', ['msg' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Parse baris USD dari HTML BI.
     * Mengembalikan ['sell' => 17969.40, 'buy' => 17790.60] atau null.
     */
    protected function extractUsdRow(string $html): ?array
    {
        // 1) Parser per <tr>/<td> (sama dengan referensi)
        if (preg_match_all('#<tr[^>]*>(.*?)</tr>#is', $html, $trMatches)) {
            foreach ($trMatches[1] as $trInner) {
                if (! preg_match_all('#<td[^>]*>\s*([^<]+?)\s*</td>#is', $trInner, $tdMatches)) {
                    continue;
                }

                $cells = array_map('trim', $tdMatches[1]);
                if (count($cells) < 4) {
                    continue;
                }

                $currencyCell = null;
                foreach ($cells as $i => $c) {
                    if (strtoupper(trim($c, " \t\n\r\0\x0B.")) === 'USD') {
                        $currencyCell = $i;
                        break;
                    }
                }

                if ($currencyCell === null) {
                    continue;
                }

                // Setelah cell USD, urutan: nilai (unit), jual, beli
                $sell = $this->parseIndonesianNumber($cells[$currencyCell + 2] ?? null);
                $buy  = $this->parseIndonesianNumber($cells[$currencyCell + 3] ?? null);

                if ($sell !== null && $buy !== null) {
                    return ['sell' => $sell, 'buy' => $buy];
                }
            }
        }

        // 2) Cadangan: baca teks halaman -> "USD 1 17.987,49 17.808,51"
        $text = $this->toText($html);

        if (preg_match_all('/\bUSD\s+1\s+([\d.,]+)\s+([\d.,]+)/u', $text, $rows, PREG_SET_ORDER)) {
            foreach ($rows as $row) {
                $sell = $this->parseIndonesianNumber($row[1]);
                $buy  = $this->parseIndonesianNumber($row[2]);

                if ($sell !== null && $buy !== null) {
                    return ['sell' => $sell, 'buy' => $buy];
                }
            }
        }

        return null;
    }

    /**
     * Parse angka format Indonesia "17.969,40" -> 17969.40
     */
    protected function parseIndonesianNumber(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $cleaned = trim($value);

        if ($cleaned === '' || ! preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $cleaned)) {
            return null;
        }

        return (float) str_replace(',', '.', str_replace('.', '', $cleaned));
    }

    /**
     * Tanggal "Update Terakhir", format "15 Oktober 2026".
     */
    protected function extractUpdateDate(string $html): ?Carbon
    {
        $day = $monthName = $year = null;

        // Pola HTML seperti referensi: Update Terakhir</span> ... <span>8 Oktober 2026</span>
        if (preg_match('#Update\s+Terakhir[^<]*</span>[^<]*<span>(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})</span>#i', $html, $m)) {
            [, $day, $monthName, $year] = $m;
        } elseif (preg_match('/Update\s+Terakhir\s+(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/iu', $this->toText($html), $m)) {
            // Cadangan: pola teks biasa
            [, $day, $monthName, $year] = $m;
        }

        if ($day === null) {
            return null;
        }

        $month = self::MONTHS[strtolower($monthName)] ?? null;

        if (! $month) {
            return null;
        }

        try {
            return Carbon::create((int) $year, $month, (int) $day)->startOfDay();
        } catch (Throwable $e) {
            return null;
        }
    }

    protected function toText(string $html): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html);
        $text = preg_replace('/<[^>]+>/', ' ', $html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
    }
}