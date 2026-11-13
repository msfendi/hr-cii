<?php

namespace App\Services\ApprovalWa;

/**
 * Normalisasi nomor telepon untuk WhatsApp.
 *
 * ══ KONTRAK ══
 *
 * Semua nomor dikembalikan sebagai DIGIT SAJA tanpa "+", supaya bisa
 * dibandingkan dengan JID Baileys ("<digits>@s.whatsapp.net").
 *
 * ══ ATURAN ══
 *
 * 1. Bentuk JID ("60127383232@s.whatsapp.net") — sudah internasional dari
 *    Baileys. Digit dipakai apa adanya.
 *
 * 2. Prefiks "+" — sudah format internasional (E.164). Kode negara
 *    dipertahankan apa adanya:
 *
 *      +60127383232  → 60127383232   (Malaysia)
 *      +84913662215  → 84913662215   (Vietnam)
 *      +94772605624  → 94772605624   (Sri Lanka)
 *
 *    Ini harus cocok persis dengan JID WhatsApp tujuan.
 *
 * 3. Tanpa "+" dan tanpa "@" — ambigu: bisa seluler Indonesia ATAU nomor
 *    luar negeri yang "+"-nya hilang. Selesaikan dengan urutan:
 *
 *    a. Awalan seluler Indonesia (62xx + prefiks operator lokal) → 62xx.
 *    b. Kalau tidak cocok, tapi cocok dengan kode negara lain → dipakai
 *       sebagai internasional apa adanya.
 *    c. Kalau tidak keduanya → DITOLAK (jangan dipaksa jadi Indonesia;
 *       nomor yang salah orang lebih berbahaya daripada tidak terkirim).
 *
 *    Konsekuensi (a) wins: "8512..." → Indonesia, walau 85 = Brazil.
 *    Angka "+" yang hilang memang ambigu dan tidak bisa dipastikan.
 *    Sumber kebenaran tetap "+<kode negara>" di PKWT.
 *
 * 4. Satu field PKWT bisa berisi >1 nomor ("0813.../0852...") — dipakai
 *    yang pertama.
 */
class PhoneFormatter
{
    /**
     * Kode negara → prefiks panggilan (ISO 3166-1 alpha-2).
     * Dipakai hanya untuk kasus "tanpa +" yang tidak cocok pola Indonesia.
     */
    public const COUNTRY_CALLING_CODES = [
        'ID' => '62',   // Indonesia
        'MY' => '60',   // Malaysia
        'SG' => '65',   // Singapore
        'TH' => '66',   // Thailand
        'VN' => '84',   // Vietnam
        'PH' => '63',   // Philippines
        'IN' => '91',   // India
        'PK' => '92',   // Pakistan
        'LK' => '94',   // Sri Lanka
        'MM' => '95',   // Myanmar
        'JP' => '81',   // Japan
        'KR' => '82',   // South Korea
        'CN' => '86',   // China
        'HK' => '852',  // Hong Kong
        'MO' => '853',  // Macau
        'TW' => '886',  // Taiwan
        'KH' => '855',  // Cambodia
        'LA' => '856',  // Laos
        'BN' => '673',  // Brunei
        'AU' => '61',   // Australia
        'NZ' => '64',   // New Zealand
        'AE' => '971',  // United Arab Emirates
        'SA' => '966',  // Saudi Arabia
        'QA' => '974',  // Qatar
        'KW' => '965',  // Kuwait
        'JO' => '962',  // Jordan
        'IL' => '972',  // Israel
        'TR' => '90',   // Turkey
        'US' => '1',    // United States / Canada
        'GB' => '44',   // United Kingdom
        'DE' => '49',   // Germany
        'FR' => '33',   // France
        'NL' => '31',   // Netherlands
        'BE' => '32',   // Belgium
        'ES' => '34',   // Spain
        'IT' => '39',   // Italy
        'SE' => '46',   // Sweden
        'NO' => '47',   // Norway
        'DK' => '45',   // Denmark
        'FI' => '358',  // Finland
        'PL' => '48',   // Poland
        'CH' => '41',   // Switzerland
        'AT' => '43',   // Austria
        'PT' => '351',  // Portugal
        'IE' => '353',  // Ireland
        'RO' => '40',   // Romania
        'BR' => '55',   // Brazil
        'AR' => '54',   // Argentina
        'MX' => '52',   // Mexico
        'ZA' => '27',   // South Africa
        'EG' => '20',   // Egypt
    ];

    /**
     * Prefiks operator seluler Indonesia (2 digit setelah angka 8).
     *
     * Dipakai untuk membedakan "8512..." (Indonesia) dari angka luar
     * negeri yang kebetulan diawali "85" (Brasil), "84" (Vietnam), dll.
     */
    public const ID_MOBILE_PREFIXES = [
        '11', '12', '13', '14', '15', '16', '17', '18', '19',
        '21', '22', '23', '24', '25', '26', '27', '28', '29',
        '31', '32', '33', '38', '39',
        '51', '52', '53', '56', '57', '58', '59',
        '61', '62', '63', '65', '66', '67', '68', '69',
        '71', '72', '77', '78', '79',
        '81', '82', '83', '85', '87', '88', '89',
        '91', '92', '93', '95', '96', '97', '98', '99',
    ];

