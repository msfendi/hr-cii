<?php

namespace App\Console\Commands;

use App\Models\ApprovalDept;
use App\Services\Approval\ApprovalChainSync;
use Illuminate\Console\Command;

/**
 * Sinkronkan rantai approval pada `leave_requests` dengan master approval.
 *
 * Dipakai kalau master approval (Manage Approval) diubah DI LUAR alur normal
 * (mis. import Excel langsung ke DB, edit manual), atau sebagai jaring
 * pengaman terjadwal. Perubahan lewat UI Manage Approval sudah otomatis
 * memanggil sinkronisasi ini.
 *
 * Hanya pengajuan berstatus PENDING yang diubah. Pengajuan yang sudah
 * diputuskan (approved/rejected) tidak pernah disentuh.
 */
class SyncApprovalChains extends Command
{
    protected $signature = 'approval:sync-chains
                            {--group= : Sinkronkan hanya satu approval group (id)}
                            {--dry-run : Tampilkan apa yang akan dilakukan, tanpa mengubah data}';

    protected $description = 'Rapikan level master approval dan sinkronkan ulang pengajuan cuti yang masih pending.';

    public function handle(): int
    {
        $groupId = $this->option('group');
        $dryRun  = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[SCAN SAJA] Tidak ada perubahan yang disimpan.');
        }

        if ($groupId) {
            if (!ApprovalDept::where('id', $groupId)->exists()) {
                $this->error("Approval group #{$groupId} tidak ditemukan.");

                return self::FAILURE;
            }

            $this->info("Menyinkronkan approval group #{$groupId} ...");

            if ($dryRun) {
                $this->reportDryRun((int) $groupId);

                return self::SUCCESS;
            }

            $stats = ApprovalChainSync::syncGroup((int) $groupId);

            $this->info(sprintf(
                'Group #%d: %d token pending, %d diubah, %d dilewati.',
                $groupId, $stats['tokens'], $stats['changed'], $stats['skipped']
            ));

            return self::SUCCESS;
        }

        // Seluruh group, supaya level master dirapikan walau tidak ada pengajuan.
        $groups = ApprovalDept::pluck('id');

        $this->info("Memeriksa {$groups->count()} approval group ...");

        $total = ['tokens' => 0, 'changed' => 0, 'skipped' => 0];

        foreach ($groups as $id) {
            if ($dryRun) {
                $this->reportDryRun((int) $id);
                continue;
            }

            $stats = ApprovalChainSync::syncGroup((int) $id);

            $total['tokens']  += $stats['tokens'];
            $total['changed'] += $stats['changed'];
            $total['skipped'] += $stats['skipped'];
        }

        if ($dryRun) {
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Selesai: %d token pending, %d diubah, %d dilewati.',
            $total['tokens'], $total['changed'], $total['skipped']
        ));

        return self::SUCCESS;
    }

    /**
     * Tampilkan group yang levelnya tidak berurutan (1..N) — indikasi pernah
     * ada level dihapus lewat jalur di luar UI.
     */
    private function reportDryRun(int $groupId): void
    {
        $rules = \App\Models\ApprovalRule::where('rules_id', $groupId)
            ->orderByRaw('CAST(level AS INT) ASC, id ASC')
            ->get();

        if ($rules->isEmpty()) {
            $this->line("  Group #{$groupId}: tidak punya rule.");

            return;
        }

        $levels = $rules->map(fn ($r) => (int) $r->level)->all();
        $gaps   = $levels !== range(1, count($levels));

        if (!$gaps) {
            return;
        }

        $this->line(sprintf(
            '  Group #%d: level %s perlu dirapikan.',
            $groupId,
            implode(',', $levels)
        ));
    }
}
