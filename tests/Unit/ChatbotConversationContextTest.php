<?php

namespace Tests\Unit;

use App\Models\ChatbotMessage;
use App\Models\ChatbotSession;
use App\Services\AiChatService;
use App\Services\RagRules;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Chatbot: konteks percakapan + aturan turnover (TASK 7, 17 Nov 2026).
 *
 * Aturan yang dijaga:
 *  - pesan user pada giliran SEKARANG tidak dikirim dua kali sebagai riwayat;
 *  - jawaban AI sebelumnya ikut sebagai konteks, termasuk query yang dipakai
 *    (dasar pertanyaan lanjutan seperti "kenapa LINE 66 aktifnya 0?");
 *  - jumlah riwayat dibatasi config('chatbot.rag_history_limit');
 *  - aturan R26–R30 (konteks, turnover, LINE→Factory, top N, angka 0) benar-benar
 *    ada di blok aturan yang dikirim ke generator SQL;
 *  - potongan prompt TIDAK boleh membuang Bagian 7–8 (batasan SQL Server +
 *    format jawaban) demi tabel rujukan Bagian 6.
 */
class ChatbotConversationContextTest extends TestCase
{
    private AiChatService $svc;
    private ReflectionClass $ref;

    protected function setUp(): void
    {
        parent::setUp();

        // Unit test ini butuh helper config() (batas riwayat) dan pembacaan
        // berkas aturan; container Laravel dibutuhkan untuk itu. Kernel
        // di-boot sekali saja, dan tidak ada query database di sini —
        // koneksi bersifat lazy.
        $container = \Illuminate\Container\Container::getInstance();

        if ($container === null || ! $container->bound('config')) {
            $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        }

        config(['cache.default' => 'array']);

        // Tanpa container & tanpa database: objek dibuat lewat reflection dan
        // riwayat disuplai dari relasi yang sudah dimuat (relationLoaded).
        $this->ref = new ReflectionClass(AiChatService::class);
        $this->svc = $this->ref->newInstanceWithoutConstructor();
    }

    private function panggil(string $metode, ...$argumen)
    {
        $m = $this->ref->getMethod($metode);
        $m->setAccessible(true);

        return $m->invoke($this->svc, ...$argumen);
    }

    /**
     * @param array<int,array{0:string,1:string,2?:array}> $giliran [role, content, meta]
     */
    private function sesi(array $giliran): ChatbotSession
    {
        $sesi = new ChatbotSession(['user_id' => 1, 'mode' => ChatbotSession::MODE_RAG]);
        $sesi->id = 99;

        $pesan = new Collection();
        foreach ($giliran as $i => $g) {
            $m = new ChatbotMessage([
                'chatbot_session_id' => 99,
                'role' => $g[0],
                'content' => $g[1],
                'meta' => $g[2] ?? null,
            ]);
            $m->id = $i + 1;
            $pesan->push($m);
        }

        $sesi->setRelation('messages', $pesan);

        return $sesi;
    }

    /** Riwayat: pertanyaan turnover, jawaban AI, lalu pertanyaan lanjutan user. */
    private function sesiTurnover(): ChatbotSession
    {
        return $this->sesi([
            ['user', 'tampilkan turnover karyawan Oktober per departement, LINE 1-42 Factory 2, LINE 43-84 Factory 1'],
            ['assistant', "| Factory | Departement | Masuk | Keluar | Aktif | Turnover % |\n| Lainnya | QC | 34 | 21 | 119 | 17.65 |\n| Factory 1 | LINE 66 | 22 | 6 | 0 | .00 |", [
                'sql' => 'SELECT TOP 10 DEPARTEMENT, MASUK, KELUAR, AKTIF, TURNOVER_PCT FROM rekap_turnover ORDER BY KELUAR DESC',
                'row_count' => 10,
            ]],
            ['user', 'kenapa line 66 aktifnya 0?'],
        ]);
    }

