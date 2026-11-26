<?php

namespace App\Services;

use App\Models\SatisfactionQuestion;
use App\Models\SatisfactionResponse;
use App\Models\SatisfactionSurveyAiAnalysis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SatisfactionSurveyAiAnalysisService
{
    /**
     * Analisis dua sisi: keluhan tertulis (answer_text) dan pertanyaan dengan
     * skor Likert terburuk (question_id + score). Keduanya menghasilkan tepat
     * tiga masalah, lalu disimpan dalam satu baris analisis.
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
            throw new RuntimeException('Konfigurasi 9router belum lengkap.');
        }

        $prompt = $this->buildPrompt($answers, $scoreStats);

        $request = Http::timeout((int) config('chatbot.request_timeout', 120))
            ->acceptJson()
            ->withHeaders(['Authorization' => 'Bearer ' . ($config['api_key'] ?? '')])
            ->post($config['base_url'], [
                'model' => $config['model'],
                'messages' => [
                    ['role' => 'system', 'content' => 'Anda analis kepuasan karyawan. Tulis semua title dan summary dalam Bahasa Indonesia yang ringkas dan faktual. Kembalikan JSON valid saja.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2,
                'max_tokens' => 1600,
                'stream' => false,
            ]);

        if (!$request->successful()) {
            throw new RuntimeException('9router gagal memproses analisis survei (HTTP ' . $request->status() . ').');
        }

        $content = $this->extractContent($request->json());
        $decoded = $this->decodePayload($content);

        $answerProblems = $this->normalizeProblems(
            $decoded['answers'] ?? $decoded['answer_problems'] ?? $decoded['problems'] ?? $decoded['top_answers'] ?? null,
            'answers',
            $content
        );
        $scoreProblems = $this->normalizeProblems(
            $decoded['scores'] ?? $decoded['score_problems'] ?? $decoded['top_scores'] ?? null,
            'scores',
            $content
        );

        if ($answerProblems === [] && $scoreProblems === []) {
            throw new RuntimeException('9router tidak mengembalikan masalah yang bisa dibaca. Cuplikan jawaban: ' . mb_substr($content, 0, 300));
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
                    'answers' => $answerProblems,
                    'scores' => $scoreProblems,
                    // Kompatibilitas dengan analisis versi sebelumnya.
                    'problems' => $answerProblems,
                ],
                'generated_at' => now(),
            ]
        );
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
            . "Tugas: tentukan tepat 3 masalah utama dari A dan tepat 3 masalah utama dari B.\n"
            . "Untuk B, dasarkan kesimpulan pada pertanyaan dengan rata-rata rendah dan jumlah jawaban skor 1-2.\n"
            . "Gunakan Bahasa Indonesia untuk semua title dan summary.\n"
            . "Jangan mengarang data di luar masukan. Balas hanya JSON dengan bentuk:\n"
            . "{\"answers\":[{\"title\":string,\"summary\":string,\"evidence_count\":integer}],"
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
        $content = data_get($json, 'choices.0.message.content');

        if (is_array($content)) {
            $content = implode("\n", array_map(function ($part) {
                if (is_string($part)) {
                    return $part;
                }
                return is_array($part) ? (string) ($part['text'] ?? '') : '';
            }, $content));
        }

        return trim((string) $content);
    }

    /**
     * Model kadang membungkus JSON dengan pagar kode atau kalimat pembuka.
     * Ambil objek JSON pertama yang valid.
     */
    private function decodePayload(string $content): array
    {
        $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $content);
        $clean = trim((string) $clean);

        $direct = json_decode($clean, true);
        if (is_array($direct)) {
            return $direct;
        }

        $start = strpos($clean, '{');
        $end = strrpos($clean, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $slice = substr($clean, $start, $end - $start + 1);
            $decoded = json_decode($slice, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Menormalkan daftar masalah: nama field alternatif, maksimal tiga item,
     * dan item yang tidak lengkap dibuang. Tiga item adalah target, bukan
     * syarat mutlak: bila model hanya memberi dua, dua itu tetap disimpan.
     */
    private function normalizeProblems($problems, string $key, string $raw = ''): array
    {
        if (!is_array($problems)) {
            return [];
        }

        $normalized = [];
        foreach ($problems as $problem) {
            if (!is_array($problem)) {
                continue;
            }

            $title = trim((string) ($problem['title'] ?? $problem['problem'] ?? $problem['name'] ?? ''));
            $summary = trim((string) ($problem['summary'] ?? $problem['description'] ?? $problem['detail'] ?? ''));
            if ($title === '' || $summary === '') {
                continue;
            }

            $item = ['title' => $title, 'summary' => $summary];

            if ($key === 'answers') {
                $item['evidence_count'] = (int) ($problem['evidence_count'] ?? $problem['count'] ?? 0);
            } else {
                $item['question_id'] = (int) ($problem['question_id'] ?? 0);
                $item['average_score'] = round((float) ($problem['average_score'] ?? 0), 2);
            }

            $normalized[] = $item;
        }

        return array_slice($normalized, 0, 3);
    }
}
