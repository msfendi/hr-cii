<?php

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Permissions used by the wage revision (annual minimum wage adjustment) screens.
     */
    private array $permissions = [
        'wage-revisions.index'    => ['name' => 'Lihat Penyesuaian Upah', 'group' => 'Payroll'],
        'wage-revisions.store'    => ['name' => 'Buat Batch Penyesuaian Upah', 'group' => 'Payroll'],
        'wage-revisions.show'     => ['name' => 'Lihat Detail Batch Penyesuaian Upah', 'group' => 'Payroll'],
        'wage-revisions.generate' => ['name' => 'Generate Item Non-Staff', 'group' => 'Payroll'],
        'wage-revisions.staff'    => ['name' => 'Isi Nominal Gaji Staff/Expat', 'group' => 'Payroll'],
        'wage-revisions.import'   => ['name' => 'Import Gaji Staff/Expat', 'group' => 'Payroll'],
        'wage-revisions.template' => ['name' => 'Unduh Template Import Gaji', 'group' => 'Payroll'],
        'wage-revisions.export'   => ['name' => 'Export Hasil Penyesuaian Upah', 'group' => 'Payroll'],
        'wage-revisions.approve'  => ['name' => 'Setujui Batch Penyesuaian Upah', 'group' => 'Payroll'],
        'wage-revisions.apply'    => ['name' => 'Terapkan Penyesuaian Upah', 'group' => 'Payroll'],
        'wage-revisions.rollback' => ['name' => 'Batalkan Penyesuaian Upah', 'group' => 'Payroll'],
        'wage-revisions.destroy'  => ['name' => 'Hapus Batch Penyesuaian Upah', 'group' => 'Payroll'],
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

        // Admin gets it by default; other roles are granted through the Role Permission screen.
        $admin = Role::where('name', 'Admin')->first();

        if ($admin) {
            $admin->permissions()->syncWithoutDetaching($permissionIds);
        }

        $parent = $this->resolvePayrollParent();

        Menu::firstOrCreate(
            ['name' => 'Penyesuaian Upah (UMR)', 'parent_id' => $parent?->id],
            [
                'route_name'    => 'wage-revisions.index',
                'permission_id' => Permission::where('route_name', 'wage-revisions.index')->value('id'),
                'icon'          => 'fas fa-money-check-alt',
                'order'         => (int) Menu::where('parent_id', $parent?->id)->max('order') + 1,
                'is_active'     => true,
            ]
        );
    }

    /**
     * Payroll menus were created directly in the production database, so look the parent up by
     * name first and fall back to the parent of any existing payroll route.
     */
    private function resolvePayrollParent(): ?Menu
    {
        $parent = Menu::whereNull('parent_id')->where('name', 'Payroll')->first();

        if ($parent) {
            return $parent;
        }

        $payrollChild = Menu::whereNotNull('parent_id')
            ->where('route_name', 'like', 'payroll-%')
            ->first();

        return $payrollChild ? Menu::find($payrollChild->parent_id) : null;
    }

    public function down(): void
    {
        Menu::where('route_name', 'wage-revisions.index')->delete();

        Permission::whereIn('route_name', array_keys($this->permissions))->delete();
    }
};
