<?php

namespace Database\Seeders;

use App\Models\Biodata;
use App\Models\LeaveBalances;
use App\Models\LeaveTypes;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LeavesBalancesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ambil tahun dari argumen CLI jika ada, default tahun sekarang
        $year = (int) ($this->command->hasOption('year') && $this->command->option('year') ? $this->command->option('year') : now()->year);
 
        $leaveTypes = LeaveTypes::where('is_active', true)->get();
        $employees  = Biodata::leftJoin('PKWT', 'BIODATA.NPK', 'PKWT.NPK')->select('BIODATA.*', 'PKWT.TMK')->get();
 
        if ($leaveTypes->isEmpty()) {
            $this->command->warn('Tidak ada LeaveType aktif. Jalankan LeaveTypeSeeder terlebih dahulu.');
            return;
        }
 
        if ($employees->isEmpty()) {
            $this->command->warn('Tidak ada karyawan aktif di tabel biodata.');
            return;
        }
 
        $this->command->info("Generating leave balances untuk tahun {$year}...");
        $this->command->info("Karyawan aktif : {$employees->count()}");
        $this->command->info("Jenis cuti     : {$leaveTypes->count()}");
 
        $created = 0;
        $skipped = 0;
        $bar     = $this->command->getOutput()->createProgressBar($employees->count());
        $bar->start();
 
        DB::transaction(function () use ($employees, $leaveTypes, $year, &$created, &$skipped, $bar) {
            foreach ($employees as $employee) {
                foreach ($leaveTypes as $type) {
                    $remainedDays = $this->calculateRemainedDays($type, $employee, $year);
 
                    $balance = LeaveBalances::firstOrCreate(
                        [
                            'NPK'    => $employee->NPK,
                            'leave_type_id' => $type->id,
                            'year'          => $year,
                        ],
                        [
                            'remained_days' => $remainedDays,
                            'used_days'      => 0,
                        ]
                    );
 
                    $balance->wasRecentlyCreated ? $created++ : $skipped++;
                }
 
                $bar->advance();
            }
        });
 
        $bar->finish();
        $this->command->newLine(2);
        $this->command->info("Selesai! Created : {$created} | Skipped (sudah ada) : {$skipped}");
    }
 
    /**
     * Hitung jatah cuti.
     * Karyawan baru mendapatkan cuti penuh setelah 1 tahun berjalan.
     *
     * Jenis cuti lain (pernikahan, melahirkan, dll.) selalu penuh.
     */
    private function calculateRemainedDays(LeaveTypes $type, Biodata $employee, int $year): int
    {
        // Jenis cuti selain tahunan → selalu penuh
        if ($type->code !== 'tahunan') {
            return $type->default_days;
        }
 
        // Jika tidak ada TMK → berikan penuh
        if (! $employee->TMK) {
            return $type->default_days;
        }
 
        $joinDate   = \Carbon\Carbon::parse($employee->TMK);
        $joinYear   = $joinDate->year;
        $targetYear = $year;
 
        // Karyawan mendapatkan full cuti di tahun berikutnya (setelah 1 tahun berjalan)
        if ($joinYear < $targetYear) {
            return $type->default_days;
        }
 
        // Karyawan di tahun pertama bergabung belum mendapatkan jatah cuti tahunan
        return 0;
    }
}
