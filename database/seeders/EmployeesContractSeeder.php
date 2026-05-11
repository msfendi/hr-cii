<?php

namespace Database\Seeders;

use App\Models\EmployeesContract;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class EmployeesContractSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $npks = [
            'C-00001', // ANTHONY CHIN
            'C-00003', // LIM SOON SENG
            'C-00005', // NGUYEN TAT DAT
            'C-00006', // LUO YONGHONG
            'C-00007', // PAUL BELLO ISANAN
            'C-00008', // SAMEERA RUBASIN
            'C-00009', // WONG WEI TSE
            'C-00011', // RAKHMAT BUDIYONO
        ];

        // Start date: 14 Mei 2025
        $startDate = Carbon::createFromFormat('Y-m-d', '2025-05-14');
        
        // Kita set durasi 12 bulan sebagai contoh, 
        // sehingga end_date akan jatuh pada 13 Mei 2026 (tepat 7 hari dari sekarang jika tgl 6 Mei 2026)
        // Ini bagus untuk menguji filter "Segera Berakhir (≤7 hari)"
        $monthDuration = 12;
        $endDate = $startDate->copy()->addMonths($monthDuration)->subDay();

        foreach ($npks as $npk) {
            // Kita pakai updateOrCreate untuk menghindari duplikat jika seeder dijalankan ulang
            EmployeesContract::updateOrCreate(
                ['npk' => $npk, 'status_contract' => 'AKTIF'],
                [
                    'contract_ke'     => 1,
                    'start_date'      => $startDate->toDateString(),
                    'end_date'        => $endDate->toDateString(),
                    'month_duration'  => $monthDuration,
                    'salary'          => 5000000, 
                    'allowance'       => 1000000,
                ]
            );
        }
    }
}
