<?php

namespace App\Services\IzinMeninggalkan;

use App\Models\IjinMeninggalkanPekerjaan;
use App\Models\IjinMeninggalkanRequest;
use App\Services\WorkAssignment\EmployeeLookupService;
use Illuminate\Support\Facades\DB;

/**
 * Sinkronisasi pengajuan Izin Meninggalkan ke tabel absensi lama
 * (`ijin_meninggalkan_pekerjaans`) setelah security mencatat jam kembali.
 *
 * Aturan:
 * 1. Tabel lama tidak boleh diubah skemanya.
 * 2. Insert hanya terjadi setelah `jam_kembali` terisi (gate scan 'in').
 * 3. `is_deduction` selalu true (keputusan bisnis).
 * 4. `id_break` diturunkan dari `dept_breaktimes` berdasarkan departemen pemohon.
 * 5. Idempoten: jika `legacy_ijin_id` sudah terisi atau baris identik sudah ada,
 *    jangan insert baris kedua.
 */
class IzinMeninggalkanLegacySync
{
    public static function sync(IjinMeninggalkanRequest $req): ?int
    {
        if ($req->legacy_ijin_id !== null) {
            return (int) $req->legacy_ijin_id;
        }

        if (empty($req->jam_kembali)) {
            return null;
        }

        $tanggal = $req->tanggal instanceof \DateTimeInterface
            ? $req->tanggal->format('Y-m-d')
            : \Carbon\Carbon::parse((string) $req->tanggal)->format('Y-m-d');

        $outCheck  = $req->securityOut();
        $jamKeluar = self::extractTime($outCheck['at'] ?? null)
            ?? self::normalizeTime((string) $req->jam_keluar);
        $jamKembali = self::normalizeTime((string) $req->jam_kembali);
        $rencanaKembali = $req->rencana_kembali
            ? self::normalizeTime((string) $req->rencana_kembali)
            : null;

        // Anti-duplikat lapis 2: adopsi baris existing jika sudah ada di tabel lama.
        $existing = IjinMeninggalkanPekerjaan::query()
            ->where('npk', $req->npk)
            ->whereDate('tanggal', $tanggal)
            ->where('jam_keluar', $jamKeluar)
            ->first();

        if ($existing) {
            $req->legacy_ijin_id = (int) $existing->id;
            $req->save();

            return (int) $existing->id;
        }

        $idBreak = self::resolveBreakId((string) $req->npk);

        $legacy = IjinMeninggalkanPekerjaan::create([
            'npk'             => (string) $req->npk,
            'tanggal'         => $tanggal,
            'jam_keluar'      => $jamKeluar,
            'rencana_kembali' => $rencanaKembali,
            'jam_kembali'     => $jamKembali,
            'reason'          => $req->alasan,
            'id_break'        => $idBreak,
            'is_deduction'    => true,
        ]);

        $req->legacy_ijin_id = (int) $legacy->id;
        $req->save();

        return (int) $legacy->id;
    }

    private static function resolveBreakId(string $npk): ?int
    {
        $deptId = EmployeeLookupService::deptIdFor($npk);
        if ($deptId === null || $deptId === '') {
            return null;
        }

        $idBreak = DB::connection('cii')
            ->table('dept_breaktimes')
            ->where('id_dept', $deptId)
            ->value('id_break');

        return $idBreak !== null ? (int) $idBreak : null;
    }

    private static function extractTime(?string $dateTime): ?string
    {
        if (empty($dateTime)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($dateTime)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function normalizeTime(string $time): string
    {
        $time = trim($time);
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time . ':00';
        }

        return $time;
    }
}
