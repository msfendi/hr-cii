<?php

namespace App\Services\RecruitmentAi;

use Illuminate\Support\Facades\Log;

/**
 * Normalisasi dan penyesuaian orientasi gambar dokumen rekrutmen.
 *
 * Mengatasi masalah umum pada RapidOCR ketika foto diambil via HP:
 *  1. Koreksi tag EXIF Orientation (rotasi sensor kamera HP).
 *  2. Penyesuaian aspek rasio dokumen (KTP & KK selalu landscape; jika
 *     diunggah portrait, langsung diputar 90°/270° sebelum diproses OCR).
 *  3. Pemulihan sudut alternatif (180° flip) jika hasil OCR awal kosong/tidak terbaca.
 */
class DocumentImageOrienter
{
    /** Dokumen yang secara fisik SELALU Landscape (lebar > tinggi). */
    private const LANDSCAPE_DOCUMENTS = [
        'scan_ktp',
        'scan_kk',
    ];

    /** Dokumen yang secara fisik umumnya Portrait (tinggi > lebar). */
    private const PORTRAIT_DOCUMENTS = [
        'scan_skck',
    ];

    /**
     * Sesuaikan orientasi gambar dokumen ke posisi tegak yang benar.
     *
     * @param  string  $path     Path absolut file di disk
     * @param  string|null  $document  Tipe dokumen (scan_ktp, scan_kk, dll)
     * @return bool True jika gambar mengalami rotasi dan disimpan ulang
     */
    public function orientImageForDocument(string $path, ?string $document = null): bool
    {
        if (! is_file($path) || ! is_readable($path) || ! is_writable($path)) {
            return false;
        }

        $mime = $this->detectMime($path);
        if (! str_starts_with($mime, 'image/')) {
            return false;
        }

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagerotate')) {
            $this->logWarning('[RecruitmentAi] Ekstensi GD tidak tersedia untuk memutar gambar.');
            return false;
        }

        $binary = (string) @file_get_contents($path);
        if ($binary === '') {
            return false;
        }

