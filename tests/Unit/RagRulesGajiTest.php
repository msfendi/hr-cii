<?php

namespace Tests\Unit;

use App\Services\RagRules;
use PHPUnit\Framework\TestCase;

/**
 * Aturan RAG untuk tabel/chart (R35) dan sumber gaji (R36).
 *
 * Yang dijaga:
 *  - dokumen memuat R35 dan R36;
 *  - aturan itu TIDAK ikut terpotong saat prompt dirakit (batas karakter);
 *  - baris peta keputusan untuk pertanyaan gaji benar-benar disisipkan;
 *  - pertanyaan "gaji" tanpa periode diarahkan ke `employees_contract`,
 *    pertanyaan rekap/periode ke `payroll_run_details`.
 */
class RagRulesGajiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $container = \Illuminate\Container\Container::getInstance();

        if ($container === null || ! $container->bound('config')) {
            $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        }

        config(['cache.default' => 'array']);

        // Batas karakter prompt RAG dibatasi oleh test lain (24.000 → bocor).
        // Produksi memakai nilai bawaan `config/chatbot.php` (64.000) karena
        // `.env` tidak menyetel `CHATBOT_RAG_RULES_MAX_CHARS`; diset eksplisit
        // di sini supaya hasil test tidak bergantung urutan eksekusi.
        config(['chatbot.rag_rules_max_chars' => 64000]);
    }

    public function test_dokumen_memuat_aturan_r35_dan_r36(): void
    {
        $rules = new RagRules();
        $isi = $rules->bagian('1');

        $this->assertGreaterThanOrEqual(36, (int) $rules->sidik()['aturan']);

        $this->assertStringContainsString('### R35.', $isi);
        $this->assertStringContainsString('### R36.', $isi);

        // R35: satu sumber data untuk tabel & grafik.
        $this->assertStringContainsString('satu baris tabel = tepat satu batang', mb_strtolower($isi));
        $this->assertStringContainsString('tabel data pada jawaban **wajib dirender sistem**', mb_strtolower($isi));

        // R36: pemetaan tabel gaji.
        $this->assertStringContainsString('`payroll_run_details`', $isi);
        $this->assertStringContainsString('`employees_contract`', $isi);
        $this->assertStringContainsString('daily_salary', $isi);
    }

    public function test_prompt_rekap_payroll_memuat_r36_tanpa_terpotong(): void
    {
        $prompt = (new RagRules())->ringkasanPrompt('rekap gaji payroll bulan agustus 2026 per karyawan');

        // R36 ada di ekor Bagian 1 — dulu pemotongan jatuh persis di bagian ini.
        $this->assertStringContainsString('### R36.', $prompt);
        $this->assertStringContainsString('### R35.', $prompt);
        $this->assertStringContainsString('payroll_run_details', $prompt);

        // Aturan wajib Bagian 7-8 tetap utuh (TIDAK boleh dikorbankan demi R36).
        $this->assertStringContainsString('BATASAN TEKNIS SQL SERVER 2014', $prompt);
        $this->assertStringContainsString('FORMAT JAWABAN', $prompt);

        $maks = (int) config('chatbot.rag_rules_max_chars', 64000);
        $this->assertLessThanOrEqual($maks + 500, mb_strlen($prompt));
    }

    public function test_prompt_menyisipkan_baris_peta_untuk_rekap_gaji(): void
    {
        $prompt = (new RagRules())->ringkasanPrompt('rekap gaji payroll bulan agustus 2026');

        $this->assertStringContainsString('BARIS PETA KEPUTUSAN YANG COCOK', $prompt);
        $this->assertStringContainsString('R36: pertanyaan **rekap/payroll/per bulan/range** ke sini', $prompt);
    }

    public function test_prompt_gaji_saja_mengarahkan_ke_kontrak_karyawan(): void
    {
        $prompt = (new RagRules())->ringkasanPrompt('gaji karyawan ririn rekayani berapa sekarang');

        $this->assertStringContainsString('Gaji pokok / gaji harian / tunjangan / PPh 21 karyawan', $prompt);
        $this->assertStringContainsString('employees_contract', $prompt);
        $this->assertStringContainsString('R36: pertanyaan **gaji saja tanpa rekap/periode** ke sini', $prompt);
    }

    public function test_prompt_pertanyaan_grafik_memuat_aturan_satu_sumber(): void
    {
        $prompt = (new RagRules())->ringkasanPrompt('tampilkan grafik gaji per departemen');

        $this->assertStringContainsString('### R35.', $prompt);
        $this->assertStringContainsString('grafik mengikuti tabel', $prompt);
    }
}
