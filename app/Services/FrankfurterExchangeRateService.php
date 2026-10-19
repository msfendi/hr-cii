<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sync histori kurs USD -> IDR dari Frankfurter (data referensi ECB, tanpa API key),
 * mengikuti FrankfurterService di exchange_rate_bi_sync (URL api.frankfurter.app, param from/to).
 *
 * Catatan: ini BUKAN kurs Bank Indonesia. Disimpan dengan source = FRANKFURTER.
 *
 * Aturan:
 *  - Membuat data dari $start sampai hari ini.
 *  - TIDAK menimpa data yang sudah ada (per tanggal USD -> IDR, dari sumber apa pun).
 *  - Tanggal tanpa data (Sabtu/Minggu/hari libur ECB) diisi kurs dari tanggal BERIKUTNYA yang
 *    punya data. Contoh: Minggu 1 Nov 2026 memakai kurs Senin 2 Nov 2026.
 *  - Tanggal di akhir rentang yang belum punya "tanggal berikutnya" dilewati.
 *  - Frankfurter hanya memberi kurs tengah ECB, jadi kurs_tengah = kurs ECB, sedangkan
 *    kurs_jual / kurs_beli = tengah +/- spread (config exchange.frankfurter_spread_pct,
 *    default 0.5%) sebagai perkiraan. Bisa dikoreksi lewat tombol Edit.
 *
 * Config (config/exchange.php):
 *   'frankfurter_spread_pct' => (float) env('FRANKFURTER_SPREAD_PCT', 0.5),
 *   'frankfurter_url'        => env('FRANKFURTER_URL', 'https://api.frankfurter.app'),
 */
class FrankfurterExchangeRateService
{
    public const SOURCE = 'FRANKFURTER';

    /** Request time series dipecah per N hari supaya selalu dapat data harian. */
    protected int $chunkDays = 60;

    protected ?string $lastError = null;

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @return array{from:string,to:string,created:int,skipped_existing:int,skipped_no_data:int}|null
     */
    public function syncRange(Carbon $start, ?Carbon $end = null): ?array
    {
        $this->lastError = null;

        $start = $start->copy()->startOfDay();
        $end   = ($end ?? Carbon::today())->copy()->startOfDay();

        if ($end->gt(Carbon::today())) {
            $end = Carbon::today();
        }

        if ($start->gt($end)) {
            $this->lastError = 'Tanggal mulai tidak boleh lebih besar dari hari ini.';
            return null;
        }

        $fetched = $this->fetchRates($start, $end);

        if ($fetched === null) {
            return null;
        }

        // Isi setiap tanggal kalender dengan kurs tanggal berikutnya yang punya data
        // (iterasi mundur dari $end ke $start).
        $filled = [];
        $next   = null;

        for ($d = $end->copy(); $d->gte($start); $d->subDay()) {
            $key = $d->format('Y-m-d');

            if (isset($fetched[$key])) {
                $next = ['rate' => $fetched[$key], 'source_date' => $key];
            }

            if ($next) {
                $filled[$key] = $next;
            }
        }

        ksort($filled);

        $skippedNoData = $start->diffInDays($end) + 1 - count($filled);

        // Tanggal yang sudah ada (USD -> IDR) tidak ditimpa
        $existing = ExchangeRate::query()
            ->usdToIdr()
            ->whereDate('rate_date', '>=', $start->format('Y-m-d'))
            ->whereDate('rate_date', '<=', $end->format('Y-m-d'))
            ->get(['id', 'rate_date'])
            ->mapWithKeys(fn ($r) => [$r->rate_date->format('Y-m-d') => true])
            ->all();

        $created         = 0;
        $skippedExisting = 0;

        try {
            DB::transaction(function () use ($filled, $existing, &$created, &$skippedExisting) {
                foreach ($filled as $date => $row) {
                    if (isset($existing[$date])) {
                        $skippedExisting++;
                        continue;
                    }

                    $mid    = round($row['rate'], 2);
                    $spread = $this->spreadFromMid($row['rate']);
                    $isFilled = $row['source_date'] !== $date;

                    $note = $isFilled
                        ? "Hari libur/akhir pekan: pakai kurs {$row['source_date']} (ECB via Frankfurter)"
                        : 'Kurs referensi ECB via Frankfurter (bukan kurs BI)';

                    ExchangeRate::create([
                        'rate_date'     => $date,
                        'currency_from' => 'USD',
                        'currency_to'   => 'IDR',
                        'kurs_jual'     => $spread['sell'],
                        'kurs_beli'     => $spread['buy'],
                        'kurs_tengah'   => $mid,
                        'source'        => self::SOURCE,
                        'raw_response'  => [
                            'provider'         => 'frankfurter',
                            'rate'             => $row['rate'],
                            'rate_source_date' => $row['source_date'],
                            'filled_from_next' => $isFilled,
                            'spread_pct'       => (float) config('exchange.frankfurter_spread_pct', 0.5),
                            'note'             => $note . '. Jual/beli = kurs tengah ECB +/- spread (perkiraan, bukan kurs BI)',
                        ],
                    ]);

                    $created++;
                }
            });
        } catch (Throwable $e) {
            $this->lastError = 'Gagal menyimpan data: ' . $e->getMessage();
            Log::error('[Frankfurter Kurs] ' . $this->lastError);
            return null;
        }

        return [
            'from'             => $start->format('Y-m-d'),
            'to'               => $end->format('Y-m-d'),
            'created'          => $created,
            'skipped_existing' => $skippedExisting,
            'skipped_no_data'  => max(0, $skippedNoData),
        ];
    }

