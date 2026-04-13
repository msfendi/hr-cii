<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Biodata;
use App\Models\LeaveTypes;
use App\Models\LeaveBalances;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GenerateDailyLeave extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave:generate-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate leave balance based on work anniversary (daily check)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = now();
        $year = $today->year;

        $leaveTypes = LeaveTypes::where('is_active', true)->get();
        if ($leaveTypes->isEmpty()) {
            $this->error('No active Leave Types found.');
            return;
        }

        // Ambil semua karyawan untuk mengecek anniversary hari ini via Collection Filter
        // Agar pembacaan format tanggal kolom database bisa di-parse secara cerdas
        $allEmployees = Biodata::leftJoin('PKWT', 'BIODATA.NPK', '=', 'PKWT.NPK')
            ->select('BIODATA.NPK', 'BIODATA.NAMA_KARYAWAN', 'PKWT.TMK', 'PKWT.TKK', 'PKWT.JK')
            ->get();

        $employeesToProcess = $allEmployees->filter(function($emp) use ($today) {
            $tkkKosong = empty($emp->TKK) || trim($emp->TKK) === '';
            $anniversaryToday = false;

            if (!empty($emp->TMK) && trim($emp->TMK) !== '') {
                try {
                    $tmk = Carbon::parse($emp->TMK);
                    
                    // Cek apakah tanggal dan bulannya sama persis dengan hari ini
                    if ($tmk->month === $today->month && $tmk->day === $today->day) {
                        // Dan apakah pekerja sudah lewat minimal 1 tahun 
                        if ($tmk->diffInYears($today) >= 1) {
                            $anniversaryToday = true;
                        }
                    }
                } catch (\Exception $e) {
                    // Abaikan format error kolom database
                }
            }
            return $tkkKosong && $anniversaryToday;
        });

        if ($employeesToProcess->isEmpty()) {
            $this->info("Today {$today->format('Y-m-d')} - Tidak ada karyawan dengan anniversary hari ini (TMK hari/bulan ini setahun lalu).");
            return;
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($employeesToProcess, $leaveTypes, $year, &$created, &$skipped) {
            foreach ($employeesToProcess as $employee) {
                foreach ($leaveTypes as $type) {
                    $remainedDays = $type->default_days;

                    // Logika JK sama seperti controller generate
                    if ($employee->JK === 'L' && in_array($type->id, [3, 4])) {
                        $remainedDays = 0;
                    } elseif ($employee->JK === 'P' && in_array($type->id, [6, 7])) {
                        $remainedDays = 0;
                    }

                    $balance = LeaveBalances::firstOrCreate(
                        [
                            'NPK'           => $employee->NPK,
                            'leave_type_id' => $type->id,
                            'year'          => $year,
                        ],
                        [
                            'remained_days' => $remainedDays,
                            'used_days'     => 0,
                        ]
                    );

                    $balance->wasRecentlyCreated ? $created++ : $skipped++;
                }
            }
        });

        $this->info("Selesai! Anniversary Date: {$today->format('Y-m-d')} | Created: {$created} | Skipped: {$skipped}");
    }
}
