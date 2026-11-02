<?php

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Permission route untuk halaman admin face attendance.
     */
    private array $permissions = [
        'face.attendance.admin.index'         => ['name' => 'Lihat Rekap Presensi Semua Karyawan', 'group' => 'Face Attendance'],
        'face.attendance.admin.export'        => ['name' => 'Export Rekap Presensi Semua Karyawan', 'group' => 'Face Attendance'],
        'face.attendance.admin.detail'         => ['name' => 'Lihat Detail Presensi Karyawan', 'group' => 'Face Attendance'],
        'face.attendance.admin.card-menu'      => ['name' => 'Lihat Pengaturan Card Menu HRIS', 'group' => 'Face Attendance'],
        'face.attendance.admin.card-menu.update' => ['name' => 'Ubah Visibility Card Menu HRIS', 'group' => 'Face Attendance'],
    ];

    public function up(): void
    {
        $permissionIds = [];

        foreach ($this->permissions as $routeName => $data) {
            $permission = Permission::firstOrCreate(
                ['route_name' => $routeName],
                ['name' => $data['name'], 'group' => $data['group']]
            );

            $permissionIds[] = $permission->id;
        }

        // Beri akses ke role Admin (role lain bisa diberikan lewat halaman Role Permission).
        $admin = Role::where('name', 'Admin')->first();

        if ($admin) {
            $admin->permissions()->syncWithoutDetaching($permissionIds);
        }

        // Parent menu "Face Attendance"
        $faceParent = Menu::firstOrCreate(
            ['name' => 'Face Attendance', 'parent_id' => null],
            ['icon' => 'fas fa-camera', 'order' => 0, 'is_active' => true]
        );

        $indexPermission = Permission::where('route_name', 'face.attendance.admin.index')->first();
        $cardMenuPermission = Permission::where('route_name', 'face.attendance.admin.card-menu')->first();

        // Sub-menu: Rekap Presensi Semua Karyawan
        Menu::firstOrCreate(
            ['name' => 'Rekap Presensi Semua Karyawan', 'parent_id' => $faceParent->id],
            [
                'route_name'    => 'face.attendance.admin.index',
                'permission_id' => $indexPermission?->id,
                'icon'          => 'fas fa-users-cog',
                'order'         => (int) Menu::where('parent_id', $faceParent->id)->max('order') + 1,
                'is_active'     => true,
            ]
        );

        // Sub-menu: Pengaturan Card Menu HRIS
        Menu::firstOrCreate(
            ['name' => 'Pengaturan Card Menu HRIS', 'parent_id' => $faceParent->id],
            [
                'route_name'    => 'face.attendance.admin.card-menu',
                'permission_id' => $cardMenuPermission?->id,
                'icon'          => 'fas fa-th-large',
                'order'         => (int) Menu::where('parent_id', $faceParent->id)->max('order') + 1,
                'is_active'     => true,
            ]
        );
    }

    public function down(): void
    {
        Menu::whereIn('route_name', [
            'face.attendance.admin.index',
            'face.attendance.admin.card-menu',
        ])->delete();

        Permission::whereIn('route_name', array_keys($this->permissions))->delete();
    }
};
