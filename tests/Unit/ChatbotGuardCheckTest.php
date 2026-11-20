<?php

namespace Tests\Unit;

use App\Models\ChatbotSession;
use App\Models\User;
use App\Services\AiChatService;
use App\Services\PayrollAccessGuard;
use App\Services\PayrollRoleFilterService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Guard check identitas + role SEBELUM aturan RAG dipakai (permintaan 18 Nov 2026).
 *
 * Yang dijaga:
 *  - setiap prompt membaca ulang `users` dan `role_payrolls` dari database;
 *  - sesi tanpa pemilik, pemilik yang tidak ada, atau sesi milik akun lain
 *    ditolak — bukan dilanjutkan dengan asumsi;
 *  - kegagalan membaca role_payrolls tidak membuka akses (role jadi kosong,
 *    data gaji tetap tertutup);
 *  - role yang tidak dikenal dianggap tidak terdaftar;
 *  - penolakan terjadi di handleRag() sebelum provider AI dipanggil;
 *  - kolom gaji dibuka hanya untuk role payroll terdaftar.
 */
class ChatbotGuardCheckTest extends TestCase
{
    private PayrollAccessGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $container = \Illuminate\Container\Container::getInstance();

        if ($container === null || ! $container->bound('config')) {
            $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        }

        config(['cache.default' => 'array']);

