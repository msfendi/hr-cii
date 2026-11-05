<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Employee Active Status Service
 * ================================================================
 * Menentukan status aktif / sudah keluar seorang karyawan dengan
 * memakai kolom `TKK` pada tabel `PKWT` (database CII).
 *
 * Aturan bisnis:
 *   - `PKWT.TKK IS NULL`      -> karyawan MASIH AKTIF, boleh lanjut.
 *   - `PKWT.TKK IS NOT NULL`  -> karyawan SUDAH KELUAR (tanggal keluar
 *                                terisi), tidak boleh lanjut.
 *
 * Catatan penting:
 *   - `NPK` pada `PKWT` unik (tidak ada duplikat), jadi satu NPK =
 *     satu status. Query cukup `first()` tanpa `orderBy`.
 *   - Semua NPK yang ada di `BIODATA` juga ada di `PKWT`, sehingga
 *     status keluar selalu bisa dipastikan dari `PKWT`.
 *   - Halaman ini dipakai bersama oleh:
 *       1. Self Register Wajah  (step 1: verifikasi NPK + tanggal lahir)
 *       2. Face Attendance      (wajah terbaca -> kirim attendance)
 *     supaya keduanya tidak berbeda perilaku.
 *
 * DESIGN:
 *   Class ini dibuat instance-based (bukan static murni) supaya data
 *   akses ke tabel PKWT bisa di-override di test lewat subclass.
 *   Pemanggil production tetap memakai API static yang ringkas
 *   (`check()`, `isActive()`, `inactiveMessage()`).
 */
class EmployeeActiveStatusService
{
    /**
     * Cache status per-NPK dalam satu request, supaya tidak query
     * berulang ke database untuk NPK yang sama.
     *
     * @var array<string, array>
     */
    protected static array $cache = [];

    /**
     * Instance yang dipakai oleh API static.
     */
    protected static ?self $default = null;

    /**
     * Ambil data status karyawan berdasarkan NPK.
     *
     * @param  string  $npk
     * @return array{ found: bool, active: bool, tkk: ?string, nama: ?string }
     */
    public function checkStatus(string $npk): array
    {
        $npk = strtoupper(trim($npk));

        if ($npk === '') {
            return ['found' => false, 'active' => false, 'tkk' => null, 'nama' => null];
        }

        if (isset(self::$cache[$npk])) {
            return self::$cache[$npk];
        }

        // Default: TIDAK ditemukan di PKWT -> TIDAK boleh dianggap keluar.
        // Aturan bisnis hanya menolak bila TKK terlihat terisi. Kalau NPK
        // tidak ada di PKWT, biarkan validasi lain yang menentukan.
        $result = ['found' => false, 'active' => true, 'tkk' => null, 'nama' => null];

        try {
            $row = $this->fetchPkwtRow($npk);

            if ($row) {
                $tkk   = $row->TKK ?? null;
                $isOut = !($tkk === null || $tkk === '');

                $result = [
                    'found' => true,
                    // TKK terisi = sudah keluar (tidak boleh lanjut).
                    'active' => !$isOut,
                    'tkk'    => $isOut ? (string) $tkk : null,
                    'nama'   => $row->NAMA ?? null,
                ];
            }
        } catch (\Throwable $e) {
            // Database CII tidak terjangkau: jangan memblokir alur.
            // Biarkan pemanggil memakai validasi normal (tanggal lahir,
            // dll). Log agar mudah ditelusuri.
            \Illuminate\Support\Facades\Log::warning('EmployeeActiveStatusService: gagal cek PKWT.TKK', [
                'npk'   => $npk,
                'error' => $e->getMessage(),
            ]);

            return ['found' => false, 'active' => true, 'tkk' => null, 'nama' => null];
        }

        self::$cache[$npk] = $result;

        return $result;
    }

    /**
     * Ambil satu baris PKWT untuk NPK tertentu.
     *
     * Protected supaya bisa di-override oleh subclass di test.
     *
     * @return object|null
     */
    protected function fetchPkwtRow(string $npk)
    {
        return DB::connection('cii')
            ->table('PKWT')
            ->where('NPK', $npk)
            ->first(['NPK', 'NAMA', 'TKK']);
    }

    /**
     * API static: cek status karyawan.
     */
    public static function check(string $npk): array
    {
        if (self::$default === null) {
            self::$default = new static();
        }

        return self::$default->checkStatus($npk);
    }

    /**
     * Helper ringkas: apakah karyawan ini masih aktif (TKK kosong)?
     */
    public static function isActive(string $npk): bool
    {
        return (bool) self::check($npk)['active'];
    }

    /**
     * Pesan alert siap tampil (Bahasa Indonesia, sesuai bahasa aplikasi).
     */
    public static function inactiveMessage(?string $nama = null, ?string $tkk = null): string
    {
        $base = 'Anda bukan karyawan aktif';

        if ($nama) {
            $base .= ' (' . $nama . ')';
        }

        $base .= '. Data kepegawaian Anda sudah tercatat keluar';

        if ($tkk) {
            $base .= ' pada tanggal ' . self::formatTanggal($tkk);
        }

        return $base . '. Silakan hubungi HRD untuk informasi lebih lanjut.';
    }

    /**
     * Format tanggal Indonesia yang enak dibaca.
     */
    protected static function formatTanggal(string $tgl): string
    {
        $ts = strtotime($tgl);

        if ($ts === false) {
            return $tgl;
        }

        $bulan = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];

        return date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    }

    /**
     * Ganti instance default (dipakai test).
     */
    public static function swap(?self $instance): void
    {
        self::$default = $instance;
    }

    /**
     * Bersihkan cache (dipakai test).
     */
    public static function flushCache(): void
    {
        self::$cache = [];
    }
}
