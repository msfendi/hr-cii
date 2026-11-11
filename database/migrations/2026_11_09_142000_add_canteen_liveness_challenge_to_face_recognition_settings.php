<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LIVENESS + RANDOM CHALLENGE UNTUK TRANSAKSI KANTIN
 * ====================================================
 *
 * Permintaan user (2026-11-09):
 *   "liveness dan random challenge di canteen transaction buat agar bisa
 *    disetting dari /face-attendance/settings"
 *
 * LATAR BELAKANG
 * --------------
 * Sampai sebelum migrasi ini, pengaturan liveness & random challenge untuk
 * kantin TIDAK berada di /face-attendance/settings. Yang ada:
 *   - canteen_transaction_settings.liveness_canteen_enabled
 *   - canteen_transaction_settings.random_challenge_canteen_enabled
 * Itu tabel TERPISAH, dikelola dari halaman admin kantin
 * (/canteen-transaction/admin), bukan dari halaman Pengaturan Presensi Wajah.
 *
 * User meminta satu tempat pengaturan: /face-attendance/settings.
 *
 * SOLUSI
 * ------
 * Menambah kolom canteen ke tabel face_recognition_settings (tabel yang
 * SUDAH dipakai halaman /face-attendance/settings untuk liveness & random
 * challenge menu lain). Kolom baru:
 *
 *   liveness_canteen_enabled              -> /canteen-transaction
 *   random_challenge_canteen_enabled      -> /canteen-transaction
 *
 * ORTogonal terhadap liveness check (sama seperti random challenge menu lain):
 * menyalakan random challenge TIDAK mengubah logika liveness, dan sebaliknya.
 *
 * SUMBER KEBENARAN TUNGGAL
 * -----------------------
 * Controller kantin membaca setting dari face_recognition_settings ( kolom
 * baru ) dengan fallback ke canteen_transaction_settings ( tabel lama ).
 * Dengan begitu:
 *   - halaman /face-attendance/settings menjadi tempat utama
 *   - pengaturan lama di admin kanten TETAP berfungsi (tidak ada fitur
 *     yang hilang, tidak ada breaking change)
 *   -/admin kanten berubah nilainya, nilai di /face-attendance/settings yang
 *     menang (keduanya fallback berurutan)
 *
 * Idempotent: cek hasColumn dulu supaya aman dijalankan berulang.
 */
return new class extends Migration
{
    /**
     * Daftar kolom yang ditambahkan.
     * key = nama kolom, value = default.
     *
     * Default liveness_canteen_enabled = true  -> fail-safe: kalau migrasi
     *   belum dijalankan, kantin tetap memakai perilaku liveness yang sekarang.
     * Default random_challenge_canteen_enabled = false -> fitur baru,
     *   belum diaktifkan sampai admin menyalakannya dari settings.
     */
    private array $columns = [
        'liveness_canteen_enabled'         => true,
        'random_challenge_canteen_enabled' => false,
    ];

    public function up(): void
    {
        if (!Schema::hasTable('face_recognition_settings')) {
            return;
        }

        foreach ($this->columns as $column => $default) {
            if (!Schema::hasColumn('face_recognition_settings', $column)) {
                Schema::table('face_recognition_settings', function (Blueprint $table) use ($column, $default) {
                    $table->boolean($column)->default($default);
                });
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('face_recognition_settings')) {
            return;
        }

        foreach (array_keys($this->columns) as $column) {
            if (Schema::hasColumn('face_recognition_settings', $column)) {
                Schema::table('face_recognition_settings', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
