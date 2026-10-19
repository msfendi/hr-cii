<?php

namespace App\Http\Controllers;

use App\Models\MonRekonLock;
use App\Services\RekonGradeExcelService;
use App\Services\RekonLockService;
use App\Services\RekonNotesExcelService;
use App\Services\RekonSummaryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Lock OCF / CPO Data + Reconciliation Summary (Reconciliation Monitoring dashboard).
 * Dua mode: `mode` = ocf | cpo (default ocf) -- dipakai di lock maupun summary.
 */
class MonitoringRekonLockController extends Controller
{
    // ------------------------------------------------------------------
    // 1) Lock Data OCF
    // ------------------------------------------------------------------
    public function store(Request $request, RekonLockService $service): JsonResponse
    {
        $data = $request->validate([
            'mode'      => ['nullable', 'in:' . implode(',', MonRekonLock::modes())],
            'lock_type' => ['required', 'in:' . implode(',', MonRekonLock::types())],
            'ocf'       => ['required_unless:mode,cpo', 'nullable', 'string', 'max:100'],
            'brand'     => ['nullable', 'string', 'max:150'],
            'style'     => ['nullable', 'string', 'max:255'],
            'sub_ref'   => ['nullable', 'string', 'max:150'],
            'uraian'    => ['required_if:mode,cpo', 'nullable', 'string', 'max:150'],
            'negara'    => ['nullable', 'string', 'max:100'],
        ]);
        $mode = MonRekonLock::normalizeMode($data['mode'] ?? null);

        try {
            $lock = $service->lock($data, $data['lock_type'], $this->userLabel($request), $mode);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Lock failed: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => MonRekonLock::modeLabel($lock->lock_mode) . " {$lock->lock_key} locked successfully ({$lock->lock_type}) for period "
                . $lock->lock_date->format('d-m-Y') . '.',
            'data' => [
                'id'        => $lock->id,
                'mode'      => $lock->lock_mode,
                'ocf'       => $lock->lock_key,
                'ocf_no'    => $lock->ocf_no,
                'cpo_no'    => $lock->cpo_no,
                'lock_type' => $lock->lock_type,
                'lock_date' => $lock->lock_date->toDateString(),
                'fabrics'   => $lock->fabrics->count(),
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // 1b) Change lock status (partial / shortship / complete)
    // ------------------------------------------------------------------
    public function updateStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lock_id'   => ['required', 'integer'],
            'lock_type' => ['required', 'in:' . implode(',', MonRekonLock::types())],
        ]);

        $lock = MonRekonLock::find($data['lock_id']);
        if (!$lock) {
            return response()->json(['success' => false, 'message' => 'Lock record not found.'], 404);
        }

        $old = $lock->lock_type;
        $new = $data['lock_type'];
        $lbl = MonRekonLock::modeLabel($lock->lock_mode ?? MonRekonLock::MODE_OCF);

        if ($old === $new) {
            return response()->json(['success' => true, 'message' => "Status of {$lbl} {$lock->lock_key} is already {$new}."]);
        }

        // Unique index (ocf_no / cpo_no, lock_date, lock_type): the same OCF/CPO cannot have two locks
        // of the same type on the same lock date.
        $duplicate = MonRekonLock::query()
            ->mode($lock->lock_mode ?? MonRekonLock::MODE_OCF)
            ->where($lock->lock_mode === MonRekonLock::MODE_CPO ? 'cpo_no' : 'ocf_no', $lock->lock_key)
            ->whereDate('lock_date', $lock->lock_date->toDateString())
            ->where('lock_type', $new)
            ->where('id', '!=', $lock->id)
            ->exists();
        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => "{$lbl} {$lock->lock_key} already has a \"{$new}\" lock on " . $lock->lock_date->format('d-m-Y') . '.',
            ], 422);
        }

        try {
            $lock->lock_type = $new;
            $lock->save();
        } catch (Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Failed to change status: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Status of {$lbl} {$lock->lock_key} changed from {$old} to {$new}.",
        ]);
    }

    // ------------------------------------------------------------------
    // 1c) Delete lock record (+ fabric snapshot rows)
    // ------------------------------------------------------------------
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lock_id' => ['required', 'integer'],
        ]);

        $lock = MonRekonLock::find($data['lock_id']);
        if (!$lock) {
            return response()->json(['success' => false, 'message' => 'Lock record not found.'], 404);
        }

        $lbl  = MonRekonLock::modeLabel($lock->lock_mode ?? MonRekonLock::MODE_OCF);
        $key  = $lock->lock_key;
        $type = $lock->lock_type;
        $date = $lock->lock_date->format('d-m-Y');

        try {
            DB::transaction(function () use ($lock) {
                // Hapus dulu snapshot fabric (child), baru header lock.
                DB::table('mon_rekon_lock_fabrics')->where('lock_id', $lock->id)->delete();

                // Hard delete supaya unique index (ocf_no/cpo_no, lock_date, lock_type)
                // terlepas dan OCF/CPO yang sama bisa di-lock ulang.
                method_exists($lock, 'forceDelete') ? $lock->forceDelete() : $lock->delete();
            });
        } catch (Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Failed to delete lock: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "{$lbl} {$key} ({$type}) lock for period {$date} has been deleted.",
        ]);
    }

    // ------------------------------------------------------------------
    // 2) Summary Rekonsiliasi
    // ------------------------------------------------------------------
    public function summaryPage(Request $request)
    {
        [$from, $to] = $this->period($request);

        return view('monitoring.rekonsiliasi_summary', [
            'dateFrom' => $from->toDateString(),
            'dateTo'   => $to->toDateString(),
            'mode'     => $this->mode($request),
        ]);
    }

    public function summaryData(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return response()->json(RekonSummaryService::make($from, $to, $this->mode($request))->build());
    }

    public function gradeTemplate(Request $request, RekonGradeExcelService $excel): BinaryFileResponse
    {
        [$from, $to] = $this->period($request);
        $mode = $this->mode($request);
        $path = $excel->buildTemplate($from, $to, $mode);

        return response()->download(
            $path,
            'template_grade_rekonsiliasi_' . $mode . '_' . $from->format('Ymd') . '_' . $to->format('Ymd') . '.xlsx'
        )->deleteFileAfterSend(true);
    }

    public function gradeImport(Request $request, RekonGradeExcelService $excel): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);
        [$from, $to] = $this->period($request);

        try {
            $result = $excel->import($request->file('file')->getRealPath(), $from, $to, $this->userLabel($request), $this->mode($request));
        } catch (Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'File could not be read: ' . $e->getMessage()], 500);
        }

        if ($result['errors']) {
            return response()->json([
                'success' => false,
                'message' => 'Import cancelled: ' . count($result['errors']) . ' row(s) have errors (no data was saved).',
                'errors'  => array_slice($result['errors'], 0, 30),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Import successful: {$result['updated']} " . MonRekonLock::modeLabel($this->mode($request)) . " updated, {$result['skipped']} row(s) skipped (empty grade).",
        ]);
    }

    public function notesTemplate(Request $request, RekonNotesExcelService $excel): BinaryFileResponse
    {
        [$from, $to] = $this->period($request);
        $mode = $this->mode($request);
        $path = $excel->buildNotesTemplate($from, $to, $mode);

        return response()->download(
            $path,
            'template_rekomendasi_notes_' . $mode . '_' . $from->format('Ymd') . '_' . $to->format('Ymd') . '.xlsx'
        )->deleteFileAfterSend(true);
    }

    public function notesImport(Request $request, RekonNotesExcelService $excel): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);
        [$from, $to] = $this->period($request);

        try {
            $result = $excel->importNotes($request->file('file')->getRealPath(), $from, $to, $this->userLabel($request), $this->mode($request));
        } catch (Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'File could not be read: ' . $e->getMessage()], 500);
        }

        if ($result['errors']) {
            return response()->json([
                'success' => false,
                'message' => 'Import cancelled: ' . count($result['errors']) . ' row(s) have errors (no data was saved).',
                'errors'  => array_slice($result['errors'], 0, 30),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Recommendations & Notes import successful: {$result['updated']} " . MonRekonLock::modeLabel($this->mode($request)) . " updated.",
        ]);
    }

    // ------------------------------------------------------------------
    private function period(Request $request): array
    {
        $to   = $request->filled('date_to') ? Carbon::parse($request->input('date_to'))->startOfDay() : Carbon::today();
        $from = $request->filled('date_from') ? Carbon::parse($request->input('date_from'))->startOfDay() : $to->copy()->startOfMonth();

        return $from->gt($to) ? [$to, $from] : [$from, $to];
    }

    /** Mode lock/summary dari request: ocf (default) | cpo. */
    private function mode(Request $request): string
    {
        return MonRekonLock::normalizeMode($request->input('mode'));
    }

    private function userLabel(Request $request): ?string
    {
        $u = $request->user();

        return $u ? (string) ($u->name ?? $u->username ?? $u->getAuthIdentifier()) : null;
    }
}