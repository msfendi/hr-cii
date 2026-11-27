<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Naikkan tabel satisfaction_submission_logs dari skema lama (kunci IP) ke
 * skema baru (kunci PERANGKAT), lalu buang kolom IP.
 *
 * Aman dijalankan berulang (idempoten) dan aman bila tabel belum ada
 * (versi 2026_11_27_000002 yang baru sudah membuat skema final).
 *
 * Langkah:
 *  1. tambah kolom device_hash bila belum ada
 *  2. isi device_hash untuk baris lama dengan nilai unik (agar unique index bisa dipasang)
 *  3. buang unique lama uq_survey_log_ip_periode, pasang uq_survey_log_device_periode
 *  4. buang index + kolom IP (ip_hash, ip_address, ip_chain) yang tidak dipakai lagi
 */
return new class extends Migration
{
    private const TABEL = 'satisfaction_submission_logs';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABEL)) {
            return; // belum ada tabel: migrasi 000002 (versi baru) yang akan membuatnya.
        }

        // 1. kolom baru
        if (!Schema::hasColumn(self::TABEL, 'device_hash')) {
            Schema::table(self::TABEL, function (Blueprint $table) {
                $table->char('device_hash', 64)->nullable();
            });
        }

        // 2. backfill baris lama supaya device_hash tidak null (unique index
        //    pada SQL Server memperlakukan NULL sebagai duplikat).
        $this->aman(function () {
            DB::table(self::TABEL)
                ->whereNull('device_hash')
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table(self::TABEL)->where('id', $row->id)->update([
                            'device_hash' => hash_hmac(
                                'sha256',
                                'legacy-log-' . $row->id,
                                (string) config('survey.pepper')
                            ),
                        ]);
                    }
                });
        }, 'backfill device_hash');

        // 3a. buang unique lama (kunci IP) bila ada
        if (in_array('uq_survey_log_ip_periode', $this->namaIndex(), true)) {
            $this->aman(fn () => Schema::table(self::TABEL, fn (Blueprint $t) => $t->dropUnique('uq_survey_log_ip_periode')), 'drop unique IP');
        }

        // 3b. pasang unique perangkat bila belum ada
        if (!in_array('uq_survey_log_device_periode', $this->namaIndex(), true)) {
            $this->aman(fn () => Schema::table(self::TABEL, fn (Blueprint $t) => $t->unique(['device_hash', 'period_key'], 'uq_survey_log_device_periode')), 'add unique perangkat');
        }

        // 4. buang index & kolom IP (tidak dipakai lagi; identitas = perangkat)
        if (in_array('idx_survey_log_ip', $this->namaIndex(), true)) {
            $this->aman(fn () => Schema::table(self::TABEL, fn (Blueprint $t) => $t->dropIndex('idx_survey_log_ip')), 'drop index IP');
        }

        $kolomIp = array_values(array_filter(
            ['ip_hash', 'ip_address', 'ip_chain'],
            fn ($k) => Schema::hasColumn(self::TABEL, $k)
        ));

        if ($kolomIp !== []) {
            $this->aman(fn () => Schema::table(self::TABEL, fn (Blueprint $t) => $t->dropColumn($kolomIp)), 'drop kolom IP');
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan: skema lama (kunci IP) sudah tidak dipakai.
    }

    /** Jalankan langkah, catat peringatan bila gagal (jangan batalkan migrasi). */
    private function aman(callable $fn, string $label): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::warning('Migrasi survey log: langkah "' . $label . '" gagal. ' . $e->getMessage());
        }
    }

    /** Daftar nama index pada tabel (lintas driver). */
    private function namaIndex(): array
    {
        try {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'sqlsrv') {
                $rows = DB::select(
                    'SELECT i.name AS name FROM sys.indexes i
                     WHERE i.object_id = OBJECT_ID(?) AND i.name IS NOT NULL',
                    [self::TABEL]
                );

                return array_map(fn ($r) => (string) $r->name, $rows);
            }

            if ($driver === 'sqlite') {
                $rows = DB::select(
                    "SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND name NOT LIKE 'sqlite_%'",
                    [self::TABEL]
                );

                return array_map(fn ($r) => (string) $r->name, $rows);
            }

            return array_values(array_filter(array_map(
                fn ($i) => is_array($i) ? ($i['name'] ?? null) : null,
                Schema::getIndexes(self::TABEL)
            )));
        } catch (\Throwable $e) {
            Log::warning('Migrasi survey log: gagal baca daftar index. ' . $e->getMessage());

            return [];
        }
    }
};