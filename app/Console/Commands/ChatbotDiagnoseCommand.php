<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Diagnostik mode RAG chatbot AI Assistant.
 *
 * Menjawab pertanyaan "kenapa chatbot bilang tidak ada tabel yang bisa
 * dibaca?" langsung di server tempat aplikasi berjalan:
 *   - koneksi read-only mana yang dipakai (config/chatbot.php),
 *   - host & database yang ditunjuk .env (host disamarkan),
 *   - login yang dipakai & database default-nya,
 *   - jumlah tabel yang terbaca dari schema dbo,
 *   - error asli kalau koneksinya gagal,
 *   - status cache skema (cache kosong lama dulu bikin chatbot tetap
 *     error walau config sudah diperbaiki).
 *
 * Jalankan: php artisan chatbot:diagnose
 * Opsi     : --clear-cache  (bersihkan cache skema RAG lalu keluar)
 */
class ChatbotDiagnoseCommand extends Command
{
    protected $signature = 'chatbot:diagnose {--clear-cache : Bersihkan cache skema RAG lalu keluar}';

    protected $description = 'Periksa kenapa mode RAG chatbot tidak bisa membaca tabel database read-only.';

    public function handle(): int
    {
        $connection = (string) config('chatbot.readonly_connection');
        $driver = (string) config("database.connections.$connection.driver", '-');
        $database = (string) config("database.connections.$connection.database", '-');
        $username = (string) config("database.connections.$connection.username", '-');
        $host = (string) config("database.connections.$connection.host", '-');

        if ($this->option('clear-cache')) {
            $this->bersihkanCache($connection);

            return self::SUCCESS;
        }

        $this->info('=== Diagnostik Chatbot RAG ===');
        $this->line('Koneksi read-only   : ' . $connection);
        $this->line('Driver              : ' . $driver);
        $this->line('Host (disamarkan)   : ' . $this->samarkanHost($host));
        $this->line('Database            : ' . $database);
        $this->line('Username            : ' . $this->samarkanUser($username));
        $this->line('Provider AI aktif   : ' . (string) config('chatbot.default_provider', '-')
            . ' (' . (string) config('chatbot.providers.' . config('chatbot.default_provider') . '.model', '-') . ')');
        $this->line('Timeout provider    : ' . (int) config('chatbot.request_timeout', 120) . ' detik');
        $this->newLine();

        // 1) Koneksi + jumlah tabel
        try {
            DB::purge($connection);
            $info = DB::connection($connection)->selectOne('SELECT DB_NAME() AS dbname, SUSER_SNAME() AS login_name');
            $this->info('[OK] Koneksi berhasil.');
            $this->line('     DB_NAME()  : ' . ($info->dbname ?? '-'));
            $this->line('     Login      : ' . ($info->login_name ?? '-'));

            $jumlah = DB::connection($connection)->selectOne(
                "SELECT COUNT(*) AS jml FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_SCHEMA = 'dbo'"
            );
            $this->line('     Tabel dbo  : ' . ($jumlah->jml ?? 0));

            if ((int) ($jumlah->jml ?? 0) === 0) {
                $this->warn('[MASALAH] Koneksi jalan tapi 0 tabel di schema dbo.');
                $this->line('     Artinya database yang ditunjuk bukan database HRIS.');
                $this->line('     Perbaiki DB_READONLY_DATABASE di .env server, lalu: php artisan config:clear');
            } else {
                $this->info('[OK] Skema terbaca. Mode RAG seharusnya bisa dipakai.');
            }
        } catch (\Throwable $e) {
            $this->error('[GAGAL] ' . get_class($e));
            $this->line('     ' . substr($e->getMessage(), 0, 400));
            $this->newLine();
            $this->warn('Penyebab paling sering: DB_READONLY_* di .env server belum diisi / ' .
                'login read-only belum dibuat / port SQL Server tidak terbuka dari server aplikasi.');
        }

        $this->newLine();
        $this->info('=== Cache skema RAG ===');
        $this->line('Ada cache tersimpan: ' . ($this->adaCache($connection) ? 'YA (perlu dibersihkan bila config baru diperbaiki)' : 'tidak ada'));
        $this->line('Bersihkan dengan   : php artisan chatbot:diagnose --clear-cache');

        return self::SUCCESS;
    }

    protected function bersihkanCache(string $connection): void
    {
        $jumlah = 0;

        try {
            $store = Cache::getStore();

            if (method_exists($store, 'flush')) {
                Cache::flush();
                $jumlah = 1;
            }
        } catch (\Throwable $e) {
            $this->error('Gagal membersihkan cache: ' . $e->getMessage());
        }

        $this->info($jumlah
            ? 'Cache (termasuk cache skema RAG koneksi ' . $connection . ') dibersihkan.'
            : 'Cache tidak dibersihkan (store tidak mendukung flush). Coba: php artisan cache:clear');
    }

    protected function adaCache(string $connection): bool
    {
        try {
            return Cache::has('chatbot_rag_schema:' . $connection . ':' . md5(json_encode([])));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Samarkan host: hanya tampilkan bentuk umum, bukan alamat internal mentah. */
    protected function samarkanHost(string $host): string
    {
        if ($host === '' || $host === '-') {
            return '-';
        }

        if (preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $host)) {
            $bagian = explode('.', $host);

            return $bagian[0] . '.' . $bagian[1] . '.x.x (LAN internal)';
        }

        return $host;
    }

    protected function samarkanUser(string $user): string
    {
        if ($user === '' || $user === '-') {
            return '-';
        }

        return substr($user, 0, 3) . str_repeat('*', max(0, strlen($user) - 3));
    }
}
