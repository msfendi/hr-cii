<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Terjemahkan label & deskripsi bawaan menu Portal Karyawan HRIS v2 ke
 * bahasa Inggris (permintaan 13 Nov 2026: diksi blade/portal v2 Inggris).
 *
 * Kenapa lewat migrasi: teks kartu menu di /v2/karyawan TIDAK berasal dari
 * blade, melainkan dari tabel hris_v2_menus (dikelola admin di /v2/admin).
 * Jadi menerjemahkan blade saja tidak cukup — baris tabelnya ikut harus
 * diterjemahkan.
 *
 * Aman untuk data yang sudah disesuaikan admin: baris hanya diubah bila
 * nilai saat ini masih PERSIS sama dengan teks bawaan Indonesia. Label
 * hasil kustomisasi admin tidak pernah tertimpa.
 */
return new class extends Migration
{
    /**
     * key => [
     *   label_id  : daftar teks label bawaan (Indonesia) yang dikenali,
     *   label_en  : label Inggris pengganti,
     *   desc_id   : daftar teks deskripsi bawaan (Indonesia) yang dikenali,
     *   desc_en   : deskripsi Inggris pengganti,
     * ]
     */
    private array $peta = [
        'slip-gaji' => [
            'label_id' => ['Slip Gaji'],
            'label_en' => 'Payslip',
            'desc_id' => ['Lihat & unduh slip gaji periode payroll yang sudah ditutup.'],
            'desc_en' => 'View and download payslips for closed payroll periods.',
        ],
        'presensi-wajah' => [
            'label_id' => ['Presensi Wajah'],
            'label_en' => 'Face Attendance',
            'desc_id' => [
                'Presensi masuk/pulang dengan verifikasi wajah & GPS.',
                'Presensi masuk/pulang dengan verifikasi wajah & GPS (lewat halaman login presensi).',
            ],
            'desc_en' => 'Clock in and out with face verification & GPS (through the attendance login page).',
        ],
        'pengajuan-cuti' => [
            'label_id' => ['Pengajuan Cuti'],
            'label_en' => 'Leave Request',
            'desc_id' => ['Ajukan cuti/izin dan pantau status persetujuannya.'],
            'desc_en' => 'Submit leave or permit requests and track their approval status.',
        ],
        'biodata-karyawan' => [
            'label_id' => ['Data Karyawan'],
            'label_en' => 'Employee Data',
            'desc_id' => ['Biodata lengkap: data pribadi, keluarga, pendidikan, kepegawaian, kontrak, dan pengalaman kerja.'],
            'desc_en' => 'Full profile: personal data, family, education, employment, contract, and work experience.',
        ],
        'transaksi-kantin' => [
            'label_id' => ['Transaksi Kantin'],
            'label_en' => 'Canteen Transaction',
            'desc_id' => ['Presensi makan di kantin dengan verifikasi wajah & GPS (kantin 1 maupun kantin 2).'],
            'desc_en' => 'Meal attendance in the canteen with face verification & GPS (canteen 1 and canteen 2).',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('hris_v2_menus')) {
            return;
        }

        foreach ($this->peta as $key => $p) {
            // 1) Label masih teks bawaan Indonesia -> ganti ke Inggris.
            try {
                DB::table('hris_v2_menus')
                    ->where('key', $key)
                    ->whereIn('label', $p['label_id'])
                    ->update(['label' => $p['label_en'], 'updated_at' => now()]);
            } catch (\Throwable $e) {
                // Lewati bila tabel belum siap; migrasi bisa dijalankan ulang.
            }

            // 2) Deskripsi masih teks bawaan Indonesia (termasuk pada baris
            //    yang labelnya sudah diganti admin) -> ganti ke Inggris.
            try {
                DB::table('hris_v2_menus')
                    ->where('key', $key)
                    ->whereIn('description', $p['desc_id'])
                    ->update(['description' => $p['desc_en'], 'updated_at' => now()]);
            } catch (\Throwable $e) {
                // Lewati bila tabel belum siap.
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('hris_v2_menus')) {
            return;
        }

        // Kembalikan ke teks bawaan Indonesia, tetap hanya bila belum diubah admin.
        foreach ($this->peta as $key => $p) {
            try {
                DB::table('hris_v2_menus')
                    ->where('key', $key)
                    ->where('label', $p['label_en'])
                    ->update(['label' => $p['label_id'][0], 'updated_at' => now()]);

                DB::table('hris_v2_menus')
                    ->where('key', $key)
                    ->where('description', $p['desc_en'])
                    ->update(['description' => $p['desc_id'][0], 'updated_at' => now()]);
            } catch (\Throwable $e) {
                // Lewati baris yang bermasalah.
            }
        }
    }
};
