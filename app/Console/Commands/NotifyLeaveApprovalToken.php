<?php

namespace App\Console\Commands;

use App\Services\ApprovalWa\LeaveApprovalNotifier;
use Illuminate\Console\Command;

/**
 * Notifikasi per-pengajuan (satu token), bukan sweep semua pending.
 *
 * Dipakai untuk:
 *  - Testing manual satu pengajuan tertentu
 *  - Trigger ulang notifikasi kalau pengajuan sebelumnya gagal dikabari
 */
class NotifyLeaveApprovalToken extends Command
{
    protected $signature = 'approval:notify-token
                            {token : Token pengajuan (Str::random dari leave_requests)}
                            {--dry-run : Print pesan tanpa mengirim ke WhatsApp}';

    protected $description = 'Kirim notifikasi WhatsApp untuk satu pengajuan cuti (by token), sesuai level yang giliran.';

    public function handle(): int
    {
        $token  = $this->argument('token');
        $dryRun = $this->option('dry-run');

        $result = LeaveApprovalNotifier::notifyToken($token, $dryRun);

        if ($result['notified'] > 0) {
            $this->info("✅ Notifikasi dikirim untuk token $token");
            foreach ($result['sent'] as $requestId => $phone) {
                $this->line("   #$requestId → $phone");
            }
        } else {
            $this->warn("⚠️  Tidak ada notifikasi dikirim untuk token $token");
        }

        foreach ($result['skipped'] as $reason) {
            $this->line("   - $reason");
        }

        if ($dryRun && isset($result['message'])) {
            $this->newLine();
            $this->line($result['message']);
        }

        return self::SUCCESS;
    }
}
