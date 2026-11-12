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
    protected $signature = 'approval:check-pending
                            {--dry-run : Print messages without actually sending to WhatsApp}';

    protected $description = 'Check pending leave_requests and send WhatsApp notifications to the correct approver. Sequential: only notify level N if all previous levels are approved.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('[ApprovalWa] Checking pending leave requests...');
        Log::info('[ApprovalWa] Cron triggered: check-pending');

        $requests = LeaveApprovalQuery::getPendingForNotification();

        if (empty($requests)) {
            $this->info('[ApprovalWa] No pending requests found.');
            Log::info('[ApprovalWa] No pending requests.');
            return self::SUCCESS;
        }

        $this->info('[ApprovalWa] Found ' . count($requests) . ' request(s) eligible for notification.');

        foreach ($requests as $request) {
            $this->processRequest($request, $dryRun);
        }

        return self::SUCCESS;
    }

    private function processRequest(object $request, bool $dryRun): void
    {
        $id          = $request->id;
        $approverNpk = $request->approval_id;
        $level       = (int) $request->approval_level;

        // Get approver info (HP, NAMA)
        $approverInfo = LeaveApprovalQuery::getApproverInfo($approverNpk);

        if (!$approverInfo) {
            $this->warn("  [#$id] Approver NPK=$approverNpk not found in PKWT");
            Log::warning("[ApprovalWa] #$id: Approver NPK=$approverNpk not found in PKWT");
            return;
        }

        $rawPhone = $approverInfo->HP ?? null;
        $phone    = PhoneFormatter::normalize($rawPhone);

        if (!PhoneFormatter::isValid($phone)) {
            $this->warn("  [#$id] Approver {$approverInfo->NAMA} has invalid/missing phone: '$rawPhone'");
            Log::warning("[ApprovalWa] #$id: Invalid phone for $approverNpk: '$rawPhone'");
            return;
        }

        // Format message with WA deep links for approve/reject
        $message = WaMessageFormatter::formatApprovalRequest($request, $approverInfo);

        $this->line("  [#$id] → {$approverInfo->NAMA} ($phone) level=$level");

        if ($dryRun) {
            $this->line("  [DRY RUN] Message:\n$message\n");
            return;
        }

        $sent = WaGateway::send($phone, $message);
        if ($sent) {
            $this->info("  [#$id] ✅ Notification sent to $phone");
            Log::info("[ApprovalWa] #$id sent to $phone");
        } else {
            $this->error("  [#$id] ❌ Failed to send to $phone");
            Log::error("[ApprovalWa] #$id failed to send to $phone");
        }
    }
}
