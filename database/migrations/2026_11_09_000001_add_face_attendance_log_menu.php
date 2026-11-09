<?php

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private array $permissions = [
        'face.attendance.admin.log'        => ['name' => 'Lihat Log Face Attendance', 'group' => 'Face Attendance'],
        'face.attendance.admin.log.export' => ['name' => 'Export Log Face Attendance', 'group' => 'Face Attendance'],
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

        $admin = Role::where('name', 'Admin')->first();
        if ($admin) {
            $admin->permissions()->syncWithoutDetaching($permissionIds);
        }

        $faceParent = Menu::where('name', 'Face Attendance')->whereNull('parent_id')->first();
        if (!$faceParent) {
            $faceParent = Menu::firstOrCreate(
                ['name' => 'Face Attendance', 'parent_id' => null],
                ['icon' => 'fas fa-camera', 'order' => 0, 'is_active' => true]
            );
        }

        $logPermission = Permission::where('route_name', 'face.attendance.admin.log')->first();

        Menu::firstOrCreate(
            ['name' => 'Log Face Attendance', 'parent_id' => $faceParent->id],
            [
                'route_name'    => 'face.attendance.admin.log',
                'permission_id' => $logPermission?->id,
                'icon'          => 'fas fa-clipboard-list',
                'order'         => (int) Menu::where('parent_id', $faceParent->id)->max('order') + 1,
                'is_active'     => true,
            ]
        );
    }

    public function down(): void
    {
        Menu::where('route_name', 'face.attendance.admin.log')->delete();
        Permission::whereIn('route_name', array_keys($this->permissions))->delete();
    }
};
