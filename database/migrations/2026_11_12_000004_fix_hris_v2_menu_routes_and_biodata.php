<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Koreksi & penambahan menu Portal Karyawan HRIS v2.
 *
 * Kenapa dipisah dari 2026_11_12_000003: migrasi yang sudah tercatat di tabel
 * `migrations` TIDAK dijalankan ulang oleh `php artisan migrate`, sehingga
 * koreksi yang ditambahkan belakangan tidak pernah sampai ke instalasi yang
 * sudah menjalankan migrasi tersebut. Migrasi ini memastikan koreksi itu
 * benar-benar diterapkan.
 *
 * Idempotent: aman dijalankan berulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hris_v2_menus')) {
            return;
        }

        // 1) Presensi Wajah WAJIB lewat /face-attendance/login (bukan /face-attendance).
        try {
            DB::table('hris_v2_menus')
                ->where('key', 'presensi-wajah')
                ->whereIn('route_name', ['face.attendance.index', 'face.attendance.login'])
                ->update([
                    'route_name' => 'face.attendance.login',
                    'description' => 'Presensi masuk/pulang dengan verifikasi wajah & GPS (lewat halaman login presensi).',
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            // Lewati bila tabel belum siap.
        }

        // 2) Menu baru: biodata lengkap karyawan (Data Karyawan).
        try {
            $sudahAda = DB::table('hris_v2_menus')->where('key', 'biodata-karyawan')->exists();

            if (! $sudahAda) {
                DB::table('hris_v2_menus')->insert([
                    'key' => 'biodata-karyawan',
                    'label' => 'Data Karyawan',
                    'description' => 'Biodata lengkap: data pribadi, keluarga, pendidikan, kepegawaian, kontrak, dan pengalaman kerja.',
                    'route_name' => 'v2.employee.biodata',
                    'icon' => 'fas fa-id-card',
                    'color' => '#4e73df',
                    'is_enabled' => true,
                    'sort_order' => 4,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('hris_v2_menus')
                    ->where('key', 'biodata-karyawan')
                    ->update([
                        'route_name' => 'v2.employee.biodata',
                        'updated_at' => now(),
                    ]);
            }
        } catch (\Throwable $e) {
            // Lewati bila tabel belum siap.
        }
    }

    public function down(): void
    {
        try {
            DB::table('hris_v2_menus')->where('key', 'biodata-karyawan')->delete();
        } catch (\Throwable $e) {
            // Lewati bila tabel belum siap.
        }
    }
};
