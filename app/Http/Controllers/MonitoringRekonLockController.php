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
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Lock OCF Data + Reconciliation Summary (Reconciliation Monitoring dashboard).
 */
class MonitoringRekonLockController extends Controller
{
    // ------------------------------------------------------------------
    // 1) Lock Data OCF
    // ------------------------------------------------------------------
    public function store(Request $request, RekonLockService $service): JsonResponse
    {
        $data = $request->validate([
            'lock_type' => ['required', 'in:' . implode(',', MonRekonLock::types())],
            'ocf'       => ['required', 'string', 'max:100'],
            'brand'     => ['nullable', 'string', 'max:150'],
            'style'     => ['nullable', 'string', 'max:255'],
            'sub_ref'   => ['nullable', 'string', 'max:150'],
            'uraian'    => ['nullable', 'string', 'max:150'],
            'negara'    => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $lock = $service->lock($data, $data['lock_type'], $this->userLabel($request));
        } catch (InvalidArgumentException | RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Lock failed: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "OCF {$lock->ocf_no} locked successfully ({$lock->lock_type}) for period "
                . $lock->lock_date->format('d-m-Y') . '.',
            'data' => [
                'id'        => $lock->id,
                'ocf'       => $lock->ocf_no,
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

        if ($old === $new) {
            return response()->json(['success' => true, 'message' => "Status of OCF {$lock->ocf_no} is already {$new}."]);
        }

        // Unique index (ocf_no, lock_date, lock_type): the same OCF cannot have two locks
        // of the same type on the same lock date.
        $duplicate = MonRekonLock::query()
            ->where('ocf_no', $lock->ocf_no)
            ->whereDate('lock_date', $lock->lock_date->toDateString())
            ->where('lock_type', $new)
            ->where('id', '!=', $lock->id)
            ->exists();
        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => "OCF {$lock->ocf_no} already has a \"{$new}\" lock on " . $lock->lock_date->format('d-m-Y') . '.',
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
            'message' => "Status of OCF {$lock->ocf_no} changed from {$old} to {$new}.",
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
        ]);
    }

    public function summaryData(Request $request): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return response()->json(RekonSummaryService::make($from, $to)->build());
    }

    public function gradeTemplate(Request $request, RekonGradeExcelService $excel): BinaryFileResponse
    {
        [$from, $to] = $this->period($request);
        $path = $excel->buildTemplate($from, $to);

        return response()->download(
            $path,
            'template_grade_rekonsiliasi_' . $from->format('Ymd') . '_' . $to->format('Ymd') . '.xlsx'
        )->deleteFileAfterSend(true);
    }

    public function gradeImport(Request $request, RekonGradeExcelService $excel): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);
        [$from, $to] = $this->period($request);

        try {
            $result = $excel->import($request->file('file')->getRealPath(), $from, $to, $this->userLabel($request));
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
            'message' => "Import successful: {$result['updated']} OCF updated, {$result['skipped']} row(s) skipped (empty grade).",
        ]);
    }

    public function notesTemplate(Request $request, RekonNotesExcelService $excel): BinaryFileResponse
    {
        [$from, $to] = $this->period($request);
        $path = $excel->buildNotesTemplate($from, $to);

        return response()->download(
            $path,
            'template_rekomendasi_notes_' . $from->format('Ymd') . '_' . $to->format('Ymd') . '.xlsx'
        )->deleteFileAfterSend(true);
    }

    public function notesImport(Request $request, RekonNotesExcelService $excel): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);
        [$from, $to] = $this->period($request);

        try {
            $result = $excel->importNotes($request->file('file')->getRealPath(), $from, $to, $this->userLabel($request));
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
            'message' => "Recommendations & Notes import successful: {$result['updated']} OCF updated.",
        ]);
    }

    // ------------------------------------------------------------------
    private function period(Request $request): array
    {
        $to   = $request->filled('date_to') ? Carbon::parse($request->input('date_to'))->startOfDay() : Carbon::today();
        $from = $request->filled('date_from') ? Carbon::parse($request->input('date_from'))->startOfDay() : $to->copy()->startOfMonth();

        return $from->gt($to) ? [$to, $from] : [$from, $to];
    }

    private function userLabel(Request $request): ?string
    {
        $u = $request->user();

        return $u ? (string) ($u->name ?? $u->username ?? $u->getAuthIdentifier()) : null;
    }
}