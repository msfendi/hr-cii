<?php

namespace App\Services;

use App\Models\ChatbotSession;
use App\Models\RolePayroll;
use App\Models\User;

/**
 * Guard data payroll untuk chatbot mode RAG (R33 di docs/RAG_RULES_CII.md).
 *
 * Kenapa ada kelas ini: data gaji/payroll tidak boleh dibuka sama rata.
 * Kewenangannya ditentukan tabel `role_payrolls.payroll_role` (bukan nama
 * role Spatie), lalu dipetakan ke cakupan karyawan:
 *
 *   Payroll_STAFF      -> staff & expat          (IS_STAFF = '1' OR IS_EXPAT = '1')
 *   Payroll_NONSTAFF   -> semua non staff        (IS_STAFF = '0')
 *   Payroll_SEWING     -> non staff sewing       (IS_STAFF = '0' AND DEPT.IS_SEWING = '0')
 *   Payroll_NONSEWING  -> non staff non sewing   (IS_STAFF = '0' AND DEPT.IS_SEWING = '1')
 *   Payroll_ALL        -> semua
 *   tidak terdaftar    -> tidak boleh apa pun (deny by default)
 *
 * Kelas ini TIDAK menebak role dari isi pertanyaan dan tidak memberi bypass
 * untuk Admin: Admin yang memang harus melihat semua data wajib punya baris
 * `role_payrolls` dengan nilai Payroll_ALL (konsisten dengan modul payroll
 * yang juga membaca role_payrolls, bukan hasRole('Admin')).
 *
 * Catatan tipe data: IS_STAFF/IS_EXPAT/IS_SEWING tersimpan sebagai teks
 * '0'/'1' (R14), jadi filter di SQL memakai literal teks.
 */
class PayrollAccessGuard
{
    /** Kata kunci yang menandakan pertanyaan menyentuh data payroll/gaji. */
    public const KATA_SENSITIF = [
        'gaji', 'upah', 'payroll', 'salary', 'kompensasi', 'compensation',
        'pinalty', 'penalti', 'penalty', 'thr', 'tunjangan', 'potongan',
        'slip', 'insentif', 'pph', 'bpjs', 'take home', 'pendapatan', 'remunerasi',
    ];

    /** Penanda permintaan yang mengarah ke staff/expat. */
    protected const KATA_STAFF = [
        'staff', 'staf', 'expat', 'ekspat', 'kantor', 'office', 'manager', 'manajer',
        'supervisor', 'spv', 'hrd', 'bulanan', 'kepala bagian', 'head',
    ];

    /** Penanda permintaan yang mengarah ke non staff. */
    protected const KATA_NONSTAFF = [
        'non staff', 'nonstaff', 'non-staff', 'nonsstaff', 'harian', 'borongan',
        'produksi', 'operator', 'sewing', 'jahit', 'non sewing', 'nonsewing',
        'pabrik', 'buruh', 'harian lepas',
    ];

    /** Penanda permintaan yang menyebut sektor tertentu. */
    protected const KATA_SEWING = ['sewing', 'jahit'];
    protected const KATA_NONSEWING = ['non sewing', 'nonsewing', 'non-sewing'];

    public const LABEL = [
        PayrollRoleFilterService::ROLE_STAFF     => 'Staff (staff & expat)',
        PayrollRoleFilterService::ROLE_NONSTAFF  => 'Non Staff (semua sektor)',
        PayrollRoleFilterService::ROLE_SEWING    => 'Sewing (non staff sewing)',
        PayrollRoleFilterService::ROLE_NONSEWING => 'Non Sewing (non staff non sewing)',
        PayrollRoleFilterService::ROLE_ALL       => 'Semua (tidak difilter)',
    ];

    /** Role payroll efektif user, dibaca dari role_payrolls. */
    public function role(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return optional($user->rolePayroll)->payroll_role;
    }

