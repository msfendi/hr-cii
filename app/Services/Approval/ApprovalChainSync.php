<?php

namespace App\Services\Approval;

use App\Models\ApprovalDept;
use App\Models\ApprovalRule;
use App\Models\LeaveRequest;
use App\Services\ApprovalWa\LeaveApprovalEffects;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Menjaga agar rantai approval pada `leave_requests` tetap sinkron dengan
 * master approval (`approval_depts` + `approval_rules`).
 *
 * MASALAH YANG DIPERBAIKI
 * -----------------------
 * Satu pengajuan = N row di `leave_requests` (satu row per level approver),
 * dibuat sebagai snapshot saat pengajuan disubmit. Kalau master approval
 * berubah setelahnya (level dihapus / ditambah / approver diganti), snapshot
 * lama jadi tidak nyambung:
 *
 *   master lama : L1 L2 L3 L4 L5
 *   L2 dihapus  : L1 L3 L4 L5      <- level 2 hilang
 *
 * Cek kelayakan notifikasi mensyaratkan SEMUA level di bawahnya sudah
 * `approved`. Level 2 tidak ada row-nya, jadi tidak pernah bernilai
 * `approved` → L3..L5 tidak pernah dapat notifikasi. Rantai berhenti.
 *
 * PENYELESAIAN
 * ------------
 * 1. Master: level pada satu group selalu dirapikan jadi 1..N (lihat
 *    normalizeGroupLevels()).
 * 2. Request: row PENDING dirakit ulang mengikuti chain master yang baru
 *    (lihat syncToken()).
 * 3. Row yang sudah DIPUTUSKAN (approved/rejected) tidak pernah diubah —
 *    keputusan yang sudah diberikan tetap sah.
 */
class ApprovalChainSync
{
    /**
     * Geser level rule lain supaya `$level` bebas dipakai.
     *
     * Dipakai saat rule ditambah / dipindah ke level yang sudah terisi —
     * supaya tidak pernah ada dua rule di level yang sama dalam satu group.
     * Urutan approver yang lain tidak berubah, hanya nomor levelnya bergeser.
     */
    public static function makeRoomForLevel(int $groupId, int $level, ?int $exceptRuleId = null): void
    {
        $conflict = ApprovalRule::where('rules_id', $groupId)
            ->where('level', $level)
            ->when($exceptRuleId, fn ($q) => $q->where('id', '!=', $exceptRuleId))
            ->exists();

        if (!$conflict) {
            return;
        }

        // Naikkan level semua rule yang >= level target, dari yang paling besar
        // dulu supaya tidak ada dua rule bertabrakan di level yang sama selama
        // proses.
        $rules = ApprovalRule::where('rules_id', $groupId)
            ->whereRaw('CAST(level AS INT) >= ?', [$level])
            ->when($exceptRuleId, fn ($q) => $q->where('id', '!=', $exceptRuleId))
            ->orderByRaw('CAST(level AS INT) DESC')
            ->get();

        foreach ($rules as $rule) {
            $rule->level = ((int) $rule->level) + 1;
            $rule->save();
        }
    }

    /**
     * Rapikan level `approval_rules` satu group supaya berurutan 1..N,
     * mengikuti urutan level yang ada sekarang.
     *
     * Idempotent: kalau levelnya sudah berurutan, tidak ada yang berubah.
     *
     * @return int jumlah rule yang levelnya berubah
     */
    public static function normalizeGroupLevels(int $groupId): int
    {
        $rules = ApprovalRule::where('rules_id', $groupId)
            ->orderByRaw('CAST(level AS INT) ASC, id ASC')
            ->get();

        $changed = 0;
        $expected = 1;

        foreach ($rules as $rule) {
            if ((string) $rule->level !== (string) $expected) {
                $rule->level = $expected;
                $rule->save();
                $changed++;
            }
            $expected++;
        }

        return $changed;
    }

