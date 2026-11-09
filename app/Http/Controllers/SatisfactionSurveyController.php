<?php

namespace App\Http\Controllers;

use App\Models\SatisfactionQuestion;
use App\Models\SatisfactionResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SatisfactionSurveyResultExport;
use App\Exports\SatisfactionLikertDistributionExport;
use Barryvdh\DomPDF\Facade\Pdf;

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

    public function adminResults()
    {
        $stats = SatisfactionQuestion::withCount('responses')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Total paket = 1 submit = 1 kelompok jawaban dalam window 2 detik yang sama.
        // Bukan jumlah baris per-pertanyaan. Sekalian bangun list paket:
        // tiap paket = waktu submit + semua jawaban + total terjawab.
        $allResp = SatisfactionResponse::with('question')->orderBy('created_at')->orderBy('id')->get();
        $paketCount = 0;
        $paketList = collect();
        $bucket = null;
        $lastKey = null;
        foreach ($allResp as $r) {
            $cur = \Carbon\Carbon::parse($r->created_at);
            if ($lastKey === null) {
                $paketCount = 1;
                $lastKey = $cur;
                $bucket = [];
            } elseif ($cur->diffInSeconds($lastKey) > 2) {
                if ($bucket !== null) {
                    $paketList->push($bucket);
                }
                $paketCount++;
                $lastKey = $cur;
                $bucket = [];
            }
            $bucket['waktu'] = $bucket['waktu'] ?? $cur;
            $bucket['answers'][] = $r;
        }
        if ($bucket !== null) {
            $paketList->push($bucket);
        }
        foreach ($paketList as &$pk) {
            $waktu = $pk['waktu'] ?? now();
            $pk['waktu'] = $waktu;
            $pk['waktu_label'] = $waktu->format('Y-m-d H:i:s');
            $pk['total'] = count($pk['answers'] ?? []);
        }
        unset($pk);
        $paketList = $paketList->map(function ($pk) {
            $pk['waktu_label'] = $pk['waktu_label'] ?? (isset($pk['waktu']) ? $pk['waktu']->format('Y-m-d H:i:s') : date('Y-m-d H:i:s'));
            $pk['total'] = $pk['total'] ?? count($pk['answers'] ?? []);
            return $pk;
        });
        $totalResponses = $paketCount;
        $likert = $stats->where('question_type', 'likert');
        $avg = collect();
        $dist = collect();
        foreach ($likert as $q) {
            $avg[$q->id] = SatisfactionResponse::where('question_id', $q->id)->avg('score');
            $rows = SatisfactionResponse::where('question_id', $q->id)
                ->selectRaw('score, COUNT(*) as c')->groupBy('score')->pluck('c', 'score');
            $arr = [];
            for ($s = 1; $s <= 5; $s++) {
                $arr[$s] = (int) ($rows[$s] ?? 0);
            }
            $dist[$q->id] = $arr;
        }

        return view('satisfaction_survey.admin_results', compact('stats', 'totalResponses', 'avg', 'dist', 'paketList'));
    }

    public function adminExport(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $from = $request->input('from');
        $to   = $request->input('to');
        $file = 'hasil-kuesioner-kepuasan-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new SatisfactionSurveyResultExport($from, $to), $file);
    }

    public function adminExportLikert(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);
        $from = $request->input('from');
        $to   = $request->input('to');
        $file = 'distribusi-likert-kuesioner-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new SatisfactionLikertDistributionExport($from, $to), $file);
    }

    public function adminExportLikertPdf(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);
        $from = $request->input('from');
        $to   = $request->input('to');

        $questions = SatisfactionQuestion::where('question_type', 'likert')
            ->orderBy('sort_order')->orderBy('id')->get();
        $avg = collect();
        $dist = collect();
        $totalAll = 0;
        foreach ($questions as $q) {
            $avg[$q->id] = SatisfactionResponse::where('question_id', $q->id)->avg('score');
            $rows = SatisfactionResponse::where('question_id', $q->id)
                ->selectRaw('score, COUNT(*) as c')->groupBy('score')->pluck('c', 'score');
            $arr = [];
            for ($s = 1; $s <= 5; $s++) {
                $arr[$s] = (int) ($rows[$s] ?? 0);
            }
            $dist[$q->id] = $arr;
            $totalAll += array_sum($arr);
        }

        $pdf = Pdf::loadView('satisfaction_survey.admin_likert_pdf', compact('questions', 'avg', 'dist', 'totalAll', 'from', 'to'));
        return $pdf->download('distribusi-likert-' . now()->format('Ymd-His') . '.pdf');
    }
}
