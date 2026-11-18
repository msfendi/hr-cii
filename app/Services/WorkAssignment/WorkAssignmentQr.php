<?php

namespace App\Services\WorkAssignment;

use App\GateSecurity\GateQr;
use App\Models\WorkAssignmentRequest;

/**
 * QR pengajuan Izin Dinas untuk discan petugas di pos security.
 *
 * Isi dan render QR-nya diserahkan ke GateQr supaya semua dokumen memakai
 * format payload yang sama (`GATE:<doctype>:<id>:<token>`). Sebelumnya di sini
 * dipakai awalan `WA:` yang berarti Work Assignment — padahal di folder yang
 * sama `Wa` juga dipakai untuk WhatsApp, dan awalan itu tidak menyebut jenis
 * dokumen sehingga tidak bisa dipakai dokumen lain.
 */
class WorkAssignmentQr
{
    /** Doctype dokumen ini, sesuai GateRegistry. */
    private const DOC_TYPE = WorkAssignmentGateAdapter::DOC_TYPE;

    /** Payload QR untuk satu pengajuan. */
    public static function payload(WorkAssignmentRequest $request): string
    {
        return GateQr::payload(self::DOC_TYPE, (int) $request->id, (string) $request->token);
    }

    /** Render QR sebagai string SVG. */
    public static function svg(WorkAssignmentRequest $request, int $size = 260): string
    {
        return GateQr::svg(self::payload($request), $size);
    }

    /** Render QR sebagai data URI, siap dipakai di atribut src / CSS. */
    public static function dataUri(WorkAssignmentRequest $request, int $size = 260): ?string
    {
        return GateQr::dataUri(self::payload($request), $size);
    }
}