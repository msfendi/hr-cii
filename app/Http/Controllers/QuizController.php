<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizOption;
use App\Models\QuizSession;
use App\Models\QuizResponse;

class QuizController extends Controller
{
    /**
     * Daftar kuis (Admin/HRD).
     */
    public function index()
    {
        $quizzes = Quiz::withCount(['questions', 'sessions'])->latest()->paginate(15);
        return view('quiz.index', compact('quizzes'));
    }

    /**
     * Form buat kuis baru.
     */
    public function create()
    {
        return view('quiz.create');
    }

    /**
     * Simpan kuis baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string',
            'mode'        => 'required|in:' . implode(',', Quiz::MODES),
        ]);

        $qrCode = 'QUIZ-' . Str::upper(Str::random(8));

        $quiz = Quiz::create([
            'title'       => $validated['title'],
            'description' => $validated['description'],
            'mode'        => $validated['mode'],
            'qr_code'     => $qrCode,
            'status'      => 'draft',
            'created_by'  => auth()->id() ?? null,
        ]);

        return redirect()->route('quiz.edit', $quiz->id)
            ->with('success', $quiz->modeLabel() . ' berhasil dibuat. Silakan tambahkan pertanyaan.');
    }

    /**
     * Form edit kuis + manajemen pertanyaan.
     */
    public function edit(Quiz $quiz)
    {
        $quiz->load('questions.options');
        return view('quiz.edit', compact('quiz'));
    }

