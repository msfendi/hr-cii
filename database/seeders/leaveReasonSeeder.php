<?php

namespace Database\Seeders;

use App\Models\leaveReason;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class leaveReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $leaveReasons = [
            ['reason' => 'Tahunan', 'duration' => '12'],
            ['reason' => 'Menikah', 'duration' => '3'],
            ['reason' => 'Melahirkan', 'duration' => '90'],
            ['reason' => 'Keguguran', 'duration' => '45'],
            ['reason' => 'Menikahkan/Menghitankan/Membaptiskan', 'duration' => '2'],
            ['reason' => 'Istri Melahirkan/Keguguran', 'duration' => '2'],
            ['reason' => 'Istri/Anak/Menantu/Ortu Meninggal', 'duration' => '2'],
            ['reason' => 'Keluarga 1 Rumah Meninggal', 'duration' => '2'],
            ['reason' => 'Alasan Pribadi', 'duration' => '1'],
        ];

        foreach ($leaveReasons as $reason) {
            leaveReason::create([
                'reason' => $reason['reason'],
                'duration' => $reason['duration'],
            ]);
        }
    }
}
