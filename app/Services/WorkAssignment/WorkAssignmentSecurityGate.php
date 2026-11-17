<?php

namespace App\Services\WorkAssignment;

use App\Models\WorkAssignmentRequest;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Gate check security untuk Work Assignment Request.
 *
 * Alur:
 *   1. Karyawan keluar  → security scan QR → catat `security_check.out`
 *   2. Karyawan kembali → security scan QR → catat `security_check.in`,
 *      sekaligus mengisi `jam_kembali` sesuai waktu scan.
 *
 * Aturan:
 *   - Hanya pengajuan yang sudah `approved` semua level yang boleh diproses.
 *   - Sekali tercatat, TIDAK bisa diubah (tidak ada update/overwrite).
 *   - Urutan dijaga: `in` hanya boleh kalau `out` sudah ada.
 */
class WorkAssignmentSecurityGate
{
    public const STAGE_OUT = 'out';
    public const STAGE_IN  = 'in';

    /**
     * Cek apakah NPK terdaftar sebagai karyawan.
     *
     * Security login cukup modal NPK terdaftar (tanpa cek shift/jabatan).
     *
     * @return array{ok:bool, npk?:string, name?:string, message?:string}
     */
    public static function verifySecurityNpk(string $npk): array
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

    /**
     * Cari pengajuan dari isi QR.
     *
     * Format QR: `WA:<id>:<token>`
     *
     * @return array{ok:bool, request?:WorkAssignmentRequest, message?:string}
     */
    public static function resolveFromScan(string $payload): array
    {
        $payload = trim($payload);

        if ($payload === '') {
            return ['ok' => false, 'message' => 'Kode QR kosong.'];
        }

        // Terima format lengkap WA:id:token maupun id mentah.
        $parts = explode(':', $payload);

        if (count($parts) >= 3 && strtoupper($parts[0]) === 'WA') {
            $id    = (int) $parts[1];
            $token = $parts[2];
        } else {
            $id    = (int) $payload;
            $token = null;
        }

        if ($id <= 0) {
            return ['ok' => false, 'message' => 'Kode QR tidak dikenali.'];
        }

        $request = WorkAssignmentRequest::find($id);

        if (!$request) {
            return ['ok' => false, 'message' => "Pengajuan #{$id} tidak ditemukan."];
        }

        if ($token !== null && (string) $request->token !== (string) $token) {
            return ['ok' => false, 'message' => 'Kode QR tidak cocok dengan pengajuan.'];
        }

        return ['ok' => true, 'request' => $request];
    }

    /**
     * Catat gate check security.
     *
     * @return array{ok:bool, message:string, stage?:string, jam_kembali?:string}
     */
    public static function record(
        WorkAssignmentRequest $request,
        string $securityNpk,
        string $securityName,
        ?string $note = null
    ): array {
        if (!$request->isReadyForSecurity()) {
            return [
                'ok'      => false,
                'message' => 'Pengajuan belum sepenuhnya disetujui. Security hanya bisa memproses pengajuan yang sudah approved semua level.',
            ];
        }

        $stage = $request->securityStage();

        if ($stage === null) {
            return [
                'ok'      => false,
                'message' => 'Pengajuan ini sudah lengkap — keluar dan kembali sudah tercatat.',
            ];
        }

        $now   = now();
        $check = $request->security_check ?? [];

        $check[$stage] = [
            'npk'    => $securityNpk,
            'name'   => $securityName,
            'status' => 'approved',
            'at'     => $now->format('Y-m-d H:i:s'),
            'note'   => $note,
        ];

        $request->security_check = $check;

        // Gate kembali → isi jam_kembali sesuai waktu scan.
        if ($stage === self::STAGE_IN) {
            $request->jam_kembali = $now->format('H:i:s');
        }

        $request->save();

        return [
            'ok'          => true,
            'message'     => $stage === self::STAGE_OUT
                ? 'Karyawan tercatat KELUAR oleh security ' . $securityName . '.'
                : 'Karyawan tercatat KEMBALI oleh security ' . $securityName . '.',
            'stage'       => $stage,
            'jam_kembali' => $request->jam_kembali,
        ];
    }

    /**
     * Ringkasan untuk ditampilkan di halaman security.
     *
     * @return array<string, mixed>
     */
    public static function summary(WorkAssignmentRequest $request): array
    {
        $out = $request->securityOut();
        $in  = $request->securityIn();

        return [
            'stage'        => $request->securityStage(),
            'out'          => $out,
            'in'           => $in,
            'jam_kembali'  => $request->jam_kembali,
            'out_by'       => $out['name'] ?? null,
            'in_by'        => $in['name'] ?? null,
        ];
    }
}