    /**
     * Update kuis (judul/deskripsi).
     */
    public function update(Request $request, Quiz $quiz)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:200',
            'description' => 'nullable|string',
        ]);

        $quiz->update($validated);

        return redirect()->route('quiz.edit', $quiz->id)
            ->with('success', 'Kuis berhasil diperbarui.');
    }

    /**
     * Tambah pertanyaan ke kuis/polling/word-cloud.
     * Tipe yang diterima bergantung pada mode kuis:
     *   - quiz        : single_choice | multiple_choice (wajib ada opsi benar)
     *   - polling     : single_choice (tanpa konsep benar/salah)
     *   - word_cloud  : open_text (teks bebas, tanpa opsi)
     */
    public function addQuestion(Request $request, Quiz $quiz)
    {
        $allowedTypes = $quiz->allowedQuestionTypes();

        $validated = $request->validate([
            'question_text' => 'required|string|max:500',
            'type'          => 'required|in:' . implode(',', $allowedTypes),
            'options'       => in_array('open_text', $allowedTypes) && !in_array('single_choice', $allowedTypes)
                                ? 'nullable|array|min:2'
                                : 'required|array|min:2',
            'options.*.text'     => 'required_with:options|string|max:200',
            'options.*.is_correct' => 'nullable|in:0,1',
        ]);

        // Untuk mode kuis (bukan polling), wajib minimal satu jawaban benar.
        if ($quiz->isQuiz()) {
            $hasCorrect = false;
            foreach (($validated['options'] ?? []) as $opt) {
                if ((int)($opt['is_correct'] ?? 0) === 1) {
                    $hasCorrect = true;
                    break;
                }
            }
            if (!$hasCorrect) {
                return back()->withErrors(['options' => 'Minimal satu opsi harus ditandai sebagai jawaban benar.'])->withInput();
            }
        }

        DB::transaction(function () use ($quiz, $validated) {
            $sortOrder = $quiz->questions()->max('sort_order') ?? -1;
            $question = QuizQuestion::create([
                'quiz_id'      => $quiz->id,
                'question_text'=> $validated['question_text'],
                'type'         => $validated['type'],
                'sort_order'   => $sortOrder + 1,
            ]);

            // Word cloud (open_text) tidak punya opsi.
            if ($validated['type'] === 'open_text') {
                return;
            }

            foreach ($validated['options'] as $idx => $opt) {
                QuizOption::create([
                    'question_id' => $question->id,
                    'option_text' => $opt['text'],
                    'is_correct'  => $opt['is_correct'] ?? 0,
                    'sort_order'  => $idx,
                ]);
            }
        });

        return redirect()->route('quiz.edit', $quiz->id)
            ->with('success', 'Pertanyaan berhasil ditambahkan.');
    }

    /**
     * Hapus pertanyaan.
     */
    public function deleteQuestion(Quiz $quiz, QuizQuestion $question)
    {
        // PERBAIKAN V15 (2026-11-06): foreign key quiz_responses.question_id
        // memakai NO ACTION (bukan cascade) supaya migrasi tidak gagal di
        // SQL Server dengan pesan "multiple cascade paths". Karena itu
        // jawaban yang merujuk ke pertanyaan ini harus dihapus manual
        // lebih dulu, termasuk jawaban pada opsi pertanyaannya.
        QuizResponse::where('question_id', $question->id)->delete();

        $question->delete();
        return redirect()->route('quiz.edit', $quiz->id)
            ->with('success', 'Pertanyaan dihapus.');
    }

    /**
     * Mulai kuis (ubah status ke active, generate QR, set started_at).
     */
    public function start(Quiz $quiz)
    {
        if ($quiz->status === 'closed') {
            return back()->with('error', 'Kuis sudah ditutup, tidak bisa dimulai ulang.');
        }

        $quiz->update([
            'status'      => 'active',
            'started_at'  => now(),
            'closed_at'   => null,
        ]);

        return redirect()->route('quiz.live', $quiz->id)
            ->with('success', 'Kuis dimulai. Peserta bisa scan QR untuk bergabung.');
    }

    /**
     * Tampilan live (Admin) - polling hasil realtime.
     */
    public function live(Quiz $quiz)
    {
        $quiz->load(['questions.options']);
        return view('quiz.live', compact('quiz'));
    }

    /**
     * Tutup kuis.
     */
    public function close(Quiz $quiz)
    {
        $quiz->update([
            'status'     => 'closed',
            'closed_at'  => now(),
        ]);

        return redirect()->route('quiz.index')
            ->with('success', 'Kuis ditutup. Hasil akhir terkunci.');
    }

    /**
     * Hapus kuis.
     */
    public function destroy(Quiz $quiz)
    {
        // PERBAIKAN V15 (2026-11-06): karena foreign key quiz_responses
        // ke quiz_questions dan quiz_options memakai NO ACTION, jawaban
        // tidak ikut terhapus otomatis dari sisi itu. Hapus manual
        // berurutan (jawaban -> sesi -> pertanyaan -> kuis) supaya tidak
        // kena pelanggaran foreign key di SQL Server.
        $questionIds = QuizQuestion::where('quiz_id', $quiz->id)->pluck('id');
        $sessionIds  = QuizSession::where('quiz_id', $quiz->id)->pluck('id');

        if ($questionIds->isNotEmpty()) {
            QuizResponse::whereIn('question_id', $questionIds)->delete();
        }
        if ($sessionIds->isNotEmpty()) {
            QuizResponse::whereIn('session_id', $sessionIds)->delete();
            QuizSession::whereIn('id', $sessionIds)->delete();
        }
        if ($questionIds->isNotEmpty()) {
            QuizQuestion::whereIn('id', $questionIds)->delete();
        }

        $quiz->delete();
        return redirect()->route('quiz.index')
            ->with('success', 'Kuis dihapus.');
    }

    /**
     * API: Data polling untuk tampilan live (Admin).
     * Mengembalikan ringkasan jawaban per pertanyaan + peserta aktif.
     *
     * Per tipe pertanyaan:
     *   - single/multiple_choice : hitung vote per opsi + persentase.
     *   - open_text              : tokenisasi jawaban, agregasi frekuensi kata
     *                              untuk word cloud (top N term + weight).
     */
    public function liveData(Quiz $quiz)
    {
        $questions = $quiz->questions()->with('options.responses')->get();

        $summary = [];
        $totalResponses = 0;

        foreach ($questions as $q) {
            if ($q->type === 'open_text') {
                // ---- WORD CLOUD ----
                // Ambil juga jawaban teks mentah untuk daftar "jawaban terbaru".
                $responses = QuizResponse::where('question_id', $q->id)
                    ->whereNotNull('answer_text')
                    ->orderByDesc('answered_at')
                    ->take(50)
                    ->get(['id', 'session_id', 'answer_text', 'answered_at']);
                $texts = $responses->pluck('answer_text');

                $freq = [];
                foreach ($texts as $txt) {
                    foreach (QuizResponse::tokenize($txt) as $w) {
                        $freq[$w] = ($freq[$w] ?? 0) + 1;
                    }
                }
                arsort($freq);
                $topWords = array_slice($freq, 0, 60, true);
                $maxFreq  = $topWords ? reset($topWords) : 0;

                $cloud = [];
                foreach ($topWords as $word => $cnt) {
                    // weight 1..10 — skala ukuran font di sisi klien.
                    $weight = $maxFreq > 0 ? max(1, (int)round(($cnt / $maxFreq) * 10)) : 1;
                    $cloud[] = ['word' => $word, 'count' => $cnt, 'weight' => $weight];
                }

                $totalResponses += $texts->count();
                $summary[] = [
                    'question_id'   => $q->id,
                    'question_text' => $q->question_text,
                    'type'          => 'open_text',
                    'total_votes'   => $texts->count(),
                    'word_cloud'    => $cloud,
                    'recent'        => $responses->map(function ($r) {
                        return [
                            'id'           => $r->id,
                            'answer'       => $r->answer_text,
                            'answered_at'  => $r->answered_at ? $r->answered_at->format('H:i:s') : null,
                        ];
                    })->values(),
                ];
                continue;
            }

            // ---- CHOICE (quiz / polling) ----
            $totalVotes = 0;
            $optionCounts = [];

            foreach ($q->options as $opt) {
                $count = $opt->responses()->count();
                $totalVotes += $count;
                $optionCounts[] = [
                    'id'         => $opt->id,
                    'text'       => $opt->option_text,
                    'is_correct' => $opt->is_correct,
                    'count'      => $count,
                ];
            }

            // Hitung percentage SETELAH totalVotes diketahui (fix division-by-zero)
            foreach ($optionCounts as &$oc) {
                $oc['percentage'] = $totalVotes > 0 ? round(($oc['count'] / $totalVotes) * 100, 1) : 0;
            }
            unset($oc);

            $totalResponses += $totalVotes;
            $summary[] = [
                'question_id'   => $q->id,
                'question_text' => $q->question_text,
                'type'          => $q->type,
                'total_votes'   => $totalVotes,
                'options'       => $optionCounts,
            ];
        }

        $activeSessions = $quiz->sessions()->count();

        return response()->json([
            'quiz_status'     => $quiz->status,
            'quiz_mode'       => $quiz->mode,
            'quiz_mode_label' => $quiz->modeLabel(),
            'active_sessions' => $activeSessions,
            'total_responses' => $totalResponses,
            'questions'       => $summary,
            'timestamp'       => now()->toIso8601String(),
        ]);
    }

    // ===========================
    // PARTICIPANT FLOW (Scan QR)
    // ===========================

    /**
     * Halaman bergabung via QR (peserta).
     * GET /quiz/join/{qr_code}
     */
    public function joinShow($qrCode)
    {
        $quiz = Quiz::where('qr_code', $qrCode)->firstOrFail();

        if ($quiz->status !== 'active') {
            return view('quiz.join_closed', compact('quiz'));
        }

        return view('quiz.join', compact('quiz'));
    }

    /**
     * Proses bergabung - simpan sesi peserta.
     * POST /quiz/join/{qr_code}
     */
    public function joinStore(Request $request, $qrCode)
    {
        $quiz = Quiz::where('qr_code', $qrCode)->firstOrFail();

        if ($quiz->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'Kuis tidak aktif.'], 403);
        }

        $validated = $request->validate([
            'participant_name' => 'required|string|max:100',
        ]);

        $session = QuizSession::create([
            'quiz_id'         => $quiz->id,
            'participant_name'=> $validated['participant_name'],
            'participant_ip'  => $request->ip(),
            'joined_at'       => now(),
        ]);

        return response()->json([
            'success'     => true,
            'session_id'  => $session->id,
            'quiz_title'  => $quiz->title,
            'questions'   => $quiz->questions()->with('options')->get(),
        ]);
    }

    /**
     * Halaman menjawab soal (peserta).
     * GET /quiz/session/{session_id}/question/{question_id}
     */
    public function sessionQuestion(QuizSession $session, QuizQuestion $question)
    {
        // Pastikan pertanyaan milik kuis yang sama
        if ($session->quiz_id !== $question->quiz_id) {
            abort(404);
        }

        $quiz = $session->quiz()->withCount('questions')->first();
        $question->load('options');

        // Cek apakah sudah dijawab
        $existing = QuizResponse::where('session_id', $session->id)
            ->where('question_id', $question->id)
            ->first();

        $nextQuestion = QuizQuestion::where('quiz_id', $quiz->id)
            ->where('sort_order', '>', $question->sort_order)
            ->orderBy('sort_order')
            ->first();

        return view('quiz.session_question', compact('session', 'quiz', 'question', 'existing', 'nextQuestion'));
    }

    /**
     * Simpan jawaban peserta.
     * POST /quiz/session/{session_id}/answer
     *
     * Payload berbeda per mode/tipe pertanyaan:
     *   - pilihan (single/multiple): option_id
     *   - open_text (word_cloud)   : answer_text
     */
    public function submitAnswer(Request $request, QuizSession $session)
    {
        $validated = $request->validate([
            'question_id' => 'required|exists:quiz_questions,id',
            'option_id'   => 'nullable|required_without:answer_text|exists:quiz_options,id',
            'answer_text' => 'nullable|required_without:option_id|string|max:500',
        ]);

        $question = QuizQuestion::findOrFail($validated['question_id']);

        // Validasi kepemilikan
        if ($session->quiz_id !== $question->quiz_id) {
            return response()->json(['success' => false, 'message' => 'Pertanyaan tidak valid.'], 403);
        }

        // Cek duplikat jawaban
        $existing = QuizResponse::where('session_id', $session->id)
            ->where('question_id', $question->id)
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Pertanyaan sudah dijawab.'], 422);
        }

        // Guard: tipe pertanyaan harus cocok dengan payload.
        if ($question->type === 'open_text') {
            if (empty(trim((string)$validated['answer_text']))) {
                return response()->json(['success' => false, 'message' => 'Jawaban teks tidak boleh kosong.'], 422);
            }
            QuizResponse::create([
                'session_id'  => $session->id,
                'question_id' => $question->id,
                'option_id'   => null,
                'answer_text' => trim($validated['answer_text']),
                'answered_at' => now(),
            ]);
        } else {
            if (empty($validated['option_id'])) {
                return response()->json(['success' => false, 'message' => 'Opsi jawaban wajib dipilih.'], 422);
            }
            // Pastikan opsi memang milik pertanyaan ini.
            $optionBelongs = QuizOption::where('id', $validated['option_id'])
                ->where('question_id', $question->id)
                ->exists();
            if (!$optionBelongs) {
                return response()->json(['success' => false, 'message' => 'Opsi tidak valid untuk pertanyaan ini.'], 422);
            }
            QuizResponse::create([
                'session_id'  => $session->id,
                'question_id' => $question->id,
                'option_id'   => $validated['option_id'],
                'answer_text' => null,
                'answered_at' => now(),
            ]);
        }

        // Cari pertanyaan berikutnya
        $nextQuestion = QuizQuestion::where('quiz_id', $question->quiz_id)
            ->where('sort_order', '>', $question->sort_order)
            ->orderBy('sort_order')
            ->first();

        return response()->json([
            'success'      => true,
            'message'      => 'Jawaban tersimpan.',
            'next_question_id' => $nextQuestion?->id,
            'next_question_text' => $nextQuestion?->question_text,
        ]);
    }

    /**
     * Hasil kuis untuk peserta setelah selesai.
     */
    public function sessionResult(QuizSession $session)
    {
        $quiz = $session->quiz;
        $session->load('responses.question.options');

        $score = 0;
        $total = 0;
        $detail = [];

        foreach ($session->responses as $response) {
            $total++;
            $option = $response->option;
            $isCorrect = $option->is_correct ?? false;
            if ($isCorrect) $score++;

            $detail[] = [
                'question'     => $response->question->question_text,
                'your_answer'  => $option->option_text ?? ($response->answer_text ?: '-'),
                'is_correct'   => $isCorrect,
            ];
        }

        $percentage = $total > 0 ? round(($score / $total) * 100, 1) : 0;

        return view('quiz.session_result', compact('session', 'quiz', 'score', 'total', 'percentage', 'detail'));
    }
}