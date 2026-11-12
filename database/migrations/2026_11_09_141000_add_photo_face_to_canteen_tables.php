<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FOTO WAJAH + DOKUMENTASI TRANSAKSI KANTIN
 * ===========================================
 *
 * Permintaan user (2026-11-09):
 *   "di Bukti Scan Kantin tampilkan foto koordinat dan hasil capture wajahnya"
 *
 * LATAR BELAKANG
 * --------------
 * Tabel canteen / canteen_twos di database 'canteen' sudah punya kolom
 * window_name, location_name, latitude, longitude. Yang BELUM ada:
 *
 *   photo_face  -> foto wajah hasil capture (base64 JPEG), supaya di
 *                  "Bukti Scan Kantin" ada jejak visual wajah yang
 *                  dicocokkan.
 *
 * Kenapa kolom TEXT (bukan BLOB):
 *   - Discrete/Foto di SQL Server = BLOB, tapi kolom TEXT lebih aman
 *    dipakai lewat driver ODBC/sybase WITHOUT RAW CAST.
 *   -Ukuran foto JPEG 320px quality 0.7 ~= 20-40 KB, muat di TEXT.
 *   - Dipotong di sisi server (max length) supaya payload tidak
 *     membanjiri database.
 *
 * Idempotent: cek hasColumn dulu.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $canteenConn = DB::connection('canteen');
            $schema = $canteenConn->getSchemaBuilder();

            foreach (['canteen', 'canteen_twos'] as $table) {
                if (!$schema->hasTable($table)) {
                    continue;
                }
                if (!$schema->hasColumn($table, 'photo_face')) {
                    $canteenConn->statement(
                        "ALTER TABLE {$table} ADD photo_face NVARCHAR(MAX) NULL"
                    );
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Migrasi photo_face di tabel canteen gagal: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        try {
            $canteenConn = DB::connection('canteen');
            $schema = $canteenConn->getSchemaBuilder();

            foreach (['canteen', 'canteen_twos'] as $table) {
                if (!$schema->hasTable($table)) {
                    continue;
                }
                if ($schema->hasColumn($table, 'photo_face')) {
                    $canteenConn->statement(
                        "ALTER TABLE {$table} DROP COLUMN photo_face"
                    );
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Rollback photo_face di tabel canteen gagal: ' . $e->getMessage());
        }
    }
};
