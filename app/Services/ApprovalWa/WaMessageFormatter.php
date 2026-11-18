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

        // Saldo cuti hanya relevan untuk Cuti Tahunan (jenis cuti yang memakai
        // saldo). Jenis lain dibatasi per pengajuan, jadi tidak ditampilkan.
        $saldoBlock = self::balanceBlock($request);

        // Label level: sertakan posisi dalam chain + tandai kalau ini level terakhir
        // (approver terakhir yang memutuskan → berdampak final ke saldo cuti).
        if ($total > 0) {
            $levelLabel = "$level dari $total"
                . ($level === $total ? ' (level terakhir)' : '');
        } else {
            $levelLabel = (string) $level;
        }

        $approveCmd = '/approve-cuti' . $id;
        $rejectCmd  = '/tolak-cuti' . $id . ' <alasan>';

        // Deep link WhatsApp: buka chat bot dengan teks command sudah terisi.
        $botNumber   = (string) config('services.wa_gateway.bot_number', '6287731665679');
        $approveLink = "https://wa.me/$botNumber?text=" . urlencode($approveCmd);
        $rejectLink  = "https://wa.me/$botNumber?text=" . urlencode($rejectCmd);

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
        $saldoBlock
        *No. Pengajuan:* *#$id*
        *Level approval:* *$levelLabel*

        ━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        Yth. *$approverName*,
        mohon berikan keputusan Anda:

        ✅ *Setujui* → $approveLink
        ❌ *Tolak* → $rejectLink

        Atau balas langsung ke chat ini:
        • `$approveCmd`
        • `$rejectCmd`

        ⚠️ Mohon pastikan *No. Pengajuan* sudah sesuai.
        MSG;
    }

    /**
     * Blok saldo cuti untuk pesan approval.
     *
     * Hanya ditampilkan untuk Cuti Tahunan (satu-satunya jenis cuti yang
     * memakai saldo). Saldo diambil untuk periode pengajuan (tahun tanggal
     * mulai), sama seperti validasi saat pengajuan disubmit.
     *
     * Kalau saldo tidak cukup, sisanya dicatat sebagai HUTANG CUTI.
     *
     * @return string  baris siap tempel, atau '' kalau tidak perlu ditampilkan
     */
    private static function balanceBlock(object $request): string
    {
        $typeId = (int) ($request->leave_type_id ?? 0);

        if ($typeId <= 0) {
            return '';
        }

        $type = self::leaveType($typeId);

        // Hanya Cuti Tahunan (code = 'tahunan').
        if (!$type || strtolower(trim((string) ($type->code ?? ''))) !== 'tahunan') {
            return '';
        }

        $year = self::balanceYear($request);
        $npk  = (string) $request->NPK;
        $need = (int) ($request->total_days ?? 0);

        $balance = self::leaveBalance($npk, $typeId, $year);

        if (!$balance) {
            return "\n*SALDO CUTI TAHUNAN $year*\n"
                . "💰 Sisa saldo: data belum tersedia — hubungi HR\n";
        }

        $remained = (int) ($balance->remained_days ?? 0);
        $used     = (int) ($balance->used_days ?? 0);
        $debt     = (int) ($balance->negative_leave ?? 0);

        $lines = [];
        $lines[] = "*SALDO CUTI TAHUNAN $year*";
        $lines[] = "💰 Sisa saldo: *$remained hari* (terpakai $used hari)";

        if ($need > 0) {
            $after = $remained - $need;

            if ($after >= 0) {
                $lines[] = "📊 Diajukan $need hari → sisa setelah disetujui: *$after hari*";
            } else {
                $lines[] = "⚠️ Diajukan $need hari, sisa saldo hanya $remained hari";
                $lines[] = "🟠 Kekurangan *" . abs($after) . " hari* dicatat sebagai *HUTANG CUTI*";
            }
        }

        // Hutang cuti yang sudah tercatat sebelumnya tetap ditampilkan.
        if ($debt > 0) {
            $lines[] = "🟠 Hutang cuti tercatat: *$debt hari*";
        }

        return "\n" . implode("\n", $lines) . "\n";
    }

    /**
     * Tahun saldo yang dipakai: tahun tanggal MULAI cuti (sama dengan
     * validasi saat pengajuan dibuat). Fallback berurutan ke tanggal
     * pengajuan, lalu tahun berjalan.
     */
    private static function balanceYear(object $request): int
    {
        foreach ([$request->start_date ?? null, $request->created_at ?? null] as $value) {
            if (empty($value)) {
                continue;
            }

            try {
                return (int) Carbon::parse((string) $value)->year;
            } catch (Throwable) {
                // lanjut ke kandidat berikutnya
            }
        }

        return (int) date('Y');
    }

    /**
     * Konfirmasi setelah keputusan diproses.
     *
     * Memuat ulang ringkasan pengajuan karena pemanggil hanya mengirim nomor
     * pengajuan: approver perlu tahu pengajuan mana yang baru ia putuskan —
     * atas nama siapa, jenis cuti apa, periode, dan durasinya. Tanpa ini
     * pesan hanya berisi nomor sehingga sulit dicocokkan saat menangani
     * banyak pengajuan.
     */
    public static function formatConfirmation(int $requestId, string $decision, string $approverName): string
    {
        $status    = strtolower($decision) === 'approve' ? '✅ DISETUJUI' : '❌ DITOLAK';
        $timestamp = now()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i');

        $summary = self::requestSummary($requestId);

        if ($summary === null) {
            // Data pengajuan tak terbaca (DB bermasalah / sudah dihapus):
            // tetap kirim konfirmasi, jangan sampai approver tidak dapat balasan.
            return <<<MSG
            ✔️ *Konfirmasi Keputusan*

            Pengajuan cuti *#$requestId* telah *$status*.
            Oleh: $approverName
            Waktu: $timestamp WIB

            ⚠️ Detail pengajuan tidak dapat dimuat saat ini.

            Terima kasih.
            MSG;
        }

        $npk         = (string) ($summary->NPK ?? '-');
        $pemohonNama = self::employeeName($npk);
        $leaveType   = self::leaveTypeName((int) ($summary->leave_type_id ?? 0));
        $periode     = self::period((string) ($summary->start_date ?? ''), (string) ($summary->end_date ?? ''));
        $durasi      = ((int) ($summary->total_days ?? 0)) . ' hari';

        return <<<MSG
        ✔️ *Konfirmasi Keputusan*

        Pengajuan cuti *#$requestId* telah *$status*.
        Oleh: $approverName
        Waktu: $timestamp WIB

        *DATA PEMOHON*
        👤 $pemohonNama
        🆔 $npk

        *DETAIL CUTI*
        📌 Jenis cuti: $leaveType
        🗓️ Periode: $periode
        ⏳ Durasi: $durasi

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
     * Ringkasan pengajuan (NPK, jenis cuti, periode, durasi) — cache per id.
     *
     * Dipakai formatConfirmation(). Null kalau pengajuan tak ditemukan atau
     * query gagal; pemanggil menampilkan pesan apa adanya.
     */
    private static function requestSummary(int $requestId): ?object
    {
        if ($requestId <= 0) {
            return null;
        }

        $key = "req:$requestId";

        if (!array_key_exists($key, self::$cache)) {
            $summary = null;
            try {
                $summary = LeaveApprovalQuery::getRequestSummary($requestId);
            } catch (Throwable) {
                // biarkan null — pesan konfirmasi tetap dikirim tanpa detail
            }
            self::$cache[$key] = $summary;
        }

        return self::$cache[$key];
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
     * Jenis cuti lengkap (name + code) — cache per id.
     * Dipakai untuk menentukan apakah jenis cuti memakai saldo.
     */
    private static function leaveType(int $leaveTypeId): ?object
    {
        if ($leaveTypeId <= 0) {
            return null;
        }

        if (!array_key_exists("ltobj:$leaveTypeId", self::$cache)) {
            $type = null;
            try {
                $type = LeaveApprovalQuery::getLeaveType($leaveTypeId);
            } catch (Throwable) {
                // biarkan null — pemanggil menanganinya
            }
            self::$cache["ltobj:$leaveTypeId"] = $type;
        }

        return self::$cache["ltobj:$leaveTypeId"];
    }

    /**
     * Saldo cuti (NPK + jenis cuti + tahun) — cache per kombinasi.
     */
    private static function leaveBalance(string $npk, int $leaveTypeId, int $year): ?object
    {
        $key = "bal:$npk:$leaveTypeId:$year";

        if (!array_key_exists($key, self::$cache)) {
            $balance = null;
            try {
                $balance = LeaveApprovalQuery::getLeaveBalance($npk, $leaveTypeId, $year);
            } catch (Throwable) {
                // biarkan null
            }
            self::$cache[$key] = $balance;
        }

        return self::$cache[$key];
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
