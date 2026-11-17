<?php

namespace App\Services\WorkAssignment;

use App\Models\WorkAssignmentRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mesin approval bertingkat untuk Work Assignment Request.
 *
 * Berbeda dengan pengajuan cuti (yang memakai N row di `leave_requests`),
 * Work Assignment memakai SATU row dengan dua kolom JSON:
 *
 *   approval_chain → snapshot approver per level (dibuat saat submit)
 *   answers        → keputusan yang sudah masuk, satu entri per level
 *
 * Format `approval_chain` (satu entri per level):
 *   {
 *     "level": 1,
 *     "role_key": "yang_diberi_tugas",
 *     "role_label": "Yang diberi Tugas",
 *     "npk": "C-00827",
 *     "name": "DIMAS GALANG RAMADHAN",
 *     "jabatan": "IT HOD"
 *   }
 *
 * Format `answers` (satu entri per level yang sudah memutuskan):
 *   {
 *     "level": 1,
 *     "decision": "approved" | "rejected",
 *     "decided_by": "C-00827",
 *     "decided_by_name": "DIMAS GALANG RAMADHAN",
 *     "decided_at": "2026-11-16 10:15:00",
 *     "reason": null | "alasan penolakan"
 *   }
 *
 * ATURAN PENTING
 * 1. Satu approver HANYA boleh memutuskan level yang NAMANYA ada di chain.
 *    Orang lain → 403.
 * 2. Level N hanya boleh diputuskan kalau SEMUA level 1..N-1 sudah `approved`.
 *    Kalau ada yang `rejected`, rantai berhenti.
 * 3. Level terakhir yang approve → pengajuan `approved`.
 * 4. Level yang ditolak → pengajuan `rejected`, level di atasnya `skipped`.
 * 5. Penolakan wajib ada alasan.
 */
