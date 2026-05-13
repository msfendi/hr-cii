<?php

namespace App\Console\Commands;

use App\Events\ContractExpiringEvent;
use App\Models\EmployeesContract;
use App\Models\NotificationsContract;
use Illuminate\Console\Command;

class CheckContractExpiring extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contract:check-expiring {--days=5}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check contracts expiring within specified days and send notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        
        $this->info("Checking contracts expiring within {$days} days...");

        try {
            // Query kontrak yang akan habis
            $expiringContracts = EmployeesContract::expiringInDays($days)->get();

            if ($expiringContracts->isEmpty()) {
                $this->info('No expiring contracts found.');
                return Command::SUCCESS;
            }

            $this->info("Found {$expiringContracts->count()} expiring contracts.");

            $createdCount = 0;
            $duplicateCount = 0;

            foreach ($expiringContracts as $contract) {
                // Cek apakah notifikasi untuk kontrak ini sudah ada hari ini
                $existingNotification = NotificationsContract::where('contract_id', $contract->id)
                    ->whereDate('created_at', today())
                    ->where('status', 'unread')
                    ->first();

                if ($existingNotification) {
                    $duplicateCount++;
                    $this->warn("Notification for contract {$contract->id} already exists today. Skipping...");
                    continue;
                }

                // Hitung sisa hari
                $daysRemaining = $contract->getDaysRemaining();

                // Create notification
                $notification = NotificationsContract::create([
                    'contract_id' => $contract->id,
                    'npk' => $contract->npk,
                    'employee_name' => $contract->getEmployeeName(),
                    'contract_end_date' => $contract->end_date,
                    'days_remaining' => $daysRemaining,
                    'type' => 'contract_expiring',
                    'status' => 'unread',
                    'notified_at' => now()
                ]);

                // Dispatch event untuk broadcast via Pusher
                ContractExpiringEvent::dispatch($notification);

                $createdCount++;
                
                $this->line(
                    "✓ Created notification for {$contract->getEmployeeName()} " .
                    "(Expires: {$contract->end_date->format('d M Y')}, Days: {$daysRemaining})"
                );
            }

            // Summary
            $this->newLine();
            $this->info("✓ Process completed!");
            $this->line("  Created: {$createdCount} notifications");
            $this->line("  Skipped (duplicates): {$duplicateCount}");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
