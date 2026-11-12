<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PERBAIKAN V41 (2026-11-10) — TAMBAHKAN KOLOM `id_ticket` DI TABEL KANTIN.
 *
 * PERMINTAAN USER
 * ---------------
 * "buat kolom id_ticket di tabel canteen dan canteen_twos, tampilkan di
 *  bukti scan + history"
 *
 * APA ITU id_ticket
 * -----------------
 * `id_ticket` adalah nomor identitas unik untuk satu transaksi scan kantin.
 * Bedanya dengan `canteen_no`:
 *   - `canteen_no` = kode kantin ("1" atau "2") — BUKAN unik, banyak baris
 *     punya nilai yang sama.
 *   - `id_ticket`  = identitas unik per transaksi, PERSIS seperti
 *     `att_id` di modul Face Attendance (format `<unix>_CTN_<6 hex>`).
 *
 * Kenapa perlu: tanpa identifier unik per transaksi, tidak ada cara
 * mengaitkan satu baris scan kantin dengan (a) bukti scan yang
 * ditunjukkan ke kasir, (b) log absensi, atau (c) log audit — kalau
 * ada banyak scan pada hari yang sama.
 *
 * FORMAT
 * ------
 * <unix_detik>_CTN_<6 hex acak>
 * Contoh: 1762060800_CTN_7F3A2B
 *
 * Sifatnya:
 *   - Mengandung unix timestamp -> bisa diurutkan kronologis, dan
 *     urut leksikografis = urut waktu.
 *   - Suffix acak 6 hex -> tetap unik walau ada 2 transaksi pada
 *     detik yang sama.
 *   - Panjang <= 50 char -> aman untuk NVARCHAR(50) / VARCHAR(50).
 *
 * DESAIN TABEL
 * ------------
 * Kolom dibuat NULLABLE dan TANPA default, jadi:
 *   1. Baris lama (yang sudah tersimpan sebelum migrasi ini) tidak
 *     _break — nilainya NULL, bukan string kosong palsu.
 *   2. Eloquent TIDAK diam-diam mengisi nilai yang tidak ada artinya.
 *
 * Kolom ini DIBUAT DI MANA SAJA tabelnya sudah ada:
 *   - Database 'canteen' (CREATE TABLE kalau tabel belum ada).
 *   - Database default (kalau tabel sudah ada di sana juga).
 *
 * PERBAIKAN V40 (2026-11-10) menjadi acuan: migrasi ini memakai
 * DDL SQL Server mentah karena tabel 'canteen'/'canteen_twos' berada di
 * database TERPISAH ('canteen'), bukan di koneksi default.
 *
 * IDEMPOTENT
 * ----------
 * Aman dijalankan berkali-kali: kolom dicek dulu sebelum ditambah, dan
 * pembuatan tabel memakai guard `IF OBJECT_ID(...) IS NULL`.
 *
 * TIDAK THROW
 * -----------
 * Kalau koneksi database 'canteen' tidak terjangkau, migrasi ini
 * TIDAK melempar exception (migrasi lain tidak boleh terblokir), tapi
 * mencatat error yang JELAS supaya bisa ditindaklanjuti.
 */
