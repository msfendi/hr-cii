<?php

namespace App\Services\ApprovalWa;

use Illuminate\Support\Facades\Log;

/**
 * Throttle pengiriman WhatsApp untuk mengurangi risk ban nomor bot.
 *
 * Strategi:
 *  - Jitter: jeda acak SEBELUM setiap pengiriman (default 15 detik).
 *    Notifikasi approval dikirim bursty dari cron sweep / cascade approve —
 *    pola bursty itu yang paling mudah di-flag WhatsApp. Jittermaking pattern
 *    terlihat lebih natural.
 *  - Rate limit: maksimal N pengiriman per menit (burst guard).
 *  - Tracker: file di storage/framework/cache/approval-wa-throttle.json
 *    berisi timestamp pengiriman terakhir, dipakai untuk enforce gap minimum
 *    antar pesan.
 *
 * Env:
 *   WA_SEND_JITTER_SECONDS=15   (0 = disable jitter, default 15)
 *   WA_SEND_MIN_GAP_SECONDS=15  (gap minimum antar pesan, default = jitter)
 *   WA_SEND_MAX_PER_MINUTE=6    (burst guard, default 6)
 */
class WaSendThrottle
{
    /** @var array<int, float> timestamps of recent sends (unix epoch) */
    private static array $recentSends = [];

    private static ?string $trackerFile = null;

    private static ?float $lastSendAt = null;

    /**
     * Terapkan jitter + rate limit sebelum mengirim pesan.
     *
     * Dipanggil SEBELUM request ke WA bridge supaya jeda terjadi antara
     * pesan sebelumnya dan pesan ini (bukan sesudahnya).
     */
    public static function wait(): void
    {
        $jitter = (int) config('services.wa_gateway.jitter_seconds', 15);
        $gap    = (int) config('services.wa_gateway.min_gap_seconds', $jitter);
        $maxPerMinute = (int) config('services.wa_gateway.max_per_minute', 6);

        $now = microtime(true);

        // ── Rate limit: burst guard per menit ──
        self::loadRecentSends();
        self::$recentSends = array_values(array_filter(
            self::$recentSends,
            fn ($ts) => ($now - $ts) < 60
        ));

        if ($maxPerMinute > 0 && count(self::$recentSends) >= $maxPerMinute) {
            $oldest = min(self::$recentSends);
            $waitFor = (int) ceil((60 - ($now - $oldest))) + 1;
            Log::info("[WaThrottle] burst limit reached ({$maxPerMinute}/min), waiting {$waitFor}s");
            self::sleep($waitFor);
            $now = microtime(true);
            self::$recentSends = array_values(array_filter(
                self::$recentSends,
                fn ($ts) => ($now - $ts) < 60
            ));
        }

        // ── Gap minimum dari pengiriman terakhir ──
        $last = self::$lastSendAt ?? (self::$recentSends ? max(self::$recentSends) : null);
        if ($last !== null) {
            $sinceLast = $now - $last;
            if ($sinceLast < $gap) {
                $waitFor = (int) ceil($gap - $sinceLast);
                Log::info("[WaThrottle] gap guard, waiting {$waitFor}s (since last: " . round($sinceLast, 1) . "s)");
                self::sleep($waitFor);
                $now = microtime(true);
            }
        }

        // ── Jitter acak ──
        if ($jitter > 0) {
            // 70-130% dari nilai base supaya tidak terlalu terpola
            $waitFor = (int) round($jitter * (0.7 + (mt_rand(0, 600) / 1000)));
            Log::info("[WaThrottle] jitter, waiting {$waitFor}s");
            self::sleep($waitFor);
            $now = microtime(true);
        }

        // Catat pengiriman
        self::$recentSends[] = $now;
        self::$lastSendAt   = $now;
        self::saveRecentSends();
    }

    /**
     * Catat bahwa pesan berhasil dikirim (dipanggil setelah request sukses).
     *
     * wait() sudah mencatat timestamp sebelum request, jadi method ini hanya
     * meng-update lastSendAt ke waktu aktual kirim (supaya gap berikutnya dihitung
     * dari waktu kirim, bukan dari waktu mulai throttle).
     */
    public static function markSent(): void
    {
        $now = microtime(true);
        self::loadRecentSends();
        self::$lastSendAt = $now;
        self::saveRecentSends();
    }

    /**
     * Sleep yang aman (toleransi exception).
     */
    private static function sleep(int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        try {
            sleep($seconds);
        } catch (\Throwable $e) {
            Log::warning('[WaThrottle] sleep interrupted: ' . $e->getMessage());
        }
    }

    /**
     * Load tracker file (timestamps + last send).
     */
    private static function loadRecentSends(): void
    {
        if (self::$lastSendAt !== null) {
            return;
        }

        $file = self::trackerFile();

        if (!is_file($file)) {
            return;
        }

        $data = @file_get_contents($file);
        if ($data === false) {
            return;
        }

        $decoded = json_decode($data, true);
        if (!is_array($decoded)) {
            return;
        }

        self::$recentSends = array_map('floatval', $decoded['recent'] ?? []);
        self::$lastSendAt  = isset($decoded['last']) ? (float) $decoded['last'] : null;
    }

    /**
     * Simpan tracker file.
     */
    private static function saveRecentSends(): void
    {
        $file = self::trackerFile();
        $dir  = dirname($file);

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        // Buang timestamp lama (> 10 menit) supaya file tidak membesar
        $now = microtime(true);
        $recent = array_values(array_filter(
            self::$recentSends,
            fn ($ts) => ($now - $ts) < 600
        ));

        @file_put_contents($file, json_encode([
            'recent' => $recent,
            'last'   => self::$lastSendAt,
        ]), LOCK_EX);
    }

    /**
     * Path file tracker.
     */
    private static function trackerFile(): string
    {
        if (self::$trackerFile === null) {
            self::$trackerFile = storage_path('framework/cache/approval-wa-throttle.json');
        }

        return self::$trackerFile;
    }
}
