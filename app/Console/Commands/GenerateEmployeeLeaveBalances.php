<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LeaveBalanceGenerationService;

class GenerateEmployeeLeaveBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave:generate-employee-balances {--npk= : NPK karyawan tertentu} {--year= : Tahun periode saldo (default tahun berjalan)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate saldo cuti tahunan (berbasis saldo) untuk karyawan';

    /**
     * Execute the console command.
     */
    public function handle(LeaveBalanceGenerationService $service): int
    {
        $year = $this->option('year') ? (int) $this->option('year') : (int) date('Y');
        $npk = $this->option('npk');

        if ($npk) {
            $this->info("Menjalankan generate saldo cuti non-tahunan untuk NPK: {$npk} (Tahun: {$year})...");
            $result = $service->generateForEmployee($npk, null, $year);

            if (!$result['success']) {
                $this->error($result['message'] ?? 'Gagal generate balance');
                return self::FAILURE;
            }

            $this->info("Selesai! Dibuat: {$result['created']} | Dilewati: {$result['skipped']} | Dilewati Gender: {$result['skipped_gender']}");
            return self::SUCCESS;
        }

        $this->info("Menjalankan generate saldo cuti non-tahunan untuk seluruh karyawan aktif (Tahun: {$year})...");
        $result = $service->generateNonAnnualForActiveEmployees($year);

        $this->info("Selesai!");
        $this->table(
            ['Tahun', 'Total Karyawan Aktif', 'Saldo Dibuat', 'Dilewati (Sudah Ada)', 'Dilewati (Gender)'],
            [[
                $result['year'],
                $result['employees_count'],
                $result['created'],
                $result['skipped'],
                $result['skipped_gender'],
            ]]
        );

        return self::SUCCESS;
    }
}
