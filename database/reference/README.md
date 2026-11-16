# Referensi Skema CII (SQLite)

Salinan skema database **CII** (SQL Server) dalam bentuk **SQLite**, dipakai sebagai
referensi lokal dan basis harness uji supaya pekerjaan tidak perlu menyentuh database
produksi.

Berkas di folder ini:

| Berkas | Isi |
| --- | --- |
| `cii_sqlite_schema.sql` | DDL SQLite hasil konversi (167 tabel). Dibuat otomatis, jangan diedit manual. |

Perintah konversi: `app/Console/Commands/CiiSchemaToSqliteCommand.php` (`php artisan cii:schema-to-sqlite`).

## Cara membuat ulang

```bash
# 1. Hanya menghasilkan skema SQL
php artisan cii:schema-to-sqlite /path/ke/CII.sql \
    --out=database/reference/cii_sqlite_schema.sql --force

# 2. Sekaligus membangun file SQLite siap pakai
php artisan cii:schema-to-sqlite /path/ke/CII.sql \
    --out=database/reference/cii_sqlite_schema.sql \
    --build=/tmp/cii_reference.sqlite --force
```

Sumber dump: Navicat Premium "Dump SQL" (SQL Server 12.00.6024, katalog `CII`, skema `dbo`).

## Hasil konversi terakhir

| Metrik | Jumlah |
| --- | --- |
| Tabel | 167 |
| Kolom | 1.920 |
| Primary key | 163 |
| Foreign key | 44 |
| Tabel tanpa primary key | 4 (`AUDIT`, `BIODATA_KELUAR`, `BLOCKED`, `PKWT_OUT`) |

Angka ini cocok dengan dump sumber: 167 `CREATE TABLE`, 163 `ADD CONSTRAINT ... PRIMARY KEY`,
44 `ADD CONSTRAINT ... FOREIGN KEY`. Dump tidak memuat view, index, trigger, prosedur, maupun data.

## Pemetaan tipe

| SQL Server | SQLite | Catatan |
| --- | --- | --- |
| `bigint`, `int`, `smallint`, `tinyint`, `bit` | `INTEGER` | `bit` menjadi 0/1 |
| `decimal`, `numeric`, `money`, `smallmoney` | `NUMERIC` | presisi/desimal diabaikan SQLite |
| `float`, `real` | `REAL` | |
| `datetime`, `datetime2`, `date`, `time`, `smalldatetime`, `timestamp` | `TEXT` | SQLite tak punya tipe tanggal asli |
| `char`, `varchar`, `nchar`, `nvarchar`, `text`, `ntext`, `sysname`, `xml` | `TEXT` | |
| `uniqueidentifier` | `TEXT` | |
| `binary`, `varbinary`, `image`, `rowversion` | `BLOB` | |

Default yang disesuaikan: `getdate()`/`sysdatetime()`/`getutcdate()` → `CURRENT_TIMESTAMP`,
`newid()`/`newsequentialid()` → `lower(hex(randomblob(16)))`, dan `DEFAULT '0'` pada kolom angka → `0`.

## Yang tidak dibawa

- `COLLATE SQL_Latin1_General_CP1_CI_AS` — SQLite tidak mendukung kolasi tersebut (perbandingan
  teks SQLite sudah case-insensitive untuk ASCII pada `LIKE`).
- `ON UPDATE` pada foreign key — SQLite hanya mendukung `ON DELETE`.
- `IDENTITY(1,1)` — tidak dibuat `AUTOINCREMENT`; cukup `PRIMARY KEY` supaya struktur tetap setia.
- `SET (LOCK_ESCALATION = TABLE)` dan `IF EXISTS ... DROP TABLE` — khusus SQL Server.
- Data. Skema ini kosong; isi sendiri seperlunya untuk pengujian.

## Cara memakai di Laravel

`.env` repo ini memakai Dotenv mutable, sehingga variabel shell **tidak** menimpa `.env`.
Jadi arahkan koneksi lewat config, bukan lewat `DB_CII_CONNECTION`:

```php
config([
    'database.default' => 'cii',
    'database.connections.cii' => [
        'driver' => 'sqlite',
        'database' => '/tmp/cii_reference.sqlite',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ],
]);
DB::purge('cii');
```

Jangan arahkan koneksi produksi ke berkas ini.

## Temuan yang sudah dipakai

`payroll_run_details` **tidak punya** kolom `NPK`; kolom karyawannya bernama
`employee_npk` (`nvarchar(255) NOT NULL`). Ini penyebab error
`Invalid column name 'NPK'` pada halaman `/v2/karyawan/slip-gaji`, dan sudah diperbaiki di
`HrisV2Controller::daftarSlip()`. Query payroll lama (`PayrollRecapController`) memang sudah
memakai `employee_npk`.

Verifikasi: `16/16` pemeriksaan lulus pada DB referensi — termasuk kendali negatif yang
mereproduksi error `no such column: prd.NPK`, lalu pembuktian `daftarSlip()` mengembalikan
hanya periode tertutup, urut terbaru lebih dulu, tanpa membocorkan slip karyawan lain.