        $this->guard = new PayrollAccessGuard();
    }

    private function sesi(?int $userId): ChatbotSession
    {
        $sesi = new ChatbotSession(['mode' => ChatbotSession::MODE_RAG]);
        $sesi->user_id = $userId;
        $sesi->id = 99;

        return $sesi;
    }

    public function test_sesi_tanpa_pemilik_ditolak(): void
    {
        $hasil = $this->guard->periksaSesi($this->sesi(null), null);

        $this->assertFalse($hasil['ok']);
        $this->assertSame('sesi_tanpa_pemilik', $hasil['kode']);
        $this->assertStringContainsString('tidak tercatat memiliki pemilik', $hasil['pesan']);
    }

    public function test_sesi_milik_akun_lain_ditolak(): void
    {
        $hasil = $this->guard->periksaSesi($this->sesi(7), 8);

        $this->assertFalse($hasil['ok']);
        $this->assertSame('bukan_pemilik_sesi', $hasil['kode']);
        $this->assertStringContainsString('milik akun lain', $hasil['pesan']);
    }

    public function test_users_dibaca_ulang_dari_database_dan_gagal_baca_berarti_ditolak(): void
    {
        // Pemilik sesi sama dengan pengguna aktif, tetapi tabel users tidak
        // bisa dibaca / barisnya tidak ada: identitas tidak terverifikasi.
        $hasil = $this->guard->periksaSesi($this->sesi(7), 7);

        $this->assertFalse($hasil['ok']);
        $this->assertContains($hasil['kode'], ['users_tidak_terbaca', 'user_tidak_ada']);
        $this->assertNull($hasil['user']);
    }

    public function test_role_payrolls_dibaca_lewat_query_bukan_relasi_sesi(): void
    {
        // Bukti struktural: periksaSesi() menyebut tabel role_payrolls dan
        // users secara eksplisit, tidak memakai $session->user / ->rolePayroll.
        $sumber = file_get_contents(dirname(__DIR__, 2) . '/app/Services/PayrollAccessGuard.php');
        $badan = substr($sumber, strpos($sumber, 'public function periksaSesi'));

        $this->assertStringContainsString('User::query()->find(', $badan);
        $this->assertStringContainsString("RolePayroll::query()->where('user_id'", $badan);
        // Tidak boleh mengambil identitas dari relasi sesi (bisa basi).
        $this->assertStringNotContainsString('= $session->user', $badan);
        $this->assertStringNotContainsString('$session->load(', $badan);
    }

    public function test_blokir_teknis_tidak_lagi_memblokir_kolom_gaji(): void
    {
        $svc = new class extends AiChatService {
            public function kolom(?string $role): array
            {
                return $this->blockedColumnsUntuk($role);
            }
        };

        foreach ([null, PayrollRoleFilterService::ROLE_ALL, PayrollRoleFilterService::ROLE_SEWING] as $role) {
            $daftar = $svc->kolom($role);

            // Sejak 18 Nov 2026 blokir teknis hanya token/kredensial.
            $this->assertArrayNotHasKey('employees_contract', $daftar);
            $this->assertContains('token', $daftar['leave_requests'] ?? []);
            $this->assertContains('password', $daftar['users'] ?? []);
        }
    }

    public function test_handle_rag_berhenti_saat_guard_check_gagal(): void
    {
        $svc = new class extends AiChatService {
            public function __construct() {}

            protected function guardSesi(ChatbotSession $session): array
            {
                return [
                    'ok' => false,
                    'kode' => 'bukan_pemilik_sesi',
                    'pesan' => 'Sesi chat ini milik akun lain. Demi keamanan data, permintaan tidak diproses.',
                    'user' => null,
                    'role' => null,
                    'user_id' => 7,
                ];
            }
        };

        $handle = (new ReflectionClass(AiChatService::class))->getMethod('handleRag');
        $handle->setAccessible(true);

        // Pertanyaan biasa pun harus berhenti di guard — bukan hanya payroll.
        $hasil = $handle->invoke($svc, $this->sesi(7), 'router9', 'jumlah karyawan aktif per departemen');

        $this->assertNull($hasil['meta']['sql']);
        $this->assertSame('bukan_pemilik_sesi', $hasil['meta']['guard_check']['kode']);
        $this->assertSame(7, $hasil['meta']['guard_check']['user_id']);
        $this->assertStringContainsString('tidak diproses', $hasil['content']);
    }

    public function test_handle_rag_menolak_payroll_saat_role_kosong(): void
    {
        $svc = new class extends AiChatService {
            public function __construct() {}

            protected function guardSesi(ChatbotSession $session): array
            {
                $user = new User();
                $user->id = 7;

                return [
                    'ok' => true,
                    'kode' => 'role_kosong',
                    'pesan' => '',
                    'user' => $user,
                    'role' => null,
                    'user_id' => 7,
                ];
            }
        };

        $handle = (new ReflectionClass(AiChatService::class))->getMethod('handleRag');
        $handle->setAccessible(true);

        $hasil = $handle->invoke($svc, $this->sesi(7), 'router9', 'tampilkan gaji staff bulan ini');

        $this->assertNull($hasil['meta']['sql']);
        $this->assertSame('role_kosong', $hasil['meta']['guard_check']['kode']);
        $this->assertNull($hasil['meta']['guard_check']['role']);
        $this->assertStringContainsString('role_payrolls', $hasil['content']);
        $this->assertStringContainsString('tidak diberikan secara default', $hasil['content']);
    }

    public function test_query_gaji_tidak_diblokir_tetapi_token_tetap_ditolak(): void
    {
        $svc = new class extends AiChatService {
            public function __construct() {}

            public function periksa(string $sql, ?string $role): array
            {
                return $this->validateReadonlySql(
                    $sql,
                    $this->blockedTables(),
                    $this->blockedColumnsUntuk($role)
                );
            }
        };

        // Blokir teknis dipersempit: kolom gaji bukan lagi urusan blocklist.
        $sql = 'SELECT ec.salary, ec.allowance FROM employees_contract ec';

        foreach ([null, PayrollRoleFilterService::ROLE_ALL, PayrollRoleFilterService::ROLE_SEWING] as $role) {
            $hasil = $svc->periksa($sql, $role);
            $this->assertTrue($hasil['valid'], 'kolom gaji tidak boleh diblokir teknis: ' . ($hasil['reason'] ?? ''));
        }

        // Kredensial/token tetap ditolak untuk semua role.
        foreach ([null, PayrollRoleFilterService::ROLE_ALL] as $role) {
            $token = $svc->periksa('SELECT token FROM leave_requests', $role);
            $this->assertFalse($token['valid']);
        }
    }
}