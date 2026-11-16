<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu Portal Karyawan HRIS v2 — penambahan menu Transaksi Kantin dan
 * penyesuaian palet warna kartu ke palet SB Admin 2.
 *
 * Kenapa migrasi terpisah: baris menu tersimpan di tabel `hris_v2_menus`,
 * sehingga instalasi yang sudah menjalankan migrasi menu sebelumnya tidak
 * akan mendapat baris baru bila hanya file migrasi lama yang diubah.
 *
 * Idempotent: aman dijalankan berulang.
 */
return new class extends Migration
{
    /** Palet SB Admin 2 untuk tiap kartu menu (key => warna). */
    private array $warnaSbAdmin2 = [
        'slip-gaji' => '#4e73df',
        'presensi-wajah' => '#36b9cc',
        'pengajuan-cuti' => '#1cc88a',
        'biodata-karyawan' => '#858796',
        'transaksi-kantin' => '#f6c23e',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('hris_v2_menus')) {
            return;
        }

        // 1) Menu baru: Transaksi Kantin (/canteen-transaction).
        try {
            $sudahAda = DB::table('hris_v2_menus')->where('key', 'transaksi-kantin')->exists();

            if (! $sudahAda) {
                DB::table('hris_v2_menus')->insert([
                    'key' => 'transaksi-kantin',
                    'label' => 'Transaksi Kantin',
                    'description' => 'Presensi makan di kantin dengan verifikasi wajah & GPS (kantin 1 maupun kantin 2).',
                    'route_name' => 'canteen-transaction.index',
                    'icon' => 'fas fa-utensils',
                    'color' => '#f6c23e',
                    'is_enabled' => true,
                    'sort_order' => 5,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('hris_v2_menus')
                    ->where('key', 'transaksi-kantin')
                    ->update([
                        'route_name' => 'canteen-transaction.index',
                        'updated_at' => now(),
                    ]);
            }
        } catch (\Throwable $e) {
            // Lewati bila tabel belum siap (mis. kolom tidak lengkap).
        }

        // 2) Warna kartu menu diselaraskan ke palet SB Admin 2.
        foreach ($this->warnaSbAdmin2 as $key => $warna) {
            try {
                DB::table('hris_v2_menus')
                    ->where('key', $key)
                    ->update(['color' => $warna, 'updated_at' => now()]);
            } catch (\Throwable $e) {
                // Lewati baris yang bermasalah, lanjut ke baris berikutnya.
            }
        }
    }

    public function down(): void
    {
        try {
            DB::table('hris_v2_menus')->where('key', 'transaksi-kantin')->delete();
        } catch (\Throwable $e) {
            // Lewati bila tabel belum siap.
        }
    }
};