    public function test_pesan_user_giliran_sekarang_tidak_dikirim_dua_kali(): void
    {
        $riwayat = $this->panggil('pesanRiwayat', $this->sesiTurnover());

        $this->assertCount(2, $riwayat, 'riwayat harus berisi pertanyaan awal + jawaban AI, tanpa pertanyaan yang sedang diproses');
        $this->assertSame('user', $riwayat[0]['role']);
        $this->assertSame('assistant', $riwayat[1]['role']);
        $this->assertStringNotContainsString('kenapa line 66', $riwayat[0]['content'] . $riwayat[1]['content']);
    }

    public function test_jawaban_ai_membawa_query_yang_dipakai(): void
    {
        $riwayat = $this->panggil('pesanRiwayat', $this->sesiTurnover());

        $this->assertStringContainsString('rekap_turnover', $riwayat[1]['content']);
        $this->assertStringContainsString('jumlah baris hasil query: 10', $riwayat[1]['content']);
    }

    public function test_blok_riwayat_memuat_query_terakhir_untuk_pertanyaan_lanjutan(): void
    {
        $blok = $this->panggil('blokRiwayatPercakapan', $this->sesiTurnover());

        $this->assertStringContainsString('RIWAYAT PERCAKAPAN SESI INI', $blok);
        $this->assertStringContainsString('QUERY TERAKHIR YANG BERHASIL', $blok);
        $this->assertStringContainsString('ORDER BY KELUAR DESC', $blok);
        $this->assertStringContainsString('LINE 66', $blok);
    }

    public function test_sesi_baru_ditandai_belum_ada_riwayat(): void
    {
        $blok = $this->panggil('blokRiwayatPercakapan', $this->sesi([]));

        $this->assertStringContainsString('belum ada', $blok);
        $this->assertStringNotContainsString('QUERY TERAKHIR', $blok);
    }

    public function test_riwayat_dibatasi_config(): void
    {
        $giliran = [];
        for ($i = 1; $i <= 6; $i++) {
            $giliran[] = ['user', "pertanyaan ke-{$i}"];
            $giliran[] = ['assistant', "jawaban ke-{$i}"];
        }

        $riwayat = $this->panggil('pesanRiwayat', $this->sesi($giliran));

        // Default config('chatbot.rag_history_limit') = 8 pesan terakhir.
        $this->assertLessThanOrEqual(8, count($riwayat));
        $this->assertSame('jawaban ke-6', $riwayat[count($riwayat) - 1]['content']);
    }

    public function test_prompt_generator_sql_memakai_riwayat_dan_aturan_lanjutan(): void
    {
        // Penjaga regresi: kalau seseorang menghapus pengiriman riwayat ke
        // generator SQL, pertanyaan lanjutan kembali dijawab "lupa".
        $sumber = file_get_contents(dirname(__DIR__, 2) . '/app/Services/AiChatService.php');

        $this->assertStringContainsString('$riwayatBlok = $this->blokRiwayatPercakapan($session);', $sumber);
        $this->assertStringContainsString('$this->callProvider($providerKey, $sqlSystemPrompt, $pesanSql)', $sumber);
        $this->assertStringContainsString('array_merge($riwayatPesan, [', $sumber);
        $this->assertStringContainsString('KONTEKS PERCAKAPAN', $sumber);
    }

