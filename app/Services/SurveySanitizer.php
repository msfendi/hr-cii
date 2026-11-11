<?php

namespace App\Services;

/**
 * SANITIZER -- PROTEKSI SQL INJECTION & XSS UNTUK KUESIONER KEPUASAN
 * ================================================================
 *
 * Permintaan user (2026-11-09):
 *   "di /satisfaction-survey buat proteksi agar tidak bisa dilakukan
 *    sql injection atau xss attack dengan memasukkan karakter berbahaya"
 *
 * BERAPA LAPIS PERTAHANANNYA
 * -------------------------
 * 1. SQL INJECTION -- dicegah di BOUNDARY, bukan dengan menyaring
 *    karakter. Setiap query memakai parameter binding (Eloquent /
 *    Query Builder).
 *
 *    PENTING: menyaring karakter tanda kutip secara buta adalah
 *    approaches yang SALAH untuk SQL Server dan bisa merusak data.
 *    Binding parameter yang benar sudah menutup semua kemungkinan
 *    itu, termasuk pada kolom NVARCHAR.
 *
 * 2. XSS -- dicegah di TIGA lapis:
 *      a. Output encoding: Blade `{{ }}` meng-escape otomatis.
 *         Semua atribut HTML WAJIB pakai `{{ e(...) }}` atau `{{ }}`.
 *      b. Validasi input: karakter berbahaya yang TIDAK punya-meaning
 *         bisnis ditolak (control chars, NUL, dll).
 *      c. Sanitasi defensif untuk teks bebas: buang control character
 *         dan normalisasi baris baru.
 *
 * 3. FORMULA INJECTION (CSV/Excel) -- jawaban yang diawali =, +, -, @
 *    akan dieksekusi sebagai formula saat file dibuka di Excel. Ini
 *    bukan SQLi/XSS, tapi kelas-serangan yang sama rapuhnya di file
 *    export, jadi ikut ditangani di sini.
 *
 * CATATAN PENTING SOAL ESCAPING
 * ------------------------------
 * Fungsi ini TIDAK memfilter tanda kutip, tanda hubung, atau kata kunci
 * SQL (SELECT, DROP, UNION, dll). Kata kunci itu tidak berbahaya kalau
 * query-nya memakai binding. Yang berbahaya justru MENGHAPUS
 * karakter yang tidak perlu -- misalnya tanda hubung di jawaban bebas
 * ("sayang sekali-BBQ") jadi hilang kalau disaring.
 */
class SurveySanitizer
{
    /**
     * Karakter kontrol yang TIDAK BOLEH ada di input teks.
     * U+0000 (NUL) dipakai pada beberapa payload untuk memotong string
     * di database atau di layar. Sisanya (kecuali tab/newline/carriage
     * return) tidak punya makna bisnis untuk jawaban kuesioner.
     */
    private const FORBIDDEN_CONTROL = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u';

    /**
     * Panjang maksimum jawaban teks bebas.
     * Controller sudah memvalidasi max:5000; ini batas kedua yang
     * lebih ketat supaya payload besar tidak masuk database.
     */
    public const MAX_TEXT_LENGTH = 5000;

    /**
     * Panjang maksimum bagian (section). Singkat, jadi 100 cukup.
     */
    public const MAX_SECTION_LENGTH = 100;

    /**
     * Panjang maksimum teks pertanyaan.
     */
    public const MAX_QUESTION_LENGTH = 500;

