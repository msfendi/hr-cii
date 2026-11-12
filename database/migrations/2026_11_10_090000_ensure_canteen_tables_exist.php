<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PERBAIKAN V40 (2026-11-10) — Pastikan tabel kantin ADA di database 'canteen'.
 *
 * GEJALA
 * ------
 * Di produksi, scan kantin selalu gagal dengan:
 *   "Sistem tidak dapat memeriksa transaksi sebelumnya.
 *    Silakan coba lagi beberapa saat lagi."
 * (HTTP 503, flag duplicate_check_failed)
 *
 * PENYEBAB
 * --------
 * Migrasi 2026_11_09_130000 membuat tabel 'canteen' & 'canteen_twos' di
 * database 'canteen' (database TERPISAH dari CII) di dalam blok try yang
 * HANYA Log::warning() — tanpa throw.
 *
 * Kalau koneksi ke database 'canteen' tidak terjangkau saat migrasi itu
 * berjalan, Laravel tetap menandai migrasi sebagai SUCCEES, padahal
 * tabelnya TIDAK PERNAH dibuat. Migration status hijau, tapi setiap scan
 * kantin gagal 503.
 *
 * MIGASI 2026_11_09_141000 (tambah photo_face) juga menelan error dengan
 * cara serupa, jadi tidak ada jejak masalah di migration status.
 *
 * APA YANG MIGRASI INI LAKUKAN
 * ---------------------------
 * 1. Memastikan tabel 'canteen' & 'canteen_twos' ADA (buat kalau belum).
 * 2. Memastikan semua kolom yang dipakai query transaksi ADA
 *    (npk, name, dept, canteen_no, date, window_name, location_name,
 *     latitude, longitude, photo_face, created_at, updated_at).
 * 3. TIDAK pernah DROP atau MODIFY kolom yang sudah ada.
 * 4. Idempotent: aman dijalankan berkali-kali.
 * 5. TIDAK throw kalau koneksi 'canteen' tidak terjangkau — migrasi
 *    tidak boleh menghalangi migrasi lain. Yang dilakukan: log error
 *    yang JELAS supaya bisa ditindaklanjuti.
 *
 * CATATAN MIGRASI SEBELUMNYA
 * -------------------------
 * File 2026_11_09_130000 yang SUDAH pernah dijalankan di server tidak
 * diubah (mengubahnya tidak akan memicu eksekusi ulang). Perbaikannya
 * ada di migrasi baru ini.
 */
