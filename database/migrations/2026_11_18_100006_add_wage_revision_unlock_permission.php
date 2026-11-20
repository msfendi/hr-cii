<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Permission untuk membuka kunci batch penyesuaian upah yang sudah dibatalkan (rolled_back).
     * Hanya Admin: pemrosesan ulang setelah pembatalan harus lewat admin.
     */
    private array $permissions = [
        'wage-revisions.unlock' => ['name' => 'Buka Kunci Batch Penyesuaian Upah (Admin)', 'group' => 'Payroll'],
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
    }

    public function down(): void
    {
        Permission::whereIn('route_name', array_keys($this->permissions))->delete();
    }
};
