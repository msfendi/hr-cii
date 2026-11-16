<?php

namespace App\Models;

use Illuminate\Support\Facades\Log;

/**
 * Card menu Portal PUBLIK HRIS v2 (/v2/publik).
 *
 * Berbeda dari HrisV2Menu (yang punya tabel sendiri `hris_v2_menus`), daftar
 * card publik bersifat tetap: kunci, ikon, dan tujuannya ditentukan kode.
 * Yang bisa diatur admin adalah TAMPIL/SEMBUNYI, disimpan sebagai key/value
 * di tabel `hris_v2_settings` (tanpa perubahan skema):
 *
 *   public_card_careers_visible = 1 | 0
 *   public_card_status_visible  = 1 | 0
 *   public_card_survey_visible  = 1 | 0
 *
 * Belum diisi / nilai tak dikenal = TAMPIL, supaya instalasi lama tidak
 * kehilangan card apa pun.
 */
class HrisV2PublicCard
{
    public const PREFIX_KEY = 'public_card_';
    public const SUFFIX_KEY = '_visible';

    /**
     * Definisi card publik. Urutan di sini = urutan tampil (01, 02, 03).
     * `icon_class` merujuk kelas warna di hris_v2/public_home.blade.php.
     */
    public static function defaults(): array
    {
        return [
            [
                'key'         => 'careers',
                'kicker'      => 'Vacancies',
                'title'       => 'Careers — Job Openings',
                'description' => 'Open positions along with their requirements.',
                'cta'         => 'View openings',
                'route_name'  => 'job-vacancy.public',
                'icon'        => 'fas fa-briefcase',
                'icon_class'  => 'pub-icon-a',
                'is_visible'  => true,
            ],
            [
                'key'         => 'status',
                'kicker'      => 'Application',
                'title'       => 'Check Application Status',
                'description' => 'Track your application (registration number / national ID).',
                'cta'         => 'Track status',
                'route_name'  => 'portal.recruitment-status.index',
                'icon'        => 'fas fa-search',
                'icon_class'  => 'pub-icon-b',
                'is_visible'  => true,
            ],
            [
                'key'         => 'survey',
                'kicker'      => 'Feedback',
                'title'       => 'Satisfaction Survey',
                'description' => 'Fill in the satisfaction survey: 29 scale questions and 6 open questions. No login needed.',
                'cta'         => 'Take the survey',
                'route_name'  => 'satisfaction-survey.create',
                'icon'        => 'fas fa-clipboard-list',
                'icon_class'  => 'pub-icon-c',
                'is_visible'  => true,
            ],
        ];
    }

    /** Semua card (termasuk yang disembunyikan) untuk halaman pengaturan admin. */
    public static function semua(): array
    {
        $out = [];

        foreach (self::defaults() as $card) {
            $card['is_visible'] = self::visible($card['key']);
            $out[] = $card;
        }

        return $out;
    }

    /**
     * Card yang tampil saja. Dipakai /v2/publik.
     * Nomor urut ditulis sebagai `number` (01, 02, ...) agar penomoran card
     * tetap rapat walau ada card yang disembunyikan.
     */
    public static function aktif(): array
    {
        $out = [];
        $nomor = 0;

        foreach (self::defaults() as $card) {
            if (! self::visible($card['key'])) {
                continue;
            }

            $nomor++;
            $card['number'] = str_pad((string) $nomor, 2, '0', STR_PAD_LEFT);
            $card['url'] = self::url($card['route_name']);
            $out[] = $card;
        }

        return $out;
    }

    /** Apakah card ini ditampilkan? Belum ada baris = tampil (perilaku lama). */
    public static function visible(string $key): bool
    {
        $nilai = HrisV2Setting::ambil(self::settingKey($key), null);

        if ($nilai === null || $nilai === '') {
            return true;
        }

        return in_array((string) $nilai, ['1', 'true', 'on', 'yes'], true);
    }

    /** Simpan status tampil card. Mengembalikan true bila tersimpan. */
    public static function setVisible(string $key, bool $visible): bool
    {
        $cocok = false;

        foreach (self::defaults() as $card) {
            if ($card['key'] === $key) {
                $cocok = true;
                break;
            }
        }

        if (! $cocok) {
            return false;
        }

        HrisV2Setting::simpan(self::settingKey($key), $visible ? '1' : '0');

        // Baca ulang: pastikan nilai benar-benar tersimpan (mis. tabel
        // hris_v2_settings belum ada -> simpan() diam-diam gagal).
        $tersimpan = HrisV2Setting::ambil(self::settingKey($key), $visible ? '1' : '0');

        if (in_array((string) $tersimpan, ['1', 'true', 'on', 'yes'], true) !== $visible) {
            Log::warning('HrisV2PublicCard: gagal menyimpan visibilitas card ' . $key);

            return false;
        }

        return true;
    }

    /** URL tujuan card; null bila route belum terdaftar (aman, tidak melempar). */
    public static function url(string $routeName): ?string
    {
        try {
            return \Illuminate\Support\Facades\Route::has($routeName) ? route($routeName) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function settingKey(string $key): string
    {
        return self::PREFIX_KEY . $key . self::SUFFIX_KEY;
    }
}
