<?php

namespace Database\Seeders;

use App\Models\LeaveTypes;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LeavesTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Aturan jenis cuti (sinkron dengan App\Models\LeaveTypes):
     * - count_calendar_days = 1  → Cuti Melahirkan & Cuti Keguguran memakai rentang
     *                                tanggal (tanggal awal & akhir) dan menghitung hari kalender.
     * - default_days > 0          → jumlah hari WAJIB dipilih persis dalam satu pengajuan.
     * - default_days = 0          → tanpa batas (Cuti Pribadi / Unpaid).
     * - max_requests              → batas jumlah pengajuan SEUMUR HIDUP
     *                                (null = belum ada batas).
     */
    public function run(): void
    {
        $types = [
            [
                'name'               => 'Cuti Tahunan',
                'code'               => 'tahunan',
                'default_days'       => 12,
                'is_active'          => true,
                'count_calendar_days' => false,
                'max_requests'       => null,
            ],
            [
                'name'               => 'Cuti Menikah',
                'code'               => 'menikah',
                'default_days'       => 3,
                'is_active'          => true,
                'count_calendar_days' => false,
                'max_requests'       => 1,
            ],
            [
                'name'               => 'Cuti Melahirkan',
                'code'               => 'melahirkan',
                'default_days'       => 90,
                'is_active'          => true,
                'count_calendar_days' => true,
                'max_requests'       => 2,
            ],
            [
                'name'               => 'Cuti Keguguran',
                'code'               => 'keguguran',
                'default_days'       => 45,
                'is_active'          => true,
                'count_calendar_days' => true,
                'max_requests'       => 2,
            ],
            [
                'name'               => 'Cuti Menikahkan Anak',
                'code'               => 'menikahkan_anak',
                'default_days'       => 2,
                'is_active'          => true,
                'count_calendar_days' => false,
                'max_requests'       => 3,
            ],
            [
                'name'               => 'Cuti Istri Melahirkan',
                'code'               => 'istri_melahirkan',
                'default_days'       => 2,
                'is_active'          => true,
                'count_calendar_days' => false,
                'max_requests'       => 2,
            ],
            [
                'name'               => 'Cuti Istri Keguguran',
                'code'               => 'istri_keguguran',
                'default_days'       => 2,
                'is_active'          => true,
                'count_calendar_days' => false,
                'max_requests'       => 2,
            ],
            [
                'name'               => 'Cuti Suami / Istri Meninggal',
                'code'               => 'suami_istri_meninggal',
                'default_days'       => 2,
                'is_active'          => true,
                'count_calendar_days' => false,
                'max_requests'       => null,
            ],
            [
                'name'               => 'Cuti Keluarga 1 Rumah Meninggal',
                'code'               => 'keluarga_meninggal',
                'default_days'       => 2,
                'is_active'          => true,
                'count_calendar_days' => false,
                'max_requests'       => null,
            ],
            [
                'name'               => 'Cuti Menghitankan/Membabtiskan Anak',
                'code'               => 'menghitankan_babtis_anak',
                'default_days'       => 2,
                'is_active'          => true,
                'count_calendar_days' => false,
                'max_requests'       => null,
            ],
            [
                'name'               => 'Cuti Anak Meninggal',
                'code'               => 'anak_meninggal',
                'default_days'       => 2,
                'is_active'          => true,
                'count_calendar_days' => false,
                'max_requests'       => null,
            ],
            [
                'name'               => 'Cuti Menantu Meninggal',
                'code'               => 'menantu_meninggal',
                'default_days'       => 2,
                'is_active'          => true,
                'count_calendar_days' => false,
                'max_requests'       => null,
            ],
            [
                'name'               => 'Cuti Orang Tua Meninggal',
                'code'               => 'orang_tua_meninggal',
                'default_days'       => 2,
                'is_active'          => true,
                'count_calendar_days' => false,
                'max_requests'       => 4,
            ],
            [
                // Tanpa batas hari per pengajuan & tanpa batas jumlah pengajuan
                'name'               => 'Cuti Pribadi (Unpaid)',
                'code'               => 'pribadi',
                'default_days'       => 0,
                'is_active'          => true,
                'count_calendar_days' => false,
                'max_requests'       => null,
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
