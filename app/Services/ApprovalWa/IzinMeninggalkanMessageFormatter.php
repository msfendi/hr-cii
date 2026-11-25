<?php

namespace App\Services\ApprovalWa;

use App\Models\IjinMeninggalkanRequest;
use App\Services\WorkAssignment\EmployeeLookupService;
use Carbon\Carbon;
use Throwable;

/**
 * Format pesan WhatsApp untuk Izin Meninggalkan Tugas / Pekerjaan (CII.FM.HR.02.06).
 */
class IzinMeninggalkanMessageFormatter
{
    private static array $nameCache = [];

    public static function formatApprovalRequest(
        IjinMeninggalkanRequest $request,
        string $approverName,
        int $level,
        int $total
    ): string {
        $id      = (int) $request->id;
        $npk     = (string) $request->npk;
        $pemohon = self::employeeName($npk);

        $tanggal = self::date($request->tanggal);
        $keluar  = self::time($request->jam_keluar);
        $kembali = self::time($request->rencana_kembali);
        $alasan  = self::clean($request->alasan) ?? '-';

        $levelLabel = $total > 0 ? "$level dari $total" : (string) $level;
        $levelLabel .= $total > 0 && $level === $total ? ' (level terakhir)' : '';

        $approveCmd = '/approve-izin-meninggalkan' . $id;
        $rejectCmd  = '/tolak-izin-meninggalkan' . $id . ' <alasan>';

        return <<<MSG
        ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
        ┃ 📋 *IZIN MENINGGALKAN — BUTUH PERSETUJUAN*
        ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

        *DATA PEMOHON*
        👤 $pemohon
        🆔 $npk

        *DETAIL IZIN*
        🗓️ Tanggal: $tanggal
        🕗 Jam keluar: $keluar
        🔁 Rencana kembali: $kembali
        📝 Alasan: $alasan

        *No. Pengajuan:* *#$id*
        *Level approval:* *$levelLabel*

        ━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        Yth. *$approverName*,
        mohon berikan keputusan Anda:

        ✅ *Setujui* → $approveCmd
        ❌ *Tolak* → $rejectCmd

        Atau balas langsung ke chat ini:
        • $approveCmd
        • $rejectCmd

        ⚠️ Mohon pastikan *No. Pengajuan* sudah sesuai.
        MSG;
    }

    public static function formatConfirmation(
        IjinMeninggalkanRequest $request,
        string $decision,
        string $approverName
    ): string {
        $status    = strtolower($decision) === 'approve' ? '✅ DISETUJUI' : '❌ DITOLAK';
        $timestamp = now()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i');

        $id      = (int) $request->id;
        $npk     = (string) $request->npk;
        $pemohon = self::employeeName($npk);
        $tanggal = self::date($request->tanggal);
        $keluar  = self::time($request->jam_keluar);
        $kembali = self::time($request->rencana_kembali);
        $alasan  = self::clean($request->alasan) ?? '-';

        return <<<MSG
        ✔️ *Konfirmasi Keputusan*

        Pengajuan *Izin Meninggalkan #$id* telah *$status*.
        Oleh: $approverName
        Waktu: $timestamp WIB

        *DATA PEMOHON*
        👤 $pemohon
        🆔 $npk

        *DETAIL IZIN*
        🗓️ Tanggal: $tanggal
        🕗 Jam keluar: $keluar
        🔁 Rencana kembali: $kembali
        📝 Alasan: $alasan

        Terima kasih.
        MSG;
    }

    public static function formatUnauthorized(int $requestId, string $reason): string
    {
        return "⛔ Gagal memproses *Izin Meninggalkan #$requestId*.\n\nAlasan: $reason";
    }

    public static function formatUnsupported(int $requestId, string $what): string
    {
        return "⚠️ *$what* tidak tersedia untuk Izin Meninggalkan #$requestId.\n\n"
            . "Izin Meninggalkan tidak memakai saldo, jadi keputusan yang sudah masuk tidak bisa dibatalkan.\n"
            . "Kalau ada kekeliruan, hubungi HR untuk penyesuaian.";
    }

    private static function employeeName(string $npk): string
    {
        if ($npk === '') {
            return '-';
        }

        if (!array_key_exists($npk, self::$nameCache)) {
            $name = null;

            try {
                $names = EmployeeLookupService::namesFor([$npk]);
                $name  = $names[$npk] ?? null;
            } catch (Throwable) {
                // fallback ke NPK
            }

            self::$nameCache[$npk] = self::clean($name);
        }

        return self::$nameCache[$npk] ?? $npk;
    }

    private static function date(mixed $value): string
    {
        if (empty($value)) {
            return '-';
        }

        try {
            $dt = $value instanceof \DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse((string) $value);
        } catch (Throwable) {
            return (string) $value;
        }

        return $dt->format('d/m/Y');
    }

    private static function time(mixed $value): string
    {
        $value = self::clean(is_string($value) ? $value : null);

        if ($value === null) {
            return '-';
        }

        return substr($value, 0, 5);
    }

    private static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/\s+/u', ' ', trim($value));

        return ($clean === '' || $clean === null) ? null : $clean;
    }
}
