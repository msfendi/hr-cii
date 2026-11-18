<?php

namespace App\Console\Commands;

use App\Models\WorkAssignmentRequest;
use App\Services\ApprovalWa\IzinDinasNotifier;
use Illuminate\Console\Command;
use Throwable;

/**
 * Kirim ulang notifikasi approval Izin Dinas yang belum terkirim.
 *
 * Jaring pengaman: webhook ke messaging layer bisa gagal (Hermes mati, jaringan
 * putus). Tanpa command ini, notifikasi yang gagal saat submit/approve hilang
 * begitu saja dan approver tidak pernah tahu ada pengajuan menunggu.
 *
 * Idempotent secara praktis: hanya level yang sedang giliran yang dikirimi,
 * jadi menjalankan berulang tidak menggandakan pesan ke level yang sudah lewat.
 *
 * Contoh:
 *   php artisan approval:notify-izin-dinas --dry-run
 *   php artisan approval:notify-izin-dinas --id=10009
 */
class NotifyIzinDinasApproval extends Command
{
    protected $signature = 'approval:notify-izin-dinas
                            {--id= : Hanya proses satu nomor pengajuan}
                            {--dry-run : Tampilkan saja, tidak mengirim}';

    protected $description = 'Kirim notifikasi WhatsApp untuk pengajuan Izin Dinas yang level-nya sedang giliran.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyId = $this->option('id');

        try {
            $query = WorkAssignmentRequest::query()
                ->where('status', 'pending')
                ->where(function ($q) {
                    $q->whereNull('void')
                      ->orWhereNotIn('void', ['true', '1']);
                });

            if ($onlyId !== null && $onlyId !== '') {
                $query->where('id', (int) $onlyId);
            }

            $requests = $query->orderBy('id')->get(['id', 'token', 'npk']);
        } catch (Throwable $e) {
            $this->error('Gagal membaca data pengajuan: ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($requests->isEmpty()) {
            $this->info('Tidak ada pengajuan Izin Dinas yang pending.');

            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;

        foreach ($requests as $request) {
            $result = IzinDinasNotifier::notifyToken((string) $request->token, $dryRun);

            if (($result['notified'] ?? 0) > 0) {
                $sent++;
                $to = implode(',', $result['sent'] ?? []);
                $this->line(($dryRun ? '  [dry] ' : '  ') . "#{$request->id} → {$to}");
                continue;
            }

            $skipped++;
            $reason = implode('; ', $result['skipped'] ?? ['tidak ada level eligible']);
            $this->line("  #{$request->id} dilewati: {$reason}");
        }

        $this->info("Selesai. Terkirim: {$sent}, dilewati: {$skipped}." . ($dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}