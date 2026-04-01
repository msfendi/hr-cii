<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PayrollSettingSeeder extends Seeder
{
    public function run()
    {
        DB::table('payroll_settings')->insert([
            [
                'component'   => 'payroll',
                'approval'    => json_encode(["C-00011", "C-00001"]),
                'created_at'  => Carbon::now(),
                'updated_at'  => Carbon::now(),
            ],
            [
                'component'   => 'sewing_insentif',
                'approval'    => json_encode(["C-00011", "C-00003", "C-00001"]),
                'created_at'  => Carbon::now(),
                'updated_at'  => Carbon::now(),
            ],
            [
                'component'   => 'pad_insentif',
                'approval'    => json_encode(["C-00011", "C-00003", "C-00001"]),
                'created_at'  => Carbon::now(),
                'updated_at'  => Carbon::now(),
            ],
            [
                'component'   => 'cutting_insentif',
                'approval'    => json_encode(["C-00011", "C-00001", "C-00003"]),
                'created_at'  => Carbon::now(),
                'updated_at'  => Carbon::now(),
            ],
        ]);
    }
}
