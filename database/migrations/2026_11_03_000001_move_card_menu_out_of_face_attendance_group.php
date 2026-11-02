<?php

use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

/**
 * PERBAIKAN: "Pengaturan Card Menu HRIS" sebelumnya dibuat sebagai sub-menu dari
 * parent "Face Attendance". Akibatnya halaman /face-attendance/admin/card-menu
 * ikut masuk ke grup route face-attendance pada sidebar.
 *
 * Migration ini memindahkannya menjadi grup menu top-level sendiri,
 * sehingga tidak lagi menjadi anak dari "Face Attendance".
 */
return new class extends Migration
{
    public function up(): void
    {
        $menu = Menu::where('route_name', 'face.attendance.admin.card-menu')->first();

        if (! $menu) {
            return;
        }

        // Parent top-level baru: "Pengaturan Card Menu"
        $parent = Menu::where('name', 'Pengaturan Card Menu')->whereNull('parent_id')->first();

        if (! $parent) {
            $maxOrder = (int) Menu::whereNull('parent_id')->max('order');
            $parent = Menu::create([
                'name'       => 'Pengaturan Card Menu',
                'parent_id'  => null,
                'route_name' => null,
                'icon'       => 'fas fa-th-large',
                'order'      => $maxOrder + 1,
                'is_active'  => true,
            ]);
        }

        // Pindahkan ke parent baru, di urutan terakhir di dalam group barunya.
        $menu->parent_id = $parent->id;
        $menu->order = (int) Menu::where('parent_id', $parent->id)->max('order') + 1;
        $menu->save();

        // Permission-nya juga dipisah dari group "Face Attendance",
        // supaya di halaman Role Permission tidak ikut nempel di grup yang sama.
        Permission::where('route_name', 'face.attendance.admin.card-menu')
            ->update(['group' => 'Pengaturan Card Menu']);
        Permission::where('route_name', 'face.attendance.admin.card-menu.update')
            ->update(['group' => 'Pengaturan Card Menu']);
    }

    public function down(): void
    {
        $menu = Menu::where('route_name', 'face.attendance.admin.card-menu')->first();

        if ($menu) {
            // Kembalikan ke parent "Face Attendance".
            $faceParent = Menu::where('name', 'Face Attendance')->whereNull('parent_id')->first();

            if ($faceParent) {
                $menu->parent_id = $faceParent->id;
                $menu->order = (int) Menu::where('parent_id', $faceParent->id)->max('order') + 1;
                $menu->save();
            }
        }

        Permission::where('route_name', 'face.attendance.admin.card-menu')
            ->update(['group' => 'Face Attendance']);
        Permission::where('route_name', 'face.attendance.admin.card-menu.update')
            ->update(['group' => 'Face Attendance']);

        // Hapus parent group baru kalau jadi kosong.
        $newParent = Menu::where('name', 'Pengaturan Card Menu')->whereNull('parent_id')->first();

        if ($newParent && Menu::where('parent_id', $newParent->id)->count() === 0) {
            $newParent->delete();
        }
    }
};
