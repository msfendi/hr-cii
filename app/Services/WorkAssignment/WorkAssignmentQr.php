<?php

namespace App\Services\WorkAssignment;

use App\Models\WorkAssignmentRequest;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Throwable;

/**
 * QR code untuk pengajuan Work Assignment.
 *
 * Isi QR: `WA:<id>:<token>` — id saja tidak cukup karena mudah ditebak,
 * jadi token pengajuan ikut dibawa supaya kode tak bisa dipalsukan dengan
 * menebak nomor urut.
 *
 * Dirender sebagai SVG: server ini tidak punya ext-imagick (PNG butuh itu),
 * sedangkan SVG jalan murni di PHP dan tetap tajam di layar maupun cetak.
 */
class WorkAssignmentQr
{
    /** Bangun payload QR untuk satu pengajuan. */
    public static function payload(WorkAssignmentRequest $request): string
    {
        return 'WA:' . $request->id . ':' . (string) $request->token;
    }

    /**
     * Render QR sebagai string SVG.
     *
     * @param  int  $size  sisi QR dalam px
     */
    public static function svg(WorkAssignmentRequest $request, int $size = 260): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString(self::payload($request));
    }

    /**
     * Render QR sebagai data URI, siap dipakai di atribut src / CSS.
     */
    public static function dataUri(WorkAssignmentRequest $request, int $size = 260): ?string
    {
        try {
            return 'data:image/svg+xml;base64,' . base64_encode(self::svg($request, $size));
        } catch (Throwable) {
            return null;
        }
    }
}