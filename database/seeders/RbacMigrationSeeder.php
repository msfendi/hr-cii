<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeder contoh untuk migrasi sebagian kecil route lama (yang pakai role:Admin|HRD dst)
 * menjadi Permission + assignment ke Role + Menu, sesuai sidebar lama Anda.
 *
 * Jalankan: php artisan db:seed --class=RbacMigrationSeeder
 * Silakan duplikasi pola di bawah untuk seluruh route lain di web.php.
 */
class RbacMigrationSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Buat permission dari beberapa contoh route yang sebelumnya pakai role:Admin|HRD|Payroll_*
        $data = [
            ['name' => 'Lihat Biodata',        'route_name' => 'biodata.index',        'group' => 'Management'],
            ['name' => 'Lihat Kontrak Karyawan', 'route_name' => 'employees-contract.index', 'group' => 'Management'],
            ['name' => 'Lihat Attendance',      'route_name' => 'attendance.index',     'group' => 'Attendance'],
            ['name' => 'Lihat Payroll Master',  'route_name' => 'payroll-master.index', 'group' => 'Payroll'],
            ['name' => 'Lihat Payroll Process', 'route_name' => 'payroll-process.index', 'group' => 'Payroll'],
            ['name' => 'Lihat Insentif Threshold', 'route_name' => 'insentif.threshold.index', 'group' => 'Insentif'],
            ['name' => 'Lihat User',            'route_name' => 'user.index',           'group' => 'System'],
            ['name' => 'Lihat Role',            'route_name' => 'role.index',           'group' => 'System'],
        ];

        $permissions = [];
        foreach ($data as $row) {
            $permissions[$row['route_name']] = Permission::firstOrCreate(
                ['route_name' => $row['route_name']],
                ['name' => $row['name'], 'group' => $row['group']]
            );
        }

        // 2) Assign ke Role sesuai middleware lama (role:Admin|HRD|Payroll_STAFF dst)
        $mapping = [
            'Admin' => array_keys($permissions),
            'HRD'   => ['biodata.index', 'employees-contract.index', 'attendance.index'],
            'Payroll_STAFF' => ['payroll-master.index', 'payroll-process.index', 'insentif.threshold.index'],
        ];

        foreach ($mapping as $roleName => $routeNames) {
            $role = Role::where('name', $roleName)->first();
            if (!$role) continue;

            $ids = collect($routeNames)->map(fn($rn) => $permissions[$rn]->id ?? null)->filter();
            $role->permissions()->syncWithoutDetaching($ids);
        }

        // 3) Buat struktur menu 2 level contoh (Management > Biodata, Kontrak)
        $management = Menu::firstOrCreate(
            ['name' => 'Management', 'parent_id' => null],
            ['icon' => 'fas fa-users-cog', 'order' => 1]
        );

        Menu::firstOrCreate(
            ['name' => 'Biodata', 'parent_id' => $management->id],
            ['route_name' => 'biodata.index', 'permission_id' => $permissions['biodata.index']->id, 'order' => 1]
        );

        Menu::firstOrCreate(
            ['name' => 'Kontrak Karyawan', 'parent_id' => $management->id],
            ['route_name' => 'employees-contract.index', 'permission_id' => $permissions['employees-contract.index']->id, 'order' => 2]
        );
    }
}
