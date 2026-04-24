<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InsentifThresholdSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('insentif_thresholds')->truncate();

        DB::table('insentif_thresholds')->insert([
            [
                'id' => 1,
                'insentif_type' => 'Sewing',
                'days' => 1,
                'minimum' => 50.00,
                'type' => 'Percentage',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'insentif_type' => 'Sewing',
                'days' => 2,
                'minimum' => 65.00,
                'type' => 'Percentage',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'insentif_type' => 'Sewing',
                'days' => 3,
                'minimum' => 85.00,
                'type' => 'Percentage',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
