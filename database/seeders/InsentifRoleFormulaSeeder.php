<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InsentifRoleFormulaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('insentif_role_formulas')->truncate();

        DB::table('insentif_role_formulas')->insert([

            /*
            |--------------------------------------------------------------------------
            | PAD DEPARTMENT
            |--------------------------------------------------------------------------
            */

            [
                'role' => 'supervisor',
                'dept' => 'pad',
                'formula' => 'totalDeptInsentif / jumlahOperator'
            ],
            [
                'role' => 'leader',
                'dept' => 'pad',
                'formula' => 'totalDeptInsentif / jumlahOperator'
            ],
            [
                'role' => 'inkmaking',
                'dept' => 'pad',
                'formula' => 'totalDeptInsentif / jumlahOperator'
            ],
            [
                'role' => 'helper',
                'dept' => 'pad',
                'formula' => '(totalDeptInsentif / jumlahOperator) * 0.75'
            ],

            /*
            |--------------------------------------------------------------------------
            | CUTTING DEPARTMENT
            |--------------------------------------------------------------------------
            */

            // 0.75x
            [
                'role' => 'Bundling',
                'dept' => 'cutting',
                'formula' => 'insentif * 0.75'
            ],
            [
                'role' => 'Rib',
                'dept' => 'cutting',
                'formula' => 'insentif * 0.75'
            ],
            [
                'role' => 'Htl',
                'dept' => 'cutting',
                'formula' => 'insentif * 0.75'
            ],
            [
                'role' => 'Accescories',
                'dept' => 'cutting',
                'formula' => 'insentif * 0.75'
            ],
            [
                'role' => 'Supermarket',
                'dept' => 'cutting',
                'formula' => 'insentif * 0.75'
            ],
            [
                'role' => 'Loading to Sewing',
                'dept' => 'cutting',
                'formula' => 'insentif * 0.75'
            ],
            [
                'role' => 'Waste',
                'dept' => 'cutting',
                'formula' => 'insentif * 0.75'
            ],
            [
                'role' => 'Ganti BS',
                'dept' => 'cutting',
                'formula' => 'insentif * 0.75'
            ],
            [
                'role' => 'Piping',
                'dept' => 'cutting',
                'formula' => 'insentif * 0.75'
            ],
            [
                'role' => 'Cutting Admin',
                'dept' => 'cutting',
                'formula' => 'insentif * 0.75'
            ],
            [
                'role' => 'Supermarket Admin',
                'dept' => 'cutting',
                'formula' => 'insentif * 0.75'
            ],

            // 1.2x
            [
                'role' => 'Manual Cutter',
                'dept' => 'cutting',
                'formula' => 'insentif * 1.2'
            ],
            [
                'role' => 'Auto Cutter',
                'dept' => 'cutting',
                'formula' => 'insentif * 1.2'
            ],

            // 1x
            [
                'role' => 'Spreading Auto',
                'dept' => 'cutting',
                'formula' => 'insentif * 1'
            ],
            [
                'role' => 'Spreading Manual',
                'dept' => 'cutting',
                'formula' => 'insentif * 1'
            ],

            /*
            |--------------------------------------------------------------------------
            | SEWING / LINE INSENTIF
            |--------------------------------------------------------------------------
            */

            [
                'role' => 'supervisor',
                'dept' => 'sewing',
                'formula' => 'totalLineInsentif * 2'
            ],
            [
                'role' => 'chief',
                'dept' => 'sewing',
                'formula' => '(totalLineInsentif * jumlahLine) / 2'
            ],
            [
                'role' => 'mekanik',
                'dept' => 'sewing',
                'formula' => '(totalLineInsentif * jumlahLine) / 4'
            ],
            [
                'role' => 'mekanik_leader',
                'dept' => 'sewing',
                'formula' => '(totalLineInsentif * jumlahLine) * 0.15'
            ],

        ]);
    }
}
