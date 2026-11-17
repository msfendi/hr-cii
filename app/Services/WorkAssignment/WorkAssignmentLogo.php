<?php

namespace App\Services\WorkAssignment;

use Illuminate\Support\Facades\Log;

/**
 * Siapkan logo untuk PDF Surat Ijin Dinas Luar.
 *
 * dompdf tidak mendukung SVG dan embedding PNG 73KB membuat PDF berat.
 * Jadi logo di-resize (GD) ke lebar ~120px lalu di-cache di storage.
 */
class WorkAssignmentLogo
{
    /** Lebar logo (px) di PDF. */
    private const WIDTH = 120;

    /**
     * Path file logo yang sudah di-resize (di-cache).
     *
     * @return string|null  path absolut, atau null kalau logo tak bisa disiapkan
     */
    public static function path(): ?string
    {
        $cached = storage_path('app/work-assignment/logo.png');

        if (is_file($cached)) {
            return $cached;
        }

        $source = public_path('img/chutex_logo.png');

        if (!is_file($source)) {
            return null;
        }

        if (!function_exists('imagecreatefrompng')) {
            // GD tidak ada — pakai file asli (PDF jadi lebih besar, tapi jalan).
            return $source;
        }

        try {
            $info = @getimagesize($source);

            if (!$info) {
                return $source;
            }

            $w = self::WIDTH;
            $h = (int) round($info[1] * ($w / max(1, $info[0])));

            $target = imagecreatetruecolor($w, $h);
            imagealphablending($target, false);
            imagesavealpha($target, true);

            $transparent = imagecolorallocatealpha($target, 255, 255, 255, 127);
            imagefill($target, 0, 0, $transparent);

            $src = imagecreatefrompng($source);

            if (!$src) {
                imagedestroy($target);

                return $source;
            }

            imagecopyresampled($target, $src, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);

            if (!is_dir(dirname($cached))) {
                @mkdir(dirname($cached), 0755, true);
            }

            imagepng($target, $cached);

            imagedestroy($target);
            imagedestroy($src);

            return $cached;
        } catch (\Throwable $e) {
            Log::warning('[WorkAssignment] gagal resize logo: ' . $e->getMessage());

            return $source;
        }
    }
}
