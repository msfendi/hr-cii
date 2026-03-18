<?php

namespace Database\Seeders;

use App\Models\LeaveTypes;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LeavesTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'name'               => 'Cuti Tahunan',
                'code'               => 'tahunan',
                'default_days'       => 12,
                'is_active'          => true,
            ],
            [
                'name'               => 'Cuti Menikah',
                'code'               => 'menikah',
                'default_days'       => 3,
                'is_active'          => true,
            ],
            [
                'name'               => 'Cuti Melahirkan',
                'code'               => 'melahirkan',
                'default_days'       => 90,
                'is_active'          => true,
            ],
            [
                'name'               => 'Cuti Keguguran',
                'code'               => 'keguguran',
                'default_days'       => 45,
                'is_active'          => true,
            ],
            [
                'name'               => 'Cuti Menikahkan, Mengkhitankan Anak',
                'code'               => 'menikahkan_anak',
                'default_days'       => 2,
                'is_active'          => true,
            ],
            [
                'name'               => 'Cuti Istri Melahirkan',
                'code'               => 'istri_melahirkan',
                'default_days'       => 2,
                'is_active'          => true,
            ],
            [
                'name'               => 'Cuti Istri Keguguran',
                'code'               => 'istri_keguguran',
                'default_days'       => 2,
                'is_active'          => true,
            ],
            [
                'name'               => 'Cuti Istri / Anak Meninggal',
                'code'               => 'istri_anak_meninggal',
                'default_days'       => 2,
                'is_active'          => true,
            ],
            [
                'name'               => 'Cuti Keluarga Meninggal',
                'code'               => 'keluarga_meninggal',
                'default_days'       => 2,
                'is_active'          => true,
            ],
            [
                'name'               => 'Cuti Pribadi',
                'code'               => 'pribadi',
                'default_days'       => 1,
                'is_active'          => true,
            ],
        ];
 
        foreach ($types as $type) {
            LeaveTypes::updateOrCreate(
                ['code' => $type['code']], // cari berdasarkan code
                $type                      // update atau create dengan data ini
            );
        }
 
        $this->command->info('LeaveType seeded: ' . count($types) . ' records.');
    }
}