        $exifOrientation = $this->readExifOrientation($path, $binary);
        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return false;
        }

        $rotated = false;

        // 1. Koreksi orientasi EXIF (dari metadata kamera)
        if ($exifOrientation > 1) {
            $imageAfterExif = $this->applyExifOrientation($image, $exifOrientation);
            if ($imageAfterExif !== $image) {
                $image = $imageAfterExif;
                $rotated = true;
            }
        }

        // 2. Langsung putar berdasarkan tipe dokumen (KTP/KK harus landscape)
        if ($document !== null) {
            $imageAfterDoc = $this->applyDocumentAspectRotation($image, $document);
            if ($imageAfterDoc !== $image) {
                $image = $imageAfterDoc;
                $rotated = true;
            }
        }

        if ($rotated) {
            $this->saveImage($image, $path, $mime);
            $this->logInfo("[RecruitmentAi] Gambar '{$document}' berhasil disesuaikan orientasinya sebelum OCR: " . basename($path) . " ({$mime})");
        }

        imagedestroy($image);

        return $rotated;
    }

    /**
     * Putar gambar dengan sudut tertentu (dalam derajat: 90, 180, 270) lalu simpan ulang.
     */
    public function rotateDegrees(string $path, int $degrees): bool
    {
        if (! is_file($path) || ! is_readable($path) || ! is_writable($path)) {
            return false;
        }

        $mime = $this->detectMime($path);
        if (! str_starts_with($mime, 'image/')) {
            return false;
        }

        $binary = (string) @file_get_contents($path);
        $image  = @imagecreatefromstring($binary);
        if ($image === false) {
            return false;
        }

        // GD imagerotate menggunakan sudut counter-clockwise (CCW).
        // Untuk derajat positif (mis. 90 CW), sudut GD = 360 - degrees.
        $gdAngle = (360 - ($degrees % 360)) % 360;
        $rotatedImage = imagerotate($image, $gdAngle, 0);

        imagedestroy($image);

        if ($rotatedImage === false) {
            return false;
        }

        $this->saveImage($rotatedImage, $path, $mime);
        imagedestroy($rotatedImage);

        $this->logInfo("[RecruitmentAi] Gambar dirotasi {$degrees}°: " . basename($path));

        return true;
    }

    /**
     * Periksa apakah teks hasil OCR memiliki kata kunci yang valid untuk dokumen tersebut.
     */
    public function hasMeaningfulText(string $document, array $lines): bool
    {
        if (empty($lines)) {
            return false;
        }

        $allText = '';
        foreach ($lines as $line) {
            $allText .= ' ' . ($line['text'] ?? '');
        }

        $upper = strtoupper($allText);

        return match ($document) {
            'scan_ktp' => preg_match('/\b[1-9]\d{15}\b/', $allText) === 1
                || preg_match('/\b(NIK|PROVINSI|KARTU|PENDUDUK|REPUBLIK|TEMPAT\/TGL|GOL\.?\s*DARAH|JENIS\s*KELAMIN|AGAMA|STATUS\s*PERKAWINAN|PEKERJAAN)\b/u', $upper) === 1,

            'scan_kk' => preg_match('/\b[1-9]\d{15}\b/', $allText) === 1
                || preg_match('/\b(KARTU\s*KELUARGA|KEPALA\s*KELUARGA|NOMOR\s*KK|DESA\/KELURAHAN|KECAMATAN)\b/u', $upper) === 1,

            'scan_skck' => preg_match('/\b(SKCK|KEPOLISIAN|CATATAN\s*KEPOLISIAN|SURAT\s*KETERANGAN)\b/u', $upper) === 1,

            'ijazah' => preg_match('/\b(IJAZAH|SURAT\s*TANDA\s*TAMAT|SEKOLAH|UNIVERSITAS|KEMENTERIAN|FAKULTAS|SARJANA|DIPLOMA)\b/u', $upper) === 1,

            'scan_akta_kelahiran' => preg_match('/\b(AKTA|KELAHIRAN|CATATAN\s*SIPIL|TELAH\s*LAHIR)\b/u', $upper) === 1,

            default => count($lines) >= 3 && strlen(trim($allText)) >= 30,
        };
    }

    /**
     * Terapkan rotasi EXIF pada resource GD image.
     *
     * @param  \GdImage|resource  $image
     * @return \GdImage|resource
     */
    private function applyExifOrientation($image, int $orientation)
    {
        // Standar EXIF Orientation (1..8)
        // 1: Normal
        // 2: Flip horizontal
        // 3: 180°
        // 4: Flip vertical
        // 5: Transpose (flip horizontal + rotate 270 CCW)
        // 6: 90° CW (koreksi GD: 270 CCW)
        // 7: Transverse
        // 8: 90° CCW (koreksi GD: 90 CCW)
        return match ($orientation) {
            2 => $this->flipImage($image, IMG_FLIP_HORIZONTAL),
            3 => imagerotate($image, 180, 0) ?: $image,
            4 => $this->flipImage($image, IMG_FLIP_VERTICAL),
            5 => $this->rotateAndFlip($image, 90, IMG_FLIP_HORIZONTAL),
            6 => imagerotate($image, 270, 0) ?: $image,
            7 => $this->rotateAndFlip($image, 270, IMG_FLIP_HORIZONTAL),
            8 => imagerotate($image, 90, 0) ?: $image,
            default => $image,
        };
    }

    /**
     * Langsung sesuaikan orientasi berdasarkan karakteristik fisik dokumen:
     * - KTP & KK wajib Landscape (lebar > tinggi). Jika portrait, putar 90° CW (270 CCW di GD).
     * - SKCK wajib Portrait (tinggi > lebar). Jika landscape, putar 90° CW.
     *
     * @param  \GdImage|resource  $image
     * @return \GdImage|resource
     */
    private function applyDocumentAspectRotation($image, string $document)
    {
        $w = imagesx($image);
        $h = imagesy($image);

        if (in_array($document, self::LANDSCAPE_DOCUMENTS, true)) {
            // Jika dokumen landscape tetapi gambar berbentuk portrait (tinggi > lebar)
            if ($h > $w) {
                // Putar 90° Clockwise (270° di GD) agar menjadi landscape
                $rotated = imagerotate($image, 270, 0);
                if ($rotated !== false) {
                    imagedestroy($image);
                    return $rotated;
                }
            }
        } elseif (in_array($document, self::PORTRAIT_DOCUMENTS, true)) {
            // Jika dokumen portrait tetapi gambar berbentuk landscape (lebar > tinggi)
            if ($w > $h) {
                $rotated = imagerotate($image, 270, 0);
                if ($rotated !== false) {
                    imagedestroy($image);
                    return $rotated;
                }
            }
        }

        return $image;
    }

    /**
     * @param  \GdImage|resource  $image
     * @return \GdImage|resource
     */
    private function flipImage($image, int $mode)
    {
        if (function_exists('imageflip')) {
            imageflip($image, $mode);
        }

        return $image;
    }

    /**
     * @param  \GdImage|resource  $image
     * @return \GdImage|resource
     */
    private function rotateAndFlip($image, int $angle, int $mode)
    {
        $rotated = imagerotate($image, $angle, 0);
        if ($rotated !== false) {
            imagedestroy($image);
            $image = $rotated;
        }

        return $this->flipImage($image, $mode);
    }

    /**
     * Simpan kembali gambar ke disk dengan kualitas optimal.
     *
     * @param  \GdImage|resource  $image
     */
    private function saveImage($image, string $path, string $mime): void
    {
        match ($mime) {
            'image/png'  => imagepng($image, $path, 6),
            'image/webp' => imagewebp($image, $path, 90),
            default      => imagejpeg($image, $path, 92),
        };
    }

    /**
     * Baca nilai EXIF Orientation (didukung via php_exif atau parser biner internal).
     */
    public function readExifOrientation(string $path, string $binary): int
    {
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            if (is_array($exif) && ! empty($exif['Orientation'])) {
                return (int) $exif['Orientation'];
            }
        }

        // Parser mandiri khusus JPEG tanpa dependensi ekstensi exif
        if (! str_starts_with($binary, "\xFF\xD8")) {
            return 1;
        }

        $len = strlen($binary);
        $offset = 2;

        while ($offset + 4 <= $len) {
            if ($binary[$offset] !== "\xFF") {
                break;
            }

            $marker = ord($binary[$offset + 1]);
            // SOS (0xDA) atau EOI (0xD9) = berhenti memindai marker
            if ($marker === 0xDA || $marker === 0xD9) {
                break;
            }

            $lengthData = substr($binary, $offset + 2, 2);
            $segLen = unpack('n', $lengthData)[1];
            if ($segLen < 2 || $offset + 2 + $segLen > $len) {
                break;
            }

            // APP1 marker (Exif)
            if ($marker === 0xE1) {
                $app1 = substr($binary, $offset + 4, $segLen - 2);
                if (str_starts_with($app1, "Exif\x00\x00")) {
                    $tiff = substr($app1, 6);
                    $tiffLen = strlen($tiff);
                    if ($tiffLen >= 8) {
                        $isLe = substr($tiff, 0, 2) === 'II';
                        $unpackShort = $isLe ? 'v' : 'n';
                        $unpackLong  = $isLe ? 'V' : 'N';

                        $ifdOffset = unpack($unpackLong, substr($tiff, 4, 4))[1];
                        if ($ifdOffset + 2 <= $tiffLen) {
                            $entries = unpack($unpackShort, substr($tiff, $ifdOffset, 2))[1];
                            for ($i = 0; $i < $entries; $i++) {
                                $entryPos = $ifdOffset + 2 + ($i * 12);
                                if ($entryPos + 12 > $tiffLen) {
                                    break;
                                }
                                $tag = unpack($unpackShort, substr($tiff, $entryPos, 2))[1];
                                if ($tag === 0x0112) { // Orientation
                                    return (int) unpack($unpackShort, substr($tiff, $entryPos + 8, 2))[1];
                                }
                            }
                        }
                    }
                }
                break;
            }

            $offset += 2 + $segLen;
        }

        return 1;
    }

    /**
     * Deteksi MIME gambar.
     */
    private function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $path);
            finfo_close($finfo);

            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'bmp'         => 'image/bmp',
            default       => 'application/octet-stream',
        };
    }

    private function logInfo(string $message): void
    {
        try {
            Log::info($message);
        } catch (\Throwable $e) {
            // Abaikan jika dipanggil di luar konteks Laravel Facade
        }
    }

    private function logWarning(string $message): void
    {
        try {
            Log::warning($message);
        } catch (\Throwable $e) {
            // Abaikan jika dipanggil di luar konteks Laravel Facade
        }
    }
}
