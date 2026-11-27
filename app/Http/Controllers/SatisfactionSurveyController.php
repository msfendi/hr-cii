<?php

namespace App\Http\Controllers;

use App\Services\SurveySanitizer;
use App\Support\SatisfactionSurveyGuard;
use App\Models\SatisfactionQuestion;
use App\Models\SatisfactionResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SatisfactionSurveyResultExport;
use App\Exports\SatisfactionLikertDistributionExport;
use Barryvdh\DomPDF\Facade\Pdf;

class SatisfactionSurveyController extends Controller
{
    /**
     * Halaman kuesioner publik dengan jawaban tanpa identitas pribadi.
     *
     * Saat pertama dibuka, ID PERANGKAT dicek ke log pengisian. Bila perangkat
     * itu sudah pernah mengirim pada bulan berjalan, form tidak dirender dan
     * halaman menampilkan SweetAlert "Spam Detection".
     *
     * ID perangkat berasal dari cookie pertama-pihak (bukan IP/MAC, karena
     * keduanya tidak bisa dipakai sebagai identitas - lihat SatisfactionSurveyGuard).
     */
    public function create(Request $request)
    {
        // Pastikan perangkat punya ID (diisi cookie bila baru) sebelum dicek.
        SatisfactionSurveyGuard::pastikanDevice($request);

        $log = SatisfactionSurveyGuard::logTerakhir($request);

        if ($log !== null) {
            return response()->view('satisfaction_survey.create', [
                'questions' => collect(),
                'grouped' => collect(),
                'blokir' => SatisfactionSurveyGuard::PESAN_SUDAH,
                'blokirPada' => $log->created_at,
            ]);
        }

        $questions = SatisfactionQuestion::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $grouped = $questions->groupBy('section');

        return view('satisfaction_survey.create', [
            'questions' => $questions,
            'grouped' => $grouped,
            'blokir' => null,
            'blokirPada' => null,
        ]);
    }

    public function store(Request $request)
    {
        // Identitas perangkat dipastikan dulu supaya kunci yang dipakai untuk
        // memblokir sama dengan yang dikirim balik sebagai cookie.
        SatisfactionSurveyGuard::pastikanDevice($request);

        // Penjaga 1: maksimal 3 percobaan kirim per perangkat per 30 hari.
        if (SatisfactionSurveyGuard::terlaluBanyakPercobaan($request)) {
            return $this->tolakSpam($request, SatisfactionSurveyGuard::PESAN_LIMIT, 429);
        }
        SatisfactionSurveyGuard::hitPercobaan($request);

        // Penjaga 2: perangkat ini sudah mengirim pada bulan berjalan.
        if (SatisfactionSurveyGuard::sudahMengisi($request)) {
            return $this->tolakSpam($request, SatisfactionSurveyGuard::PESAN_SUDAH, 429);
        }

        $questionIds = SatisfactionQuestion::where('is_active', true)->pluck('id')->all();
        $rules = [];
        foreach ($questionIds as $qid) {
            $q = SatisfactionQuestion::find($qid);
            $rules["answers.{$qid}"] = $q && $q->isLikert()
                ? 'required|integer|between:1,5'
                : 'nullable|string|max:5000';
        }
        $rules['answers'] = 'required|array';
        $validated = $request->validate($rules);

        $submissionId = Schema::hasColumn('satisfaction_responses', 'submission_id')
            ? (string) \Illuminate\Support\Str::uuid()
            : null;

        try {
            DB::transaction(function () use ($validated, $request, $submissionId) {
                // Dicatat lebih dulu: bila perangkat yang sama menembus penjaga
                // secara bersamaan, unique index menolak dan seluruh transaksi
                // batal sehingga tidak ada jawaban ganda yang tersimpan.
                SatisfactionSurveyGuard::catat($request, $submissionId, true);

                foreach (($validated['answers'] ?? []) as $qid => $val) {
                    $q = SatisfactionQuestion::find($qid);
                    if (!$q) continue;

                    $payload = [
                        'question_id' => $q->id,
                        'score' => $q->isLikert() ? (int) $val : null,
                        'answer_text' => $q->isLikert()
                            ? null
                            : (SurveySanitizer::cleanText($val) ?: null),
                    ];
                    if ($submissionId !== null) {
                        $payload['submission_id'] = $submissionId;
                    }
                    SatisfactionResponse::create($payload);
                }
            });

            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Terima kasih atas partisipasi Anda.']);
            }

            return redirect()->route('satisfaction-survey.thanks')->with('success', 'Terima kasih atas partisipasi Anda.');
        } catch (\Illuminate\Database\QueryException $e) {
            if ($this->galatDuplikat($e)) {
                return $this->tolakSpam($request, SatisfactionSurveyGuard::PESAN_SUDAH, 429);
            }

            Log::error('Satisfaction survey store: ' . $e->getMessage());

            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Gagal menyimpan jawaban. Silakan coba lagi.'], 500);
            }