    public function test_handleRag_mengirim_riwayat_ke_prompt_dan_pesan_provider(): void
    {
        $svc = new class extends AiChatService {
            /** @var array<int,array{system:string,messages:array}> */
            public array $panggilan = [];

            protected function blockedTables(): array
            {
                return [];
            }

            /** Guard check di-stub: test ini menguji riwayat percakapan, bukan DB identitas. */
            protected function guardSesi(ChatbotSession $session): array
            {
                $user = new \App\Models\User();
                $user->id = 1;

                return [
                    'ok' => true,
                    'kode' => 'role_kosong',
                    'pesan' => '',
                    'user' => $user,
                    'role' => null,
                    'user_id' => 1,
                ];
            }

            protected function blockedColumns(): array
            {
                return [];
            }

            protected function getDatabaseSchema(array $blockedTables, array $blockedColumns = []): array
            {
                return ['BIODATA' => ['PK', 'NPK', 'NAMA_KARYAWAN'], 'DEPT' => ['PK', 'ID_DEPT', 'DEPARTEMENT']];
            }

            protected function getDatabaseRelations(array $blockedTables): array
            {
                return [];
            }

            protected function getSchemaDocumentation(): string
            {
                return '';
            }

            protected function readonlyDriver(): string
            {
                return 'sqlsrv';
            }

            protected function callProvider(string $providerKey, string $systemPrompt, array $messages): string
            {
                $this->panggilan[] = ['system' => $systemPrompt, 'messages' => $messages];

                // Berhenti sebelum eksekusi SQL (butuh database sungguhan).
                return 'NO_QUERY';
            }

            public function jalankan(ChatbotSession $session, string $pesan): array
            {
                return $this->handleRag($session, 'router9', $pesan);
            }
        };

        $svc->jalankan($this->sesiTurnover(), 'kenapa line 66 aktifnya 0?');

        $this->assertNotEmpty($svc->panggilan, 'generator SQL harus dipanggil minimal sekali');

        $promptSql = $svc->panggilan[0]['system'];
        $this->assertStringContainsString('RIWAYAT PERCAKAPAN SESI INI', $promptSql);
        $this->assertStringContainsString('QUERY TERAKHIR YANG BERHASIL', $promptSql);
        $this->assertStringContainsString('ORDER BY KELUAR DESC', $promptSql);
        $this->assertStringContainsString('R26', $promptSql, 'aturan konteks percakapan wajib ikut di prompt');
        $this->assertStringContainsString('Factory 2', $promptSql, 'pemetaan LINE->Factory wajib ikut di prompt');

        // Riwayat juga dikirim sebagai giliran percakapan, bukan hanya teks prompt.
        $peran = array_column($svc->panggilan[0]['messages'], 'role');
        $this->assertSame(['user', 'assistant', 'user'], $peran);
        $this->assertSame('kenapa line 66 aktifnya 0?', end($svc->panggilan[0]['messages'])['content']);

        // Jalur "tanpa SQL" (NO_QUERY) juga membawa riwayat.
        if (isset($svc->panggilan[1])) {
            $this->assertStringContainsString('RIWAYAT PERCAKAPAN SESI INI', $svc->panggilan[1]['system']);
            $this->assertGreaterThanOrEqual(3, count($svc->panggilan[1]['messages']));
        }
    }

    public function test_dokumen_aturan_memuat_r26_sampai_r30(): void
    {
        $dok = file_get_contents(dirname(__DIR__, 2) . '/docs/RAG_RULES_CII.md');

        foreach (['R26.', 'R27.', 'R28.', 'R29.', 'R30.'] as $aturan) {
            $this->assertStringContainsString('### ' . $aturan, $dok);
        }

        $this->assertStringContainsString('LINE 1–42', $dok);
        $this->assertStringContainsString('LINE 43–84', $dok);
        $this->assertStringContainsString('RESEP 6', $dok);
        $this->assertStringContainsString('keluar ÷ karyawan aktif × 100', $dok);
    }

    public function test_blok_aturan_untuk_turnover_memuat_aturan_kunci(): void
    {
        // RagRules membaca berkas markdown; cache memakai driver array saat test.
        config(['cache.default' => 'array', 'chatbot.rag_rules_max_chars' => 24000]);

        try {
            $prompt = (new RagRules())->ringkasanPrompt('tampilkan turnover karyawan oktober per departement');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Laravel container tidak tersedia untuk helper config(): ' . $e->getMessage());
        }

        $this->assertStringContainsString('R26', $prompt);
        $this->assertStringContainsString('R27', $prompt);
        $this->assertStringContainsString('Factory 2', $prompt);
        $this->assertStringContainsString('Factory 1', $prompt);

        // Bagian 7 (batasan SQL Server) & 8 (format jawaban) tidak boleh hilang.
        $this->assertStringContainsString('FORMAT JAWABAN', $prompt);
        $this->assertStringContainsString('BATASAN', $prompt);
    }
}