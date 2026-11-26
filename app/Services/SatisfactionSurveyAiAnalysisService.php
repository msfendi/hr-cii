<?php

namespace App\Services;

use App\Models\SatisfactionResponse;
use App\Models\SatisfactionSurveyAiAnalysis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SatisfactionSurveyAiAnalysisService
{
    /**
     * Analisis dua sisi: keluhan tertulis (answer_text) dan pertanyaan dengan
     * skor Likert terburuk. Hasilnya tepat tiga masalah per kategori bila data
     * mencukupi; bila data kurang dari tiga, dikembalikan sebanyak yang ada.
     */
    public function analyze(): SatisfactionSurveyAiAnalysis
    {
        $config = config('chatbot.providers.router9', []);

        $answers = SatisfactionResponse::query()
            ->whereHas('question', fn ($query) => $query->where('is_active', true))
            ->whereNotNull('answer_text')
            ->whereRaw("LTRIM(RTRIM(answer_text)) <> ''")
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('answer_text')
            ->map(fn ($answer) => trim((string) $answer))
            ->filter()
            ->values();

        $scoreStats = $this->worstScoreStats();

        if ($answers->isEmpty() && $scoreStats->isEmpty()) {
            throw new RuntimeException('Belum ada jawaban esai maupun skor untuk dianalisis.');
        }
        if (empty($config['base_url']) || empty($config['model'])) {
            throw new RuntimeException('Konfigurasi router AI belum lengkap.');
        }

        $prompt = $this->buildPrompt($answers, $scoreStats);

        $content = $this->requestCompletion($config, $prompt);

        if (trim($content) === '') {
            throw new RuntimeException('Router AI mengembalikan respons kosong. Coba lagi atau periksa koneksi ke server AI.');
        }

        $decoded = $this->decodePayload($content);

        $answerProblems = $this->normalizeProblems(
            $decoded['answers'] ?? $decoded['answer_problems'] ?? $decoded['problems'] ??
            $decoded['top_answers'] ?? $decoded['masalah_esai'] ?? $decoded['keluhan'] ?? null,
            'answers'
        );
        $scoreProblems = $this->normalizeProblems(
            $decoded['scores'] ?? $decoded['score_problems'] ?? $decoded['top_scores'] ??
            $decoded['masalah_skor'] ?? $decoded['skor'] ?? null,
            'scores'
        );
        $topRequests = $this->normalizeProblems(
            $decoded['requests'] ?? $decoded['top_requests'] ?? $decoded['keinginan'] ??
            $decoded['permintaan'] ?? $decoded['harapan'] ?? null,
            'answers'
        );

        // Fallback: model membalas list flat (array of object/string), bukan nested.
        if ($answerProblems === [] && $scoreProblems === [] && $topRequests === [] && !empty($decoded)) {
            $flat = $this->normalizeProblems(array_values($decoded), 'answers');
            if (!empty($flat)) {
                $answerProblems = $flat;
            }
        }

        if ($answerProblems === [] && $scoreProblems === [] && $topRequests === []) {
            Log::warning('SatisfactionAI: format respons tidak dikenali', [
                'keys' => array_keys($decoded),
                'panjang_konten' => mb_strlen($content),
                'cuplikan' => mb_substr($content, 0, 600),
            ]);

            throw new RuntimeException(
                'Analisis AI tidak bisa dibaca — format respons tidak dikenali. ' .
                'Coba klik Perbarui Analisis lagi. ' .
                '(panjang respons: ' . mb_strlen($content) . ' karakter)'
            );
        }

        if (!Schema::connection((new SatisfactionSurveyAiAnalysis())->getConnectionName() ?: config('database.default'))
            ->hasTable('satisfaction_survey_ai_analyses')) {
            throw new RuntimeException('Tabel penyimpanan analisis belum tersedia. Jalankan migrasi survei terlebih dahulu.');
        }

        return SatisfactionSurveyAiAnalysis::updateOrCreate(
            ['scope_key' => 'all'],
            [
                'source_count' => $answers->count(),
                'provider' => 'router9',
                'model' => $config['model'],
                'analysis_json' => [
                    'answers'   => $answerProblems,
                    'scores'    => $scoreProblems,
                    'requests'  => $topRequests,
                    // Kompatibilitas dengan analisis versi sebelumnya.
                    'problems'  => $answerProblems,
                ],
                'generated_at' => now(),
            ]
        );
    }

    /**
     * Panggil router AI sekali, dengan satu percobaan ulang bila jawaban
     * terpotong karena kehabisan budget token (finish_reason=length).
     *
     * Model di balik combo Chutex selalu mengirim ~2.500 karakter proses
     * berpikir walau thinking diminta dimatikan. Budget token kecil membuat
     * JSON jawaban berhenti di tengah sehingga tidak bisa diparse — inilah
     * akar error "format respons tidak dikenali". Karena itu budget diambil
     * dari config (bukan angka tetap) dan dinaikkan saat terpotong.
     */
    private function requestCompletion(array $config, string $prompt): string
    {
        $timeout = (int) config('chatbot.request_timeout', 120);

        $pesan = [
            [
                'role' => 'system',
                'content' => 'Balas HANYA dengan satu objek JSON valid. Tanpa teks pembuka, '
                    . 'tanpa penjelasan, tanpa markdown code-fence. Mulai langsung dari { dan akhiri dengan }. '
                    . 'Gunakan Bahasa Indonesia. Jangan menulis karakter non-Latin.',
            ],
            ['role' => 'user', 'content' => $prompt],
        ];

        $body = $this->buildBody($config, $pesan);
        $response = $this->postProvider($config, $body, $timeout);

        if (!$response->successful()) {
            throw new RuntimeException('Router AI gagal memproses analisis survei (HTTP ' . $response->status() . ').');
        }

        $data = $this->responsBerupaStream($response) ? null : $response->json();

        if (is_array($data) && !empty($data['error'])) {
            $pesanError = is_array($data['error'])
                ? (string) ($data['error']['message'] ?? json_encode($data['error']))
                : (string) $data['error'];

            throw new RuntimeException('Router AI menolak permintaan: ' . mb_substr($pesanError, 0, 300));
        }

        // Jawaban terpotong (finish_reason=length): dicatat untuk diagnosa.
        // Pemulihan dilakukan decodePayload() yang menutup JSON yang belum tutup.
        if (is_array($data) && $this->jawabanTerpotong($data)) {
            Log::warning('SatisfactionAI: jawaban provider terpotong (finish_reason=length).', [
                'model' => $config['model'] ?? null,
            ]);
        }

        if ($this->responsBerupaStream($response)) {
            return $this->bacaStreamProvider($response->body());
        }

        return $this->extractContent($data);
    }

    /**
     * Susun body permintaan ke router AI.
     *
     * `max_tokens` / `max_completion_tokens` SENGAJA tidak dikirim: jumlah
     * jawaban esai bisa ribuan, dan batas token tetap membuat jawaban berhenti
     * di tengah sehingga JSON tidak lengkap. Tanpa batas, provider memakai
     * kapasitas penuh model.
     */
    private function buildBody(array $config, array $messages): array
    {
        $body = [
            'model' => $config['model'],
            'messages' => $messages,
            'temperature' => 0.1,
            'stream' => false,
        ];

        if (config('chatbot.disable_thinking', true)) {
            $body['thinking'] = ['type' => 'disabled'];
        }

        return $body;
    }

    private function postProvider(array $config, array $body, int $timeout)
    {
        return Http::timeout($timeout)
            ->acceptJson()
            ->withHeaders(['Authorization' => 'Bearer ' . ($config['api_key'] ?? '')])
            ->post($config['base_url'], $body);
    }

    /**
     * Apakah respons berupa SSE streaming, bukan JSON utuh?
     */
    private function responsBerupaStream($response): bool
    {
        $tipe = strtolower((string) $response->header('Content-Type'));

        if (str_contains($tipe, 'text/event-stream')) {
            return true;
        }

        return str_starts_with(ltrim((string) $response->body()), 'data:');
    }

    /**
     * Satukan potongan SSE (`data: {...}` per baris) menjadi satu teks.
     * Potongan reasoning (reasoning_content) TIDAK dipakai sebagai jawaban.
     */
    private function bacaStreamProvider(string $body): string
    {
        $teks = '';

        $barisStream = explode("\n", str_replace(["\r\n", "\r"], "\n", $body));

        foreach ($barisStream as $baris) {
            $baris = trim($baris);

            if ($baris === '' || !str_starts_with($baris, 'data:')) {
                continue;
            }

            $isi = trim(substr($baris, 5));

            if ($isi === '' || $isi === '[DONE]') {
                continue;
            }

            $chunk = json_decode($isi, true);

            if (!is_array($chunk)) {
                continue;
            }

            $delta = $chunk['choices'][0]['delta'] ?? ($chunk['choices'][0]['message'] ?? null);

            if (!is_array($delta)) {
                continue;
            }

            $content = $delta['content'] ?? null;

            if (is_string($content)) {
                $teks .= $content;
            } elseif (is_array($content)) {
                foreach ($content as $part) {
                    if (is_string($part)) {
                        $teks .= $part;
                    } elseif (is_array($part)) {
                        $teks .= (string) ($part['text'] ?? '');
                    }
                }
            }
        }

        return trim($teks);
    }

    /**
     * Apakah provider berhenti karena kehabisan budget token?
     */
    private function jawabanTerpotong(array $data): bool
    {
        foreach ((array) ($data['choices'] ?? []) as $choice) {
            if (is_array($choice) && ($choice['finish_reason'] ?? null) === 'length') {
                return true;
            }
        }

        return false;
    }

    /**
     * Statistik pertanyaan Likert, diurutkan dari rata-rata terendah (terburuk).
     * Hanya pertanyaan yang sudah punya jawaban yang diikutkan.
     */
    public function worstScoreStats(int $limit = 10)
    {
        $rows = DB::table('satisfaction_responses as r')
            ->join('satisfaction_questions as q', 'q.id', '=', 'r.question_id')
            ->where('q.question_type', 'likert')
            ->where('q.is_active', true)
            ->whereNotNull('r.score')
            ->groupBy('q.id', 'q.section', 'q.question_text')
            ->selectRaw('q.id as question_id, q.section, q.question_text, AVG(CAST(r.score AS FLOAT)) as average_score, COUNT(r.score) as total_scores')
            ->orderBy('average_score')
            ->orderBy('total_scores', 'desc')
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) {
            $distribution = DB::table('satisfaction_responses')
                ->where('question_id', $row->question_id)
                ->whereNotNull('score')
                ->selectRaw('score, COUNT(*) as total')
                ->groupBy('score')
                ->pluck('total', 'score');

            $counts = [];
            for ($score = 1; $score <= 5; $score++) {
                $counts[$score] = (int) ($distribution[$score] ?? 0);
            }

            return [
                'question_id' => (int) $row->question_id,
                'section' => (string) $row->section,
                'question_text' => (string) $row->question_text,
                'average_score' => round((float) $row->average_score, 2),
                'total_scores' => (int) $row->total_scores,
                'distribution' => $counts,
            ];
        })->values();
    }

    private function buildPrompt($answers, $scoreStats): string
    {
        $prompt = "Anda menerima dua sumber data kuesioner kepuasan karyawan.\n"
            . "A. Jawaban esai terbuka (answer_text).\n"
            . "B. Rata-rata skor Likert 1-5 per pertanyaan, sudah diurutkan dari yang TERBURUK.\n\n"
            . "Tugas:\n"
            . "1. Tentukan 3 masalah utama dari A (keluhan/kritik yang paling sering disebut).\n"
            . "2. Tentukan 3 permintaan/harapan karyawan dari A (hal yang paling banyak diminta karyawan).\n"
            . "3. Tentukan 3 masalah utama dari B, dasarkan pada pertanyaan dengan rata-rata rendah dan jumlah jawaban skor 1-2.\n\n"
            . "ATURAN PENTING:\n"
            . "- Selalu berikan TEPAT 3 item untuk setiap kategori (answers, requests, scores) selama datanya mencukupi.\n"
            . "- Hanya bila data benar-benar kurang dari 3, berikan sebanyak yang tersedia (boleh kurang dari 3).\n"
            . "- Setiap item WAJIB punya title dan summary yang berbeda satu sama lain (tidak boleh duplikat).\n"
            . "- Gunakan Bahasa Indonesia. Jangan menulis karakter Mandarin/Han/aksara non-Latin.\n"
            . "- Jangan mengarang data di luar masukan. Balas hanya JSON dengan bentuk:\n"
            . "{\"answers\":[{\"title\":string,\"summary\":string,\"evidence_count\":integer}],"
            . "\"requests\":[{\"title\":string,\"summary\":string,\"evidence_count\":integer}],"
            . "\"scores\":[{\"title\":string,\"summary\":string,\"question_id\":integer,\"average_score\":number}]}\n\n"
            . "A. Jawaban esai:\n";

        if ($answers->isEmpty()) {
            $prompt .= "(tidak ada jawaban esai)\n";
        } else {
            foreach ($answers as $index => $answer) {
                $prompt .= ($index + 1) . '. ' . mb_substr($answer, 0, 1200) . "\n";
            }
        }

        $prompt .= "\nB. Skor Likert per pertanyaan (terburuk lebih dulu):\n";
        if ($scoreStats->isEmpty()) {
            $prompt .= "(tidak ada skor Likert)\n";
        } else {
            foreach ($scoreStats as $index => $stat) {
                $prompt .= ($index + 1) . '. question_id=' . $stat['question_id']
                    . ' | section=' . $stat['section']
                    . ' | rata-rata=' . $stat['average_score']
                    . ' | jawaban=' . $stat['total_scores']
                    . ' | skor1=' . $stat['distribution'][1]
                    . ' skor2=' . $stat['distribution'][2]
                    . ' | teks: ' . mb_substr($stat['question_text'], 0, 300) . "\n";
            }
        }

        return $prompt;
    }

    /**
     * Sebagian provider mengirim content sebagai string, sebagian lagi sebagai
     * array part. Keduanya diratakan menjadi teks.
     */
    private function extractContent($json): string
    {
        if (!is_array($json)) {
            return '';
        }

        $message = $json['choices'][0]['message'] ?? null;
        $content = is_array($message) ? ($message['content'] ?? null) : ($json['choices'][0]['text'] ?? null);

        if (is_string($content)) {
            return trim($content);
        }

        if (is_array($content)) {
            $parts = [];
            foreach ($content as $part) {
                if (is_string($part)) {
                    $parts[] = $part;
                } elseif (is_array($part)) {
                    $parts[] = (string) ($part['text'] ?? ($part['content'] ?? ''));
                }
            }

            return trim(implode("\n", $parts));
        }

        return '';
    }

    /**
     * Model kadang membungkus JSON dengan pagar kode, kalimat pembuka, atau
     * teks naratif sesudahnya. Strategi berlapis:
     * 1. Strip markdown code-fence lalu decode langsung.
     * 2. Scan semua kombinasi {...} (start × end), ambil array pertama yang valid.
     * 3. Perbaiki JSON terpotong (tutup kurung/kurung kurawal yang belum tutup).
     */
    private function decodePayload(string $content): array
    {
        $clean = preg_replace('/```(?:json)?\s*([\s\S]*?)\s*```/i', '$1', $content);
        $clean = trim((string) $clean);

        $direct = json_decode($clean, true);
        if (is_array($direct)) {
            return $direct;
        }

        $mulai = strpos($clean, '{');

        // Utamakan objek UTUH dari '{' pertama (termasuk yang terpotong):
        // memperbaiki objek terluar menjaga kunci answers/requests/scores tetap ada.
        // Scan sub-slice lebih dulu justru bisa menemukan objek dalam dan
        // membuang kunci terluar — inilah sebab "keys diterima: []".
        if ($mulai !== false) {
            $repaired = $this->perbaikiJsonTerpotong(substr($clean, $mulai));
            if ($repaired !== null) {
                return $repaired;
            }
        }

        // Cadangan: coba semua kombinasi start × end bila perbaikan terluar gagal.
        $len = strlen($clean);
        $starts = [];
        $ends = [];

        for ($i = 0; $i < $len; $i++) {
            if ($clean[$i] === '{') {
                $starts[] = $i;
            }
        }
        for ($i = $len - 1; $i >= 0; $i--) {
            if ($clean[$i] === '}') {
                $ends[] = $i;
            }
        }

        foreach ($starts as $s) {
            foreach ($ends as $e) {
                if ($e <= $s) {
                    continue;
                }
                $decoded = json_decode(substr($clean, $s, $e - $s + 1), true);
                if (is_array($decoded) && !empty($decoded)) {
                    return $decoded;
                }
            }
        }

        return [];
    }

    /**
     * Perbaiki JSON yang terpotong di tengah.
     *
     * Penutupan struktur memakai pendekatan stack (bukan sekadar menghitung
     * jumlah '{' vs '}'), karena urutan penutup menentukan validitas:
     * untuk `[{"a":1` penutup yang benar adalah `}]`, bukan `]}`.
     *
     * Dilakukan bertahap: coba tutup pada posisi terakhir, bila masih tidak
     * valid potong satu karakter dari ujung dan coba lagi (terbatas), sehingga
     * sisa potongan seperti `..."requ` atau angka yang menggantung ikut dibuang.
     */
    private function perbaikiJsonTerpotong(string $json): ?array
    {
        // Buang karakter kontrol yang tidak valid di dalam string.
        $json = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $json);

        // Mulai dari '{' pertama (buang teks pembuka).
        $mulai = strpos($json, '{');
        if ($mulai === false) {
            return null;
        }
        $json = substr($json, $mulai);

        $kandidat = $json;

        for ($percobaan = 0; $percobaan < 400 && $kandidat !== ''; $percobaan++) {
            $uji = rtrim($kandidat, " \t\n\r,");

            // Tutup string yang belum ditutup (kutip ganda ganjil).
            if ($this->kutipGanjil($uji)) {
                $uji .= '"';
            }

            // Buang pasangan key yang menggantung di ujung ("key": atau "key": ).
            $uji = rtrim($uji, " \t\n\r,");
            $uji = preg_replace('/(,\s*"[^"]*"\s*:\s*)$/', '', $uji) ?? $uji;
            $uji = rtrim($uji, " \t\n\r,");

            $decoded = json_decode($uji . $this->penutupStruktur($uji), true);

            if (is_array($decoded) && !empty($decoded)) {
                return $decoded;
            }

            // Belum valid: potong satu karakter dari ujung lalu coba lagi.
            $kandidat = substr($kandidat, 0, -1);
        }

        return null;
    }

    /**
     * Apakah jumlah kutip ganda yang belum tertutup ganjil?
     * Escape (`\"`) diabaikan.
     */
    private function kutipGanjil(string $teks): bool
    {
        $dalamString = false;
        $escape = false;
        $len = strlen($teks);

        for ($i = 0; $i < $len; $i++) {
            $c = $teks[$i];

            if ($escape) {
                $escape = false;
                continue;
            }
            if ($c === '\\') {
                $escape = true;
                continue;
            }
            if ($c === '"') {
                $dalamString = !$dalamString;
            }
        }

        return $dalamString;
    }

    /**
     * Susun teks penutup ({, }, [, ]) sesuai struktur yang masih terbuka,
     * dari terdalam ke terluar. Karakter di dalam string diabaikan.
     */
    private function penutupStruktur(string $teks): string
    {
        $stack = [];
        $dalamString = false;
        $escape = false;
        $len = strlen($teks);

        for ($i = 0; $i < $len; $i++) {
            $c = $teks[$i];

            if ($dalamString) {
                if ($escape) {
                    $escape = false;
                } elseif ($c === '\\') {
                    $escape = true;
                } elseif ($c === '"') {
                    $dalamString = false;
                }
                continue;
            }

            if ($c === '"') {
                $dalamString = true;
            } elseif ($c === '{') {
                $stack[] = '}';
            } elseif ($c === '[') {
                $stack[] = ']';
            } elseif ($c === '}' || $c === ']') {
                array_pop($stack);
            }
        }

        return implode('', array_reverse($stack));
    }

    /**
     * Menormalkan daftar masalah: nama field alternatif yang sangat luas,
     * deduplikasi, bersih dari aksara CJK, maksimal tiga item.
     */
    private function normalizeProblems($problems, string $key): array
    {
        if (!is_array($problems)) {
            return [];
        }

        // Array asosiatif satu item (tanpa index numerik) dibungkus jadi list.
        if (!isset($problems[0]) && (isset($problems['title']) || isset($problems['summary']))) {
            $problems = [$problems];
        }

        $normalized = [];
        $terpakai = [];

        foreach ($problems as $problem) {
            if (!is_array($problem)) {
                if (is_string($problem) && trim($problem) !== '') {
                    $teks = $this->bersihkanTeks($problem);
                    if ($teks !== '') {
                        $normalized[] = $this->buatItem($teks, $teks, $key, []);
                    }
                }
                continue;
            }

            $title = $this->bersihkanTeks((string) (
                $problem['title'] ?? $problem['problem'] ?? $problem['name'] ??
                $problem['judul'] ?? $problem['masalah'] ?? $problem['topik'] ??
                $problem['permintaan'] ?? $problem['harapan'] ?? $problem['keluhan'] ?? ''
            ));

            $summary = $this->bersihkanTeks((string) (
                $problem['summary'] ?? $problem['description'] ?? $problem['detail'] ??
                $problem['uraian'] ?? $problem['penjelasan'] ?? $problem['deskripsi'] ??
                $problem['keterangan'] ?? $problem['ringkasan'] ?? ''
            ));

            if ($title === '' && $summary !== '') {
                $title = mb_substr($summary, 0, 80);
            }
            if ($summary === '' && $title !== '') {
                $summary = $title;
            }
            if ($title === '') {
                continue;
            }

            // Deduplikasi: judul yang sudah muncul tidak dimasukkan lagi.
            $kunci = mb_strtolower($title);
            if (isset($terpakai[$kunci])) {
                continue;
            }
            $terpakai[$kunci] = true;

            $normalized[] = $this->buatItem($title, $summary, $key, $problem);

            if (count($normalized) >= 3) {
                break;
            }
        }

        return $normalized;
    }

    private function buatItem(string $title, string $summary, string $key, array $problem): array
    {
        $item = [
            'title' => mb_substr($title, 0, 160),
            'summary' => mb_substr($summary, 0, 600),
        ];

        if ($key === 'answers') {
            $item['evidence_count'] = (int) (
                $problem['evidence_count'] ?? $problem['count'] ??
                $problem['jumlah'] ?? $problem['frekuensi'] ?? 0
            );
        } else {
            $item['question_id'] = (int) ($problem['question_id'] ?? $problem['id'] ?? 0);
            $item['average_score'] = round((float) ($problem['average_score'] ?? $problem['score'] ?? $problem['rata_rata'] ?? 0), 2);
        }

        return $item;
    }

    /**
     * Bersihkan teks dari aksara CJK (Han, Hiragana, Katakana, Hangul,
     * fullwidth, dan blok terkait) agar tidak bocor ke tampilan.
     */
    private function bersihkanTeks(string $teks): string
    {
        $teks = preg_replace(
            '/[\x{2E80}-\x{2EFF}\x{3000}-\x{303F}\x{3040}-\x{309F}\x{30A0}-\x{30FF}'
            . '\x{3130}-\x{318F}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{AC00}-\x{D7AF}'
            . '\x{F900}-\x{FAFF}\x{FE30}-\x{FE4F}\x{FF00}-\x{FFEF}\x{20000}-\x{2FA1F}]/u',
            '',
            $teks
        );

        // Rapatkan spasi berlebih yang tertinggal setelah karakter dibuang.
        $teks = preg_replace('/\s+/', ' ', (string) $teks);

        return trim((string) $teks);
    }
}
