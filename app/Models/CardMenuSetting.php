<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CardMenuSetting extends Model
{
    protected $table = 'card_menu_settings';

    protected $fillable = [
        'key', 'name', 'route', 'icon', 'color', 'is_visible', 'order',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'order'      => 'integer',
    ];

    /**
     * Semua card menu yang tampil, urut.
     * Kalau tabel belum ada (migration belum dijalankan), fallback ke default hardcoded
     * supaya halaman HRIS tidak blank.
     */
    public static function visibleCards(): array
    {
        // Cek keberadaan tabel DULU: kalau DB tidak terjangkau / tabel belum ada,
        // fallback ke default hardcoded supaya halaman HRIS tidak blank.
        if (!self::tableExists()) {
            return self::applyFileVisibility(self::defaultCards());
        }

        try {
            $rows = self::query()
                ->where('is_visible', true)
                ->orderBy('order')
                ->get();
        } catch (\Throwable $e) {
            return self::defaultCards();
        }

        if ($rows->isEmpty()) {
            return self::defaultCards();
        }

        $cards = [];
        foreach ($rows as $r) {
            // SUMBER UTAMA = DB. File hanya cadangan saat DB tidak bisa dibaca
            // (ditangani di cabang catch di atas). Sebelumnya file menang di
            // sini, sehingga nilai basi di file membuat toggle terlihat
            // "tidak berefek" di halaman HRIS.
            $visible = (bool) $r->is_visible;

            if (!$visible) {
                continue;
            }

            $cards[] = [
                'key'   => $r->key,
                // Nama tampilan memakai versi Bahasa Inggris; nilai aslinya
                // (dari DB) tetap disimpan di 'name_db' supaya halaman
                // pengaturan admin tetap menampilkan/menyimpan nama asli.
                'name'  => self::englishLabel($r->key, $r->name),
                'name_db' => $r->name,
                'route' => $r->route,
                'icon'  => $r->icon,
                'color' => $r->color ?: 'primary',
            ];
        }

        return $cards;
    }

    /**
     * Peta label Bahasa Inggris untuk card menu HRIS.
     *
     * Keterangan:
     *  - Halaman HRIS (display) memakai label Bahasa Inggris.
     *  - Halaman Pengaturan Card Menu (admin) tetap memakai nilai DB apa
     *    adanya supaya admin tidak bingung melihat nama yang berubah.
     *  - Kalau key tidak ada di peta, nama dari DB dipakai apa adanya
     *    (aman untuk card kustom milik admin).
     */
    private static function englishLabel(?string $key, ?string $dbName): string
    {
        $map = [
            'login'              => 'Login',
            'slip'               => 'Payslip',
            'career'             => 'Career',
            'recruitment'        => 'Recruitment Announcement',
            'food-orders'        => 'Food Orders',
            'leave-request'      => 'Leave Request',
            'face-attendance'    => 'Face Attendance',
            'self-register-face' => 'Face Self Registration',
        ];

        if ($key !== null && array_key_exists($key, $map)) {
            return $map[$key];
        }

        return $dbName ?: '';
    }

    /**
     * Semua card menu (termasuk yang disembunyikan) untuk halaman pengaturan admin.
     */
    public static function allCards(): array
    {
        if (!self::tableExists()) {
            return self::applyFileVisibility(self::defaultCards());
        }

        try {
            $rows = self::query()->orderBy('order')->get();
        } catch (\Throwable $e) {
            return self::defaultCards();
        }

        if ($rows->isEmpty()) {
            return self::defaultCards();
        }

        return $rows->map(function ($r) {
            return [
                'id'         => $r->id,
                'key'        => $r->key,
                'name'       => $r->name,
                'route'      => $r->route,
                'icon'       => $r->icon,
                'color'      => $r->color ?: 'primary',
                // DB adalah sumber utama (lihat catatan di visibleCards()).
                'is_visible' => (bool) $r->is_visible,
                'order'      => (int) $r->order,
            ];
        })->all();
    }

    /**
     * Terapkan setting file fallback ke daftar card default.
     */
    private static function applyFileVisibility(array $cards): array
    {
        $fileSettings = self::readFileSettings();

        $out = [];
        foreach ($cards as $c) {
            $visible = array_key_exists($c['key'], $fileSettings)
                ? (bool) $fileSettings[$c['key']]
                : (bool) ($c['is_visible'] ?? true);

            $c['is_visible'] = $visible;

            if ($visible) {
                $out[] = $c;
            }
        }

        return $out;
    }

    /**
     * Toggle atau set status tampil sebuah card.
     */
    public static function setVisibility(string $key, bool $visible): bool
    {
        $dbOk = false;

        if (self::tableExists()) {
            try {
                $affected = DB::table('card_menu_settings')
                    ->where('key', $key)
                    ->update([
                        'is_visible' => $visible ? 1 : 0,
                        'updated_at' => now(),
                    ]);

                if ($affected === 0) {
                    // Baris belum ada (migration belum di-seed) -> insert baru.
                    DB::table('card_menu_settings')->insert([
                        'key'        => $key,
                        'name'       => $key,
                        'route'      => null,
                        'icon'       => 'fas fa-circle',
                        'color'      => 'primary',
                        'is_visible' => $visible ? 1 : 0,
                        'order'      => 99,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $dbOk = true;
            } catch (\Throwable $e) {
                \Log::error('card_menu_settings: gagal update DB: ' . $e->getMessage());
                $dbOk = false;
            }
        }

        // File hanya berperan sebagai cadangan (mirror) untuk kondisi DB mati.
        // Sebelumnya file justru MENANG atas DB saat dibaca (lihat readFileSettings
        // usage), sehingga nilai DB yang sudah benar bisa tertimpa nilai file basi.
        $fileMap = self::readFileSettings();
        $fileMap[$key] = $visible;
        $fileOk = self::writeFileSettings($fileMap);

        return $dbOk || $fileOk;
    }


    /**
     * Path file penyimpanan fallback (dipakai bila tabel DB belum ada /
     * koneksi DB tidak terjangkau). Tanpa fallback ini, toggle di halaman
     * pengaturan seolah "tidak berefek" karena tidak ada tempat menyimpan:
     * halaman HRIS selalu kembali ke defaultCards().
     */
    public static function storagePath(): string
    {
        return storage_path('app/card_menu_settings.json');
    }

    /**
     * Baca setting dari file fallback. Format: ["key" => true|false].
     */
    public static function readFileSettings(): array
    {
        $path = self::storagePath();

        if (!is_file($path)) {
            return [];
        }

        try {
            $data = json_decode((string) file_get_contents($path), true);
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Tulis setting ke file fallback (atomic write).
     */
    public static function writeFileSettings(array $map): bool
    {
        try {
            $path = self::storagePath();
            $dir  = dirname($path);

            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            $json = json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if ($json === false) {
                return false;
            }

            $tmp = $path . '.tmp';
            if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
                return false;
            }

            return @rename($tmp, $path);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Cache hasil pengecekan table per request (bukan antar-request) supaya
     * hasTable() tidak dipanggil berkali-kali dalam satu render.
     */
    private static ?bool $tableExistsCache = null;

    /**
     * Cek keberadaan table `card_menu_settings` pada koneksi default.
     *
     * CATATAN PENTING (perbaikan): implementasi lama memanggil
     * `\PDO::setAttribute(\PDO::ATTR_TIMEOUT, 3)`. Driver sqlsrv TIDAK
     * mendukung atribut itu sehingga selalu melempar exception, dan
     * hasilnya method ini SELALU mengembalikan false di server produksi.
     * Akibatnya `setVisibility()` tidak pernah menulis ke DB dan
     * `allCards()` selalu memakai defaultCards() -> toggle terlihat
     * "tidak berefek". Batas waktu koneksi sekarang diatur lewat config
     * database (LoginTimeout), bukan lewat setAttribute.
     */
    public static function tableExists(bool $fresh = false): bool
    {
        if (!$fresh && self::$tableExistsCache !== null) {
            return self::$tableExistsCache;
        }

        try {
            $conn = DB::connection();
            $conn->getPdo(); // buka koneksi lebih awal agar error tertangkap di sini

            self::$tableExistsCache = $conn->getSchemaBuilder()->hasTable('card_menu_settings');
        } catch (\Throwable $e) {
            \Log::debug('card_menu_settings: table check gagal: ' . $e->getMessage());
            self::$tableExistsCache = false;
        }

        return self::$tableExistsCache;
    }

    /**
     * Default hardcoded, mencerminkan urutan card di hris.blade.php.
     * WAJIB menyertakan key `order` dan `is_visible` karena view pengaturan
     * membaca keduanya (Undefined array key "order" kalau tidak lengkap).
     */
    public static function defaultCards(): array
    {
        $cards = [
            ['key' => 'login',              'name' => 'Login',                    'route' => '/login',                      'icon' => 'fas fa-users',              'color' => 'primary'],
            ['key' => 'slip',               'name' => 'Payslip',                  'route' => '/employee-payroll',          'icon' => 'fas fa-file-invoice-dollar', 'color' => 'primary'],
            ['key' => 'career',             'name' => 'Career',                   'route' => '/lowongan',                  'icon' => 'fas fa-briefcase',          'color' => 'success'],
            ['key' => 'recruitment',        'name' => 'Recruitment Announcement', 'route' => '/portal-recruitment-status', 'icon' => 'fa fa-bullhorn',            'color' => 'success'],
            ['key' => 'food-orders',        'name' => 'Food Orders',              'route' => '/food-orders/scan',          'icon' => 'fas fa-utensils',           'color' => 'primary'],
            ['key' => 'leave-request',      'name' => 'Leave Request',            'route' => '/pengajuan-cuti/login',      'icon' => 'fas fa-calendar',           'color' => 'warning'],
            ['key' => 'face-attendance',    'name' => 'Face Attendance',          'route' => '/face-attendance/login',     'icon' => 'fas fa-camera',             'color' => 'primary'],
            ['key' => 'self-register-face', 'name' => 'Face Self Registration',   'route' => '/self-register-face',        'icon' => 'fas fa-id-badge',           'color' => 'info'],
        ];

        // Lengkapi dengan `order` + `is_visible` supaya aman dipakai view.
        foreach ($cards as $i => &$c) {
            $c['order']      = $i + 1;
            $c['is_visible'] = $c['key'] !== 'food-orders';
        }
        unset($c);

        return $cards;
    }
}
