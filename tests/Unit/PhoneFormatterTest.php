<?php

namespace Tests\Unit;

use App\Services\ApprovalWa\PhoneFormatter;
use PHPUnit\Framework\TestCase;

/**
 * Normalisasi nomor WhatsApp —Indonesia & luar negeri.
 *
 * Aturan inti:
 *  - "+<kode negara>" → internasional, kode negara dipertahankan.
 *  - JID Baileys → digit 그대로.
 *  - Tanpa "+" → heuristik Indonesia dulu, baru negara lain.
 *  - Ambigu → DITOLAK, bukan dipaksa jadi Indonesia.
 */
class PhoneFormatterTest extends TestCase
{
    /**
     * Nomor dari DB (PKWT.HP) → hasil normalisasi.
     *
     * @dataProvider providerNormalize
     */
    public function test_normalize(string $raw, ?string $expected, string $note): void
    {
        $this->assertSame($expected, PhoneFormatter::normalize($raw), $note);
    }

    public static function providerNormalize(): array
    {
        return [
            // ── Indonesia, format lokal ──
            ['081390207272', '6281390207272', '08xx lokal Indonesia'],
            ['081390207272', '6281390207272', 'ok'],
            ['0813 9020 7272', '6281390207272', 'spasi dibuang'],
            ['+62 819-9826-0665', '6281998260665', '+62 dengan pemisah'],
            ['6281390207272', '6281390207272', 'sudah 62'],

            // ── Luar negeri, DENGAN "+" → dipertahankan ──
            ['+60127383232', '60127383232', 'Malaysia +60'],
            ['+84913662215', '84913662215', 'Vietnam +84'],
            ['+94772605624', '94772605624', 'Sri Lanka +94'],
            ['+6281275827136', '6281275827136', 'Indonesia +62'],

            // ── Luar negeri, TANPA "+" → dipakai apa adanya ──
            ['60127383232', '60127383232', 'Malaysia tanpa +'],
            ['94772605624', '94772605624', 'Sri Lanka tanpa +'],
            ['84913662215', '84913662215', 'Vietnam tanpa + (84 bukan prefiks ID)'],

            // ── JID Baileys ──
            ['60127383232@s.whatsapp.net', '60127383232', 'JID Malaysia'],
            ['6281390207272@s.whatsapp.net', '6281390207272', 'JID Indonesia'],
            ['84913662215@s.whatsapp.net', '84913662215', 'JID Vietnam'],

            // ── Multi-nomor dalam satu field ──
            ['081330555852/085229141066', '6281330555852', 'ambil yang pertama'],

            // ── Tidak valid / kosong ──
            ['', null, 'string kosong'],
            ['   ', null, 'spasi saja'],
            ['-', null, 'tanda hubung saja'],
            ['abc', null, 'bukan angka'],
        ];
    }

    /**
     * Nomor lokal Indonesia yang bisa disalahartikan sebagai negara lain
     * harus tetap jadi Indonesia.
     *
     * @dataProvider providerIndonesiaPriority
     */
    public function test_indonesia_priority(string $raw, string $expected): void
    {
        $this->assertSame($expected, PhoneFormatter::normalize($raw));
    }

    public static function providerIndonesiaPriority(): array
    {
        return [
            // Awalan "85…" → Brasil (55) & Hong Kong (852), tapi Indonesia menang
            // kalau prefiks operatornya cocok.
            ['851234567890', '62851234567890'],
            ['85722395122', '6285722395122'],
            ['85218407138', '6285218407138'],
            ['881234567890', '62881234567890'],
            ['89988776655', '6289988776655'],
            // Operator yang penandanya tidak cocok → dipakai sebagai negara lain
            // (digits tetap utuh, tidak dipaksa jadi 62).
            ['84913662215', '84913662215'],
        ];
    }

    public function test_is_valid_accepts_international(): void
    {
        $this->assertTrue(PhoneFormatter::isValid('60127383232'));
        $this->assertTrue(PhoneFormatter::isValid('84913662215'));
        $this->assertTrue(PhoneFormatter::isValid('94772605624'));
        $this->assertTrue(PhoneFormatter::isValid('6281390207272'));

        $this->assertFalse(PhoneFormatter::isValid(null));
        $this->assertFalse(PhoneFormatter::isValid(''));
        $this->assertFalse(PhoneFormatter::isValid('0812'));
    }

    public function test_is_indonesian(): void
    {
        $this->assertTrue(PhoneFormatter::isIndonesian('6281390207272'));
        $this->assertTrue(PhoneFormatter::isIndonesian('62851234567890'));

        $this->assertFalse(PhoneFormatter::isIndonesian('60127383232'));
        $this->assertFalse(PhoneFormatter::isIndonesian('84913662215'));
        $this->assertFalse(PhoneFormatter::isIndonesian('94772605624'));
    }

    public function test_country_for(): void
    {
        $this->assertSame('MY', PhoneFormatter::countryFor('60127383232'));
        $this->assertSame('VN', PhoneFormatter::countryFor('84913662215'));
        $this->assertSame('LK', PhoneFormatter::countryFor('94772605624'));
        $this->assertSame('ID', PhoneFormatter::countryFor('6281390207272'));
        $this->assertSame('HK', PhoneFormatter::countryFor('85212345678'));
    }

    /**
     * Nomor yang dinormalisasi harus cocok persis dengan JID Baileys —
     * ini yang menentukan pesan benar-benar terkirim.
     */
    public function test_normalized_matches_baileys_jid(): void
    {
        $cases = [
            ['+60127383232', '60127383232@s.whatsapp.net'],
            ['+84913662215', '84913662215@s.whatsapp.net'],
            ['+94772605624', '94772605624@s.whatsapp.net'],
            ['081390207272', '6281390207272@s.whatsapp.net'],
        ];

        foreach ($cases as [$raw, $expectedJid]) {
            $normalized = PhoneFormatter::normalize($raw);
            $this->assertNotNull($normalized, "normalize('$raw') harusnya tidak null");
            $this->assertSame($expectedJid, $normalized . '@s.whatsapp.net');
        }
    }
}
