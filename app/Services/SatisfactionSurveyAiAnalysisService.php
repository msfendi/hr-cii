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
                    ['role' => 'system', 'content' => 'Anda analis kepuasan karyawan. Ringkas, faktual, dan kembalikan JSON valid saja.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.2,
                'max_tokens' => 1600,
                'stream' => false,
            ]);

        if (!$request->successful()) {
            throw new RuntimeException('9router gagal memproses analisis survei (HTTP ' . $request->status() . ').');
        }

        $content = trim((string) data_get($request->json(), 'choices.0.message.content', ''));
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $content);
        $decoded = json_decode($content, true);

        $answerProblems = $this->validProblems($decoded['answers'] ?? null, 'answers');
        $scoreProblems = $this->validProblems($decoded['scores'] ?? null, 'scores');

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

    private function validProblems($problems, string $key): array
    {
        if (!is_array($problems) || count($problems) !== 3) {
            throw new RuntimeException("Jawaban 9router tidak berisi tepat tiga masalah pada bagian {$key}.");
        }

        foreach ($problems as $problem) {
            if (!is_array($problem)
                || trim((string) ($problem['title'] ?? '')) === ''
                || trim((string) ($problem['summary'] ?? '')) === '') {
                throw new RuntimeException("Struktur masalah pada bagian {$key} tidak lengkap.");
            }
        }

        return array_values($problems);
    }
}
