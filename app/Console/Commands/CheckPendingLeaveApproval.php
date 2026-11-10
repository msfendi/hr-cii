<?php

namespace App\Console\Commands;

use App\Services\ApprovalWa\LeaveApprovalQuery;
use App\Services\ApprovalWa\PhoneFormatter;
use App\Services\ApprovalWa\WaGateway;
use App\Services\ApprovalWa\WaMessageFormatter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckPendingLeaveApproval extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'approval:check-pending
                            {--dry-run : Print messages without actually sending to WhatsApp}';

    /**
     * The console command description.
     */
    protected $description = 'Check pending leave_requests and send WhatsApp notifications to the next approver in chain.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('[ApprovalWa] Checking pending leave requests...');
        Log::info('[ApprovalWa] Cron triggered: check-pending');

        $requests = LeaveApprovalQuery::getPendingRequests();

        if (empty($requests)) {
            $this->info('[ApprovalWa] No pending requests found.');
            Log::info('[ApprovalWa] No pending requests.');
            return self::SUCCESS;
        }

        $this->info('[ApprovalWa] Found ' . count($requests) . ' pending request(s).');

        foreach ($requests as $request) {
            $this->processRequest($request, $dryRun);
        }

        return self::SUCCESS;
    }

    private function processRequest(object $request, bool $dryRun): void
    {
        $id       = $request->id;
        $progress = (int) $request->approval_progress;
        $level    = (int) $request->approval_level;

        // If already at final level, skip (shouldn't happen but safety net)
        if ($progress >= $level) {
            $this->line("  [#$id] Already at final level ($progress/$level), skipping.");
            return;
        }

        // Get the next approver in chain
        $approver = LeaveApprovalQuery::getNextApprover($request->approval_id, $progress);

        if (!$approver) {
            $this->warn("  [#$id] No approver found for approval_id={$request->approval_id} level=" . ($progress + 1));
            Log::warning("[ApprovalWa] #$id: No approver found for approval_id={$request->approval_id} level=" . ($progress + 1));
            return;
        }

        // Normalize phone
        $rawPhone  = $approver->HP ?? null;
        $phone     = PhoneFormatter::normalize($rawPhone);

        if (!PhoneFormatter::isValid($phone)) {
            $this->warn("  [#$id] Approver {$approver->NAMA} has invalid/missing phone: '$rawPhone'");
            Log::warning("[ApprovalWa] #$id: Invalid phone for approver {$approver->approval_id}: '$rawPhone'");
            return;
        }

        // Format message
        $message = WaMessageFormatter::formatApprovalRequest($request, $approver);

        $this->line("  [#$id] → {$approver->NAMA} ($phone) level=" . ($progress + 1) . "/$level");

        if ($dryRun) {
            $this->line("  [DRY RUN] Message:\n$message\n");
            return;
        }

        // Send via WA gateway
        $sent = WaGateway::send($phone, $message);
        if ($sent) {
            $this->info("  [#$id] ✅ Notification sent to $phone");
        } else {
            $this->error("  [#$id] ❌ Failed to send to $phone");
        }
    }
}
