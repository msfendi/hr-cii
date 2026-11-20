<?php

namespace App\Services\WorkAssignment;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Kelola lampiran Work Assignment Request.
 *
 * File disimpan di disk `public` dengan path relatif:
 *   work-assignment/{npk}/{32-hex}.{ext}
 *
 * Path relatif disimpan di kolom JSON `attach_files` supaya aman dipindah
 * server (bukan absolut) dan bisa di-resolve ulang via Storage.
 */
class WorkAssignmentAttachment
{
    /** Disk tempat file disimpan. */
    public const DISK = 'public';

    /** Folder root di dalam disk. */
    public const FOLDER = 'work-assignment';

    /** MIME yang boleh diunggah. */
    public const ALLOWED = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];

    /** Ukuran maksimum per file (KB). */
    public const MAX_KB = 5120;

    /**
     * Simpan banyak file; kembalikan array path relatif.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, string>
     */
    public static function store(array $files, string $npk): array
    {
        $paths = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');

            $path = $file->storeAs(
                self::FOLDER . '/' . self::sanitizeNpk($npk),
                Str::random(32) . '.' . $ext,
                self::DISK
            );

            if ($path) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * Simpan banyak file + nama asli; kembalikan array descriptor.
     *
     * Nama file di disk tetap random (aman), tapi nama asli disimpan di
     * `original_name` supaya admin tetap mengenali lampirannya.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{path:string, original_name:string, mime:string, size:int}>
     */
    public static function storeWithNames(array $files, string $npk): array
    {
        $out = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');

            $path = $file->storeAs(
                self::FOLDER . '/' . self::sanitizeNpk($npk),
                Str::random(32) . '.' . $ext,
                self::DISK
            );

            if (!$path) {
                continue;
            }

            $out[] = [
                'path'          => $path,
                'original_name' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
                'mime'          => (string) $file->getClientMimeType(),
                'size'          => $file->getSize() ?: 0,
            ];
        }

        return $out;
    }

    /**
     * Ambil path dari satu entri lampiran (string atau descriptor).
     *
     * @param  mixed  $entry
     */
    public static function pathOf($entry): ?string
    {
        if (is_string($entry)) {
            return $entry !== '' ? $entry : null;
        }

        if (is_array($entry) && !empty($entry['path'])) {
            return (string) $entry['path'];
        }

        return null;
    }

    /**
     * Format list lampiran → array info untuk UI (nama, ext, url, ikon).
     *
     * Menerima dua bentuk entri:
     *   - string path            (lama)
     *   - {path, original_name}  (baru, nama asli disimpan)
     *
     * @param  array<int, mixed>|null  $files
     * @return array<int, array<string, mixed>>
     */
    public static function format(?array $files): array
    {
        if (empty($files)) {
            return [];
        }

        $out = [];

        foreach ($files as $entry) {
            $path = self::pathOf($entry);

            if ($path === null) {
                continue;
            }

            $ext      = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $original = is_array($entry) ? (string) ($entry['original_name'] ?? '') : '';

            $out[] = [
                'path'     => $path,
                'name'     => $original !== '' ? $original : basename($path),
                'ext'      => $ext,
                'url'      => Storage::disk(self::DISK)->url($path),
                'size'     => self::humanSize($path),
                'is_image' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true),
                'is_pdf'   => $ext === 'pdf',
                'is_word'  => in_array($ext, ['doc', 'docx'], true),
                'is_sheet' => in_array($ext, ['xls', 'xlsx'], true),
                'icon'     => self::iconFor($ext),
                'exists'   => Storage::disk(self::DISK)->exists($path),
            ];
        }

        return $out;
    }

    /**
     * Hapus satu file dari disk + kembalikan daftar entri yang tersisa.
     *
     * @param  array<int, mixed>  $files
     * @return array<int, mixed>
     */
    public static function delete(array $files, string $target): array
    {
        $remaining = [];

        foreach ($files as $entry) {
            $path = self::pathOf($entry);

            if ($path !== null && $path === $target) {
                Storage::disk(self::DISK)->delete($path);
                continue;
            }

            $remaining[] = $entry;
        }

        return array_values($remaining);
    }

    /**
     * Ikon FontAwesome sesuai ekstensi.
     */
    public static function iconFor(string $ext): string
    {
        return match ($ext) {
            'pdf'              => 'fa-file-pdf',
            'jpg', 'jpeg', 'png', 'webp' => 'fa-file-image',
            'doc', 'docx'      => 'fa-file-word',
            'xls', 'xlsx'      => 'fa-file-excel',
            default            => 'fa-file',
        };
    }

    /**
     * Ukuran file dalam format manusia.
     */
    private static function humanSize(string $path): string
    {
        $disk = Storage::disk(self::DISK);

        if (!$disk->exists($path)) {
            return '-';
        }

        $bytes = $disk->size($path);

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }

    /**
     * Bersihkan NPK supaya aman dipakai sebagai nama folder.
     */
    private static function sanitizeNpk(string $npk): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_-]/', '', trim($npk));

        return $clean !== '' ? $clean : 'umum';
    }
}
