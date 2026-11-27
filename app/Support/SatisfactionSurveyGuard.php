<?php

namespace App\Support;

use App\Models\SatisfactionSubmissionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Penjaga anti-spam kuesioner kepuasan.
 *
 * KUNCI = ID PERANGKAT (cookie pertama-pihak `satisfaction_device`).
 * TIDAK memakai alamat IP sama sekali.
 *
 * Kenapa bukan IP / MAC:
 *  - MAC address perangkat TIDAK BISA dibaca dari HTTP/PHP. Browser tidak
 *    mengirim MAC, tidak ada header atau API yang menyediakannya, dan PHP
 *    hanya bisa melihat MAC lewat tabel ARP server untuk host yang berada di
 *    segmen jaringan yang sama - tidak andal untuk klien di balik router/proxy.
 *  - Alamat IP juga tidak layak: di belakang proxy alamat yang terlihat server
 *    adalah alamat proxy/server, bukan alamat perangkat karyawan.
 *  - Karena itu identitas perangkat dibuat sendiri oleh aplikasi: token acak
 *    (cookie) yang disimpan di browser. Ini pengganti praktis MAC address.
 *
 * Aturan:
 *  - 1 kiriman berhasil per perangkat per bulan (kalender WIB), dijaga unique index.
 *  - maksimal 3 percobaan kirim per perangkat per 30 hari (rate limiter).
 *
 * Hanya dipakai pada halaman publik /satisfaction-survey. Bila tabel log
 * belum ada (migrasi belum dijalankan), penjaga bersikap "lolos" supaya
 * survei tidak mati; kegagalan dicatat ke log.
 */
class SatisfactionSurveyGuard
{
    public const PESAN_SUDAH = 'Spam Detection: perangkat ini sudah pernah mengisi kuesioner pada bulan ini.';

    public const PESAN_LIMIT = 'Spam Detection: batas 3 kali percobaan pengisian per bulan sudah tercapai. Silakan coba lagi bulan depan.';

    /** Nama atribut request tempat ID perangkat disimpan selama satu request. */
    public const ATTR_DEVICE = 'satisfaction_device_id';

    private static ?bool $tabelSiap = null;

    /* ===================== identitas perangkat ===================== */

    public static function namaCookie(): string
    {
        return (string) config('survey.device_cookie', 'satisfaction_device');
    }

    /**
     * Pastikan request ini punya ID perangkat, lalu kembalikan nilainya.
     *
     * Urutan: atribut request -> cookie -> ID baru (di-queue ke respons
     * sebagai cookie berumur panjang). Dipanggil di awal create() dan store()
     * supaya ID yang dipakai untuk memblokir sama dengan yang dikirim balik.
     */
    public static function pastikanDevice(Request $request): string
    {
        $ada = $request->attributes->get(self::ATTR_DEVICE);
        if (is_string($ada) && $ada !== '') {
            return $ada;
        }

        $dariCookie = self::idPerangkatValid($request->cookie(self::namaCookie()));
        if ($dariCookie !== null) {
            $request->attributes->set(self::ATTR_DEVICE, $dariCookie);

            return $dariCookie;
        }

        $baru = bin2hex(random_bytes(32));
        $request->attributes->set(self::ATTR_DEVICE, $baru);

        try {
            Cookie::queue(Cookie::make(
                self::namaCookie(),
                $baru,
                (int) config('survey.device_cookie_days', 400) * 1440,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax'
            ));
        } catch (Throwable $e) {
            Log::warning('Survey spam guard: gagal menyetel cookie perangkat. ' . $e->getMessage());
        }

        return $baru;
    }

    /** Nilai ID perangkat yang sah (64 hex) atau null. */
    private static function idPerangkatValid($nilai): ?string
    {
        if (!is_string($nilai)) {
            return null;
        }

        return preg_match('/^[0-9a-fA-F]{64}$/', $nilai) === 1 ? strtolower($nilai) : null;
    }

    /**
     * ID perangkat untuk request ini. Tanpa cookie (mis. POST langsung tanpa
     * membuka form, atau bot) dipakai sidik jari lunak dari User-Agent agar
     * nilainya selalu ada.
     */
    public static function deviceId(Request $request): string
    {
        $ada = $request->attributes->get(self::ATTR_DEVICE);
        if (is_string($ada) && $ada !== '') {
            return $ada;
        }

        $cookie = self::idPerangkatValid($request->cookie(self::namaCookie()));
        if ($cookie !== null) {
            return $cookie;
        }

        return 'fingerprint:' . (string) $request->userAgent();
    }