    /**
     * Jual/beli perkiraan dari kurs tengah: mid +/- spread.
     *
     * @return array{sell: float, buy: float}
     */
    protected function spreadFromMid(float $mid): array
    {
        $pct = (float) config('exchange.frankfurter_spread_pct', 0.5) / 100;

        return [
            'sell' => round($mid * (1 + $pct), 2),
            'buy'  => round($mid * (1 - $pct), 2),
        ];
    }

    /**
     * @return array<string,float>|null  [ 'Y-m-d' => rate ]  (hanya hari kerja ECB)
     */
    protected function fetchRates(Carbon $start, Carbon $end): ?array
    {
        $base  = rtrim(config('exchange.frankfurter_url', 'https://api.frankfurter.app'), '/');
        $rates = [];

        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $chunkEnd = $cursor->copy()->addDays($this->chunkDays - 1);
            if ($chunkEnd->gt($end)) {
                $chunkEnd = $end->copy();
            }

            $url = "{$base}/{$cursor->format('Y-m-d')}..{$chunkEnd->format('Y-m-d')}";

            try {
                $response = Http::timeout(30)
                    ->get($url, ['from' => 'USD', 'to' => 'IDR']);
            } catch (Throwable $e) {
                $this->lastError = 'Koneksi ke Frankfurter gagal: ' . $e->getMessage();
                Log::error('[Frankfurter Kurs] ' . $this->lastError, ['url' => $url]);
                return null;
            }

            // Rentang yang isinya hanya akhir pekan/libur bisa dibalas 404 -> anggap kosong
            if ($response->status() === 404) {
                $cursor = $chunkEnd->copy()->addDay();
                continue;
            }

            if (! $response->successful()) {
                $this->lastError = 'Frankfurter membalas HTTP ' . $response->status();
                Log::error('[Frankfurter Kurs] ' . $this->lastError, ['url' => $url, 'body' => $response->body()]);
                return null;
            }

            foreach ((array) $response->json('rates', []) as $date => $row) {
                if (isset($row['IDR']) && is_numeric($row['IDR'])) {
                    $rates[$date] = (float) $row['IDR'];
                }
            }

            $cursor = $chunkEnd->copy()->addDay();
        }

        ksort($rates);

        return $rates;
    }
}