<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Menu Portal Karyawan HRIS v2 (/v2/karyawan).
 *
 * Baris di tabel ini menentukan kartu menu yang muncul di dashboard
 * karyawan. Dikelola admin lewat /v2/admin (aktif/nonaktif, urutan,
 * label, deskripsi, ikon, warna, route tujuan).
 */
class HrisV2Menu extends Model
{
    protected $table = 'hris_v2_menus';

    protected $fillable = [
        'key', 'label', 'description', 'route_name', 'icon', 'color', 'is_enabled', 'sort_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Menu default (dipakai kalau tabel belum dimigrasi) supaya dashboard
     * karyawan tidak pernah kosong tanpa alasan.
     */
    public static function defaults(): array
    {
        return [
            ['key' => 'slip-gaji', 'label' => 'Payslip', 'description' => 'View and download payslips for closed payroll periods.', 'route_name' => 'v2.employee.payslip', 'icon' => 'fas fa-file-invoice-dollar', 'color' => '#4e73df', 'is_enabled' => true, 'sort_order' => 1],
            ['key' => 'presensi-wajah', 'label' => 'Face Attendance', 'description' => 'Clock in and out with face verification & GPS (through the attendance login page).', 'route_name' => 'face.attendance.login', 'icon' => 'fas fa-camera-retro', 'color' => '#36b9cc', 'is_enabled' => true, 'sort_order' => 2],
            ['key' => 'pengajuan-cuti', 'label' => 'Leave Request', 'description' => 'Submit leave or permit requests and track their approval status.', 'route_name' => 'pengajuan-cuti.form', 'icon' => 'fas fa-calendar-check', 'color' => '#1cc88a', 'is_enabled' => true, 'sort_order' => 3],
            ['key' => 'biodata-karyawan', 'label' => 'Employee Data', 'description' => 'Full profile: personal data, family, education, employment, contract, and work experience.', 'route_name' => 'v2.employee.biodata', 'icon' => 'fas fa-id-card', 'color' => '#858796', 'is_enabled' => true, 'sort_order' => 4],
            ['key' => 'transaksi-kantin', 'label' => 'Canteen Transaction', 'description' => 'Meal attendance in the canteen with face verification & GPS (canteen 1 and canteen 2).', 'route_name' => 'canteen-transaction.index', 'icon' => 'fas fa-utensils', 'color' => '#f6c23e', 'is_enabled' => true, 'sort_order' => 5],
        ];
    }

    /** Semua menu (untuk halaman admin), urut. */
    public static function semua(): array
    {
        if (! self::tableSiap()) {
            return collect(self::defaults())->map(fn ($r) => (object) ($r + ['id' => null]))->all();
        }

        try {
            $rows = self::query()->orderBy('sort_order')->orderBy('id')->get();

            return $rows->isEmpty()
                ? collect(self::defaults())->map(fn ($r) => (object) ($r + ['id' => null]))->all()
                : $rows->all();
        } catch (\Throwable $e) {
            return collect(self::defaults())->map(fn ($r) => (object) ($r + ['id' => null]))->all();
        }
    }

    /** Menu aktif saja (untuk dashboard karyawan), urut. */
    public static function aktif(): array
    {
        if (! self::tableSiap()) {
            return array_values(array_filter(self::defaults(), fn ($r) => $r['is_enabled']));
        }

        try {
            $rows = self::query()->where('is_enabled', true)->orderBy('sort_order')->orderBy('id')->get();

            return $rows->isEmpty()
                ? array_values(array_filter(self::defaults(), fn ($r) => $r['is_enabled']))
                : $rows->all();
        } catch (\Throwable $e) {
            return array_values(array_filter(self::defaults(), fn ($r) => $r['is_enabled']));
        }
    }

    /** Apakah menu tertentu aktif (dipakai middleware/penjaga akses). */
    public static function aktifKey(string $key): bool
    {
        foreach (self::aktif() as $menu) {
            $menuKey = is_array($menu) ? ($menu['key'] ?? '') : ($menu->key ?? '');

            if ($menuKey === $key) {
                return true;
            }
        }

        return false;
    }

    protected static function tableSiap(): bool
    {
        try {
            return Schema::hasTable('hris_v2_menus');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
