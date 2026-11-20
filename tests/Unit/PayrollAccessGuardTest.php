<?php

namespace Tests\Unit;

use App\Models\ChatbotSession;
use App\Models\RolePayroll;
use App\Models\User;
use App\Services\AiChatService;
use App\Services\PayrollAccessGuard;
use App\Services\PayrollRoleFilterService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Guard data payroll per role_payrolls (R33).
 *
 * Yang dijaga:
 *  - pertanyaan payroll/gaji/kompensasi/pinalty dikenali sebagai sensitif;
 *  - role diambil dari role_payrolls, dengan cakupan sesuai tabel R33;
 *  - permintaan di luar cakupan ditolak (bukan dijawab "data tidak ada"),
 *    dan penolakan menyebutkan role + cakupannya;
 *  - role yang belum terdaftar tidak boleh apa pun (deny by default);
 *  - query payroll wajib memuat filter cakupan sebelum dijalankan;
 *  - penolakan benar-benar terjadi di AiChatService::handleRag() SEBELUM
 *    provider AI dipanggil (tidak ada query, tidak ada angka).
 */
class PayrollAccessGuardTest extends TestCase
{
    private PayrollAccessGuard $guard;
    private ReflectionClass $guardRef;

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
        $this->guardRef = new ReflectionClass(PayrollAccessGuard::class);
    }

    public function test_pertanyaan_payroll_dikenali_sensitif(): void
    {
        foreach ([
            'Berapa gaji C-10231 bulan ini?',
            'rekap payroll Oktober',
            'tampilkan kompensasi per departemen',
            'total pinalty bulan ini',
            'slip gaji staff',
            'berapa PPh 21 karyawan X',
        ] as $tanya) {
            $this->assertTrue($this->guard->sensitif($tanya), "harus sensitif: {$tanya}");
        }

        foreach ([
            'jumlah karyawan aktif per departemen',
            'rekap absensi hari ini',
            'siapa saja yang lembur di LINE 66',
            'turnover Oktober per factory',
        ] as $tanya) {
            $this->assertFalse($this->guard->sensitif($tanya), "tidak boleh sensitif: {$tanya}");
        }
    }

    public function test_cakupan_setiap_role_sesuai_r33(): void
    {
        $ini = fn(string $role) => $this->guard->cakupan($role);

        $this->assertSame("BIODATA.IS_STAFF = '1' OR BIODATA.IS_EXPAT = '1'", $ini(PayrollRoleFilterService::ROLE_STAFF)['sql']);
        $this->assertSame("BIODATA.IS_STAFF = '0'", $ini(PayrollRoleFilterService::ROLE_NONSTAFF)['sql']);
        $this->assertSame("BIODATA.IS_STAFF = '0' AND DEPT.IS_SEWING = '0'", $ini(PayrollRoleFilterService::ROLE_SEWING)['sql']);
        $this->assertSame("BIODATA.IS_STAFF = '0' AND DEPT.IS_SEWING = '1'", $ini(PayrollRoleFilterService::ROLE_NONSEWING)['sql']);
        $this->assertSame('', $ini(PayrollRoleFilterService::ROLE_ALL)['sql']);

        $this->assertTrue($ini(PayrollRoleFilterService::ROLE_STAFF)['staff']);
        $this->assertFalse($ini(PayrollRoleFilterService::ROLE_STAFF)['nonstaff']);
        $this->assertTrue($ini(PayrollRoleFilterService::ROLE_ALL)['semua']);
    }

    public function test_sewing_ditolak_saat_meminta_gaji_staff_atau_expat(): void
    {
        $tolak = $this->guard->pelanggaran(PayrollRoleFilterService::ROLE_SEWING, 'tampilkan gaji staff dan expat bulan ini');

        $this->assertNotNull($tolak);
        $this->assertStringContainsString('tidak diperbolehkan', $tolak);
        $this->assertStringContainsString('Payroll_SEWING', $tolak);
        $this->assertStringContainsString('non staff sewing', $tolak);

        // Variasi kata: "expat" saja.
        $this->assertNotNull($this->guard->pelanggaran(PayrollRoleFilterService::ROLE_SEWING, 'rekap gaji ekspat'));
    }

    public function test_sewing_boleh_untuk_cakupannya_sendiri(): void
    {
        $this->assertNull($this->guard->pelanggaran(PayrollRoleFilterService::ROLE_SEWING, 'rekap gaji sewing bulan Oktober'));
        $this->assertNull($this->guard->pelanggaran(PayrollRoleFilterService::ROLE_SEWING, 'total pinalty non staff sewing'));
        // Pertanyaan payroll tanpa penanda sektor: tidak ditolak, nanti difilter SQL.
        $this->assertNull($this->guard->pelanggaran(PayrollRoleFilterService::ROLE_SEWING, 'berapa total gaji bulan ini'));
    }

    public function test_sewing_ditolak_untuk_non_sewing(): void
    {
        $tolak = $this->guard->pelanggaran(PayrollRoleFilterService::ROLE_SEWING, 'gaji non sewing periode ini');

        $this->assertNotNull($tolak);
        $this->assertStringContainsString('non sewing', $tolak);
    }

    public function test_nonsewing_ditolak_untuk_sewing(): void
    {
        $this->assertNotNull($this->guard->pelanggaran(PayrollRoleFilterService::ROLE_NONSEWING, 'gaji sewing'));
    }

    public function test_staff_ditolak_untuk_non_staff_dan_sebaliknya(): void
    {
        $this->assertNotNull($this->guard->pelanggaran(PayrollRoleFilterService::ROLE_STAFF, 'gaji non staff harian'));
        $this->assertNotNull($this->guard->pelanggaran(PayrollRoleFilterService::ROLE_NONSTAFF, 'gaji staff bulanan'));
        $this->assertNull($this->guard->pelanggaran(PayrollRoleFilterService::ROLE_NONSTAFF, 'gaji non staff'));
    }

    public function test_role_all_tidak_pernah_ditolak(): void
    {
        $this->assertNull($this->guard->pelanggaran(PayrollRoleFilterService::ROLE_ALL, 'gaji staff, expat, non staff, semua'));
    }

    public function test_role_belum_terdaftar_ditolak_untuk_semua_pertanyaan_payroll(): void
    {
        $tolak = $this->guard->pelanggaran(null, 'berapa gaji karyawan C-10231');

        $this->assertNotNull($tolak);
        $this->assertStringContainsString('belum punya role payroll', $tolak);

        // Pertanyaan non-payroll tidak ikut diblokir.
        $this->assertNull($this->guard->pelanggaran(null, 'jumlah karyawan aktif per departemen'));
    }

    public function test_blok_prompt_memuat_filter_wajib(): void
    {
        $blok = $this->guard->blokPrompt(PayrollRoleFilterService::ROLE_SEWING);

        $this->assertStringContainsString('R33', $blok);
        $this->assertStringContainsString("IS_STAFF = '0'", $blok);
        $this->assertStringContainsString("DEPT.IS_SEWING = '0'", $blok);
        $this->assertStringContainsString('NO_QUERY', $blok);

        $this->assertStringContainsString('NO_QUERY', $this->guard->blokPrompt(null));
    }

    public function test_query_tanpa_filter_cakupan_ditolak(): void
    {
        $tanpaFilter = 'SELECT TOP 10 NPK, NAMA_KARYAWAN FROM BIODATA';

        $this->assertFalse($this->guard->sqlSesuaiCakupan($tanpaFilter, PayrollRoleFilterService::ROLE_SEWING));

        // Ada IS_STAFF tapi belum IS_SEWING (role sewing) → tetap ditolak.
        $this->assertFalse($this->guard->sqlSesuaiCakupan(
            "SELECT NPK FROM BIODATA b WHERE b.IS_STAFF = '0'",
            PayrollRoleFilterService::ROLE_SEWING
        ));

        $this->assertTrue($this->guard->sqlSesuaiCakupan(
            "SELECT b.NPK FROM BIODATA b JOIN DEPT d ON d.ID_DEPT = b.ID_DEPT WHERE b.IS_STAFF = '0' AND d.IS_SEWING = '0'",
            PayrollRoleFilterService::ROLE_SEWING
        ));

        $this->assertTrue($this->guard->sqlSesuaiCakupan($tanpaFilter, PayrollRoleFilterService::ROLE_ALL));
        $this->assertFalse($this->guard->sqlSesuaiCakupan($tanpaFilter, null));
    }

    public function test_role_dibaca_dari_role_payrolls_bukan_dari_nama_user(): void
    {
        $user = new User();
        $user->id = 7;
        $user->setRelation('rolePayroll', new RolePayroll(['user_id' => 7, 'payroll_role' => PayrollRoleFilterService::ROLE_SEWING]));

        $this->assertSame(PayrollRoleFilterService::ROLE_SEWING, $this->guard->role($user));

        $tanpaRole = new User();
        $tanpaRole->id = 8;
        $tanpaRole->setRelation('rolePayroll', null);
        $this->assertNull($this->guard->role($tanpaRole));
    }

    public function test_handle_rag_menolak_sebelum_provider_dipanggil(): void
    {
        // guardSesi() diganti stub: test ini menguji jalur penolakan, bukan
        // pembacaan DB (itu diuji terpisah di ChatbotGuardCheckTest).
        $svc = new class (7, PayrollRoleFilterService::ROLE_SEWING) extends AiChatService {
            public function __construct(private int $uid, private ?string $role) {}

            protected function guardSesi(\App\Models\ChatbotSession $session): array
            {
                $user = new User();
                $user->id = $this->uid;

                return [
                    'ok' => true,
                    'kode' => $this->role ? 'role_terdaftar' : 'role_kosong',
                    'pesan' => '',
                    'user' => $user,
                    'role' => $this->role,
                    'user_id' => $this->uid,
                ];
            }
        };

        $sesi = new ChatbotSession(['user_id' => 7, 'mode' => ChatbotSession::MODE_RAG]);
        $sesi->id = 42;

        $handle = (new ReflectionClass(AiChatService::class))->getMethod('handleRag');
        $handle->setAccessible(true);

        // Tanpa API key / tanpa koneksi DB: kalau jalur ini sampai memanggil
        // provider atau mengintrospeksi skema, test akan gagal/timeout.
        $hasil = $handle->invoke($svc, $sesi, 'router9', 'tampilkan gaji staff dan expat bulan ini');

        $this->assertNull($hasil['meta']['sql']);
        $this->assertStringContainsString('tidak diperbolehkan', $hasil['content']);
        $this->assertStringContainsString('Payroll_SEWING', $hasil['content']);
        $this->assertSame(PayrollRoleFilterService::ROLE_SEWING, $hasil['meta']['payroll_guard']['role']);
        $this->assertSame("BIODATA.IS_STAFF = '0' AND DEPT.IS_SEWING = '0'", $hasil['meta']['payroll_guard']['filter_sql']);
    }
}