return new class extends Migration
{
    /** Nama kolom baru. */
    private const COLUMN = 'id_ticket';

    /** Definisi kolom per tipe database. */
    private const COLUMN_DEF = [
        'sqlsrv' => 'NVARCHAR(50) NULL',
        'mysql'  => 'VARCHAR(50) NULL',
        'pgsql'  => 'VARCHAR(50) NULL',
        'sqlite' => 'VARCHAR(50) NULL',
    ];

    public function up(): void
    {
        foreach ($this->targetConnections() as $connName => $conn) {
            foreach (['canteen', 'canteen_twos'] as $table) {
                try {
                    $this->ensureColumn($conn, $table);
                } catch (\Throwable $e) {
                    \Log::error(
                        'V41: gagal menambahkan kolom id_ticket di '
                        . $connName . '.' . $table . ': ' . $e->getMessage()
                        . ' — jalankan: php fix_canteen_tables_20261110.php --apply'
                    );
                }
            }
        }
    }

    public function down(): void
    {
        // TIDAK menghapus kolom. id_ticket berisi referensi transaksi yang
        // sudah dipakai di bukti scan & history. Menghapusnya akan merusak
        // histori. Migrasi ini purely additive, jadi rollback = no-op.
    }

    /**
     * Koneksi mana saja yang perlu diperiksa.
     *
     * Koneksi 'canteen' adalah yang dipakai model CanteenReport /
     * CanteenTwoReport. Koneksi default diperiksa juga karena di
     * beberapa environment tabel kantin pernah dibuat di sana (mis.
     * oleh V40 yang memakai DDL mentah).
     *
     * @return array<string, \Illuminate\Database\ConnectionInterface>
     */
    private function targetConnections(): array
    {
        $out = [];

        try {
            $out['canteen'] = DB::connection('canteen');
        } catch (\Throwable $e) {
            \Log::error(
                'V41: koneksi database "canteen" tidak terjangkau: '
                . $e->getMessage()
                . ' — kolom id_ticket TIDAK ditambahkan. Jalankan: '
                . 'php fix_canteen_tables_20261110.php --apply'
            );
        }

        try {
            $out['default'] = DB::connection();
        } catch (\Throwable $e) {
            // Koneksi default tidak terjangkau -> lewati saja.
        }

        return $out;
    }

    /**
     * Pastikan tabel ada (buat kalau belum) dan kolom id_ticket ada.
     */
    private function ensureColumn($conn, string $table): void
    {
        $schema = $conn->getSchemaBuilder();
        $driver = $conn->getDriverName();

        if (! $schema->hasTable($table)) {
            $this->createTable($conn, $table, $driver);
            // Tabel baru dibuat WITH kolom id_ticket -> selesai.
            return;
        }

        // Tabel sudah ada -> pastikan kolomnya ada.
        try {
            if ($schema->hasColumn($table, self::COLUMN)) {
                return;
            }
        } catch (\Throwable $e) {
            // Gagal cek kolom -> coba tambah saja (idempotent).
        }

        $def = self::COLUMN_DEF[$driver] ?? 'VARCHAR(50) NULL';

        try {
            $conn->statement(
                'ALTER TABLE ' . $this->q($table)
                . ' ADD ' . $this->q(self::COLUMN) . ' ' . $def
            );
            \Log::info('V41: kolom id_ticket ditambahkan di ' . $table . '.');
        } catch (\Throwable $e) {
            // SQL Server: kalau kolom sebenarnya sudah ada, ALTER TABLE
            // akan error "Column names in each table must be unique".
            // Itu BUKAN kegagalan fatal -> diamkan.
            if ($this->isDuplicateColumnError($e)) {
                return;
            }
            throw $e;
        }
    }

    /**
     * Buat tabel kantin kalau belum ada, INCLUDING kolom id_ticket.
     */
    private function createTable($conn, string $table, string $driver): void
    {
        $idTicket = self::COLUMN_DEF[$driver] ?? 'VARCHAR(50) NULL';

        if ($driver === 'sqlsrv') {
            $conn->statement(
                "IF OBJECT_ID('" . $table . "', 'U') IS NULL
                 BEGIN
                     CREATE TABLE [" . $table . "] (
                         [id] INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
                         [npk] NVARCHAR(191) NULL,
                         [name] NVARCHAR(191) NULL,
                         [dept] NVARCHAR(191) NULL,
                         [canteen_no] NVARCHAR(50) NULL,
                         [id_ticket] NVARCHAR(50) NULL,
                         [date] DATE NULL,
                         [window_name] NVARCHAR(191) NULL,
                         [location_name] NVARCHAR(191) NULL,
                         [latitude] DECIMAL(10,7) NULL,
                         [longitude] DECIMAL(11,7) NULL,
                         [photo_face] NVARCHAR(MAX) NULL,
                         [created_at] DATETIME NULL,
                         [updated_at] DATETIME NULL
                     );
                 END"
            );
        } else {
            $conn->statement(
                'CREATE TABLE IF NOT EXISTS ' . $this->q($table) . ' ('
                . '"id" INTEGER PRIMARY KEY AUTOINCREMENT, '
                . '"npk" VARCHAR(191) NULL, '
                . '"name" VARCHAR(191) NULL, '
                . '"dept" VARCHAR(191) NULL, '
                . '"canteen_no" VARCHAR(50) NULL, '
                . '"id_ticket" VARCHAR(50) NULL, '
                . '"date" DATE NULL, '
                . '"window_name" VARCHAR(191) NULL, '
                . '"location_name" VARCHAR(191) NULL, '
                . '"latitude" DECIMAL(10,7) NULL, '
                . '"longitude" DECIMAL(11,7) NULL, '
                . '"photo_face" TEXT NULL, '
                . '"created_at" DATETIME NULL, '
                . '"updated_at" DATETIME NULL'
                . ')'
            );
        }

        \Log::info('V41: tabel ' . $table . ' dibuat dengan kolom id_ticket.');
    }

    /**
     * Deteksi error "kolom sudah ada" supaya tidak dianggap kegagalan.
     */
    private function isDuplicateColumnError(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'column names in each table must be unique')
            || str_contains($msg, 'duplicate column name')
            || str_contains($msg, 'already exists')
            || str_contains($msg, 'duplicate column');
    }

    /**
     * Quote identifier untuk driver yang dipakai.
     */
    private function q(string $ident): string
    {
        return '[' . $ident . ']';
    }
};
