<?php

namespace App\Services\ApprovalWa;

use Carbon\Carbon;
use Throwable;

class WaMessageFormatter
{
    /** Cache lookup per-request supaya tidak query berulang dalam satu run. */
    private static array $cache = [];

    /**
     * Format pesan notifikasi pengajuan cuti untuk approver.
     *
     * Memuat informasi yang relevan bagi approver: pemohon (nama + NPK),
     * tanggal pengajuan, jenis cuti, periode, durasi, alasan, nomor pengajuan,
     * serta posisi level approval.
     *
     * Menyertakan deep link WhatsApp untuk aksi setujui / tolak.
     */
    public static function formatApprovalRequest(object $request, object $approver): string
    {
        $id    = (int) $request->id;
        $level = (int) $request->approval_level;
        $total = self::totalLevels($request);

        $pemohonNama = self::employeeName((string) $request->NPK);
        $leaveType   = self::leaveTypeName((int) ($request->leave_type_id ?? 0));
        $reason      = self::cleanText($request->reason ?? null) ?? '-';

        $tglPengajuan = self::fmtDate($request->created_at ?? null, withTime: true);
        $periode      = self::period((string) $request->start_date, (string) $request->end_date);
        $durasi       = ((int) $request->total_days) . ' hari';

        $approverName = self::cleanText($approver->NAMA ?? null) ?? (string) $approver->NPK;

        // Label level: sertakan posisi dalam chain + tandai kalau ini level terakhir
        // (approver terakhir yang memutuskan → berdampak final ke saldo cuti).
        if ($total > 0) {
            $levelLabel = "$level dari $total"
                . ($level === $total ? ' (level terakhir)' : '');
        } else {
            $levelLabel = (string) $level;
        }

        // Deep link WhatsApp: buka chat bot dengan teks command sudah terisi.
        $botNumber   = (string) config('services.wa_gateway.bot_number', '6287731665679');
        $approveLink = "https://wa.me/$botNumber?text=" . urlencode("/approve $id");
        $rejectLink  = "https://wa.me/$botNumber?text=" . urlencode("/tolak $id <alasan>");

        return <<<MSG
        ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
        ┃ 🗂️ *PENGAJUAN CUTI — BUTUH PERSETUJUAN*
        ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

        *DATA PEMOHON*
        👤 $pemohonNama
        🆔 {$request->NPK}

        *DETAIL PENGAJUAN*
        📌 Jenis cuti: $leaveType
        📅 Diajukan: $tglPengajuan
        🗓️ Periode: $periode
        ⏳ Durasi: $durasi
        📝 Alasan: $reason

        *No. Pengajuan:* *#$id*
        *Level approval:* *$levelLabel*

        ━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        Yth. *$approverName*,
        mohon berikan keputusan Anda:

        ✅ *Setujui* → $approveLink
        ❌ *Tolak* → $rejectLink

        Atau balas langsung ke chat ini:
        • `/approve $id`
        • `/tolak $id <alasan>`

        ⚠️ Mohon pastikan *No. Pengajuan* sudah sesuai.
        MSG;
    }

    /**
     * Konfirmasi setelah keputusan diproses.
     */
    public static function formatConfirmation(int $requestId, string $decision, string $approverName): string
    {
        $status    = strtolower($decision) === 'approve' ? '✅ DISETUJUI' : '❌ DITOLAK';
        $timestamp = now()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i');

        return <<<MSG
        ✔️ *Konfirmasi Keputusan*

        Pengajuan cuti *#$requestId* telah *$status*.
        Oleh: $approverName
        Waktu: $timestamp WIB

        Terima kasih.
        MSG;
    }

    /**
     * Pesan penolakan / gagal proses.
     */
    public static function formatUnauthorized(int $requestId, string $reason): string
    {
        return "⛔ Gagal memproses pengajuan *#$requestId*.\n\nAlasan: $reason";
    }

    // ─────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────

    /**
     * Total level approval pada satu pengajuan (cache per token).
     */
    private static function totalLevels(object $request): int
    {
        $token = (string) ($request->token ?? '');
        if ($token === '') {
            return 0;
        }

        if (!isset(self::$cache["levels:$token"])) {
            try {
                self::$cache["levels:$token"] = LeaveApprovalQuery::countTokenLevels($token);
            } catch (Throwable) {
                self::$cache["levels:$token"] = 0;
            }
        }

        return (int) self::$cache["levels:$token"];
    }

    /**
     * Nama karyawan (cache per NPK).
     */
    private static function employeeName(string $npk): string
    {
        if (!isset(self::$cache["emp:$npk"])) {
            $name = null;
            try {
                $name = LeaveApprovalQuery::getEmployeeName($npk);
            } catch (Throwable) {
                // fallback ke NPK kalau lookup gagal
            }
            self::$cache["emp:$npk"] = $name;
        }

        return (string) (self::$cache["emp:$npk"] ?? $npk);
    }

    /**
     * Nama jenis cuti (cache per id).
     */
    private static function leaveTypeName(int $leaveTypeId): string
    {
        if ($leaveTypeId <= 0) {
            return '-';
        }

        if (!isset(self::$cache["lt:$leaveTypeId"])) {
            $name = null;
            try {
                $name = LeaveApprovalQuery::getLeaveTypeName($leaveTypeId);
            } catch (Throwable) {
                // fallback di bawah
            }
            self::$cache["lt:$leaveTypeId"] = $name;
        }

        return (string) (self::$cache["lt:$leaveTypeId"] ?? "ID $leaveTypeId");
    }

    /**
     * Format tanggal ke d/m/Y (opsional dengan jam WIB).
     */
    private static function fmtDate(mixed $value, bool $withTime = false): string
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

        $dt = $dt->setTimezone('Asia/Jakarta');

        return $withTime ? $dt->format('d/m/Y H:i') . ' WIB' : $dt->format('d/m/Y');
    }

    /**
     * Rentang periode cuti. Kalau tanggal sama, tampilkan satu tanggal saja.
     */
    private static function period(string $start, string $end): string
    {
        $a = self::fmtDate($start);
        $b = self::fmtDate($end);

        if ($a === $b) {
            return $a;
        }

        return "$a — $b";
    }

    /**
     * Rapikan teks: buang CR/LF ganda dan spasi berlebih.
     */
    private static function cleanText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/\s+/u', ' ', trim($value));

        return ($clean === '' || $clean === null) ? null : $clean;
    }
}