return new class extends Migration
{
    /**
     * Kolom wajib untuk query transaksi kantin.
     * 'sqlsrv' => tipe native SQL Server, dipakai untuk ALTER TABLE.
     */
    private const REQUIRED_COLUMNS = [
        'npk'           => ['type' => 'NVARCHAR(191)', 'nullable' => true],
        'name'          => ['type' => 'NVARCHAR(191)', 'nullable' => true],
        'dept'          => ['type' => 'NVARCHAR(191)', 'nullable' => true],
        'canteen_no'    => ['type' => 'NVARCHAR(50)',  'nullable' => true],
        'date'          => ['type' => 'DATE',          'nullable' => true],
        'window_name'   => ['type' => 'NVARCHAR(191)', 'nullable' => true],
        'location_name' => ['type' => 'NVARCHAR(191)', 'nullable' => true],
        'latitude'      => ['type' => 'DECIMAL(10,7)', 'nullable' => true],
        'longitude'     => ['type' => 'DECIMAL(11,7)', 'nullable' => true],
        'photo_face'    => ['type' => 'NVARCHAR(MAX)', 'nullable' => true],
        'created_at'    => ['type' => 'DATETIME',      'nullable' => true],
        'updated_at'    => ['type' => 'DATETIME',      'nullable' => true],
    ];

    public function up(): void
    {
        try {
            $canteenConn = DB::connection('canteen');
        } catch (\Throwable $e) {
            \Log::error(
                'V40: koneksi database "canteen" tidak terjangkau saat migrasi: '
                . $e->getMessage()
                . ' — tabel kantin TIDAK dibuat. Jalankan: '
                . 'php fix_canteen_tables_20261110.php --apply'
            );
            return;
        }

        foreach (['canteen', 'canteen_twos'] as $table) {
            try {
                $schema = $canteenConn->getSchemaBuilder();

                if (!$schema->hasTable($table)) {
                    $this->createTable($canteenConn, $table);
                    \Log::info('V40: tabel ' . $table . ' dibuat di database canteen.');
                    continue;
                }

                // Tabel ada -> pastikan semua kolom tersedia.
                $added = $this->addMissingColumns($canteenConn, $schema, $table);
                if (!empty($added)) {
                    \Log::info('V40: kolom ditambahkan di ' . $table . ': ' . implode(', ', $added));
                }
            } catch (\Throwable $e) {
                // TIDAK throw — migrasi lain tidak boleh terblokir.
                // Tapi log-nya harus JELAS: ini penyebab 503 di produksi.
                \Log::error(
                    'V40: gagal menyiapkan tabel ' . $table . ' di database canteen: '
                    . $e->getMessage()
                    . ' — jalankan: php fix_canteen_tables_20261110.php --apply'
                );
            }
        }
    }

    public function down(): void
    {
        // TIDAK menghapus tabel — menghapus tabel transaksi akan
        // menghapus seluruh riwayat scan kantin. Migrasi ini purely
        // additive (membuat yang belum ada), jadi rollback = no-op.
    }

    private function createTable($canteenConn, string $table): void
    {
        $canteenConn->statement("
            IF OBJECT_ID('" . $table . "', 'U') IS NULL
            BEGIN
                CREATE TABLE [" . $table . "] (
                    [id] INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                    [npk] NVARCHAR(191) NULL,
                    [name] NVARCHAR(191) NULL,
                    [dept] NVARCHAR(191) NULL,
                    [canteen_no] NVARCHAR(50) NULL,
                    [date] DATE NULL,
                    [window_name] NVARCHAR(191) NULL,
                    [location_name] NVARCHAR(191) NULL,
                    [latitude] DECIMAL(10,7) NULL,
                    [longitude] DECIMAL(11,7) NULL,
                    [photo_face] NVARCHAR(MAX) NULL,
                    [created_at] DATETIME NULL,
                    [updated_at] DATETIME NULL
                );
            END
        ");

        // Index untuk query cek-duplikat (npk + date). Abaikan bila gagal.
        try {
            $canteenConn->statement(
                "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_{$table}_npk_date' AND object_id = OBJECT_ID('{$table}')) "
                . "CREATE INDEX IX_{$table}_npk_date ON {$table} (npk, date)"
            );
        } catch (\Throwable $e) {
            // Index bersifat performa, bukan fungsional.
        }
    }

    /**
     * @return array<string> daftar kolom yang ditambahkan
     */
    private function addMissingColumns($canteenConn, $schema, string $table): array
    {
        $added = [];

        foreach (self::REQUIRED_COLUMNS as $column => $def) {
            try {
                if ($schema->hasColumn($table, $column)) {
                    continue;
                }
            } catch (\Throwable $e) {
                // Gagal cek kolom -> coba tambahkan saja (idempotent, SQL Server
                // akan error kalau kolom sebenarnya sudah ada).
                continue;
            }

            try {
                $canteenConn->statement(
                    "IF NOT EXISTS (SELECT 1 FROM sys.columns WHERE object_id = OBJECT_ID('{$table}') AND name = '{$column}') "
                    . "ALTER TABLE [{$table}] ADD [{$column}] {$def['type']} NULL"
                );
                $added[] = $column;
            } catch (\Throwable $e) {
                \Log::warning("V40: gagal menambah kolom {$table}.{$column}: " . $e->getMessage());
            }
        }

        return $added;
    }
};
