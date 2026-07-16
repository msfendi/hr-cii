<?php

namespace Database\Seeders;

use App\Models\RolePayroll;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder ini SEKALI JALAN untuk migrasi dari sistem lama (role Spatie
 * Payroll_STAFF/NONSTAFF/SEWING/NONSEWING) ke table role_payrolls yang baru.
 *
 * Jalankan dengan: php artisan db:seed --class=RolePayrollSeeder
 *
 * CATATAN PENTING:
 * User dengan role Spatie "Compliance" TIDAK di-seed otomatis di sini,
 * karena sesuai requirement, sebagian Compliance itu Payroll_STAFF dan
 * sebagian lagi Payroll_NONSTAFF -- harus di-assign manual satu-satu
 * lewat halaman admin /role-payroll setelah seeder ini jalan.
 */
class RolePayrollSeeder extends Seeder
{
    public function run(): void
    {
        $payrollRoles = [
            'Payroll_STAFF',
            'Payroll_NONSTAFF',
            'Payroll_SEWING',
            'Payroll_NONSEWING',
            'Payroll_ALL',
        ];

        foreach ($payrollRoles as $roleName) {

            $users = User::role($roleName)->get();

            foreach ($users as $user) {

                RolePayroll::updateOrCreate(
                    ['user_id' => $user->id],
                    ['payroll_role' => $roleName]
                );

                $this->command->info("User #{$user->id} ({$user->name}) -> {$roleName}");
            }
        }

        $this->command->warn(
            'Selesai. User dengan role Compliance TIDAK di-seed otomatis - ' .
            'silakan assign manual lewat halaman /role-payroll.'
        );
    }
}
