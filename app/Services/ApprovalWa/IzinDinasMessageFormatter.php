<?php

namespace App\Services\ApprovalWa;

use App\Models\WorkAssignmentRequest;
use App\Services\WorkAssignment\EmployeeLookupService;
use Carbon\Carbon;
use Throwable;

/**
 * Format pesan WhatsApp untuk Izin Dinas (Surat Ijin Dinas Luar).
 *
 * Bentuk pesan sengaja mengikuti pesan cuti supaya approver tidak perlu
 * belajar dua gaya berbeda. Bedanya hanya isi: Izin Dinas punya beberapa
 * tanggal (multi-date), tujuan, jam keluar, dan rencana kembali.
 */
class IzinDinasMessageFormatter
{
    /** Cache nama karyawan per NPK dalam satu request/run. */
    private static array $nameCache = [];

    /**
     * Pesan permintaan persetujuan untuk satu level.
     *
     * @param  string  $approverName  nama approver yang menerima pesan
     * @param  int     $level         level yang sedang giliran
     * @param  int     $total         total level pada rantai
     */
    public static function formatApprovalRequest(
        WorkAssignmentRequest $request,
        string $approverName,
        int $level,
        int $total
    ): string {
        $id      = (int) $request->id;
        $npk     = (string) $request->npk;
        $pemohon = self::employeeName($npk);

        $tanggal = self::dutyDates($request);
        $durasi  = ((int) $request->total_days) . ' hari';
        $tujuan  = self::clean($request->dinas_ke) ?? '-';
        $perlu   = self::clean($request->keperluan) ?? '-';
        $keluar  = self::time($request->jam_keluar);
        $kembali = self::time($request->rencana_kembali);

        $levelLabel = $total > 0 ? "$level dari $total" : (string) $level;
        $levelLabel .= $total > 0 && $level === $total ? ' (level terakhir)' : '';

        $approveCmd = '/approve-izin-dinas' . $id;
        $rejectCmd  = '/tolak-izin-dinas' . $id . ' <alasan>';

        // Deep link WhatsApp: buka chat bot dengan teks command sudah terisi.
        // Nomor bot diambil dari config yang sama dengan pesan cuti, supaya
        // approver selalu melihat satu nomor bot saja di semua dokumen.
        $botNumber   = (string) config('services.wa_gateway.bot_number', '6287731665679');
        $approveLink = 'https://wa.me/' . $botNumber . '?text=' . urlencode($approveCmd);
        $rejectLink  = 'https://wa.me/' . $botNumber . '?text=' . urlencode($rejectCmd);

        return <<<MSG
        ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
        ┃ 📋 *IZIN DINAS — BUTUH PERSETUJUAN*
        ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

        *DATA PEMOHON*
        👤 $pemohon
        🆔 $npk

        *DETAIL PERJALANAN*
        🎯 Dinas ke: $tujuan
        📝 Keperluan: $perlu
        🗓️ Tanggal: $tanggal
        ⏳ Durasi: $durasi
        🕗 Jam keluar: $keluar
        🔁 Rencana kembali: $kembali

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
     * Konfirmasi setelah keputusan approver diproses.
     */
    public static function formatConfirmation(
        WorkAssignmentRequest $request,
        string $decision,
        string $approverName
    ): string {
        $status    = strtolower($decision) === 'approve' ? '✅ DISETUJUI' : '❌ DITOLAK';
        $timestamp = now()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i');

        $id      = (int) $request->id;
        $npk     = (string) $request->npk;
        $pemohon = self::employeeName($npk);

        $tanggal = self::dutyDates($request);
        $durasi  = ((int) $request->total_days) . ' hari';
        $tujuan  = self::clean($request->dinas_ke) ?? '-';

        return <<<MSG
        ✔️ *Konfirmasi Keputusan*

        Pengajuan *Izin Dinas #$id* telah *$status*.
        Oleh: $approverName
        Waktu: $timestamp WIB

        *DATA PEMOHON*
        👤 $pemohon
        🆔 $npk

        *DETAIL PERJALANAN*
        🎯 Dinas ke: $tujuan
        🗓️ Tanggal: $tanggal
        ⏳ Durasi: $durasi

        Terima kasih.
        MSG;
    }

    /**
     * Pesan penolakan / gagal proses.
     */
    public static function formatUnauthorized(int $requestId, string $reason): string
    {
        return "⛔ Gagal memproses *Izin Dinas #$requestId*.\n\nAlasan: $reason";
    }

    /**
     * Pesan untuk command yang tidak didukung pada Izin Dinas.
     */
    public static function formatUnsupported(int $requestId, string $what): string
    {
        return "⚠️ *$what* tidak tersedia untuk Izin Dinas #$requestId.\n\n"
            . "Izin Dinas tidak memakai saldo, jadi keputusan yang sudah masuk tidak bisa dibatalkan.\n"
            . "Kalau ada kekeliruan, hubungi HR untuk penyesuaian.";
    }

    // ─────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────

    /**
     * Nama karyawan dari master (sumber sama dengan layar approval Izin Dinas).
     */
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

    /**
     * Daftar tanggal dinas. Kalau lebih dari tiga, dipotong supaya pesan
     * tidak memanjang — jumlah total tetap disebutkan.
     */
    private static function dutyDates(WorkAssignmentRequest $request): string
    {
        $dates = $request->dates();

        if (empty($dates)) {
            $start = self::date($request->start_date);
            $end   = self::date($request->end_date);

            return $start === $end ? $start : "$start — $end";
        }

        $total = count($dates);
        $shown = array_slice($dates, 0, 3);
        $text  = implode(', ', array_map(fn ($d) => self::date($d), $shown));

        if ($total > 3) {
            $text .= ' … (+' . ($total - 3) . ' tanggal lain)';
        }

        return $text;
    }

    /** Format tanggal d/m/Y. */
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

    /** Format jam HH:MM dari kolom time/datetime. */
    private static function time(mixed $value): string
    {
        $value = self::clean(is_string($value) ? $value : null);

        if ($value === null) {
            return '-';
        }

        return substr($value, 0, 5);
    }

    /** Rapikan teks bebas. */
    private static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/\s+/u', ' ', trim($value));

        return ($clean === '' || $clean === null) ? null : $clean;
    }
}