    /**
     * Sinkronkan semua pengajuan PENDING milik satu approval group.
     *
     * Dipanggil setelah master group berubah.
     *
     * @return array{tokens:int, changed:int, skipped:int}
     */
    public static function syncGroup(int $groupId): array
    {
        self::normalizeGroupLevels($groupId);

        $tokens = DB::connection('cii')->select("
            SELECT DISTINCT token
            FROM leave_requests
            WHERE rules_id = ?
              AND status = 'pending'
              AND (void IS NULL OR void = 'false' OR void = '')
        ", [$groupId]);

        $stats = ['tokens' => count($tokens), 'changed' => 0, 'skipped' => 0];

        foreach ($tokens as $t) {
            $result = self::syncToken((string) $t->token);

            if ($result['synced']) {
                $stats['changed'] += $result['changed'] ? 1 : 0;
            } else {
                $stats['skipped']++;
            }
        }

        return $stats;
    }
    /**
     * Sinkronkan SATU token pengajuan ke chain master terbaru.
     *
     * Aturan:
     *  - Row yang sudah diputuskan (approved/rejected/cancelled) TIDAK diubah.
     *  - Kalau sudah ada yang menolak, rantai sudah selesai → tidak disentuh.
     *  - Row pending dirakit ulang: approver yang masih ada dipertahankan
     *    (id row-nya ikut, supaya deep link WA lama tetap valid), approver
     *    yang hilang dibatalkan, approver baru ditambahkan.
     *  - Level dirapikan 1..N dan `approval_progress` diarahkan ke level
     *    berikutnya yang harus bertindak.
     *
     * @return array{synced:bool, changed:bool, reason?:string}
     */
    public static function syncToken(string $token): array
    {
        $rows = LeaveRequest::where('token', $token)
            ->orderBy('approval_level')
            ->get();

        if ($rows->isEmpty()) {
            return ['synced' => false, 'changed' => false, 'reason' => 'token tidak ditemukan'];
        }

        // Sudah ada yang menolak → rantai selesai, jangan dibangunkan lagi.
        $rejected = $rows->first(fn ($r) => strtolower((string) $r->status) === 'rejected');
        if ($rejected) {
            return ['synced' => false, 'changed' => false, 'reason' => 'sudah ditolak'];
        }

        $groupId = self::resolveGroupId($rows);
        if ($groupId === null) {
            return ['synced' => false, 'changed' => false, 'reason' => 'group tidak bisa ditentukan'];
        }

        $master = ApprovalRule::where('rules_id', $groupId)
            ->orderByRaw('CAST(level AS INT) ASC, id ASC')
            ->get();

        if ($master->isEmpty()) {
            // Group dihapus / semua rule dihapus. Jangan hapus pengajuan yang
            // sudah jalan — biarkan apa adanya supaya manusia memutuskan.
            return ['synced' => false, 'changed' => false, 'reason' => 'master kosong'];
        }

        // Baris yang masih bagian rantai (bukan void/sibling).
        $active = $rows->filter(fn ($r) => !in_array(strtolower((string) $r->void), ['true', '1'], true))->values();

        // Tidak ada baris aktif pending → pengajuan sudah selesai, jangan disentuh.
        $pending = $active->filter(fn ($r) => strtolower((string) $r->status) === 'pending')
            ->sortBy('approval_level')
            ->values();

        if ($pending->isEmpty()) {
            return ['synced' => true, 'changed' => false, 'reason' => 'sudah selesai'];
        }

        // Keputusan yang sah (rejected sudah ditangani di atas).
        $decided = $active->filter(fn ($r) => strtolower((string) $r->status) === 'approved')
            ->sortBy('approval_level')
            ->values();

        // Approver yang sudah memutuskan tidak diminta lagi.
        $decidedApprovers = $decided
            ->map(fn ($r) => (string) $r->approval_id)
            ->all();

        // Chain yang harus ada: master, minus approver yang sudah memutuskan.
        $target = [];
        foreach ($master as $rule) {
            $npk = (string) $rule->approval_id;

            if ($npk === '' || in_array($npk, $decidedApprovers, true)) {
                continue;
            }

            $target[] = $npk;
        }

        $changed = false;
        $chainComplete = false;

        DB::connection('cii')->transaction(function () use (
            $rows, $decided, $pending, $target, $groupId, $token, &$changed, &$chainComplete
        ) {
            $usedIds = [];
            $pool    = $pending->values();
            $level   = 1;
            $nextProgress = 1;

            // ── 1. Row yang sudah diputuskan → rapatkan level 1..k ──
            // Status/keputusan TIDAK diubah; hanya nomor level yang dirapikan
            // supaya rantai tetap berurutan tanpa celah.
            foreach ($decided as $row) {
                if ((int) $row->approval_level !== $level) {
                    LeaveRequest::where('id', $row->id)->update([
                        'approval_level' => $level,
                        'updated_at'     => now(),
                    ]);
                    $changed = true;
                }
                $level++;
            }

            // ── 2. Row pending → pasangkan dengan chain master yang baru ──
            $nextProgress = $level;

            foreach ($target as $npk) {
                // 1) Utamakan row pending dengan approver yang sama (id tetap,
                //    deep link WA lama masih valid).
                $match = $pool->first(fn ($r) => !in_array($r->id, $usedIds, true)
                    && (string) $r->approval_id === $npk);

                // 2) Kalau tidak ada, pakai row pending paling awal yang belum
                //    terpakai — approver di posisi itu diganti orang lain.
                if (!$match) {
                    $match = $pool->first(fn ($r) => !in_array($r->id, $usedIds, true));
                }

                if ($match) {
                    $usedIds[] = $match->id;

                    $updates = [];

                    if ((int) $match->approval_level !== $level) {
                        $updates['approval_level'] = $level;
                    }

                    if ((string) $match->approval_id !== $npk) {
                        $updates['approval_id'] = $npk;
                    }

                    if ($updates) {
                        LeaveRequest::where('id', $match->id)->update($updates + [
                            'void'       => 'false',
                            'updated_at' => now(),
                        ]);
                        $changed = true;
                    }
                } else {
                    // Approver baru hasil perubahan master → tambahkan row.
                    $src = $pool->first() ?? $decided->first();

                    LeaveRequest::create([
                        'NPK'               => $src->NPK,
                        'leave_type_id'     => $src->leave_type_id,
                        'start_date'        => $src->start_date,
                        'end_date'          => $src->end_date,
                        'total_days'        => $src->total_days,
                        'reason'            => $src->reason,
                        'approval_id'       => $npk,
                        'approval_level'    => $level,
                        'approval_progress' => $nextProgress,
                        'approval_date'     => null,
                        'status'            => 'pending',
                        'token'             => $token,
                        'void'              => 'false',
                        'attach_files'      => $src->attach_files,
                        'custom_dates'      => $src->custom_dates,
                        'rules_id'          => $groupId,
                    ]);
                    $changed = true;
                }

                $level++;
            }

            // ── 3. Sisa row pending yang approvernya sudah tidak ada di master ──
            // Dibuat `cancelled` + void (bukan dihapus) supaya jejaknya tetap ada.
            foreach ($pool as $orphan) {
                if (in_array($orphan->id, $usedIds, true)) {
                    continue;
                }

                $marker = '[otomatis: level dihapus dari Manage Approval]';
                $note   = trim((string) $orphan->comment);

                if (!str_contains($note, $marker)) {
                    $note = trim($note . ' ' . $marker);
                }

                LeaveRequest::where('id', $orphan->id)->update([
                    'status'         => 'cancelled',
                    'void'           => 'true',
                    // Pindahkan levelnya jauh ke luar rantai supaya tidak
                    // bertabrakan dengan level hasil perapatan (kalau tidak,
                    // ada dua baris di level yang sama).
                    'approval_level' => 900000 + (int) $orphan->id,
                    'comment'        => $note,
                    'updated_at'     => now(),
                ]);
                $changed = true;
            }

            // ── 4. Arahkan progress + pastikan rules_id terisi ──
            // Rantai selesai (semua approver master sudah memutuskan) → progress
            // diarahkan ke level terakhir yang ada, bukan level kosong di luar itu.
            $chainComplete = empty($target);
            $progressLevel = $chainComplete ? max(1, $level - 1) : $nextProgress;

            LeaveRequest::where('token', $token)->update([
                'approval_progress' => $progressLevel,
                'rules_id'          => $groupId,
                'updated_at'        => now(),
            ]);
        });

        // ── 5. Rantai selesai karena level terakhir dihapus ──
        // Semua approver yang tersisa di master sudah menyetujui → pengajuan
        // selesai dan saldo harus dipotong, sama seperti kalau level terakhir
        // di-approve lewat jalur normal.
        if ($chainComplete) {
            $anchor = $decided->last() ?? $pending->last();

            if ($anchor) {
                $leave = LeaveRequest::find($anchor->id);

                if ($leave) {
                    LeaveApprovalEffects::finalizeApproval($leave);
                    Log::info("[ApprovalChainSync] token {$token}: level terakhir dihapus & semua approver master sudah approve → difinalisasi");
                }
            }
        }

        return ['synced' => true, 'changed' => $changed, 'completed' => $chainComplete];
    }

    /**
     * Sinkronkan SEMUA token pending (sapuan menyeluruh).
     *
     * @return array{checked:int, changed:int, skipped:int}
     */
    public static function syncAllPending(): array
    {
        $tokens = DB::connection('cii')->select("
            SELECT DISTINCT token
            FROM leave_requests
            WHERE status = 'pending'
              AND (void IS NULL OR void = 'false' OR void = '')
        ");

        $stats = ['checked' => count($tokens), 'changed' => 0, 'skipped' => 0];

        foreach ($tokens as $t) {
            $result = self::syncToken((string) $t->token);

            if ($result['synced'] && $result['changed']) {
                $stats['changed']++;
            } elseif (!$result['synced']) {
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    /**
     * Tentukan approval group untuk satu token.
     *
     * Pakai `rules_id` yang tersimpan; kalau belum ada (data lama sebelum
     * kolom ini diisi), tebak dari departemen + section pemohon.
     */
    public static function resolveGroupId(Collection $rows): ?int
    {
        $stored = $rows->pluck('rules_id')->filter()->first();

        if ($stored) {
            return (int) $stored;
        }

        $npk = $rows->pluck('NPK')->filter()->first();

        if (!$npk) {
            return null;
        }

        $employee = DB::connection('cii')
            ->table('BIODATA')
            ->where('NPK', $npk)
            ->select('ID_DEPT', 'SECTION')
            ->first();

        if (!$employee) {
            Log::warning("[ApprovalChainSync] NPK {$npk} tidak ditemukan di BIODATA");

            return null;
        }

        $group = self::resolveGroupForDept(
            (string) $employee->ID_DEPT,
            $employee->SECTION === null ? null : (string) $employee->SECTION
        );

        return $group?->id;
    }

    /**
     * Group approval untuk kombinasi departemen + section.
     *
     * Prioritas: group khusus section → group umum departemen → group pertama.
     * Logika sama dengan yang dipakai saat pengajuan disubmit.
     */
    public static function resolveGroupForDept(string $deptId, ?string $sectionId): ?ApprovalDept
    {
        $groups = ApprovalDept::with(['rules'])
            ->get()
            ->filter(function ($group) use ($deptId) {
                $depts = is_array($group->dept)
                    ? $group->dept
                    : (json_decode((string) $group->dept, true) ?? []);

                return in_array((string) $deptId, array_map('strval', $depts), true);
            });

        if ($groups->isEmpty()) {
            return null;
        }

        if (!empty($sectionId)) {
            $bySection = $groups->first(fn ($g) => (string) $g->section === (string) $sectionId);
            if ($bySection) {
                return $bySection;
            }
        }

        return $groups->first(fn ($g) => empty($g->section)) ?? $groups->first();
    }
}
