<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Nonce (token sekali pakai) untuk mencegah pemalsuan payload presensi.
 *
 * MASALAH YANG DITUTUP
 * --------------------
 * Endpoint presensi membaca latitude/longitude apa adanya dari request.
 * Tanpa nonce, satu baris fetch() di DevTools atau aplikasi Fake GPS bisa
 * mengirim presensi dari koordinat mana pun. Nonce memastikan request
 * berasal dari halaman presensi yang benar-benar dibuka server.
 *
 * BATAS KEJUJURAN
 * ---------------
 * Nonce TIDAK membuat pemalsuan mustahil. Penyerang yang membuka halaman
 * presensi sungguhan (mendapat nonce sah) masih bisa memalsukan koordinat
 * GPS di request yang sama, karena browser tetap satu-satunya sumber
 * koordinat. Yang dicegah: replay payload lama, dan pengiriman presensi
 * tanpa sesi halaman yang nyata.
 *
 * PERBAIKAN V31 (2026-11-05) — MENGAPA ALASAN DIPISAH
 * ---------------------------------------------------
 * Versi pertama memakai TTL 90 detik dan hanya satu token per halaman.
 * Akibatnya karyawan SAH ikut ditandai is_suspicious karena:
 *   - butuh lebih dari 90 detik menyiapkan kamera & GPS,
 *   - clock-out di halaman yang sama memakai token yang sudah terpakai,
 *   - cache dibersihkan / driver cache berganti.
 * Sekarang alasan dibedakan tajam, dan HANYA 'nonce_tidak_dikirim' yang
 * pantas dianggap mencurigakan. Kedaluwarsa BUKAN kecurangan.
 */
class AttendanceNonceService
{
    /**
     * Masa hidup nonce dalam detik.
     *
     * 30 menit: cukup untuk mencari sinyal GPS, mengatur kamera, mengulang
     * liveness beberapa kali, dan menyelesaikan shift pendek. Nonce tetap
     * SEKALI PAKAI, jadi memperpanjang TTL tidak melemahkan anti-replay.
     */
    public const NONCE_TTL = 1800;

    /**
     * Jumlah token yang diterbitkan per halaman.
     *
     * Satu halaman bisa dipakai clock-in DAN clock-out, ditambah retry kalau
     * submit gagal (mis. GPS belum dapat, jaringan putus). Dengan 5 token,
     * pemakaian normal tidak pernah kehabisan.
     */
    public const NONCE_PER_HALAMAN = 5;

    /** Prefix cache agar tidak bertabrakan dengan cache lain. */
    private const PREFIX = 'attendance_nonce:';

    /**
     * Alasan yang MENANDAI presensi sebagai mencurigakan.
     *
     * Hanya token yang benar-benar TIDAK DIKIRIM. Request tanpa token berarti
     * halaman presensi tidak pernah dibuka lewat server — itu pola paling
     * khas script/Fake GPS. Kedaluwarsa, salah NPK, dan cache hilang TIDAK
     * masuk daftar ini karena semuanya bisa terjadi pada karyawan jujur.
     */
    public const ALASAN_MENCURIGAKAN = ['nonce_kosong'];

    /**
     * Buat beberapa nonce sekaligus untuk NPK + menu tertentu.
     *
     * @return array<int,string> daftar token 40 karakter
     */
    public function issueBatch(string $npk, string $menu = 'attendance', ?int $jumlah = null): array
    {
        $jumlah = $jumlah ?? self::NONCE_PER_HALAMAN;
        $token  = [];

        for ($i = 0; $i < $jumlah; $i++) {
            $token[] = $this->issue($npk, $menu);
        }

        return $token;
    }

    /**
     * Terbitkan satu nonce baru untuk NPK + menu tertentu.
     */
    public function issue(string $npk, string $menu = 'attendance'): string
    {
        $token = Str::random(40);

        Cache::put(
            $this->key($token),
            [
                'npk'  => (string) $npk,
                'menu' => $menu,
                'at'   => now()->toIso8601String(),
            ],
            now()->addSeconds(self::NONCE_TTL)
        );

        return $token;
    }

    /**
     * Pakai nonce: kembalikan status validasi lalu hapus tokennya.
     *
     * @return array{valid: bool, alasan: string, mencurigakan: bool}
     */
    public function consume(?string $token, string $npk, string $menu = 'attendance'): array
    {
        $token = is_string($token) ? trim($token) : '';

        if ($token === '') {
            // SATU-SATUNYA kondisi yang menandai. Request datang tanpa token
            // sama sekali -> halaman presensi tidak pernah dibuka server.
            return $this->hasil(false, 'nonce_kosong');
        }

        // Batasi panjang supaya tidak ada upaya menyalahgunakan cache key.
        if (strlen($token) > 100) {
            return $this->hasil(false, 'nonce_tidak_valid');
        }

        $data = Cache::get($this->key($token));

        if (!is_array($data)) {
            // Token dikirim tapi tidak ada di cache. Penyebab paling umum BUKAN
            // kecurangan: sudah terpakai (clock-in lalu clock-out), TTL habis,
            // atau cache dibersihkan. Tidak menandai.
            return $this->hasil(false, 'nonce_kedaluwarsa_atau_terpakai');
        }

        // Sekali pakai: hapus SEGERA, sebelum pengecekan lain, supaya
        // percobaan berulang dengan token yang sama selalu gagal.
        Cache::forget($this->key($token));

        if ((string) ($data['npk'] ?? '') !== (string) $npk) {
            return $this->hasil(false, 'nonce_npk_tidak_cocok');
        }

        if ((string) ($data['menu'] ?? '') !== $menu) {
            return $this->hasil(false, 'nonce_menu_tidak_cocok');
        }

        return $this->hasil(true, 'ok');
    }

    /**
     * Susun hasil validasi secara seragam.
     */
    private function hasil(bool $valid, string $alasan): array
    {
        return [
            'valid'        => $valid,
            'alasan'       => $alasan,
            'mencurigakan' => in_array($alasan, self::ALASAN_MENCURIGAKAN, true),
        ];
    }

    private function key(string $token): string
    {
        return self::PREFIX . hash('sha256', $token);
    }
}
