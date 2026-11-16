<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel menu Portal Karyawan HRIS v2 (/v2/karyawan).
 *
 * Menu yang tampil di dashboard karyawan bisa DIKELOLA ADMIN lewat
 * /v2/admin: aktif/nonaktif, urutan, label, deskripsi, ikon, warna, dan
 * route tujuan. Baris awal: Slip Gaji, Presensi Wajah, Pengajuan Cuti.
 *
 * Idempotent: aman dijalankan berulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hris_v2_menus')) {
            Schema::create('hris_v2_menus', function (Blueprint $table) {
                $table->id();
                $table->string('key', 60)->unique();
                $table->string('label', 120);
                $table->string('description', 255)->nullable();
                $table->string('route_name', 150);
                $table->string('icon', 60)->default('fas fa-circle');
                $table->string('color', 30)->default('#1d3f86');
                $table->boolean('is_enabled')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // Setelan portal (key/value) yang bisa diubah admin: lama sesi,
        // judul portal, catatan halaman login, dsb.
        if (! Schema::hasTable('hris_v2_settings')) {
            Schema::create('hris_v2_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 60)->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        foreach ([
            'session_minutes' => '480',
            'portal_title' => 'Portal Karyawan — PT. Chutex International Indonesia',
            'employee_login_note' => 'Masukkan NPK Anda, lalu ikuti verifikasi wajah sesuai mode yang aktif.',
        ] as $key => $value) {
            try {
                if (! DB::table('hris_v2_settings')->where('key', $key)->exists()) {
                    DB::table('hris_v2_settings')->insert([
                        'key' => $key,
                        'value' => $value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                // Lewati kalau tabel belum siap.
            }
        }

        // Seed menu awal (idempotent per key).
        $rows = [
            [
                'key' => 'slip-gaji',
                'label' => 'Slip Gaji',
                'description' => 'Lihat & unduh slip gaji periode payroll yang sudah ditutup.',
                'route_name' => 'v2.employee.payslip',
                'icon' => 'fas fa-file-invoice-dollar',
                'color' => '#1d3f86',
                'is_enabled' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'presensi-wajah',
                'label' => 'Presensi Wajah',
                'description' => 'Presensi masuk/pulang dengan verifikasi wajah & GPS.',
                'route_name' => 'face.attendance.login',
                'icon' => 'fas fa-camera-retro',
                'color' => '#0891b2',
                'is_enabled' => true,
                'sort_order' => 2,
            ],
            [
                'key' => 'pengajuan-cuti',
                'label' => 'Pengajuan Cuti',
                'description' => 'Ajukan cuti/izin dan pantau status persetujuannya.',
                'route_name' => 'pengajuan-cuti.form',
                'icon' => 'fas fa-calendar-check',
                'color' => '#22c55e',
                'is_enabled' => true,
                'sort_order' => 3,
            ],
            [
                'key' => 'biodata-karyawan',
                'label' => 'Data Karyawan',
                'description' => 'Biodata lengkap: data pribadi, keluarga, pendidikan, kepegawaian, kontrak, dan pengalaman kerja.',
                'route_name' => 'v2.employee.biodata',
                'icon' => 'fas fa-id-card',
                'color' => '#4e73df',
                'is_enabled' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($rows as $row) {
            try {
                $exists = DB::table('hris_v2_menus')->where('key', $row['key'])->exists();

                if (! $exists) {
                    DB::table('hris_v2_menus')->insert($row + [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                // Lewati kalau tabel belum siap — seed bisa dijalankan ulang.
            }
        }

        // Koreksi idempotent untuk pemasangan yang sudah menyeed menu Presensi
        // Wajah dengan route lama (langsung ke /face-attendance). Menu itu
        // sekarang WAJIB lewat halaman login /face-attendance/login.
        try {
            DB::table('hris_v2_menus')
                ->where('key', 'presensi-wajah')
                ->where('route_name', 'face.attendance.index')
                ->update([
                    'route_name' => 'face.attendance.login',
                    'description' => 'Presensi masuk/pulang dengan verifikasi wajah & GPS (lewat halaman login presensi).',
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            // Lewati kalau tabel belum siap.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hris_v2_menus');
    }
};
