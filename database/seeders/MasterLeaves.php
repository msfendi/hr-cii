<?php

namespace Database\Seeders;

use App\Models\Biodata;
use App\Models\LeaveRecap;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MasterLeaves extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employee = Biodata::all();

        foreach ($employee as $employee) {
            LeaveRecap::create([
                'npk' => $employee->NPK,
                'year' => date('Y'),
                'tahunan_balance' => 12,
                'tahunan_used' => 0,
                'tahunan_remaining' => 12,
                'menikah_balance' => 3,
                'menikah_used' => 0,
                'menikah_remaining' => 3,
                'melahirkan_balance' => 90,
                'melahirkan_used' => 0,
                'melahirkan_remaining' => 90,
                'keguguran_balance' => 45,
                'keguguran_used' => 0,
                'keguguran_remaining' => 45,
                'menikahkan_balance' => 2,
                'menikahkan_used' => 0,
                'menikahkan_remaining' => 2,
                'istri_melahirkan_balance' => 2,
                'istri_melahirkan_used' => 0,
                'istri_melahirkan_remaining' => 2,
                'istri_keguguran_balance' => 2,
                'istri_keguguran_used' => 0,
                'istri_keguguran_remaining' => 2,
                'istri_anak_meninggal_balance' => 2,
                'istri_anak_meninggal_used' => 0,
                'istri_anak_meninggal_remaining' => 2,
                'keluarga_meninggal_balance' => 2,
                'keluarga_meninggal_used' => 0,
                'keluarga_meninggal_remaining' => 2,
                'pribadi_balance' => 1,
                'pribadi_used' => 0,
                'pribadi_remaining' => 1,
            ]);
        }
    }
}
