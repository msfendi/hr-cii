<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PayrollComponentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('payroll_components')->insert(
            [
                'name' => 'Basic Salary',
                'code' => 'basic_salary',
                'type' => 'earning',
                'calculation_method' => 'fixed',
                'value' => 5000000,
                'description' => 'Gaji pokok bulanan',
                'category' => 'gaji pokok',
                'priority' => 100,
                'is_taxable' => true,
                'is_active' => true
            ],
        );

        DB::table('payroll_components')->insert(
            [
                'name' => 'Overtime Pay',
                'code' => 'overtime_pay',
                'type' => 'earning',
                'calculation_method' => 'formula',
                'formula' => '(basic_salary / 173) * 1.5 * overtime_hours',
                'description' => 'Upah lembur per jam (1.5x upah per jam)',
                'category' => 'tunjangan',
                'priority' => 90,
                'is_taxable' => true,
                'is_active' => true
            ]
        );

        DB::table('payroll_components')->insert(
            [
                'name' => 'Absence Deduction',
                'code' => 'absence_deduction',
                'type' => 'deduction',
                'calculation_method' => 'formula',
                'formula' => '(basic_salary / 21) * absence_days',
                'description' => 'Potongan gaji karena ketidakhadiran (per hari)',
                'category' => 'Potongan',
                'priority' => 80,
                'is_taxable' => false,
                'is_active' => true
            ]
        );
    }
}
