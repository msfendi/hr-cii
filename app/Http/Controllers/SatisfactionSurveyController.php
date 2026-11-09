<?php

namespace App\Http\Controllers;

use App\Models\SatisfactionQuestion;
use App\Models\SatisfactionResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SatisfactionSurveyController extends Controller
{
    /**
     * Halaman kuesioner — publik, anonim, mobile-first.
     * Semua pertanyaan diambil dari DB (satisfaction_questions) sehingga bisa di-manage admin.
     */
    public function create()
    {
        $questions = SatisfactionQuestion::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $grouped = $questions->groupBy('section');

        return view('satisfaction_survey.create', compact('questions', 'grouped'));
    }

    public function store(Request $request)
    {
        $questionIds = SatisfactionQuestion::where('is_active', true)->pluck('id')->all();

        // Validasi dinamis berdasar tipe pertanyaan di DB.
        $rules = [];
        foreach ($questionIds as $qid) {
            $q = SatisfactionQuestion::find($qid);
            if ($q && $q->isLikert()) {
                $rules["answers.{$qid}"] = 'required|integer|between:1,5';
            } else {
                $rules["answers.{$qid}"] = 'nullable|string|max:5000';
            }
        }
        $rules['answers'] = 'required|array';

        $validated = $request->validate($rules);

        try {
            DB::transaction(function () use ($validated) {
                foreach (($validated['answers'] ?? []) as $qid => $val) {
                    $q = SatisfactionQuestion::find($qid);
                    if (!$q) continue;

                    if ($q->isLikert()) {
                        SatisfactionResponse::create([
                            'question_id' => $q->id,
                            'score' => (int) $val,
                            'answer_text' => null,
                        ]);
                    } else {
                        SatisfactionResponse::create([
                            'question_id' => $q->id,
                            'score' => null,
                            'answer_text' => $val ? trim((string) $val) : null,
                        ]);
                    }
                }
            });

            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Terima kasih atas partisipasi Anda.']);
            }

            return redirect()->route('satisfaction-survey.thanks')->with('success', 'Terima kasih atas partisipasi Anda.');
        } catch (\Throwable $e) {
            Log::error('Satisfaction survey store: ' . $e->getMessage());
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Gagal menyimpan jawaban. Silakan coba lagi.'], 500);
            }
            return back()->withInput()->withErrors(['answers' => 'Gagal menyimpan jawaban. Silakan coba lagi.']);
        }
    }

    public function thanks()
    {
        return view('satisfaction_survey.thanks');
    }

    // -----------------------------------------------------------------
    // Admin: manage pertanyaan (CRUD + reorder + aktif/nonaktif)
    // -----------------------------------------------------------------

    public function adminIndex()
    {
        $questions = SatisfactionQuestion::orderBy('sort_order')->orderBy('id')->get();
        return view('satisfaction_survey.admin_index', compact('questions'));
    }

    public function adminStore(Request $request)
    {
        $request->validate([
            'section' => 'required|string|max:50',
            'question_text' => 'required|string',
            'question_type' => 'required|in:likert,open',
            'sort_order' => 'nullable|integer',
        ]);

        $maxOrder = (int) (SatisfactionQuestion::max('sort_order') ?? 0);
        SatisfactionQuestion::create([
            'section' => $request->input('section'),
            'question_text' => $request->input('question_text'),
            'question_type' => $request->input('question_type'),
            'sort_order' => $request->input('sort_order') !== null ? (int) $request->input('sort_order') : $maxOrder + 1,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Pertanyaan ditambahkan.');
    }

    public function adminUpdate(Request $request, $id)
    {
        $q = SatisfactionQuestion::findOrFail($id);
        $request->validate([
            'section' => 'required|string|max:50',
            'question_text' => 'required|string',
            'question_type' => 'required|in:likert,open',
            'sort_order' => 'nullable|integer',
        ]);

        $q->update([
            'section' => $request->input('section'),
            'question_text' => $request->input('question_text'),
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
}