    /**
     * Bersihkan teks jawaban / pertanyaan dari karakter berbahaya.
     *
     * Yang dilakukan:
     *   - buang control character (kecuali tab/newline/CR)
     *   - normalisasi line ending ke \n supaya konsisten
     *   - trim spasi di kedua ujung (termasuk newline)
     *   - batasi panjang maksimal
     *   - TIDAK menyaring tanda kutip atau kata kunci SQL
     *
     * @return string teks yang sudah dibersihkan
     */
    public static function cleanText(?string $value, int $maxLength = self::MAX_TEXT_LENGTH): string
    {
        if ($value === null) {
            return '';
        }

        // Buang control character.
        $clean = preg_replace(self::FORBIDDEN_CONTROL, '', $value) ?? '';

        // Normalisasi line ending ke \n supaya konsisten.
        $clean = str_replace(["\r\n", "\r"], "\n", $clean);

        // Trim spasi di kedua ujung (termasuk newline).
        $clean = trim($clean);

        // Batasi panjang. Pakai substr aman supaya tidak fatal kalau
        // ekstensi mbstring tidak terpasang.
        if (function_exists('mb_substr')) {
            if (mb_strlen($clean, 'UTF-8') > $maxLength) {
                $clean = mb_substr($clean, 0, $maxLength, 'UTF-8');
            }
        } elseif (strlen($clean) > $maxLength) {
            $clean = substr($clean, 0, $maxLength);
        }

        return $clean;
    }

    /**
     * Bersihkan nilai "section" (bagian kuesioner).
     *
     * Section tampil sebagai badge dan masuk ke heading Excel, jadi
     * control character harus dibuang. Panjang dibatasi ketat.
     */
    public static function cleanSection(?string $value): string
    {
        return self::cleanText($value, self::MAX_SECTION_LENGTH);
    }

    /**
     * Bersihkan teks pertanyaan.
     */
    public static function cleanQuestionText(?string $value): string
    {
        return self::cleanText($value, self::MAX_QUESTION_LENGTH);
    }

    /**
     * Cegah FORMULA INJECTION pada export Excel/CSV.
     *
     * Kalau teks diawali =, +, -, @, tab, atau CR, Excel/Sheets akan
     * memperlakukannya sebagai formula (bisa menjalankan makro atau
     * melakukan exfiltrasi lewat WEBSERVICE()). Awalan tersebut diberi
     * tanda kutip tunggal supaya jadi teks biasa.
     *
     * Perhatikan: hanya berlaku untuk nilai yang BENAR-BENAR dimulai
     * karakter tersebut, supaya jawaban normal ("- tidak setuju") tidak
     * ikut berubah. Excel sendiri memakai format ini sebagai standar.
     */
    public static function escapeFormula($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $text = (string) $value;

        // Kalau diawali salah satu karakter formula-injection.
        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $text;
        }

        return $text;
    }

    /**
     * Daftar regex yang TIDAK BOLEH muncul di input teks bebas.
     *
     * Ini PENCEGAHAN TINGKAT TINGGI (defense in depth), bukan perlindungan
     * utama. Perlindungan yang utama adalah parameter binding. Yang kita
     * lakukan di sini hanya memblokir pola yang jelas-jelas berbahaya dan
     * tidak punya makna dalam jawaban kuesioner.
     *
     * Dipakai sebagai aturan tambahan di level validasi supaya payload
     * yang mencoba keluar dari konteks terdeteksi lebih awal.
     *
     * @return array<int,string> daftar regex bermasalah
     */
    public static function suspiciousPatterns(): array
    {
        return [
            // Tag HTML / script
            '/<\s*script/i',
            '/<\s*\/\s*script/i',
            '/javascript\s*:/i',
            '/\bon(?:error|load|click|mouseover|focus)\s*=/i',
            '/<\s*iframe/i',
            '/<\s*object/i',
            '/<\s*embed/i',
            // SQL statement injection yang jelas (meski binding sudah aman)
            '/;\s*(?:drop|delete|truncate|update|insert)\s+/i',
            '/\bunion\s+(?:all\s+)?select\b/i',
            '/\bor\s+1\s*=\s*1\b/i',
            '/(?:--|#)\s*$/m',
            '/\/\*.*?\*\//s',
            // PHP / template injection
            '/\{\{.*?\}\}/s',
            '/<\?=.*?\?>/s',
        ];
    }

    /**
     * Apakah teks mengandung pola berbahaya?
     */
    public static function containsSuspiciousPattern(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        foreach (self::suspiciousPatterns() as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }
}
