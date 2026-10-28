<?php

namespace App\Services\RecruitmentAi;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien RapidOCR — memanggil server OCR lokal untuk membaca teks dari gambar.
 *
 * Digunakan sebagai pre-processing: teks yang terbaca ditambahkan ke prompt AI
 * sebagai konteks, sehingga model vision bisa mencocokkan apa yang "terlihat"
 * di gambar dengan teks mentah OCR — akurasi naik terutama untuk scan KTP/KK
 * yang resolusinya rendah atau blur.
 *
 * Server RapidOCR menyediakan dua endpoint:
 *   POST /ocr       — multipart (image_file)
 *   POST /ocr/json  — JSON body (image_data = base64 / data URI)
 *
 * Kita pakai /ocr/json supaya tidak perlu kirim file multipart dari PHP.
 */
class RapidOcrClient
{
    /** Batas waktu respons (detik) */
    private int $timeout;

    /** Base URL server RapidOCR (tanpa trailing slash) */
    private string $baseUrl;

    /** Apakah fitur OCR pre-processing aktif */
    private bool $enabled;

    public function __construct()
    {
        $config        = config('recruitment_ai.rapidocr', []);
        $this->baseUrl = rtrim($config['base_url'] ?? 'http://192.168.0.68:9003', '/');
        $this->timeout = (int) ($config['timeout'] ?? 30);
        $this->enabled = (bool) ($config['enabled'] ?? true);
    }

    /**
     * Apakah RapidOCR aktif dan bisa dipakai.
     */
    public function isEnabled(): bool
    {
        return $this->enabled && $this->baseUrl !== '';
    }

    /**
     * Baca teks dari file gambar.
     *
     * @param  string  $absolutePath  path absolut file gambar di disk
     * @return string  teks yang terbaca (kosong bila gagal / tidak ada teks)
     */
    public function extractText(string $absolutePath): string
    {
        if (! $this->isEnabled()) {
            return '';
        }

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return '';
        }

        try {
            $binary  = file_get_contents($absolutePath);
            $base64  = base64_encode((string) $binary);
            $mime    = $this->detectMime($absolutePath);

            // Kirim sebagai data URI supaya server bisa mengenali formatnya.
            $dataUri = 'data:' . $mime . ';base64,' . $base64;

            $response = Http::timeout($this->timeout)
                ->connectTimeout(10)
                ->post($this->baseUrl . '/ocr/json', [
                    'image_data' => $dataUri,
                ]);

            if ($response->failed()) {
                Log::warning('[RapidOCR] Request gagal: HTTP ' . $response->status());

                return '';
            }

            $body = $response->json();
            $data = $body['data'] ?? $body['results'] ?? [];

            // data bisa berupa array berindeks numerik maupun string ("0", "1", ...)
            if (is_array($data)) {
                $data = array_values($data);
            } else {
                $data = [];
            }

            return $this->formatResults($data);
        } catch (\Throwable $e) {
            Log::warning('[RapidOCR] Gagal memanggil server OCR: ' . $e->getMessage());

            return '';
        }
    }

    /**
     * Baca teks dari binary gambar (tanpa menyimpan ke disk).
     *
     * @param  string  $binary   binary content gambar
     * @param  string  $mime     MIME type (mis. image/jpeg)
     * @return string  teks yang terbaca
     */
    public function extractTextFromBinary(string $binary, string $mime = 'image/jpeg'): string
    {
        if (! $this->isEnabled()) {
            return '';
        }

        try {
            $dataUri = 'data:' . $mime . ';base64,' . base64_encode($binary);

            $response = Http::timeout($this->timeout)
                ->connectTimeout(10)
                ->post($this->baseUrl . '/ocr/json', [
                    'image_data' => $dataUri,
                ]);

            if ($response->failed()) {
                Log::warning('[RapidOCR] Request gagal: HTTP ' . $response->status());

                return '';
            }

            $body = $response->json();
            $data = $body['data'] ?? $body['results'] ?? [];

            if (is_array($data)) {
                $data = array_values($data);
            } else {
                $data = [];
            }

            return $this->formatResults($data);
        } catch (\Throwable $e) {
            Log::warning('[RapidOCR] Gagal memanggil server OCR (binary): ' . $e->getMessage());

            return '';
        }
    }

    /**
     * Cek apakah server RapidOCR bisa dihubungi.
     */
    public function healthCheck(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->get($this->baseUrl . '/health');

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /* ---------------------------------------------------------------------
     | Helper
     |---------------------------------------------------------------------*/

    /**
     * Gabungkan hasil OCR menjadi teks berurutan.
     *
     * Hasil RapidOCR berupa array baris yang masing-masing punya:
     *   dt_boxes / box : koordinat kotak teks (polygon 4 titik [[x,y], ...])
     *   rec_txt / text : teks yang terbaca
     *   score / confidence : skor kepercayaan
     *
     * Baris diurutkan berdasar posisi vertikal (Y) lalu horizontal (X)
     * supaya urutan bacanya natural (kiri-ke-kanan, atas-ke-bawah).
     */
    private function formatResults(array $results): string
    {
        if (empty($results)) {
            return '';
        }

        // Setiap item punya dt_boxes/box, rec_txt/text, score/confidence.
        // Urutkan berdasar posisi vertikal (Y koordinat pertama) lalu horizontal.
        usort($results, function (array $a, array $b) {
            $boxesA = $a['dt_boxes'] ?? $a['box'] ?? [];
            $boxesB = $b['dt_boxes'] ?? $b['box'] ?? [];

            $ay = $boxesA[0][1] ?? 0;
            $by = $boxesB[0][1] ?? 0;

            // Toleransi baris yang sama (~15px)
            if (abs($ay - $by) < 15) {
                $ax = $boxesA[0][0] ?? 0;
                $bx = $boxesB[0][0] ?? 0;

                return $ax <=> $bx;
            }

            return $ay <=> $by;
        });

        $lines = [];
        $lastY = null;

        foreach ($results as $item) {
            $text = trim((string) ($item['rec_txt'] ?? $item['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $boxes = $item['dt_boxes'] ?? $item['box'] ?? [];
            $currentY = $boxes[0][1] ?? 0;

            // Gabungkan teks yang berada di baris yang sama (dalam toleransi 15px).
            if ($lastY !== null && abs($currentY - $lastY) < 15) {
                $lines[count($lines) - 1] .= '  ' . $text;
            } else {
                $lines[] = $text;
            }

            $lastY = $currentY;
        }

        return implode("\n", $lines);
    }

    /**
     * Deteksi MIME type sederhana berdasar magic bytes atau ekstensi.
     */
    private function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $path);
            finfo_close($finfo);

            if (is_string($mime) && str_starts_with($mime, 'image/')) {
                return $mime;
            }
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'bmp'         => 'image/bmp',
            default       => 'image/jpeg',
        };
    }
}
