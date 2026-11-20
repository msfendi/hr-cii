<?php

namespace Tests\Unit;

use App\Services\AiChatService;
use App\Services\RagRules;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Blocklist tabel/kolom pindah dari .env ke Bagian 6c dokumen aturan (R32).
 *
 * Yang dijaga:
 *  - Bagian 6c benar-benar ada dan bisa diurai mesin;
 *  - daftar tabel/kolom terbaca dari dokumen, bukan dari config;
 *  - AiChatService memakai daftar dokumen WALAU config/.env dikosongkan;
 *  - larangan tetap jalan (tidak fail-open) bila config kosong.
 */
class RagRulesBlocklistTest extends TestCase
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
    }

    public function test_bagian_6c_hanya_memblokir_kredensial_dan_token(): void
    {
        $blok = (new RagRules())->blokTeknis();

        // Sejak 18 Nov 2026 blokir teknis dipersempit ke kredensial/token saja.
        // Kolom gaji TIDAK lagi diblokir di sini (dijaga cakupan role R33).
        $this->assertSame([], $blok['tabel']);

        $this->assertArrayNotHasKey('employees_contract', $blok['kolom']);
        $this->assertArrayNotHasKey('pkwt', $blok['kolom']);
        $this->assertArrayNotHasKey('pelamar_details', $blok['kolom']);

        $this->assertContains('token', $blok['kolom']['leave_requests'] ?? []);
        $this->assertContains('token', $blok['kolom']['whatsapp_devices'] ?? []);

        $users = $blok['kolom']['users'] ?? [];
        $this->assertContains('password', $users);
        $this->assertContains('remember_token', $users);
        $this->assertContains('two_factor_secret', $users);

        // Tepat tiga baris KOLOM: tidak ada blokir lain yang menyelinap masuk.
        $this->assertCount(3, $blok['kolom']);
    }

    public function test_dokumen_menuat_aturan_r32_r33_dan_r34(): void
    {
        $rules = new RagRules();
        $sidik = $rules->sidik();

        $this->assertGreaterThanOrEqual(34, (int) $sidik['aturan']);

        $isi = $rules->bagian('1');
        $this->assertStringContainsString('### R32.', $isi);
        $this->assertStringContainsString('### R33.', $isi);
        $this->assertStringContainsString('R33.1', $isi);
        $this->assertStringContainsString('### R34.', $isi);
        $this->assertStringContainsString('Payroll_SEWING', $isi);
        $this->assertStringContainsString('role_payrolls', $isi);
        $this->assertStringContainsString('contract_ke', $isi);
    }

    public function test_ai_service_memakai_blocklist_dokumen_walau_config_kosong(): void
    {
        // Kondisi server baru: .env sudah dikosongkan.
        config([
            'chatbot.rag_blocked_tables' => [],
            'chatbot.rag_blocked_columns' => [],
            'chatbot.rag_rules_enforce_r25' => false,
        ]);

        $ref = new ReflectionClass(AiChatService::class);
        $svc = $ref->newInstanceWithoutConstructor();

        $tabel = $ref->getMethod('blockedTables');
        $tabel->setAccessible(true);
        $kolom = $ref->getMethod('blockedColumns');
        $kolom->setAccessible(true);

        $daftarTabel = $tabel->invoke($svc);
        $daftarKolom = $kolom->invoke($svc);

        $this->assertIsArray($daftarTabel);
        $this->assertContains('token', $daftarKolom['leave_requests'] ?? []);
        $this->assertContains('password', $daftarKolom['users'] ?? []);
        $this->assertArrayNotHasKey('employees_contract', $daftarKolom);
    }

    public function test_blocklist_tidak_pernah_kosong(): void
    {
        config([
            'chatbot.rag_blocked_tables' => [],
            'chatbot.rag_blocked_columns' => [],
        ]);

        $ref = new ReflectionClass(AiChatService::class);
        $svc = $ref->newInstanceWithoutConstructor();

        $tabel = $ref->getMethod('blockedTables');
        $tabel->setAccessible(true);
        $kolom = $ref->getMethod('blockedColumns');
        $kolom->setAccessible(true);

        $this->assertIsArray($tabel->invoke($svc));
        // Blokir kredensial harus selalu terpasang, bahkan tanpa config.
        $this->assertNotEmpty($kolom->invoke($svc));
    }
}