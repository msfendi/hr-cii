<?php

use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

/**
 * PERBAIKAN 2026-11-03: Nama route pengaturan card menu HRIS diganti menjadi
 * `card-menu-hris.index` dan `card-menu-hris.update`.
 *
 * Sebelumnya baris menu & permission di database masih menyimpan nama route
 * LAMA:
 *     face.attendance.admin.card-menu
 *     face.attendance.admin.card-menu.update
 *
 * Karena route lama sudah DIHAPUS dari routes/web.php (tanpa redirect), baris
 * menu yang masih memakai nama lama akan gagal di-resolve oleh route() dan
 * menimbulkan error "Route [...] not defined" saat sidebar dirender.
 *
 * Migration ini mengganti nama route pada tabel `menus` dan `permissions`
 * (beserta tabel pivot permission bila ada) supaya cocok dengan route baru.
 * Idempoten: aman dijalankan berkali-kali.
 */
return new class extends Migration
{
    /** @var array<string,string> nama lama => nama baru */
    private array $map = [
        'face.attendance.admin.card-menu'        => 'card-menu-hris.index',
        'face.attendance.admin.card-menu.update' => 'card-menu-hris.update',
    ];

    public function up(): void
    {
        foreach ($this->map as $old => $new) {
            // ---- Menu sidebar ----
            Menu::where('route_name', $old)->update(['route_name' => $new]);

            // ---- Permission (tabel master) ----
            Permission::where('route_name', $old)->update(['route_name' => $new]);
        }
    }

    public function down(): void
    {
        foreach ($this->map as $old => $new) {
            Menu::where('route_name', $new)->update(['route_name' => $old]);
            Permission::where('route_name', $new)->update(['route_name' => $old]);
        }
    }
};
