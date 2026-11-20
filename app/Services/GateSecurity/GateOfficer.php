<?php

namespace App\Services\GateSecurity;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Identitas petugas yang memproses gate check.
 *
 * Petugas login cukup dengan NPK yang terdaftar di master karyawan — tanpa
 * cek jabatan/shift, sesuai kesepakatan. Identitas inilah yang dicatat saat
 * menyetujui karyawan keluar atau kembali.
 */
class GateOfficer
{
    /**
     * Verifikasi NPK petugas.
     *
     * @return array{ok:bool, npk?:string, name?:string, message?:string}
     */
    public static function verify(string $npk): array
    {
        $npk = trim($npk);

        if ($npk === '') {
            return ['ok' => false, 'message' => 'NPK wajib diisi.'];
        }

        try {
            $row = DB::connection('cii')->table('PKWT')->where('NPK', $npk)->first(['NPK', 'NAMA']);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal membaca data karyawan: ' . $e->getMessage()];
        }

        if (!$row) {
            return ['ok' => false, 'message' => "NPK {$npk} tidak terdaftar."];
        }

        return [
            'ok'   => true,
            'npk'  => (string) $row->NPK,
            'name' => (string) ($row->NAMA ?? $npk),
        ];
    }
}