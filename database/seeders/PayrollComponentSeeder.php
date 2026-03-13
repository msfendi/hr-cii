<?php

namespace Database\Seeders;

use App\Models\PayrollComponent;
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
        $components = [
            ['name' => 'Basic Salary', 'code' => 'basic_salary', 'type' => 'earning', 'calculation_method' => 'formula', 'value' => '', 'formula' => 'basic_salary', 'description' => 'Gaji pokok bulanan', 'category' => 'Gaji Pokok', 'priority' => '100', 'is_taxable' => '1', 'is_active' => '1',],
            ['name' => 'Overtime Pay', 'code' => 'overtime_pay', 'type' => 'earning', 'calculation_method' => 'formula', 'value' => '', 'formula' => '(overtime_hours > 0 ? (basic_salary/173)*((2*overtime_hours)-0.5) : 0)', 'description' => 'Upah lembur karyawan, *1.5 untuk jam pertama *2 untuk jam selanjutnya', 'category' => 'Overtime', 'priority' => '90', 'is_taxable' => '0', 'is_active' => '1',],
            ['name' => 'Absence Deduction', 'code' => 'absence_deduction', 'type' => 'deduction', 'calculation_method' => 'formula', 'value' => '', 'formula' => '(basic_salary/21)*absence_days', 'description' => 'Potongan gaji karena ketidakhadiran (per hari)', 'category' => 'Potongan', 'priority' => '80', 'is_taxable' => '0', 'is_active' => '1',],
            ['name' => 'Monthly Premi', 'code' => 'monthly_premi', 'type' => 'earning', 'calculation_method' => 'formula', 'value' => '', 'formula' => '(absence_days > 0 ? 0 : 50000)', 'description' => 'Premi bulanan didapatkan ketika karyawan tidak ada absence', 'category' => 'Tunjangan', 'priority' => '90', 'is_taxable' => '0', 'is_active' => '1',],
            ['name' => 'Long Service Allowance', 'code' => 'long_service_allowance', 'type' => 'earning', 'calculation_method' => 'formula', 'value' => '', 'formula' => '(working_years > 15 ? 52000 : (working_years > 10 && working_years <= 15 ? 41000 : (working_years > 5 && working_years <= 10 ? 31000 : (working_years > 1 && working_years <= 5 ? 21000 : 0))))', 'description' => 'Tunjangan masa kerja karyawan', 'category' => 'Tunjangan', 'priority' => '90', 'is_taxable' => '0', 'is_active' => '1',],
            ['name' => 'Allowance', 'code' => 'allowance', 'type' => 'earning', 'calculation_method' => 'formula', 'value' => '', 'formula' => 'allowance', 'description' => 'Tunjangan karyawan', 'category' => 'Tunjangan', 'priority' => '90', 'is_taxable' => '0', 'is_active' => '1',],
            ['name' => 'BPJS Kesehatan', 'code' => 'bpjs_kesehatan', 'type' => 'deduction', 'calculation_method' => 'formula', 'value' => '', 'formula' => '(basic_salary > 12000000 ? (12000000*1/100) : (basic_salary*1/100))', 'description' => 'BPJS Kesehatan', 'category' => 'Potongan', 'priority' => '80', 'is_taxable' => '0', 'is_active' => '1',],
            ['name' => 'BPJS Ketenagakerjaan', 'code' => 'bpjs_ketenagakerjaan', 'type' => 'deduction', 'calculation_method' => 'formula', 'value' => '', 'formula' => '(basic_salary > 10547428 ? (10547428*1/100) : (basic_salary*2/100))', 'description' => 'BPJS Ketenagakerjaan', 'category' => 'Potongan', 'priority' => '80', 'is_taxable' => '0', 'is_active' => '1',],
            ['name' => 'Special Overtime', 'code' => 'special_overtime_pay', 'type' => 'earning', 'calculation_method' => 'formula', 'value' => '', 'formula' => '(basic_salary > 3800000 ? floor(special_overtime_hours/8)*(basic_salary/21) : (basic_salary/173)*(special_overtime_hours<=8 ? 2*special_overtime_hours : (16 + 3 + 4*max(0,special_overtime_hours-9))))', 'description' => 'Special Overtime', 'category' => 'Overtime', 'priority' => '90', 'is_taxable' => '0', 'is_active' => '1',],
            ['name' => 'PPH 21', 'code' => 'pph_21', 'type' => 'earning', 'calculation_method' => 'formula', 'value' => '', 'formula' => '((basic_salary*12) <= 50000000 && basic_salary > 5000000 ? (basic_salary*5/100) : ((basic_salary*12) > 50000000 && (basic_salary*12) <= 250000000 ? (basic_salary*15/100) : ((basic_salary*12) > 250000000 && (basic_salary*12) <= 500000000 ? (basic_salary*25/100) : ((basic_salary*12) > 500000000 ? (basic_salary*30/100) : 0))))', 'description' => 'PPH 21', 'category' => 'Pajak', 'priority' => '80', 'is_taxable' => '0', 'is_active' => '1',],
            ['name' => 'Deduction PPH 21', 'code' => 'pph_21_deduction', 'type' => 'deduction', 'calculation_method' => 'formula', 'value' => '', 'formula' => '((basic_salary*12) <= 50000000 && basic_salary > 5000000 ? (basic_salary*5/100) : ((basic_salary*12) > 50000000 && (basic_salary*12) <= 250000000 ? (basic_salary*15/100) : ((basic_salary*12) > 250000000 && (basic_salary*12) <= 500000000 ? (basic_salary*25/100) : ((basic_salary*12) > 500000000 ? (basic_salary*30/100) : 0))))', 'description' => 'Potongan PPH 21', 'category' => 'Pajak', 'priority' => '80', 'is_taxable' => '0', 'is_active' => '1',],
            ['name' => 'Sewing Insentif', 'code' => 'sewing_insentif', 'type' => 'earning', 'calculation_method' => 'formula', 'value' => '', 'formula' => '{"105":37291.93,"104":34833.55,"103":32375.16,"102":29916.77,"101":27458.39,"100":27000,"99":25000,"98":24100,"97":23300,"96":22600,"95":22000,"94":20400,"93":19950,"92":19550,"91":19200,"90":18900,"89":18650,"88":18450,"87":18300,"86":18200,"85":18000,"70":15000,"50":12000}', 'description' => 'Insentif Operator Sewing, SPV, Chief, Mekanik', 'category' => 'Insentif', 'priority' => '90', 'is_taxable' => '0', 'is_active' => '1',],
            ['name' => 'Pad Print Insentif', 'code' => 'pad_insentif', 'type' => 'earning', 'calculation_method' => 'formula', 'value' => '', 'formula' => '{ "100": 11.00, "95": 8.00, "90": 6.00, "85": 2.50 }', 'description' => 'Insentif Pad Print dan Heatseal', 'category' => 'Insentif', 'priority' => '90', 'is_taxable' => '0', 'is_active' => '1',],
            ['name' => 'Cutting Insentif', 'code' => 'cutting_insentif', 'type' => 'earning', 'calculation_method' => 'formula', 'value' => '', 'formula' => '{"105":37291.93,"104":34833.55,"103":32375.16,"102":29916.77,"101":27458.39,"100":27000,"99":25000,"98":24100,"97":23300,"96":22600,"95":22000,"94":20400,"93":19950,"92":19550,"91":19200,"90":18900,"89":18650,"88":18450,"87":18300,"86":18200,"85":18000,"84":17750,"83":17550,"82":17400,"81":17300,"80":17250,"79":17000,"78":16800,"77":16650,"76":16550,"75":16500}', 'description' => 'Insentif Cutting', 'category' => 'Insentif', 'priority' => '90', 'is_taxable' => '0', 'is_active' => '1',],
        ];

        foreach ($components as $component) {
            PayrollComponent::create($component);
        }
    }
}
