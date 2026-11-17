<?php

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Menu sidebar v1 + permission untuk halaman admin What's New (TASK 2).
 *
 * Idempotent: firstOrCreate / syncWithoutDetaching, jadi aman dijalankan
 * pada instalasi yang sudah punya menu "System" (dibuat migrasi face
 * recognition) maupun yang belum.
 */
return new class extends Migration
{
    /** @var array<string, array{name: string, group: string}> */
    private array $permissions = [
        'admin.whats-new.index'   => ['name' => 'Lihat Banner What\'s New', 'group' => 'System'],
        'admin.whats-new.create'  => ['name' => 'Tambah Banner What\'s New', 'group' => 'System'],
        'admin.whats-new.store'   => ['name' => 'Simpan Banner What\'s New', 'group' => 'System'],
        'admin.whats-new.edit'    => ['name' => 'Ubah Banner What\'s New', 'group' => 'System'],
        'admin.whats-new.update'  => ['name' => 'Perbarui Banner What\'s New', 'group' => 'System'],
        'admin.whats-new.destroy' => ['name' => 'Hapus Banner What\'s New', 'group' => 'System'],
        'admin.whats-new.toggle'  => ['name' => 'Aktifkan/Nonaktifkan Banner What\'s New', 'group' => 'System'],
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

        // Admin selalu lolos middleware permission, tetapi permission tetap
        // di-attach supaya bisa dibagikan ke role lain lewat menu Role.
        $admin = Role::where('name', 'Admin')->first();

        if ($admin) {
            $admin->permissions()->syncWithoutDetaching($permissionIds);
        }

        $system = Menu::firstOrCreate(
            ['name' => 'System', 'parent_id' => null],
            ['icon' => 'fas fa-cog', 'order' => 0, 'is_active' => true]
        );

        $indexPermission = Permission::where('route_name', 'admin.whats-new.index')->first();

        Menu::firstOrCreate(
            ['name' => "What's New", 'parent_id' => $system->id],
            [
                'route_name'    => 'admin.whats-new.index',
                'permission_id' => $indexPermission?->id,
                'icon'          => 'fas fa-bullhorn',
                'order'         => (int) Menu::where('parent_id', $system->id)->max('order') + 1,
                'is_active'     => true,
            ]
        );
    }

    public function down(): void
    {
        Menu::where('route_name', 'admin.whats-new.index')->delete();

        Permission::whereIn('route_name', array_keys($this->permissions))->delete();
    }
};