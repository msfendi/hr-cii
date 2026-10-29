<?php

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Permission yang dipakai oleh route face recognition.
     */
    private array $permissions = [
        'face-recognition.index'    => ['name' => 'Lihat Manage Face Recognition', 'group' => 'System'],
        'face-recognition.store'    => ['name' => 'Simpan Face Recognition', 'group' => 'System'],
        'face-recognition.settings' => ['name' => 'Pengaturan Face Recognition', 'group' => 'System'],
        'face-recognition.identify' => ['name' => 'Test Face Recognition', 'group' => 'System'],
        'face-recognition.lookup'   => ['name' => 'Cari Karyawan Face Recognition', 'group' => 'System'],
        'face-recognition.toggle'   => ['name' => 'Toggle Face Recognition', 'group' => 'System'],
        'face-recognition.destroy'  => ['name' => 'Hapus Face Recognition', 'group' => 'System'],
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

        // Beri akses ke role Admin (role lain bisa diberikan lewat menu Role Permission).
        $admin = Role::where('name', 'Admin')->first();

        if ($admin) {
            $admin->permissions()->syncWithoutDetaching($permissionIds);
        }

        $system = Menu::firstOrCreate(
            ['name' => 'System', 'parent_id' => null],
            ['icon' => 'fas fa-cog', 'order' => 0]
        );

        $indexPermission = Permission::where('route_name', 'face-recognition.index')->first();

        Menu::firstOrCreate(
            ['name' => 'Face Recognition', 'parent_id' => $system->id],
            [
                'route_name'    => 'face-recognition.index',
                'permission_id' => $indexPermission?->id,
                'icon'          => 'fas fa-camera',
                'order'         => (int) Menu::where('parent_id', $system->id)->max('order') + 1,
                'is_active'     => true,
            ]
        );
    }

    public function down(): void
    {
        Menu::where('route_name', 'face-recognition.index')->delete();

        Permission::whereIn('route_name', array_keys($this->permissions))->delete();
    }
};
