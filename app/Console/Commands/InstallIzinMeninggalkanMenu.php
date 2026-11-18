<?php

namespace App\Console\Commands;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pasang permission + menu Izin Meninggalkan Tugas / Pekerjaan.
 *
 * Idempotent — aman dijalankan berulang kali.
 *
 * Command:
 *   php artisan izin-meninggalkan:install-menu
 *   php artisan izin-meninggalkan:install-menu --role=Admin
 */
class InstallIzinMeninggalkanMenu extends Command
{
    protected $signature = 'izin-meninggalkan:install-menu
                            {--role= : Nama role yang diberi akses (default: role yang punya "Lihat Leave Recap")}';

    protected $description = 'Pasang permission + menu Izin Meninggalkan Tugas / Pekerjaan (idempotent).';

    public function handle(): int
    {
        // ── 1. Permission ──
        $permissions = [
            [
                'name'        => 'Pengajuan Izin Meninggalkan Tugas',
                'route_name'  => 'izin-meninggalkan.form',
                'group'       => 'Cuti',
                'description' => 'Pengajuan Surat Ijin Meninggalkan Tugas / Pekerjaan (CII.FM.HR.02.06)',
            ],
            [
                'name'        => 'Rekap Izin Meninggalkan Tugas',
                'route_name'  => 'izin-meninggalkan.recap.index',
                'group'       => 'Cuti',
                'description' => 'Melihat semua pengajuan Izin Meninggalkan Tugas',
            ],
            [
                'name'        => 'Approval Izin Meninggalkan Tugas',
                'route_name'  => 'izin-meninggalkan.approval.index',
                'group'       => 'Cuti',
                'description' => 'Approval bertingkat Izin Meninggalkan Tugas',
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
                'parent_id'     => $leaveParent->id,
                'name'          => 'Rekap Izin Meninggalkan Tugas',
                'route_name'    => 'izin-meninggalkan.recap.index',
                'icon'          => 'fas fa-door-open',
                'permission_id' => $permIds['izin-meninggalkan.recap.index'],
                'order'         => 1,
            ],
            [
                'parent_id'     => $approvalParent->id,
                'name'          => 'Approval Izin Meninggalkan Tugas',
                'route_name'    => 'izin-meninggalkan.approval.index',
                'icon'          => 'fas fa-door-open',
                'permission_id' => $permIds['izin-meninggalkan.approval.index'],
                'order'         => 2,
            ],
        ];

        // Catatan: form pengajuan (`izin-meninggalkan.form`) sengaja TIDAK
        // dipasang di sidebar — pemohon membukanya lewat alur login NPK sendiri.

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
            // Warisi role yang sudah bisa "Lihat Leave Recap" (317), sama
            // seperti pemasangan menu modul lain.
            $roles = Role::all()->filter(fn ($r) => $r->permissions()->where('permissions.id', 317)->exists());

            foreach ($roles as $role) {
                foreach (array_values($permIds) as $permId) {
                    if (!$role->permissions()->where('permissions.id', $permId)->exists()) {
                        $role->permissions()->syncWithoutDetaching([$permId]);
                    }
                }
            }

            $this->info('  permission ditambahkan ke role yang punya "Lihat Leave Recap": '
                . $roles->pluck('name')->implode(', '));
        }

        $this->info('Selesai.');

        return self::SUCCESS;
    }
}