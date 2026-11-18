<?php

namespace App\GateSecurity;

use App\Services\WorkAssignment\WorkAssignmentGateAdapter;

/**
 * Daftar jenis dokumen yang dilayani halaman Security Gate.
 *
 * Ini satu-satunya tempat yang tahu dokumen apa saja yang didukung. Menambah
 * dokumen baru cukup menambahkan baris di sini + satu kelas adapter.
 */
class GateRegistry
{
    /**
     * doctype → kelas adapter (implements GateDocument)
     *
     * @var array<string, class-string<GateDocument>>
     */
    private const DOCUMENTS = [
        WorkAssignmentGateAdapter::DOC_TYPE => WorkAssignmentGateAdapter::class,
    ];

    /**
     * Cari dokumen dari hasil scan QR.
     *
     * @return array{ok:bool, document?:GateDocument, message?:string}
     */
    public static function resolve(string $docType, int $id, ?string $token): array
    {
        $class = self::DOCUMENTS[$docType] ?? null;

        if ($class === null) {
            return ['ok' => false, 'message' => "Jenis dokumen \"{$docType}\" belum didukung."];
        }

        $document = $class::find($id, $token);

        if ($document === null) {
            return [
                'ok'      => false,
                'message' => 'Pengajuan tidak ditemukan atau kode QR tidak cocok.',
            ];
        }

        return ['ok' => true, 'document' => $document];
    }

    /**
     * Resolusi langsung dari payload QR mentah.
     *
     * @return array{ok:bool, document?:GateDocument, message?:string}
     */
    public static function resolvePayload(string $payload): array
    {
        $parsed = GateQr::parse($payload);

        if ($parsed === null) {
            return ['ok' => false, 'message' => 'Kode QR tidak dikenali.'];
        }

        return self::resolve($parsed['doc'], $parsed['id'], $parsed['token']);
    }

    /** Label dokumen dari doctype, untuk pesan/log. */
    public static function label(string $docType): string
    {
        $class = self::DOCUMENTS[$docType] ?? null;

        return $class !== null ? $class::label() : $docType;
    }

    /** Daftar doctype yang didukung. */
    public static function supported(): array
    {
        return array_keys(self::DOCUMENTS);
    }
}