    /** Apakah pertanyaan menyentuh payroll/gaji/kompensasi/pinalty. */
    public function sensitif(string $pertanyaan): bool
    {
        $teks = ' ' . mb_strtolower($pertanyaan) . ' ';

        foreach (self::KATA_SENSITIF as $kata) {
            if (str_contains($teks, $kata)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cakupan yang boleh dibuka untuk satu role.
     *
     * @return array{role:?string,label:string,staff:bool,nonstaff:bool,sewing:?int,sql:string,teks:string,semua:bool}
     */
    public function cakupan(?string $role): array
    {
        return match ($role) {
            PayrollRoleFilterService::ROLE_STAFF => [
                'role' => $role,
                'label' => self::LABEL[$role],
                'staff' => true,
                'nonstaff' => false,
                'sewing' => null,
                'sql' => "BIODATA.IS_STAFF = '1' OR BIODATA.IS_EXPAT = '1'",
                'teks' => 'staff dan expat saja',
                'semua' => false,
            ],
            PayrollRoleFilterService::ROLE_NONSTAFF => [
                'role' => $role,
                'label' => self::LABEL[$role],
                'staff' => false,
                'nonstaff' => true,
                'sewing' => null,
                'sql' => "BIODATA.IS_STAFF = '0'",
                'teks' => 'seluruh non staff (sewing dan non sewing)',
                'semua' => false,
            ],
            PayrollRoleFilterService::ROLE_SEWING => [
                'role' => $role,
                'label' => self::LABEL[$role],
                'staff' => false,
                'nonstaff' => true,
                'sewing' => 0,
                'sql' => "BIODATA.IS_STAFF = '0' AND DEPT.IS_SEWING = '0'",
                'teks' => 'non staff sewing saja',
                'semua' => false,
            ],
            PayrollRoleFilterService::ROLE_NONSEWING => [
                'role' => $role,
                'label' => self::LABEL[$role],
                'staff' => false,
                'nonstaff' => true,
                'sewing' => 1,
                'sql' => "BIODATA.IS_STAFF = '0' AND DEPT.IS_SEWING = '1'",
                'teks' => 'non staff non sewing saja',
                'semua' => false,
            ],
            PayrollRoleFilterService::ROLE_ALL => [
                'role' => $role,
                'label' => self::LABEL[$role],
                'staff' => true,
                'nonstaff' => true,
                'sewing' => null,
                'sql' => '',
                'teks' => 'semua karyawan (staff, expat, non staff, sewing, non sewing)',
                'semua' => true,
            ],
            default => [
                'role' => $role,
                'label' => 'Belum terdaftar',
                'staff' => false,
                'nonstaff' => false,
                'sewing' => null,
                'sql' => '1 = 0',
                'teks' => 'tidak ada (role payroll belum diberikan)',
                'semua' => false,
            ],
        };
    }

    /** Ringkasan untuk meta jawaban / log. */
    public function ringkas(?string $role): array
    {
        $c = $this->cakupan($role);

        return [
            'role' => $c['role'],
            'label' => $c['label'],
            'cakupan' => $c['teks'],
            'filter_sql' => $c['sql'],
        ];
    }

    /**
     * Apakah permintaan user keluar dari cakupan role-nya?
     *
     * @return array{staff:?bool,sewing:?int}|null null = tidak ada penanda khusus
     */
    public function permintaan(string $pertanyaan): ?array
    {
        $teks = ' ' . mb_strtolower($pertanyaan) . ' ';

        // "non staff" / "non sewing" BUKAN penanda staff/sewing. Kalau tidak
        // dinetralkan lebih dulu, frasa "non staff" ikut cocok dengan kata
        // kunci "staff" dan permintaan yang sah (non staff) malah dianggap
        // permintaan staff — lalu ditolak untuk role non staff.
        $tanpaNon = preg_replace('/\bnon[\s\-_]?(staff|staf|sewing)\b/', ' ', $teks) ?? $teks;

        $mintaStaff = null;
        $mintaSewing = null;

        foreach (self::KATA_SEWING as $kata) {
            if (str_contains($tanpaNon, $kata)) {
                $mintaSewing = 0;
            }
        }

        foreach (self::KATA_NONSEWING as $kata) {
            if (str_contains($teks, $kata)) {
                $mintaSewing = 1;
            }
        }

        foreach (self::KATA_NONSTAFF as $kata) {
            if (str_contains($teks, $kata)) {
                $mintaStaff = false;
            }
        }

        foreach (self::KATA_STAFF as $kata) {
            if (str_contains($tanpaNon, $kata)) {
                $mintaStaff = true;
            }
        }

        if ($mintaStaff === null && $mintaSewing === null) {
            return null;
        }

        return ['staff' => $mintaStaff, 'sewing' => $mintaSewing];
    }

    /**
     * Kalimat penolakan bila permintaan di luar cakupan; null bila boleh.
     * Role yang tidak terdaftar di role_payrolls selalu ditolak.
     */
    public function pelanggaran(?string $role, string $pertanyaan): ?string
    {
        if (! $this->sensitif($pertanyaan)) {
            return null;
        }

        $c = $this->cakupan($role);

        if ($c['semua']) {
            return null;
        }

        if ($c['role'] === null || ! PayrollRoleFilterService::isRegistered($c['role'])) {
            return "Maaf, data payroll/gaji tidak bisa saya tampilkan untuk akun ini.\n\n"
                . "Anda belum punya role payroll di `role_payrolls`, dan akses data payroll tidak "
                . "diberikan secara default. Cakupan yang bisa dibuka: **tidak ada** sampai role diberikan.\n\n"
                . "Kalau Anda memang berhak, ajukan penetapan role payroll (mis. `Payroll_STAFF`, "
                . "`Payroll_NONSTAFF`, `Payroll_SEWING`, `Payroll_NONSEWING`, atau `Payroll_ALL`) ke admin HRIS.";
        }

        $minta = $this->permintaan($pertanyaan);

        if ($minta === null) {
            return null;
        }

        $alasan = null;

        if ($minta['staff'] === true && ! $c['staff']) {
            $alasan = 'staff/expat';
        }

        if ($minta['staff'] === false && ! $c['nonstaff']) {
            $alasan = 'non staff';
        }

        if ($minta['sewing'] !== null && $c['sewing'] !== null && $minta['sewing'] !== $c['sewing']) {
            $alasan = $minta['sewing'] === 0 ? 'non staff sewing' : 'non staff non sewing';
        }

        if ($alasan === null) {
            return null;
        }

        return "Maaf, saya tidak bisa menampilkan data **{$alasan}** yang Anda minta.\n\n"
            . "Anda **tidak diperbolehkan** mengakses data payroll di luar cakupan role payroll Anda.\n\n"
            . "- Role payroll Anda: `{$c['role']}` — {$c['label']}\n"
            . "- Cakupan yang boleh dibuka: **{$c['teks']}**"
            . ($c['sql'] !== '' ? " (`{$c['sql']}`)" : '') . "\n\n"
            . "Permintaan itu hanya bisa dibuka oleh role yang cakupannya mencakup {$alasan}. "
            . "Kalau Anda memang membutuhkan data tersebut, ajukan perubahan role payroll ke admin HRIS.";
    }

    /**
     * Blok aturan yang disisipkan ke prompt penyusun SQL.
     */
    public function blokPrompt(?string $role): string
    {
        $c = $this->cakupan($role);
        $kolomGaji = 'Kolom gaji `employees_contract.salary`, `daily_salary`, `allowance`, `pph21` '
            . 'TERSEDIA untuk role ini dan boleh dipakai (tidak lagi diblokir) — tetapi hanya untuk '
            . 'karyawan dalam cakupan di bawah. Kolom identitas (KTP/KK/HP) dan token TETAP diblokir. '
            . 'Bila Bagian 6c menyebut kolom gaji sebagai terblokir, untuk penanya ini pengecualian '
            . 'R33.1 berlaku (role payroll terdaftar).';

        if ($c['semua']) {
            return "=== BATAS AKSES PAYROLL PENGGUNA (R33) ===\n"
                . "Role payroll penanya: `{$c['role']}` ({$c['label']}).\n"
                . "Cakupan: semua karyawan — tidak ada filter cakupan yang perlu ditambahkan.\n"
                . "{$kolomGaji}\n"
                . "Larangan tabel audit (R25) dan tabel payroll agregat (Bagian 6c) TETAP berlaku.\n";
        }

        if (! PayrollRoleFilterService::isRegistered($c['role'])) {
            return "=== BATAS AKSES PAYROLL PENGGUNA (R33) ===\n"
                . "Penanya TIDAK punya role payroll di role_payrolls.\n"
                . "Untuk pertanyaan yang menyentuh gaji/payroll/kompensasi/pinalty: balas NO_QUERY.\n";
        }

        return "=== BATAS AKSES PAYROLL PENGGUNA (R33) ===\n"
            . "Role payroll penanya: `{$c['role']}` ({$c['label']}).\n"
            . "Cakupan: {$c['teks']}.\n"
            . "{$kolomGaji}\n"
            . "Filter WAJIB ada di setiap query yang menyentuh gaji/payroll/kompensasi/pinalty: {$c['sql']}\n"
            . "Gabung sektor lewat BIODATA.ID_DEPT = DEPT.ID_DEPT. IS_STAFF/IS_EXPAT/IS_SEWING bertipe teks ('0'/'1').\n"
            . "Jangan pernah menampilkan baris, jumlah, atau total di luar cakupan itu — termasuk lewat COUNT/SUM.\n"
            . "Kalau pertanyaan hanya bisa dijawab dengan data di luar cakupan: balas NO_QUERY.\n"
            . "Kalau user meminta rekap gabungan, tampilkan rekap cakupannya saja dan sebutkan bahwa hanya "
            . "data dalam cakupannya yang disertakan.\n";
    }

    /**
     * Apakah role ini boleh melihat komponen gaji pada employees_contract.
     *
     * R33: cakupan role adalah soal KARYAWAN MANA (staff/expat/sewing/...),
     * bukan soal kolom. Jadi begitu role terdaftar di role_payrolls, kolom
     * gaji harus terbuka — kalau tidak, blocklist R32/R19 membuat semua
     * orang (termasuk Payroll_ALL) ditolak saat menanyakan gaji, dan aturan
     * role tidak pernah terpakai.
     */
    public function bolehLihatGaji(?string $role): bool
    {
        return PayrollRoleFilterService::isRegistered($role);
    }

    /**
     * Pemeriksaan wajib SEBELUM aturan RAG dipakai (guard check).
     *
     * Membaca ulang `users` dan `role_payrolls` dari database — bukan dari
     * relasi yang sudah ter-cache di sesi — supaya:
     *  - sesi tanpa pemilik / pemilik yang tidak ada di `users` ditolak;
     *  - sesi tidak bisa dipakai akun lain (user_id sesi != user login);
     *  - role payroll yang dipakai guard adalah nilai TERKINI di database,
     *    bukan nilai lama yang mungkin sudah dicabut.
     *
     * Hasilnya dipakai AiChatService untuk memutuskan: lanjut, tolak payroll,
     * atau tolak seluruh permintaan.
     */
    public function periksaSesi(ChatbotSession $session, ?int $penggunaAktif = null): array
    {
        $hasil = [
            'ok' => false,
            'kode' => 'tidak_diketahui',
            'pesan' => '',
            'user' => null,
            'role' => null,
            'user_id' => $session->user_id,
        ];

        if (! $session->user_id) {
            $hasil['kode'] = 'sesi_tanpa_pemilik';
            $hasil['pesan'] = 'Sesi chat ini tidak tercatat memiliki pemilik, jadi tidak bisa dipakai untuk '
                . 'membaca data. Mulai sesi baru dari halaman chatbot.';

            return $hasil;
        }

        if ($penggunaAktif !== null && (int) $penggunaAktif !== (int) $session->user_id) {
            $hasil['kode'] = 'bukan_pemilik_sesi';
            $hasil['pesan'] = 'Sesi chat ini milik akun lain. Demi keamanan data, permintaan tidak diproses.';

            return $hasil;
        }

        // users dibaca ULANG dari database (bukan $session->user yang bisa
        // berasal dari relasi lama / objek di memori).
        try {
            $user = User::query()->find($session->user_id);
        } catch (\Throwable $e) {
            $hasil['kode'] = 'users_tidak_terbaca';
            $hasil['pesan'] = 'Identitas pengguna tidak bisa diverifikasi saat ini (tabel users tidak terbaca). '
                . 'Permintaan tidak diproses supaya tidak ada data yang keluar tanpa verifikasi.';

            return $hasil;
        }

        if (! $user) {
            $hasil['kode'] = 'user_tidak_ada';
            $hasil['pesan'] = 'Akun pemilik sesi ini tidak ditemukan di tabel users. Permintaan tidak diproses.';

            return $hasil;
        }

        $hasil['user'] = $user;

        // role_payrolls dibaca ULANG juga. Gagal baca BUKAN alasan membuka
        // akses: role dianggap kosong, sehingga data gaji tetap tertutup.
        $role = null;

        try {
            $role = RolePayroll::query()->where('user_id', $user->id)->value('payroll_role');
            $hasil['kode'] = 'role_kosong';
        } catch (\Throwable $e) {
            $hasil['kode'] = 'role_payrolls_tidak_terbaca';
            \Illuminate\Support\Facades\Log::warning(
                'PayrollAccessGuard: role_payrolls tidak terbaca — ' . $e->getMessage()
            );
        }

        $role = $role !== null ? trim((string) $role) : null;

        if ($role !== null && $role !== '' && ! PayrollRoleFilterService::isRegistered($role)) {
            \Illuminate\Support\Facades\Log::warning('PayrollAccessGuard: role payroll tidak dikenal', [
                'user_id' => $user->id,
                'role' => $role,
            ]);

            $role = null;
            $hasil['kode'] = 'role_tidak_dikenal';
        } elseif ($role !== null && $role !== '') {
            $hasil['kode'] = 'role_terdaftar';
        }

        $hasil['role'] = $role;
        $hasil['ok'] = true;

        return $hasil;
    }

    public function sqlSesuaiCakupan(string $sql, ?string $role): bool
    {
        $c = $this->cakupan($role);

        if ($c['semua']) {
            return true;
        }

        if (! PayrollRoleFilterService::isRegistered($c['role'])) {
            return false;
        }

        $teks = mb_strtolower($sql);
        $adaStaff = str_contains($teks, 'is_staff') || str_contains($teks, 'is_expat');

        if (! $adaStaff) {
            return false;
        }

        if ($c['sewing'] !== null && ! str_contains($teks, 'is_sewing')) {
            return false;
        }

        return true;
    }

    /** Alasan yang dikirim ke AI saat query tidak memuat filter cakupan. */
    public function pesanFilterHilang(?string $role): string
    {
        $c = $this->cakupan($role);

        return "Query tidak memuat filter cakupan payroll milik penanya (role {$c['role']}). "
            . "Tambahkan kondisi: {$c['sql']} — join DEPT lewat BIODATA.ID_DEPT = DEPT.ID_DEPT.";
    }
}