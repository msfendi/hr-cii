<?php

namespace App\Services\ApprovalWa;

/**
 * Parser command approval dari pesan WhatsApp.
 *
 * Command dikelompokkan per jenis dokumen (doctype), supaya nomor pengajuan
 * dari dokumen berbeda tidak pernah tertukar:
 *
 *   /approve-cuti909              → cuti, id 909
 *   /approve-izin-dinas10009      → izin dinas, id 10009
 *   /tolak-izin-dinas10009 <alasan>
 *
 * Format lama tanpa doctype tetap diterima dan dianggap CUTI, karena pesan
 * yang sudah terkirim ke approver memuat `/approve 909` — membuangnya berarti
 * approver yang membuka chat lama tiba-tiba gagal.
 *
 * Spasi antara doctype dan nomor opsional: orang mengetik tidak konsisten.
 */
class ApprovalCommandParser
{
    public const DOC_CUTI               = 'cuti';
    public const DOC_IZIN_DINAS         = 'izin-dinas';
    public const DOC_IZIN_MENINGGALKAN  = 'izin-meninggalkan';

    /** Label tampilan per doctype. */
    public const DOC_LABELS = [
        self::DOC_CUTI              => 'Cuti',
        self::DOC_IZIN_DINAS        => 'Izin Dinas',
        self::DOC_IZIN_MENINGGALKAN => 'Izin Meninggalkan',
    ];

    /**
     * Parse satu pesan approver.
     *
     * @return array{doc:string, decision:string, id:int, reason:?string}|null
     *         null kalau format tidak dikenali
     */
    public static function parse(string $message): ?array
    {
        $message = trim($message);

        if ($message === '') {
            return null;
        }

        $docPattern = '(' . self::DOC_CUTI . '|' . self::DOC_IZIN_DINAS . '|' . self::DOC_IZIN_MENINGGALKAN . ')';

        // ── APPROVE ──
        // /approve-cuti909, /approve-izin-dinas 10009, APPROVE-IZIN-DINAS#10009
        if (preg_match('/^(?:APPROVE|\/approve)-' . $docPattern . '\s*#?(\d+)$/i', $message, $m)) {
            return [
                'doc'      => self::normalizeDoc($m[1]),
                'decision' => 'approve',
                'id'       => (int) $m[2],
                'reason'   => null,
            ];
        }

        // ── REJECT (alasan opsional) ──
        if (preg_match('/^(?:REJECT|\/tolak)-' . $docPattern . '\s*#?(\d+)(?:\s+(.+))?$/i', $message, $m)) {
            return [
                'doc'      => self::normalizeDoc($m[1]),
                'decision' => 'reject',
                'id'       => (int) $m[2],
                'reason'   => isset($m[3]) ? trim($m[3]) : null,
            ];
        }

        // ── BATAL / REVERT (alasan opsional) ──
        // Hanya untuk cuti: izin dinas tidak memakai saldo, jadi tidak ada
        // yang perlu dikembalikan saat keputusan dibatalkan.
        if (preg_match('/^(?:BATAL|\/batal)-(cuti)\s*#?(\d+)(?:\s+(.+))?$/i', $message, $m)) {
            return [
                'doc'      => self::DOC_CUTI,
                'decision' => 'revert',
                'id'       => (int) $m[2],
                'reason'   => isset($m[3]) && trim($m[3]) !== '' ? trim($m[3]) : 'Dibatalkan via WhatsApp',
            ];
        }

        // ── Legacy tanpa doctype → cuti ──
        if (preg_match('/^(?:APPROVE|\/approve)\s+#?(\d+)$/i', $message, $m)) {
            return [
                'doc'      => self::DOC_CUTI,
                'decision' => 'approve',
                'id'       => (int) $m[1],
                'reason'   => null,
            ];
        }

        if (preg_match('/^(?:REJECT|\/tolak)\s+#?(\d+)(?:\s+(.+))?$/i', $message, $m)) {
            return [
                'doc'      => self::DOC_CUTI,
                'decision' => 'reject',
                'id'       => (int) $m[1],
                'reason'   => isset($m[2]) ? trim($m[2]) : null,
            ];
        }

        if (preg_match('/^(?:BATAL|\/batal)\s+#?(\d+)(?:\s+(.+))?$/i', $message, $m)) {
            return [
                'doc'      => self::DOC_CUTI,
                'decision' => 'revert',
                'id'       => (int) $m[1],
                'reason'   => isset($m[2]) && trim($m[2]) !== '' ? trim($m[2]) : 'Dibatalkan via WhatsApp',
            ];
        }

        return null;
    }

    /**
     * Apakah pesan ini berupa command approval (format apa pun yang dikenali)?
     */
    public static function looksLikeCommand(string $message): bool
    {
        return self::parse($message) !== null;
    }

    /** Label dokumen untuk ditampilkan di pesan. */
    public static function label(string $doc): string
    {
        return self::DOC_LABELS[$doc] ?? $doc;
    }

    /**
     * Teks bantuan format command.
     *
     * Disusun per doctype supaya approver tahu persis mana yang harus dipakai
     * untuk pengajuan yang sedang ia tangani.
     */
    public static function helpText(): string
    {
        return "⚠️ Format tidak dikenali.\n\n"
            . "Gunakan (sesuaikan jenis dokumennya):\n"
            . "• /approve-cuti<ID>\n"
            . "• /tolak-cuti<ID> <alasan>\n"
            . "• /batal-cuti<ID> <alasan>\n"
            . "• /approve-izin-dinas<ID>\n"
            . "• /tolak-izin-dinas<ID> <alasan>\n"
            . "• /approve-izin-meninggalkan<ID>\n"
            . "• /tolak-izin-meninggalkan<ID> <alasan>\n\n"
            . "Contoh: /approve-izin-dinas10009";
    }

    /**
     * Samakan penulisan doctype (izin-dinas vs IZIN DINAS) ke bentuk kanonik.
     */
    private static function normalizeDoc(string $doc): string
    {
        $doc = strtolower(str_replace([' ', '_'], '-', trim($doc)));

        return match ($doc) {
            self::DOC_IZIN_DINAS        => self::DOC_IZIN_DINAS,
            self::DOC_IZIN_MENINGGALKAN => self::DOC_IZIN_MENINGGALKAN,
            default                     => self::DOC_CUTI,
        };
    }
}