    public static function deviceHashId(string $deviceId): string
    {
        return hash_hmac('sha256', $deviceId, (string) config('survey.pepper'));
    }

    public static function deviceHash(Request $request): string
    {
        return self::deviceHashId(self::deviceId($request));
    }

    /* ===================== periode & tabel ===================== */

    /** Periode kalender WIB, contoh 2026-11. */
    public static function periode(?Carbon $now = null): string
    {
        return ($now ?: Carbon::now('Asia/Jakarta'))->format('Y-m');
    }

    public static function tabelSiap(): bool
    {
        if (self::$tabelSiap === null) {
            try {
                self::$tabelSiap = Schema::hasTable('satisfaction_submission_logs');
            } catch (Throwable $e) {
                Log::warning('Survey spam guard: gagal cek tabel log. ' . $e->getMessage());
                self::$tabelSiap = false;
            }
        }

        return self::$tabelSiap;
    }

    /** Dipakai di tes setelah membuat tabel secara manual. */
    public static function lupakanStatusTabel(): void
    {
        self::$tabelSiap = null;
    }

    public static function logTerakhir(Request $request): ?SatisfactionSubmissionLog
    {
        if (!self::tabelSiap()) {
            return null;
        }

        try {
            return SatisfactionSubmissionLog::query()
                ->where('device_hash', self::deviceHash($request))
                ->where('period_key', self::periode())
                ->orderByDesc('id')
                ->first();
        } catch (Throwable $e) {
            Log::warning('Survey spam guard: gagal baca log. ' . $e->getMessage());

            return null;
        }
    }

    public static function sudahMengisi(Request $request): bool
    {
        return self::logTerakhir($request) !== null;
    }

    /**
     * Catat kiriman yang berhasil. Mengembalikan null bila tabel belum ada
     * atau insert gagal (mis. balapan dua request: unique index menolak).
     *
     * $lemparException = true dipakai di dalam transaksi penyimpanan jawaban:
     * exception unique index dilempar supaya seluruh transaksi batal, bukan
     * diam-diam menyimpan jawaban ganda.
     */
    public static function catat(Request $request, ?string $submissionId, bool $lemparException = false): ?SatisfactionSubmissionLog
    {
        if (!self::tabelSiap()) {
            return null;
        }

        $ua = (string) $request->userAgent();

        try {
            return SatisfactionSubmissionLog::create([
                'device_hash' => self::deviceHash($request),
                'user_agent' => $ua !== '' ? mb_substr($ua, 0, 255) : null,
                'period_key' => self::periode(),
                'submission_id' => $submissionId,
            ]);
        } catch (Throwable $e) {
            $duplikat = $e instanceof \Illuminate\Database\QueryException
                && (str_contains($e->getMessage(), 'uq_survey_log_device_periode')
                    || str_contains($e->getMessage(), 'satisfaction_submission_logs'));

            if ($lemparException && $duplikat) {
                throw $e;
            }

            Log::warning('Survey spam guard: gagal mencatat log. ' . $e->getMessage());

            return null;
        }
    }

    public static function kunciPercobaan(Request $request): string
    {
        return 'survey-fill:' . self::deviceHash($request);
    }

    public static function terlaluBanyakPercobaan(Request $request): bool
    {
        return RateLimiter::tooManyAttempts(
            self::kunciPercobaan($request),
            max(1, (int) config('survey.max_attempts', 3))
        );
    }

    /** Hitung satu percobaan kirim (termasuk yang akhirnya ditolak). */
    public static function hitPercobaan(Request $request): void
    {
        $detik = max(60, (int) config('survey.limit_decay_days', 30) * 86400);

        RateLimiter::hit(self::kunciPercobaan($request), $detik);
    }

    public static function sisaPercobaan(Request $request): int
    {
        $maks = max(1, (int) config('survey.max_attempts', 3));

        return max(0, $maks - RateLimiter::attempts(self::kunciPercobaan($request)));
    }
}