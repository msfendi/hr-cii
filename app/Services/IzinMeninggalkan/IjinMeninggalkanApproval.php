<?php

namespace App\Services\IzinMeninggalkan;

use App\Models\IjinMeninggalkanRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Aturan approval Izin Meninggalkan Tugas / Pekerjaan.
 *
 * Struktur data sama dengan Izin Dinas (rantai + jawaban sebagai JSON), jadi
 * aturannya pun mengikuti: level diputuskan berurutan, satu approver hanya
 * boleh memutuskan level miliknya, dan keputusan disimpan atomik.
 *
 * Tidak ada efek samping saldo. Penyalinan ke tabel absensi
 * (`ijin_meninggalkan_pekerjaans`) diurus terpisah oleh
 * IjinMeninggalkanLegacySync — service ini tidak menyentuhnya.
 */
class IjinMeninggalkanApproval
{
    /**
     * Level milik seorang approver pada pengajuan ini.
     */
    public static function levelOf(IjinMeninggalkanRequest $request, string $approverNpk): ?int
    {
        foreach ($request->chain() as $step) {
            if ((string) ($step['npk'] ?? '') === $approverNpk) {
                return (int) ($step['level'] ?? 0);
            }
        }

        return null;
    }

    /**
     * Apakah approver memegang level tertentu (null = level apa pun).
     */
    public static function holdsLevel(IjinMeninggalkanRequest $request, string $approverNpk, ?int $level = null): bool
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
     * Apakah level ini sudah giliran diputuskan.
     *
     * Semua level di bawahnya harus approved, tidak boleh ada yang rejected.
     */
    public static function isActionable(IjinMeninggalkanRequest $request, int $level): bool
    {
        if (!$request->isOpen()) {
            return false;
        }

        $current = (int) $request->current_level;

        foreach ($request->chain() as $step) {
            if ((int) ($step['level'] ?? 0) >= $level) {
                continue;
            }

            $decision = self::decisionAt($request, (int) $step['level']);

            if ($decision === 'rejected') {
                return false;
            }

            if ($decision !== 'approved') {
                return false;
            }
        }

        return $level === $current;
    }

    /**
     * Setujui satu level.
     *
     * @return array{ok:bool, message:string, status?:string, next_level?:int}
     */
    public static function approve(
        IjinMeninggalkanRequest $request,
        string $approverNpk,
        int $level,
        ?string $comment = null
    ): array {
        if (!self::holdsLevel($request, $approverNpk, $level)) {
            return [
                'ok'      => false,
                'message' => 'Anda tidak berwenang memutuskan level ini pada pengajuan #' . $request->id . '.',
            ];
        }

        return self::writeDecision($request, $approverNpk, $level, 'approved', $comment);
    }

    /**
     * Tolak satu level. Alasan wajib.
     *
     * @return array{ok:bool, message:string, status?:string}
     */
    public static function reject(
        IjinMeninggalkanRequest $request,
        string $approverNpk,
        int $level,
        ?string $reason
    ): array {
        $reason = $reason !== null ? trim($reason) : '';

        if ($reason === '') {
            return ['ok' => false, 'message' => 'Alasan penolakan wajib diisi.'];
        }

        if (!self::holdsLevel($request, $approverNpk, $level)) {
            return [
                'ok'      => false,
                'message' => 'Anda tidak berwenang memutuskan level ini pada pengajuan #' . $request->id . '.',
            ];
        }

        return self::writeDecision($request, $approverNpk, $level, 'rejected', $reason);
    }

    /**
     * Tulis keputusan di dalam transaksi.
     *
     * Reload di dalam transaksi supaya dua klik bersamaan tidak bisa
     * keduanya lolos.
     *
     * @return array{ok:bool, message:string, status?:string, next_level?:int}
     */
    private static function writeDecision(
        IjinMeninggalkanRequest $request,
        string $approverNpk,
        int $level,
        string $decision,
        ?string $note
    ): array {
        $conn = DB::connection('cii');

        try {
            $conn->beginTransaction();

            $fresh = IjinMeninggalkanRequest::find($request->id);

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

            $answers   = $fresh->answered();
            $answers[] = [
                'level'           => $level,
                'decision'        => $decision,
                'decided_by'      => $approverNpk,
                'decided_by_name' => $name,
                'decided_at'      => now()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
                'reason'          => $note !== null && trim($note) !== '' ? trim($note) : null,
            ];

            $fresh->answers = $answers;
            $fresh->status  = 'pending';

            $next = null;

            if ($decision === 'rejected') {
                $fresh->status        = 'rejected';
                $fresh->current_level = $level;
            } else {
                $next = $fresh->nextLevel();

                if ($next === null) {
                    $fresh->status        = 'approved';
                    $fresh->current_level = $level;
                } else {
                    $fresh->current_level = (int) ($next['level'] ?? $level + 1);
                }
            }

            $fresh->save();

            $conn->commit();
        } catch (\Throwable $e) {
            try {
                $conn->rollBack();
            } catch (\Throwable $ignored) {
                // tidak ada transaksi aktif
            }

            Log::error("[IzinMeninggalkan] keputusan #{$request->id} gagal: " . $e->getMessage());

            return ['ok' => false, 'message' => 'Gagal menyimpan keputusan: ' . $e->getMessage()];
        }

        if ($decision === 'rejected') {
            return [
                'ok'      => true,
                'message' => 'Pengajuan #' . $request->id . ' ditolak.',
                'status'  => 'rejected',
            ];
        }

        return [
            'ok'         => true,
            'message'    => $next === null
                ? 'Pengajuan #' . $request->id . ' disetujui sepenuhnya.'
                : 'Disetujui. Diteruskan ke level ' . $fresh->current_level . '.',
            'status'     => $fresh->status,
            'next_level' => $fresh->current_level,
        ];
    }

    /**
     * Langkah rantai pada level tertentu.
     *
     * @return array<string, mixed>
     */
    public static function stepAt(IjinMeninggalkanRequest $request, int $level): array
    {
        foreach ($request->chain() as $step) {
            if ((int) ($step['level'] ?? 0) === $level) {
                return $step;
            }
        }

        return [];
    }

    /**
     * Keputusan yang tercatat pada satu level (null kalau belum ada).
     */
    private static function decisionAt(IjinMeninggalkanRequest $request, int $level): ?string
    {
        foreach ($request->answered() as $answer) {
            if ((int) ($answer['level'] ?? 0) === $level) {
                return strtolower((string) ($answer['decision'] ?? '')) ?: null;
            }
        }

        return null;
    }
}