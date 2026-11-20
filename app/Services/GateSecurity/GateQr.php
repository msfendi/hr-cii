<?php

namespace App\Services\GateSecurity;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Throwable;

/**
 * QR untuk dokumen gate. Isi QR: `GATE:<doctype>:<id>:<token>`.
 *
 * Token ikut dibawa supaya kode tidak bisa dipalsukan dengan menebak nomor
 * urut. Doctype di dalam payload membuat satu halaman Security bisa melayani
 * beberapa jenis dokumen tanpa menebak-nebak.
 *
 * Kompatibilitas: QR lama berbentuk `WA:<id>:<token>` (WA = Work Assignment,
 * sebelum ada doctype) tetap dibaca dan dipetakan ke 'izin-dinas'. QR yang
 * sudah dibuka/dicetak karyawan tidak boleh langsung hangus.
 *
 * Dirender sebagai SVG: server ini tidak punya ext-imagick (PNG butuh itu),
 * sedangkan SVG jalan murni di PHP dan tetap tajam di layar maupun cetak.
 */
class GateQr
{
    /** Doctype yang dipakai QR lama (WA = Work Assignment). */
    public const LEGACY_DOC = 'izin-dinas';

    /** Awalan payload QR lama — hanya untuk dibaca, tidak ditulis lagi. */
    private const LEGACY_PREFIX = 'WA';

    /** Bangun payload QR. */
    public static function payload(string $docType, int $id, string $token): string
    {
        return 'GATE:' . $docType . ':' . $id . ':' . $token;
    }

    /** Payload QR untuk sebuah dokumen. */
    public static function payloadFor(GateDocument $document): string
    {
        return self::payload($document::docType(), $document->id(), $document->token());
    }

    /**
     * Baca isi QR hasil scan.
     *
     * Bentuk yang diterima:
     *   GATE:<doctype>:<id>:<token>   ← format sekarang
     *   WA:<id>:<token>               ← format lama (dianggap izin-dinas)
     *
     * @return array{doc:string, id:int, token:?string}|null  null kalau tidak dikenali
     */
    public static function parse(string $payload): ?array
    {
        $payload = trim($payload);

        if ($payload === '') {
            return null;
        }

        $parts = explode(':', $payload);

        // Format sekarang: GATE:<doctype>:<id>:<token>
        if (count($parts) >= 4 && strtoupper($parts[0]) === 'GATE') {
            $id = (int) $parts[2];

            if ($id <= 0 || trim($parts[1]) === '') {
                return null;
            }

            return [
                'doc'   => strtolower(trim($parts[1])),
                'id'    => $id,
                'token' => $parts[3] !== '' ? $parts[3] : null,
            ];
        }

        // Format lama: WA:<id>:<token>
        if (count($parts) >= 3 && strtoupper($parts[0]) === self::LEGACY_PREFIX) {
            $id = (int) $parts[1];

            if ($id <= 0) {
                return null;
            }

            return [
                'doc'   => self::LEGACY_DOC,
                'id'    => $id,
                'token' => $parts[2] !== '' ? $parts[2] : null,
            ];
        }

        return null;
    }

    /**
     * Render QR sebagai string SVG.
     *
     * @param  int  $size  sisi QR dalam px
     */
    public static function svg(string $payload, int $size = 260): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($payload);
    }

    /**
     * Render QR sebagai data URI, siap dipakai di atribut src / CSS.
     */
    public static function dataUri(string $payload, int $size = 260): ?string
    {
        try {
            return 'data:image/svg+xml;base64,' . base64_encode(self::svg($payload, $size));
        } catch (Throwable) {
            return null;
        }
    }
}