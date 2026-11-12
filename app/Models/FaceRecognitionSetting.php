<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class FaceRecognitionSetting extends Model
{
    protected $fillable = [
        'is_enabled',
        'match_threshold',
        'is_qr_login_enabled',
        'is_qr_payroll_enabled',
        'is_qr_thr_enabled',
        'is_qr_cuti_enabled',
        // PERBAIKAN 25: toggle liveness check
        'is_liveness_enabled',
        'liveness_presensi_enabled',
        'liveness_login_enabled',
        'liveness_slip_enabled',
        'liveness_cuti_enabled',
        // PERBAIKAN 4 (2026-11-06): toggle input NPK dulu
        'is_npk_required_enabled',
        // Random challenge (anti-kecurangan): master + per menu
        'is_random_challenge_enabled',
        'random_challenge_presensi_enabled',
        'random_challenge_login_enabled',
        'random_challenge_slip_enabled',
        'random_challenge_cuti_enabled',
        'random_challenge_registrasi_enabled',
        // PERBAIKAN V32 (2026-11-09): liveness & random challenge untuk
        // /canteen-transaction, disetting dari /face-attendance/settings.
        'liveness_canteen_enabled',
        'random_challenge_canteen_enabled',
        // PERBAIKAN V32 (2026-11-09): mode canteen -- liveness ATAU
        // random challenge. Nilai: 'liveness' (default, fail-safe),
        // 'random_challenge', atau 'none'.
        'canteen_verification_mode',
    ];

    protected $casts = [
        'is_enabled'            => 'boolean',
        'match_threshold'       => 'float',
        'is_qr_login_enabled'   => 'boolean',
        'is_qr_payroll_enabled' => 'boolean',
        'is_qr_thr_enabled'     => 'boolean',
        'is_qr_cuti_enabled'    => 'boolean',
        // PERBAIKAN 25
        'is_liveness_enabled'       => 'boolean',
        'liveness_presensi_enabled' => 'boolean',
        'liveness_login_enabled'    => 'boolean',
        'liveness_slip_enabled'     => 'boolean',
        'liveness_cuti_enabled'     => 'boolean',
        // PERBAIKAN 4
        'is_npk_required_enabled'   => 'boolean',
        // Random challenge (anti-kecurangan)
        'is_random_challenge_enabled'         => 'boolean',
        'random_challenge_presensi_enabled'   => 'boolean',
        'random_challenge_login_enabled'      => 'boolean',
        'random_challenge_slip_enabled'       => 'boolean',
        'random_challenge_cuti_enabled'       => 'boolean',
        'random_challenge_registrasi_enabled' => 'boolean',
        // PERBAIKAN V32 (2026-11-09): canteen (kolom boolean lama,
        // dipakai sebagai cermin kompatibilitas mode canteen).
        'liveness_canteen_enabled'         => 'boolean',
        'random_challenge_canteen_enabled' => 'boolean',
    ];

    // PERBAIKAN V33 (2026-11-09) -- cache daftar kolom per tabel.
    //
    // Kenapa: Schema::hasColumn()/hasTable() issue query ke INFORMATION_SCHEMA
    // tiap kali dipanggil. Di SQL Server remote (CII di 192.168.3.88, TLS
    // encrypt=yes) tiap query itu 50-300ms. Controller settings memanggilnya
    // 30+ kali per request -> 2-10 detik hang. Sama untuk canteen index
    // (current() + canteenVerificationMode() + isNpkRequired...).
    //
    // Fix: satu getColumnListing() per tabel, di-cache 1 jam (pola yang
    // sudah dipakai FaceAttendanceSetting::cachedColumns sejak V33
    // 2026-11-05). Request-scope static juga menghindari race antar
    // request dalam satu proses (Octane/queue).
    //
    // Pakai helper ini di controller/model BARU. Helper lama
    // FaceAttendanceSetting::hasAllowanceColumn() tetap jalan (punya
    // cache sendiri + fallback hasColumn).
    //
    // PERBAIKAN V34 HOTFIX (2026-11-09) -- cachedHasTable TIDAK lagi
    // memakai Schema::getTables(). Di SQL Server, getTables() menjalankan
    // query berat yang join sys.partitions + sys.allocation_units untuk
    // menghitung size tiap tabel (compileTables di SqlServerGrammar).
    // Di database besar itu bisa detik->timeout (HYT00 Login timeout
    // expired, terbukti lokal 2026-11-09). Karena model-model ini
    // SUDAH menampilkan try/catch + fallback safe-default untuk tabel
    // yang tidak ada, query checker hanya dipakai sebagai optimisation
    // (guard) -- bukan sumber kebenaran. Di V34, cachedHasTable() cukup
    // memeriksa cache kolom yang sudah ada (berasal dari 1 query
    // getColumnListing per tabel). Tabel yang kolomnya TIDAK bisa
    // dibaca (tiada di cache dan query gagal) dianggap tidak ada,
    // sehingga controller memakai fallback aman tanpa pernah hang.
    // Baris ini juga menghapus panggilan getTables() dari request path.
    // Artinya: query schema berat di request path = 0 kali.
    protected static $cachedColumnsCache = [];
    protected static $cachedTablesCache = null; // kept for BC, unused in V34

    public static function forgetSchemaCache(): void
    {
        static::$cachedColumnsCache = [];
        static::$cachedTablesCache = null;
        try {
            \Illuminate\Support\Facades\Cache::forget('schema_tables');
        } catch (\Throwable $e) {
            // Abaikan.
        }
        try {
            // Bersihkan listing kolom tabel-tabel yang umum dipakai supaya
            // berikutnya tidak basi setelah migrasi.
            foreach (['face_recognition_settings', 'canteen_settings', 'face_attendance_settings', 'employee_faces', 'holidays'] as $t) {
                \Illuminate\Support\Facades\Cache::forget('schema_columns_' . $t);
            }
        } catch (\Throwable $e) {
            // Abaikan.
        }
    }

    public static function cachedColumns(string $table): array
    {
        if (isset(static::$cachedColumnsCache[$table])) {
            return static::$cachedColumnsCache[$table];
        }

        $cacheKey = 'schema_columns_' . $table;
        try {
            $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
            if (is_array($cached)) {
                static::$cachedColumnsCache[$table] = $cached;
                return $cached;
            }
        } catch (\Throwable $e) {
            // Cache tidak tersedia -> lanjut ke query langsung.
        }

        $columns = [];
        try {
            $columns = \Illuminate\Support\Facades\Schema::getColumnListing($table);
        } catch (\Throwable $e) {
            \Log::warning('Gagal membaca daftar kolom ' . $table . ': ' . $e->getMessage());
            return [];
        }

        try {
            \Illuminate\Support\Facades\Cache::put($cacheKey, $columns, now()->addHour());
        } catch (\Throwable $e) {
            // Abaikan kalau cache gagal ditulis.
        }

        static::$cachedColumnsCache[$table] = $columns;
        return $columns;
    }

    public static function cachedHasColumn(string $table, string $column): bool
    {
        return in_array($column, static::cachedColumns($table), true);
    }

    /**
     * PERBAIKAN V34 HOTFIX (2026-11-09) -- pengganti hasTable tanpa
     * query getTables() yang berat.
     *
     * Alasan: Schema::getTables() di SQL Server memakai query
     *   select t.name, SCHEMA_NAME(t.schema_id), sum(u.total_pages)*8*1024
     *   from sys.tables t join sys.partitions p on p.object_id = t.object_id
     *   join sys.allocation_units u on u.container_id = p.hobt_id
     *   group by t.name, t.schema_id order by t.name
     * yang mengitung size SEMUA tabel. Di database besar / koneksi
     * lambat ini bisa timeout (HYT00 Login timeout expired), dan karena
     * dipanggil di request path controller settings, halaman
     * /face-attendance/settings dan /canteen-transaction langsung 500.
     *
     * Implementasi V34: cukup periksa cache kolom per tabel.
     * cachedColumns($table) mengembalikan daftar kolom bukan kosong
     * hanya bila tabel ada (query getColumnListing berhasil).
     * Tabel yang tidak ada mengembalikan [] sehingga hasTable=false
     * dan controller memakai fallback aman yang sudah ada di catch.
     *
     * Catatan: magic ini berarti "tidak bisa dibaca" dianggap "tidak
     * ada". Itu sengaja fail-safe: halaman tetap tampil (is holiday
     * libur, canteenSetting=null, dll) daripada 500.
     */
    public static function cachedHasTable(string $table): bool
    {
        // 1) Static request-scope cache kolom
        if (isset(static::$cachedColumnsCache[$table]) && static::$cachedColumnsCache[$table] !== []) {
            return true;
        }

        // 2) Laravel Cache kolom (1 jam) -- tanpa query DB
        try {
            $cached = \Illuminate\Support\Facades\Cache::get('schema_columns_' . $table);
            if (is_array($cached) && $cached !== []) {
                static::$cachedColumnsCache[$table] = $cached;
                return true;
            }
        } catch (\Throwable $e) {
            // Abaikan - jatuh ke query column listing di bawah.
        }

        // 3) Baca kolom langsung (1 query ringan sys.columns utk tabel ini).
        // Kalau tabel tidak ada, getColumnListing mengembalikan [].
        try {
            $cols = \Illuminate\Support\Facades\Schema::getColumnListing($table);
            if (is_array($cols) && $cols !== []) {
                static::$cachedColumnsCache[$table] = $cols;
                try {
                    \Illuminate\Support\Facades\Cache::put('schema_columns_' . $table, $cols, now()->addHour());
                } catch (\Throwable $e) {
                    // Abaikan.
                }
                return true;
            }
        } catch (\Throwable $e) {
            \Log::warning('cachedHasTable(' . $table . ') gagal baca kolom: ' . $e->getMessage());
        }

        // Tabel tidak ada / tidak bisa dibaca -> fallback false (aman).
        // PENTING: tidak lagi memakai Schema::getTables() / hasTable()
        // karena itu query berat & bisa timeout -> 500.
        return false;
    }

    /**
     * Setting face recognition bersifat singleton (1 baris).
     *
     * PERBAIKAN V33 (2026-11-09) -- cache request-scope.
     * Sebelumnya current() selalu query()->first() per panggilan.
     * Index canteen memanggil current() secara tidak langsung lewat
     * canteenVerificationMode() + isNpkRequiredEnabled() +
     * FaceRecognitionService::settings() (threshold/isEnabled/dll)
     * = 4-6x query baris yang sama per request.
     * Sekarang di-cache per proses request; mutasi via save()/update
     * harus panggil forgetCurrent() agar tidak basi.
     */
    protected static $cachedCurrent = null;

    public static function forgetCurrent(): void
    {
        static::$cachedCurrent = null;
    }

    public static function current(): self
    {
        if (static::$cachedCurrent !== null) {
            return static::$cachedCurrent;
        }
        try {
            $setting = static::query()->first();

            if (!$setting) {
                $setting = new static();
                $setting->is_enabled = true;
                $setting->match_threshold = 0.500;
                // PERBAIKAN V33: pakai helper cached (1 query listing
                // per tabel, bukan 4x hasColumn per request).
                self::fillDefaultsOnNew($setting);
                try {
                    $setting->save();
                } catch (\Throwable $e) {
                    // PERBAIKAN 25 (2026-11-05): DULU error ditelan diam-diam,
                    // sehingga objek TIDAK tersimpan (exists=false) dikembalikan
                    // dan updateSettings() melakukan update pada objek hantu ->
                    // toggle liveness tampak "berhasil" tapi TIDAK tersimpan.
                    // Sekarang dicatat supaya kelihatan di log.
                    \Illuminate\Support\Facades\Log::warning(
                        'FaceRecognitionSetting::current() gagal membuat baris default: ' . $e->getMessage()
                    );
                }
            }

            static::$cachedCurrent = $setting;
            return $setting;
        } catch (\Throwable $e) {
            $fallback = new static();
            $fallback->is_enabled = true;
            $fallback->match_threshold = 0.500;
            $fallback->is_qr_login_enabled   = true;
            $fallback->is_qr_payroll_enabled = true;
            $fallback->is_qr_thr_enabled     = true;
            $fallback->is_qr_cuti_enabled    = true;
            $fallback->is_liveness_enabled       = true;
            $fallback->liveness_presensi_enabled = true;
            $fallback->liveness_login_enabled    = true;
            $fallback->liveness_slip_enabled     = true;
            $fallback->liveness_cuti_enabled     = true;
            $fallback->is_npk_required_enabled   = true;
            $fallback->is_random_challenge_enabled = false;
            return $fallback;
        }
    }

    /**
     * PERBAIKAN V33: sinkronkan cache request-scope setelah model disimpan.
     * Override save() untuk memastikan updateSettings/updateLiveness yang
     * mengubah objek ini tidak membuat request berikutnya membaca nilai basi.
     */
    public function save(array $options = [])
    {
        $result = parent::save($options);
        if ($result) {
            static::$cachedCurrent = $this;
        }
        return $result;
    }

    public function getIsQrLoginEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getIsQrPayrollEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getIsQrThrEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getIsQrCutiEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    /*
    |--------------------------------------------------------------------------
    | PERBAIKAN 25 -- Accessor Liveness Check
    |--------------------------------------------------------------------------
    | Semua accessor null-safe (default TRUE). Jadi kalau migrasi belum jalan,
    | sistem TETAP berperilaku seperti sekarang (liveness aktif), tidak
    | tiba-tiba mematikan keamanan presensi.
    */

    public function getIsLivenessEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getIsNpkRequiredEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    /*
    |--------------------------------------------------------------------------
    | Static helper -- dipakai controller & view
    |--------------------------------------------------------------------------
    | FaceAttendanceController memanggil FaceRecognitionSetting::isNpkRequiredEnabled()
    | secara statik. Tanpa method ini akan fatal error:
    | "Call to undefined method App\Models\FaceRecognitionSetting::isNpkRequiredEnabled()".
    | Default TRUE supaya bila kolom belum termigrasi, langkah input NPK tetap aman.
    */

    public static function isNpkRequiredEnabled(): bool
    {
        try {
            return (bool) static::current()->is_npk_required_enabled;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'FaceRecognitionSetting::isNpkRequiredEnabled() gagal: ' . $e->getMessage()
            );
            return true;
        }
    }

    public function getLivenessPresensiEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getLivenessLoginEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getLivenessSlipEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getLivenessCutiEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    /*
    |--------------------------------------------------------------------------
    | Accessor Random Challenge (anti-kecurangan)
    |--------------------------------------------------------------------------
    |
    | ORTogonal terhadap liveness: nilai default kolom master adalah FALSE
    | (fitur baru, belum diaktifkan sampai admin menyalakannya), sedangkan
    | switch per menu default TRUE supaya begitu master dinyalakan, semua
    | menu yang relevan langsung ikut aktif.
    |
    */

    public function getIsRandomChallengeEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : false;
    }

    public function getRandomChallengePresensiEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getRandomChallengeLoginEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getRandomChallengeSlipEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getRandomChallengeCutiEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getRandomChallengeRegistrasiEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    /* ---------------------------------------------------------------
     | PERBAIKAN V32 (2026-11-09) -- AKSESOR CANTEEN
     |---------------------------------------------------------------
     | Default liveness_canteen_enabled = TRUE (fail-safe: kantin tetap
     | memakai perilaku liveness yang sekarang walau kolom belum ada).
     | Default random_challenge_canteen_enabled = FALSE (fitur baru,
     | belum diaktifkan sampai admin menyalakannya dari settings).
     */
    public function getLivenessCanteenEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : true;
    }

    public function getRandomChallengeCanteenEnabledAttribute($value)
    {
        return $value !== null ? (bool) $value : false;
    }

    /* ---------------------------------------------------------------
     | PERBAIKAN V32 (2026-11-09) -- MODE CANTEEN: LIVENESS ATAU RANDOM
     | ---------------------------------------------------------------
     | Permintaan user: "mode tersebut bisa memilih mau pakai liveness
     | atau pakai random challenge, bukan keduanya".
     |
     | canteen_verification_mode disimpan di face_recognition_settings
     | (bukan canteen_settings) sesuai permintaan user: "liveness dan
     | random challenge untuk canteen transaction ada di table
     | face_recognition_settings bukan canteen_settings".
     |
     | Nilai:
     |   'liveness'         -> hanya liveness (kedip/menoleh)
     |   'random_challenge' -> hanya tantangan acak (kiri/kanan/buka mulut)
     |   'none'             -> tanpa verifikasi gerakan (wajah saja)
     |
     | Nilai kolom lama (boolean) tetap dibaca sebagai fallback bila
     | kolom mode belum termigrasi:
     |   liveness=true,  random=false -> 'liveness'
     |   random=true,    liveness=false -> 'random_challenge'
     |   kedua true -> 'liveness' (fail-safe: instruksi "Lanjut ke
     |              verifikasi liveness" lama tetap benar)
     |   kedua false -> 'none'
     */

    public const CANTEEN_MODE_LIVENESS = 'liveness';
    public const CANTEEN_MODE_RANDOM = 'random_challenge';
    public const CANTEEN_MODE_NONE = 'none';

    public static function canteenVerificationModes(): array
    {
        return [self::CANTEEN_MODE_LIVENESS, self::CANTEEN_MODE_RANDOM, self::CANTEEN_MODE_NONE];
    }

    public static function normalizeCanteenVerificationMode($value): string
    {
        $value = strtolower(trim((string) $value));

        if (in_array($value, [self::CANTEEN_MODE_RANDOM, 'random', 'challenge', 'random-challenge'], true)) {
            return self::CANTEEN_MODE_RANDOM;
        }

        if (in_array($value, [self::CANTEEN_MODE_NONE, 'off', 'tanpa_verifikasi', 'disabled'], true)) {
            return self::CANTEEN_MODE_NONE;
        }

        // Default fail-safe: liveness.
        return self::CANTEEN_MODE_LIVENESS;
    }

    public function getCanteenVerificationModeAttribute($value)
    {
        if ($value === null || $value === '') {
            // Fallback dari kolom boolean lama.
            $liveness = $this->getRawOriginal('liveness_canteen_enabled');
            $random = $this->getRawOriginal('random_challenge_canteen_enabled');

            if ((bool) $random && !(bool) $liveness) {
                return self::CANTEEN_MODE_RANDOM;
            }

            if (!(bool) $liveness && !(bool) $random && $liveness !== null && $random !== null) {
                return self::CANTEEN_MODE_NONE;
            }

            return self::CANTEEN_MODE_LIVENESS;
        }

        return self::normalizeCanteenVerificationMode($value);
    }

    public static function canteenVerificationMode(): string
    {
        // PERBAIKAN V33 (2026-11-09) -- pakai cachedHasColumn
        // supaya TIDAK hit INFORMATION_SCHEMA tiap panggil.
        // Sebelumnya: hasTable + hasColumn + current() + 2x helper
        // boolean (yang masing2 hit current() lagi) = 5-7 query schema
        // per request kantin index. Sekarang: 1 kolom listing cached 1 jam.
        try {
            if (static::cachedHasColumn('face_recognition_settings', 'canteen_verification_mode')) {
                $value = static::current()->getRawOriginal('canteen_verification_mode');

                if ($value !== null && $value !== '') {
                    return self::normalizeCanteenVerificationMode($value);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'FaceRecognitionSetting::canteenVerificationMode() gagal: ' . $e->getMessage()
            );
        }

        // Fallback kolom boolean lama.
        try {
            $liveness = self::isLivenessCanteenEnabled();
            $random = self::isRandomChallengeCanteenEnabled();

            if ($random && !$liveness) {
                return self::CANTEEN_MODE_RANDOM;
            }

            if (!$liveness && !$random) {
                return self::CANTEEN_MODE_NONE;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'FaceRecognitionSetting::canteenVerificationMode() fallback gagal: ' . $e->getMessage()
            );
        }

        return self::CANTEEN_MODE_LIVENESS;
    }

    public static function isLivenessCanteenMode(): bool
    {
        return self::canteenVerificationMode() === self::CANTEEN_MODE_LIVENESS;
    }

    public static function isRandomChallengeCanteenMode(): bool
    {
        return self::canteenVerificationMode() === self::CANTEEN_MODE_RANDOM;
    }

    /**
     * Kompatibilitas: isLivenessCanteenEnabled() / isRandomChallengeCanteenEnabled()
     * tetap tersedia untuk pemakai lama, tetapi kini meneruskan ke mode.
     */
    public static function isLivenessCanteenEnabled(): bool
    {
        return self::isLivenessCanteenMode();
    }

    public static function isRandomChallengeCanteenEnabled(): bool
    {
        return self::isRandomChallengeCanteenMode();
    }

    // PERBAIKAN V33 (2026-11-09) -- isi kolom defaults di current()
    // sebelumnya panggil Schema::hasColumn() 4x (QR, liveness, NPK,
    // random) = 4 query INFORMATION_SCHEMA tiap baris belum ada.
    // Sekarang: baca 1x cachedColumns() lalu cek in_array di memori.
    // Behavior SAMA (kolom tidak ada -> skip), tapi request pertama
    // setelah cache ketemu tidak hit DB lagi.
    protected static function fillDefaultsOnNew(self $setting): void
    {
        $cols = self::cachedColumns('face_recognition_settings');

        if (in_array('is_qr_login_enabled', $cols, true)) {
            $setting->is_qr_login_enabled   = true;
            $setting->is_qr_payroll_enabled = true;
            $setting->is_qr_thr_enabled     = true;
            $setting->is_qr_cuti_enabled    = true;
        }

        if (in_array('is_liveness_enabled', $cols, true)) {
            $setting->is_liveness_enabled       = true;
            $setting->liveness_presensi_enabled = true;
            $setting->liveness_login_enabled    = true;
            $setting->liveness_slip_enabled     = true;
            $setting->liveness_cuti_enabled     = true;
        }

        if (in_array('is_npk_required_enabled', $cols, true)) {
            $setting->is_npk_required_enabled = true;
        }

        if (in_array('is_random_challenge_enabled', $cols, true)) {
            $setting->is_random_challenge_enabled = false;
        }
    }
}
