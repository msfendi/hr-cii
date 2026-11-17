<?php

namespace App\Console\Commands;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pasang permission + menu Work Assignment Request.
 *
 * Idempotent — aman dijalankan berulang kali.
 *
 * Menu dipasang di area yang sama dengan Leave Recap & Approval Leave:
 *   parent "Approval"          → child "Work Assignment Approval"
 *   parent "Leave & Holiday"   → child "Work Assignment"
 *
 * Command:
 *   php artisan work-assignment:install-menu
 *   php artisan work-assignment:install-menu --role=Admin
 */
class InstallWorkAssignmentMenu extends Command
{
    protected $signature = 'work-assignment:install-menu
                            {--role= : Nama role yang diberi akses (default: semua role yang punya "Lihat Leave Recap")}';

    protected $description = 'Pasang permission + menu Work Assignment Request (idempotent).';

    public function handle(): int
    {
        $conn = DB::connection('cii');

        // ── 1. Permission ──
        $permissions = [
            [
                'name'        => 'Pengajuan Work Assignment',
                'route_name'  => 'work-assignment.form',
                'group'       => 'Cuti',
                'description' => 'Pengajuan Surat Ijin Dinas Luar (CII.FM.HR.02.08)',
            ],
            [
                'name'        => 'Manage Work Assignment',
                'route_name'  => 'work-assignment.manage.index',
                'group'       => 'Cuti',
                'description' => 'Manage/oversee semua pengajuan Surat Ijin Dinas Luar',
            ],
            [
                'name'        => 'Approval Work Assignment',
                'route_name'  => 'work-assignment.approval.index',
                'group'       => 'Cuti',
                'description' => 'Approval bertingkat Work Assignment Request',
            ],
        ];

        $permIds = [];

        foreach ($permissions as $data) {
            $existing = Permission::where('route_name', $data['route_name'])->first();

            if ($existing) {
                $permIds[$data['route_name']] = (int) $existing->id;
                $this->line("  permission ada: {$data['name']} (#{$existing->id})");
                continue;
            }

            $perm = Permission::create($data);
            $permIds[$data['route_name']] = (int) $perm->id;
            $this->info("  permission dibuat: {$data['name']} (#{$perm->id})");
        }

        // ── 2. Menu ──
        $approvalParent = Menu::whereNull('parent_id')->where('name', 'Approval')->first();
        $leaveParent    = Menu::whereNull('parent_id')->where('name', 'Leave & Holiday')->first();

        if (!$approvalParent || !$leaveParent) {
            $this->error('Parent menu "Approval" / "Leave & Holiday" tidak ditemukan.');

            return self::FAILURE;
        }

        $menus = [
            [
                'parent_id'    => $leaveParent->id,
                'name'         => 'Manage Work Assignment',
                'route_name'   => 'work-assignment.manage.index',
                'icon'         => 'fas fa-file-alt',
                'permission_id' => $permIds['work-assignment.manage.index'],
                'order'        => 0,
            ],
            [
                'parent_id'    => $approvalParent->id,
                'name'         => 'Work Assignment Approval',
                'route_name'   => 'work-assignment.approval.index',
                'icon'         => 'fas fa-plane-departure',
                'permission_id' => $permIds['work-assignment.approval.index'],
                'order'        => 0,
            ],
        ];

        // Catatan: form pengajuan (`work-assignment.form`) sengaja TIDAK dipasang
        // di sidebar — pemohon membukanya lewat alur login NPK sendiri.

        foreach ($menus as $data) {
            $existing = Menu::where('parent_id', $data['parent_id'])
                ->where('route_name', $data['route_name'])
                ->first();

            if ($existing) {
                $existing->update([
                    'name'          => $data['name'],
                    'icon'          => $data['icon'],
                    'permission_id' => $data['permission_id'],
                    'is_active'     => true,
                ]);
                $this->line("  menu ada: {$data['name']} (#{$existing->id})");
                continue;
            }

            $menu = Menu::create($data + ['is_active' => true]);
            $this->info("  menu dibuat: {$data['name']} (#{$menu->id})");
        }

        // ── 3. Role ──
        $roleName = $this->option('role');

        if ($roleName) {
            $role = Role::where('name', $roleName)->first();

            if (!$role) {
                $this->error("Role \"{$roleName}\" tidak ditemukan.");

                return self::FAILURE;
            }

            foreach (array_values($permIds) as $permId) {
                if (!$role->permissions()->where('permissions.id', $permId)->exists()) {
                    $role->permissions()->syncWithoutDetaching([$permId]);
                    $this->info("  role {$roleName}: permission #{$permId} ditambahkan");
                }
            }
        } else {
            // Warisi role yang sudah bisa Lihat Leave Recap (317).
            $roles = Role::all()->filter(fn ($r) => $r->permissions()->where('permissions.id', 317)->exists());

            foreach ($roles as $role) {
                foreach (array_values($permIds) as $permId) {
                    if (!$role->permissions()->where('permissions.id', $permId)->exists()) {
                        $role->permissions()->syncWithoutDetaching([$permId]);
                    }
                }
            }

            $this->info('  permission ditambahkan ke role yang punya "Lihat Leave Recap": ' . $roles->pluck('name')->implode(', '));
        }

        $this->info('Selesai.');

        return self::SUCCESS;
    }
}
