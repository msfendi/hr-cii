<?php

namespace Tests\Unit;

use App\Services\AiChatService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Chatbot: teks proses berpikir (reasoning) model TIDAK BOLEH tampil sebagai
 * jawaban di bubble chat, dan jawaban yang berhenti karena kehabisan token
 * harus dikenali sebagai terpotong.
 *
 * Latar: provider di balik router9 (gemini-3-8-flash) selalu mengirim
 * reasoning_content sekitar 2.500 karakter walau thinking diminta dimatikan.
 * Saat budget token habis, isi pesan yang sampai ke user justru potongan
 * monolog bahasa Inggris ("Wait, ...", "Notice: ...") alih-alih SQL/jawaban.
 */
class ChatbotReasoningLeakTest extends TestCase
{
    private AiChatService $svc;
    private ReflectionClass $ref;

    protected function setUp(): void
    {
        parent::setUp();

        // Metode yang diuji tidak memakai state instance, jadi objek cukup
        // dibuat tanpa memanggil konstruktor (tidak butuh koneksi database).
        $this->ref = new ReflectionClass(AiChatService::class);
        $this->svc = $this->ref->newInstanceWithoutConstructor();
    }

    private function panggil(string $metode, ...$argumen)
    {
        $m = $this->ref->getMethod($metode);
        $m->setAccessible(true);

        return $m->invoke($this->svc, ...$argumen);
    }

    public function test_content_kosong_tidak_diisi_teks_penalaran(): void
    {
        $data = [
            'choices' => [[
                'finish_reason' => 'length',
                'message' => [
                    'content' => '',
                    'reasoning_content' => 'Wait, the user is asking for October turnover. Notice: ORDER BY DESC.',
                ],
            ]],
        ];

        $this->assertSame('', $this->panggil('resolveProviderMessage', $data));
    }

    public function test_reasoning_di_level_atas_juga_tidak_dipakai(): void
    {
        $data = [
            'choices' => [[
                'message' => ['content' => ''],
            ]],
            'reasoning' => 'Let me think about this step by step.',
        ];

        $this->assertSame('', $this->panggil('resolveProviderMessage', $data));
    }

    public function test_content_normal_tetap_dipakai(): void
    {
        $sql = "```sql\nSELECT TOP 3 Dept, COUNT(*) AS jml FROM TKK GROUP BY Dept\n```";

        $this->assertSame($sql, $this->panggil('resolveProviderMessage', [
            'choices' => [['message' => ['content' => $sql]]],
        ]));
    }

    /**
     * @dataProvider providerPenalaran
     */
    public function test_deteksi_teks_penalaran(string $teks, bool $harusTerdeteksi, string $catatan): void
    {
        $this->assertSame($harusTerdeteksi, $this->panggil('tampakSepertiPenalaran', $teks), $catatan);
    }

    public static function providerPenalaran(): array
    {
        return [
            'monolog bahasa Inggris' => [
                'Notice: "top 3 departement dari mana?" means ranking per department. Wait, can we sort by turnover?',
                true,
                'penanda "notice:" / "wait," harus terdeteksi',
            ],
            'potongan di tengah kalimat tanpa blok kode' => [
                str_repeat('Analisis turnover Oktober berdasarkan data TKK ', 8),
                true,
                'teks panjang tanpa tanda akhir kalimat dianggap terpotong',
            ],
            'kalimat jawaban wajar' => [
                'Jumlah karyawan keluar pada Oktober 2026 adalah 17 orang dari 3 departemen.',
                false,
                'jawaban utuh berbahasa Indonesia tidak boleh dianggap penalaran',
            ],
            'jawaban berisi blok kode' => [
                "Berikut query-nya:\n```sql\nSELECT 1\n```",
                false,
                'blok kode menandakan jawaban jadi',
            ],
            'teks kosong' => ['', false, 'teks kosong bukan penalaran'],
        ];
    }

    /**
     * @dataProvider providerTerpotong
     */
    public function test_deteksi_jawaban_terpotong(string $finishReason, bool $harusTerpotong): void
    {
        $data = ['choices' => [['finish_reason' => $finishReason, 'message' => ['content' => 'x']]]];

        $this->assertSame($harusTerpotong, $this->panggil('jawabanTerpotong', $data));
    }

    public static function providerTerpotong(): array
    {
        return [
            'length → terpotong' => ['length', true],
            'stop → utuh' => ['stop', false],
            'tool_calls → utuh' => ['tool_calls', false],
        ];
    }
}