class WorkAssignmentApproval
{
    /**
     * Pengajuan-pengajuan yang menunggu keputusan approver ini.
     *
     * Mirip `LeaveApprovalController`:
     *  - giliran dia (level == current_level)
     *  - masih nunggu level sebelumnya
     *  - sudah dia putuskan
     *
     * @return \Illuminate\Support\Collection<int, WorkAssignmentRequest>
     */
    public static function forApprover(string $approverNpk): \Illuminate\Support\Collection
    {
        $approverNpk = trim($approverNpk);

        if ($approverNpk === '') {
            return collect();
        }

        $requests = WorkAssignmentRequest::orderByDesc('id')->get();

        return $requests->filter(function (WorkAssignmentRequest $r) use ($approverNpk) {
            foreach ($r->chain() as $step) {
                if ((string) ($step['npk'] ?? '') === $approverNpk) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Apakah approver ini MEILIKI level tertentu pada pengajuan ini?
     */
    public static function holdsLevel(WorkAssignmentRequest $request, string $approverNpk, ?int $level = null): bool
    {
        foreach ($request->chain() as $step) {
            if ((string) ($step['npk'] ?? '') !== $approverNpk) {
                continue;
            }

            if ($level === null || (int) ($step['level'] ?? 0) === $level) {
                return true;
            }
        }

        return false;
    }

    /**
     * Level milik approver ini pada pengajuan ini (kalau ada).
     */
    public static function levelOf(WorkAssignmentRequest $request, string $approverNpk): ?int
    {
        foreach ($request->chain() as $step) {
            if ((string) ($step['npk'] ?? '') === $approverNpk) {
                return (int) ($step['level'] ?? 0);
            }
        }

        return null;
    }

    /**
     * Apakah level ini yang sedang giliran (boleh diputuskan)?
     *
     * Semua level di bawahnya harus sudah `approved` dan tidak ada yang
     * `rejected`.
     */
    public static function isActionable(WorkAssignmentRequest $request, int $level): bool
    {
        if (!$request->isOpen()) {
            return false;
        }

        $timeline = $request->timeline();
        $current  = (int) $request->current_level;

        // Sudah ada yang menolak → rantai berhenti.
        foreach ($timeline as $step) {
            if ($step['decision'] === 'rejected') {
                return false;
            }
        }

        // Level ini harus level yang sedang giliran.
        return $level === $current;
    }

    /**
     * Level berikutnya yang harus memutuskan (untuk notifikasi).
     */
    public static function nextLevel(WorkAssignmentRequest $request): ?array
    {
        return $request->nextLevel();
    }

    /**
     * Setujui satu level.
     *
     * @param  string  $approverNpk  NPK approver yang memutuskan
     * @param  int     $level        level yang diputuskan
     * @param  string  $comment      catatan opsional
     * @return array{ok:bool, message:string, status?:string, next_level?:int}
     */
    public static function approve(WorkAssignmentRequest $request, string $approverNpk, int $level, ?string $comment = null): array
    {
        $check = self::authorize($request, $approverNpk, $level);

        if (!$check['ok']) {
            return $check;
        }

        $conn = DB::connection('cii');

        try {
            $conn->beginTransaction();

            // Reload di dalam transaksi supaya dua klik bersamaan tidak
            // bisa keduanya lolos.
            $fresh = WorkAssignmentRequest::find($request->id);

            if (!$fresh || !$fresh->isOpen()) {
                $conn->rollBack();

                return ['ok' => false, 'message' => 'Pengajuan ini sudah diproses sebelumnya.'];
            }

            if (!self::isActionable($fresh, $level)) {
                $conn->rollBack();

                return ['ok' => false, 'message' => 'Level ini belum giliran. Tunggu level sebelumnya menyetujui.'];
            }

            $step  = self::stepAt($fresh, $level);
            $name  = (string) ($step['name'] ?? $approverNpk);

            $answers = $fresh->answered();
            $answers[] = [
                'level'            => $level,
                'decision'         => 'approved',
                'decided_by'       => $approverNpk,
                'decided_by_name'  => $name,
                'decided_at'       => now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
                'reason'           => $comment !== null && trim($comment) !== '' ? trim($comment) : null,
            ];

            $fresh->answers = $answers;
            $fresh->status  = 'pending';

            $next = self::nextLevel($fresh);

            if ($next === null) {
                // Level terakhir → pengajuan selesai disetujui.
                $fresh->status        = 'approved';
                $fresh->current_level = $level;
            } else {
                $fresh->current_level = (int) ($next['level'] ?? $level + 1);
            }

            $fresh->save();

            $conn->commit();
        } catch (\Throwable $e) {
            try {
                $conn->rollBack();
            } catch (\Throwable $ignored) {
                // tidak ada transaksi aktif
            }

            Log::error("[WorkAssignment] approve #{$request->id} gagal: " . $e->getMessage());

            return ['ok' => false, 'message' => 'Gagal menyimpan keputusan: ' . $e->getMessage()];
        }

        return [
            'ok'         => true,
            'message'    => $next === null
                ? 'Work Assignment #' . $request->id . ' disetujui sepenuhnya.'
                : 'Disetujui. diteruskan ke level ' . $fresh->current_level . '.',
            'status'     => $fresh->status,
            'next_level' => $fresh->current_level,
        ];
    }

    /**
     * Tolak satu level. Alasan WAJIB diisi.
     *
     * @return array{ok:bool, message:string, status?:string}
     */
    public static function reject(WorkAssignmentRequest $request, string $approverNpk, int $level, ?string $reason): array
    {
        $reason = $reason !== null ? trim($reason) : '';

        if ($reason === '') {
            return ['ok' => false, 'message' => 'Alasan penolakan wajib diisi.'];
        }

        $check = self::authorize($request, $approverNpk, $level);

        if (!$check['ok']) {
            return $check;
        }

        $conn = DB::connection('cii');

        try {
            $conn->beginTransaction();

            $fresh = WorkAssignmentRequest::find($request->id);

            if (!$fresh || !$fresh->isOpen()) {
                $conn->rollBack();

                return ['ok' => false, 'message' => 'Pengajuan ini sudah diproses sebelumnya.'];
            }

            if (!self::isActionable($fresh, $level)) {
                $conn->rollBack();

                return ['ok' => false, 'message' => 'Level ini belum giliran. Tunggu level sebelumnya menyetujui.'];
            }

            $step = self::stepAt($fresh, $level);
            $name = (string) ($step['name'] ?? $approverNpk);

            $answers = $fresh->answered();
            $answers[] = [
                'level'            => $level,
                'decision'         => 'rejected',
                'decided_by'       => $approverNpk,
                'decided_by_name'  => $name,
                'decided_at'       => now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
                'reason'           => $reason,
            ];

            $fresh->answers = $answers;
            $fresh->status  = 'rejected';
            $fresh->save();

            $conn->commit();
        } catch (\Throwable $e) {
            try {
                $conn->rollBack();
            } catch (\Throwable $ignored) {
                // tidak ada transaksi aktif
            }

            Log::error("[WorkAssignment] reject #{$request->id} gagal: " . $e->getMessage());

            return ['ok' => false, 'message' => 'Gagal menyimpan keputusan: ' . $e->getMessage()];
        }

        return [
            'ok'      => true,
            'message' => 'Work Assignment #' . $request->id . ' ditolak.',
            'status'  => 'rejected',
        ];
    }

    /**
     * Ubah keputusan yang sudah diambil (approved ↔ rejected).
     *
     * Hanya boleh selama level berikutnya belum bertindak.
     *
     * @return array{ok:bool, message:string}
     */
    public static function updateDecision(WorkAssignmentRequest $request, string $approverNpk, string $newStatus, ?string $reason = null): array
    {
        $newStatus = strtolower(trim($newStatus));

        if (!in_array($newStatus, ['approved', 'rejected'], true)) {
            return ['ok' => false, 'message' => 'Status baru tidak valid.'];
        }

        $level = self::levelOf($request, $approverNpk);

        if ($level === null) {
            return ['ok' => false, 'message' => 'Anda tidak memiliki level pada pengajuan ini.'];
        }

        $conn = DB::connection('cii');

        try {
            $conn->beginTransaction();

            // Reload di dalam transaksi supaya jawaban yang baru masuk ikut
            // terbaca (instance di luar transaksi bisa sudah basi).
            $fresh = WorkAssignmentRequest::find($request->id);

            if (!$fresh) {
                $conn->rollBack();

                return ['ok' => false, 'message' => 'Pengajuan tidak ditemukan.'];
            }

            // Cari jawaban lama level ini.
            $existing = null;

            foreach ($fresh->answered() as $answer) {
                if ((int) ($answer['level'] ?? 0) === $level) {
                    $existing = $answer;
                    break;
                }
            }

            if ($existing === null) {
                $conn->rollBack();

                return ['ok' => false, 'message' => 'Belum ada keputusan Anda pada pengajuan ini.'];
            }

            // Level berikutnya belum boleh bertindak.
            foreach ($fresh->timeline() as $step) {
                if ((int) $step['level'] <= $level) {
                    continue;
                }

                if (in_array($step['decision'], ['approved', 'rejected'], true)) {
                    $conn->rollBack();

                    return [
                        'ok'      => false,
                        'message' => 'Tidak bisa diubah — level ' . $step['level'] . ' sudah memutuskan.',
                    ];
                }
            }

            if ($newStatus === 'rejected' && ($reason === null || trim($reason) === '')) {
                $conn->rollBack();

                return ['ok' => false, 'message' => 'Alasan penolakan wajib diisi.'];
            }

            $answers = [];

            foreach ($fresh->answered() as $answer) {
                if ((int) ($answer['level'] ?? 0) === $level) {
                    $answer['decision']        = $newStatus;
                    $answer['decided_at']      = now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s');
                    $answer['decided_by_name'] = (string) ($answer['decided_by_name'] ?? $approverNpk);
                    $answer['reason']          = $newStatus === 'rejected'
                        ? trim((string) $reason)
                        : null;
                }

                $answers[] = $answer;
            }

            $fresh->answers = $answers;

            // Hitung ulang status overall dari seluruh jawaban.
            $fresh->status        = self::recalculateStatus($fresh);
            $fresh->current_level = self::recalculateCurrentLevel($fresh);

            $fresh->save();

            $conn->commit();
        } catch (\Throwable $e) {
            try {
                $conn->rollBack();
            } catch (\Throwable $ignored) {
                // tidak ada transaksi aktif
            }

            Log::error("[WorkAssignment] updateDecision #{$request->id} gagal: " . $e->getMessage());

            return ['ok' => false, 'message' => 'Gagal menyimpan keputusan: ' . $e->getMessage()];
        }

        return ['ok' => true, 'message' => 'Keputusan diubah menjadi ' . ($newStatus === 'approved' ? 'Disetujui' : 'Ditolak') . '.'];
    }

    /**
     * Otorisasi: approver harus pemilik level, dan level harus sedang giliran.
     */
    private static function authorize(WorkAssignmentRequest $request, string $approverNpk, int $level): array
    {
        if (!self::holdsLevel($request, $approverNpk, $level)) {
            return [
                'ok'      => false,
                'message' => 'Anda tidak berwenang memutuskan level ini pada pengajuan #' . $request->id . '.',
            ];
        }

        return ['ok' => true, 'message' => ''];
    }

    /**
     * Satu entri chain pada level tertentu.
     */
    private static function stepAt(WorkAssignmentRequest $request, int $level): array
    {
        foreach ($request->chain() as $step) {
            if ((int) ($step['level'] ?? 0) === $level) {
                return $step;
            }
        }

        return [];
    }

    /**
     * Status overall dari seluruh jawaban.
     *
     * - ada yang rejected → rejected
     * - semua level sudah approved → approved
     * - selain itu → pending
     */
    public static function recalculateStatus(WorkAssignmentRequest $request): string
    {
        $timeline = $request->timeline();
        $total    = count($timeline);
        $approved = 0;

        foreach ($timeline as $step) {
            if ($step['decision'] === 'rejected') {
                return 'rejected';
            }

            if ($step['decision'] === 'approved') {
                $approved++;
            }
        }

        if ($total > 0 && $approved >= $total) {
            return 'approved';
        }

        return 'pending';
    }

    /**
     * Level yang sedang aktif = level approved pertama yang belum dijawab.
     */
    public static function recalculateCurrentLevel(WorkAssignmentRequest $request): int
    {
        $answeredLevels = array_column($request->answered(), 'level');

        foreach ($request->chain() as $step) {
            $level = (int) ($step['level'] ?? 0);

            if (!in_array($level, array_map('intval', $answeredLevels), true)) {
                return $level;
            }
        }

        return (int) $request->current_level;
    }

    /**
     * Ringkasan untuk notifikasi / tampilan.
     *
     * @return array{level:int, role_label:string, name:string, npk:string, jabatan:string}
     */
    public static function describeNext(WorkAssignmentRequest $request): ?array
    {
        $next = self::nextLevel($request);

        if ($next === null) {
            return null;
        }

        return [
            'level'      => (int) ($next['level'] ?? 0),
            'role_label' => (string) ($next['role_label'] ?? ''),
            'name'       => (string) ($next['name'] ?? ''),
            'npk'        => (string) ($next['npk'] ?? ''),
            'jabatan'    => (string) ($next['jabatan'] ?? ''),
        ];
    }
}