            return back()->withInput()->withErrors(['answers' => 'Gagal menyimpan jawaban. Silakan coba lagi.']);
        } catch (\Throwable $e) {
            Log::error('Satisfaction survey store: ' . $e->getMessage());
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Gagal menyimpan jawaban. Silakan coba lagi.'], 500);
            }
            return back()->withInput()->withErrors(['answers' => 'Gagal menyimpan jawaban. Silakan coba lagi.']);
        }
    }

    /** Balasan penolakan anti-spam: JSON untuk AJAX, flash + SweetAlert untuk form biasa. */
    private function tolakSpam(Request $request, string $pesan, int $status)
    {
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'spam' => true,
                'message' => $pesan,
            ], $status);
        }

        return redirect()->route('satisfaction-survey.create')->with('spam_block', $pesan);
    }

    /** True bila exception berasal dari unique index anti-spam. */
    private function galatDuplikat(\Throwable $e): bool
    {
        $pesan = $e->getMessage();

        return str_contains($pesan, 'uq_survey_log_device_periode')
            || str_contains($pesan, 'satisfaction_submission_logs');
    }

    public function thanks() { return view('satisfaction_survey.thanks'); }

    public function adminIndex()
    {
        $questions = SatisfactionQuestion::orderBy('sort_order')->orderBy('id')->get();
        return view('satisfaction_survey.admin_index', compact('questions'));
    }

    public function adminStore(Request $request)
    {
        $request->validate(['section' => 'required|string|max:50', 'question_text' => 'required|string', 'question_type' => 'required|in:likert,open', 'sort_order' => 'nullable|integer']);
        $maxOrder = (int) (SatisfactionQuestion::max('sort_order') ?? 0);
        SatisfactionQuestion::create([
            'section' => SurveySanitizer::cleanSection($request->input('section')),
            'question_text' => SurveySanitizer::cleanQuestionText($request->input('question_text')),
            'question_type' => $request->input('question_type'),
            'sort_order' => $request->input('sort_order') !== null ? (int) $request->input('sort_order') : $maxOrder + 1,
            'is_active' => $request->boolean('is_active', true),
        ]);
        return back()->with('success', 'Pertanyaan ditambahkan.');
    }

    public function adminUpdate(Request $request, $id)
    {
        $q = SatisfactionQuestion::findOrFail($id);
        $request->validate(['section' => 'required|string|max:50', 'question_text' => 'required|string', 'question_type' => 'required|in:likert,open', 'sort_order' => 'nullable|integer']);
        $q->update([
            'section' => SurveySanitizer::cleanSection($request->input('section')),
            'question_text' => SurveySanitizer::cleanQuestionText($request->input('question_text')),
            'question_type' => $request->input('question_type'),
            'sort_order' => $request->input('sort_order') !== null ? (int) $request->input('sort_order') : $q->sort_order,
            'is_active' => $request->boolean('is_active', true),
        ]);
        return back()->with('success', 'Pertanyaan diperbarui.');
    }

    public function adminDestroy($id)
    {
        SatisfactionQuestion::findOrFail($id)->delete();
        return back()->with('success', 'Pertanyaan dihapus.');
    }

    public function adminToggle($id)
    {
        $q = SatisfactionQuestion::findOrFail($id);
        $q->is_active = !$q->is_active;
        $q->save();
        return back()->with('success', $q->is_active ? 'Pertanyaan diaktifkan.' : 'Pertanyaan dinonaktifkan.');
    }

    public function adminResults()
    {
        $stats = SatisfactionQuestion::withCount('responses')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Hanya jawaban untuk pertanyaan yang masih aktif yang ditampilkan.
        $allResp = SatisfactionResponse::with('question')
            ->whereHas('question', fn ($query) => $query->where('is_active', true))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $paketList = app(\App\Services\SatisfactionSubmissionGrouper::class)->group($allResp)->map(function ($pk) {
            $waktu = $pk['waktu'] ?? now();
            $answers = collect($pk['answers'] ?? []);
            $likertScores = $answers->filter(fn ($r) => $r->question && $r->question->isLikert() && $r->score !== null)->pluck('score')->map(fn ($s) => (float) $s);
            $pk['waktu'] = $waktu;
            $pk['waktu_label'] = $waktu->format('Y-m-d H:i:s');
            $pk['waktu_tanggal'] = $waktu->format('d M Y');
            $pk['waktu_jam'] = $waktu->format('H:i:s');
            $pk['total'] = $answers->count();
            $pk['likert_avg'] = $likertScores->isNotEmpty() ? round($likertScores->avg(), 2) : null;
            $pk['open_count'] = $answers->filter(fn ($r) => $r->question && !$r->question->isLikert())->count();
            return $pk;
        });
        $totalResponses = $paketList->count();
        $likert = $stats->where('question_type', 'likert');
        $avg = collect();
        $dist = collect();
        foreach ($likert as $q) {
            $avg[$q->id] = SatisfactionResponse::where('question_id', $q->id)->avg('score');
            $rows = SatisfactionResponse::where('question_id', $q->id)->selectRaw('score, COUNT(*) as c')->groupBy('score')->pluck('c', 'score');
            $arr = [];
            for ($s = 1; $s <= 5; $s++) $arr[$s] = (int) ($rows[$s] ?? 0);
            $dist[$q->id] = $arr;
        }

        $aiAnalysis = null;
        if (class_exists(\App\Models\SatisfactionSurveyAiAnalysis::class)
            && Schema::hasTable('satisfaction_survey_ai_analyses')) {
            $aiAnalysis = \App\Models\SatisfactionSurveyAiAnalysis::where('scope_key', 'all')->first();
        }

        return view('satisfaction_survey.admin_results', compact('stats', 'totalResponses', 'avg', 'dist', 'paketList', 'aiAnalysis'));
    }

    public function analyzeSurvey(Request $request)
    {
        try {
            app(\App\Services\SatisfactionSurveyAiAnalysisService::class)->analyze();
            return redirect()->route('satisfaction-survey.admin.results')
                ->with('ai_analysis_success', 'Analisis AI berhasil dibuat dan disimpan.');
        } catch (\Throwable $e) {
            Log::warning('Satisfaction survey AI analysis failed: ' . $e->getMessage());
            return redirect()->route('satisfaction-survey.admin.results')
                ->withErrors(['ai_analysis' => 'Analisis AI gagal dibuat: ' . $e->getMessage()]);
        }
    }

    /**
     * Detail komentar di balik angka sebutan pada panel Analisis AI.
     *
     * Dipanggil modal di halaman hasil: angka "N sebutan / N komentar" bisa
     * diklik, lalu menampilkan komentar esai yang benar-benar cocok.
     * Parameter: kategori (answers|requests|scores|departments|persons) dan
     * index (nomor item, atau "total" untuk seluruh item sekaligus).
     */
    public function adminAnalysisComments(Request $request)
    {
        $kategori = (string) $request->query('kategori', '');
        $index = $request->query('index', 'total');

        try {
            $detail = app(\App\Services\SatisfactionSurveyAiAnalysisService::class)
                ->detailKomentar($kategori, is_string($index) ? $index : 'total');

            return response()->json(array_merge(['ok' => true], $detail));
        } catch (\InvalidArgumentException | \OutOfBoundsException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::warning('Satisfaction survey comment detail failed: ' . $e->getMessage());

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    private function exportDateRange(Request $request): array
    {
        return [trim((string) $request->input('from', '')), trim((string) $request->input('to', ''))];
    }

    public function adminExport(Request $request)
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        [$from, $to] = $this->exportDateRange($request);
        return Excel::download(new SatisfactionSurveyResultExport($from, $to), 'hasil-kuesioner-kepuasan-' . now()->format('Ymd-His') . '.xlsx');
    }

    public function adminExportLikert(Request $request)
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        [$from, $to] = $this->exportDateRange($request);
        return Excel::download(new SatisfactionLikertDistributionExport($from, $to), 'distribusi-likert-kuesioner-' . now()->format('Ymd-His') . '.xlsx');
    }

    public function adminExportLikertPdf(Request $request)
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        [$from, $to] = $this->exportDateRange($request);
        $questions = SatisfactionQuestion::where('question_type', 'likert')
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->get();
        $avg = collect();
        $dist = collect();
        $totalAll = 0;
        foreach ($questions as $q) {
            $avg[$q->id] = SatisfactionResponse::where('question_id', $q->id)->avg('score');
            $rows = SatisfactionResponse::where('question_id', $q->id)->selectRaw('score, COUNT(*) as c')->groupBy('score')->pluck('c', 'score');
            $arr = [];
            for ($s = 1; $s <= 5; $s++) $arr[$s] = (int) ($rows[$s] ?? 0);
            $dist[$q->id] = $arr;
            $totalAll += array_sum($arr);
        }
        return Pdf::loadView('satisfaction_survey.admin_likert_pdf', compact('questions', 'avg', 'dist', 'totalAll', 'from', 'to'))
            ->download('distribusi-likert-' . now()->format('Ymd-His') . '.pdf');
    }
}