    /**
     * Normalisasi nomor telepon ke digit internasional tanpa "+".
     *
     * @param  string|null  $phone  nomor mentah dari PKWT / pengirim WA
     * @return string|null  digit saja, mis. "6281234567890"
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $raw = trim($phone);

        if ($raw === '') {
            return null;
        }

        // ── 1. Bentuk JID WhatsApp ──
        // "60127383232@s.whatsapp.net" → "60127383232"
        // Digit dari Baileys sudah internasional (kode negara ikut).
        if (str_contains($raw, '@')) {
            $digits = preg_replace('/[^0-9]/', '', $raw);

            return self::isValidE164($digits) ? $digits : null;
        }

        // Satu field PKWT kadang berisi >1 nomor ("0813.../0852...").
        if (str_contains($raw, '/')) {
            $parts = array_filter(array_map('trim', explode('/', $raw)));
            if (empty($parts)) {
                return null;
            }
            $raw = $parts[0];
        }

        $hasPlus = str_starts_with($raw, '+');
        $digits  = preg_replace('/[^0-9]/', '', $raw);

        if ($digits === null || $digits === '') {
            return null;
        }

        // ── 2. Prefiks "+" → internasional eksplisit, pertahankan ──
        if ($hasPlus) {
            return self::isValidE164($digits) ? $digits : null;
        }

        // ── 3. Sudah "62..." → Indonesia ──
        if (str_starts_with($digits, '62')) {
            return self::isValidE164($digits) ? $digits : null;
        }

        // ── 4. Awalan 0 → seluler Indonesia lokal ──
        if (str_starts_with($digits, '0')) {
            $local = '62' . ltrim($digits, '0');

            return self::isValidIndonesian($local) ? $local : null;
        }

        // ── 5. Awalan 8 → cek pola seluler Indonesia dulu ──
        if (str_starts_with($digits, '8')) {
            $local = '62' . $digits;

            if (self::looksLikeIndonesianMobile($digits)) {
                return self::isValidIndonesian($local) ? $local : null;
            }

            // Bukan pola Indonesia → mungkin luar negeri tanpa "+".
            if (self::isValidE164($digits) && self::countryFor($digits) !== null) {
                return $digits;
            }

            // Ambigu: bisa Indonesia atau negara lain. Tolak — nomor yang
            // salah orang lebih berbahaya daripada tidak terkirim.
            return null;
        }
        // ── 6. Awalan 6 → bisa negara lain tanpa "+" (mis. Malaysia 60…) ──
        if (str_starts_with($digits, '6')) {
            if (self::isValidE164($digits) && self::countryFor($digits) !== null) {
                return $digits;
            }
        }

        // ── 7. Kode negara lain tanpa "+" (mis. 94 Sri Lanka, 91 India) ──
        // Aman dipakai apa adanya karena kode negara tersebut tidak pernah
        // jadi awal nomor seluler Indonesia.
        $country = self::countryFor($digits);

        if ($country !== null && $country !== 'ID' && self::isValidE164($digits)) {
            return $digits;
        }

        // Sisanya tidak bisa dipastikan.
        return null;
    }

    /**
     * True kalau digit cocok pola seluler Indonesia (diawali 8 + prefiks
     * operator yang dikenal).
     */
    public static function looksLikeIndonesianMobile(string $digits): bool
    {
        if (!str_starts_with($digits, '8') || strlen($digits) < 10) {
            return false;
        }

        return in_array(substr($digits, 1, 2), self::ID_MOBILE_PREFIXES, true);
    }

    /**
     * Validasi nomor internasional (E.164).
     *
     * 1-3 digit kode negara + nomor lokal, total 8-15 digit, tidak boleh
     * diawali 0.
     */
    public static function isValidE164(?string $normalized): bool
    {
        if (empty($normalized)) {
            return false;
        }

        return (bool) preg_match('/^[1-9][0-9]{7,14}$/', $normalized);
    }

    /**
     * Validasi nomor seluler Indonesia (62xx).
     */
    public static function isValidIndonesian(?string $normalized): bool
    {
        if (empty($normalized)) {
            return false;
        }

        return (bool) preg_match('/^628[0-9]{8,12}$/', $normalized);
    }

    /**
     * Validasi nomor untuk WhatsApp.
     *
     * Menerima SEMUA nomor internasional yang valid — termasuk yang
     * bernegara lain. Nomor seluler Indonesia juga termasuk.
     */
    public static function isValid(?string $normalized): bool
    {
        return self::isValidE164($normalized);
    }

    /**
     * True kalau nomor tersebut berasal dari Indonesia.
     */
    public static function isIndonesian(?string $normalized): bool
    {
        return self::isValidIndonesian($normalized);
    }

    /**
     * Kode negara (ISO 3166-1 alpha-2) dari nomor internasional.
     *
     * Cocoknya pakai prefiks terpanjang dulu supaya "852" (Hong Kong)
     * tidak tertangkap oleh "85" (Brasil).
     */
    public static function countryFor(?string $normalized): ?string
    {
        if (empty($normalized)) {
            return null;
        }

        $codes = self::COUNTRY_CALLING_CODES;

        // Urutkan berdasarkan panjang prefiks descending.
        uksort($codes, fn ($a, $b) => strlen($codes[$b]) <=> strlen($codes[$a]));

        foreach ($codes as $iso => $prefix) {
            if (!str_starts_with($normalized, $prefix)) {
                continue;
            }

            $rest = substr($normalized, strlen($prefix));

            // Kode negara 1 digit (US/CA): sisa minimal 9 digit.
            if (strlen($prefix) === 1 && strlen($rest) < 9) {
                continue;
            }

            return $iso;
        }

        return null;
    }
}
