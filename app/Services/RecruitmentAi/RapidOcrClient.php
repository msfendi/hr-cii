<?php

namespace App\Services\RecruitmentAi;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien RapidOCR — memanggil server OCR lokal untuk membaca teks dari gambar.
 *
 * Output utama adalah daftar baris (text + box + score). Teks gabungan dipakai
 * sebagai konteks prompt AI, sedangkan daftar baris dipakai parser deterministik
 * (KTP/KK/ijazah) supaya dokumen cetak tidak selalu bergantung pada model AI.
 *
 * Server RapidOCR umumnya menyediakan:
 *   POST /ocr       — multipart (image_file)
 *   POST /ocr/json  — JSON body (image_data = base64 / data URI)
 *
 * Path bisa diubah lewat RAPIDOCR_JSON_PATH / RAPIDOCR_HEALTH_PATH.
 */
class RapidOcrClient
{
    private int $timeout;

    private string $baseUrl;

    private string $jsonPath;

    private string $multipartPath;

    private string $multipartField;

    private string $format;

    private string $healthPath;

    private bool $enabled;

    /** Pesan error terakhir (untuk diagnostik / log). */
    private string $lastError = '';

    /** Baris terakhir yang berhasil dibaca. */
    private array $lastLines = [];

    public function __construct()
    {
        $config            = config('recruitment_ai.rapidocr', []);
        $this->baseUrl     = rtrim((string) ($config['base_url'] ?? ''), '/');
        $this->jsonPath    = (string) ($config['json_path'] ?? '/ocr/json');
        $this->multipartPath = (string) ($config['multipart_path'] ?? '/ocr');
        $this->multipartField = (string) ($config['multipart_field'] ?? 'image_file');
        $this->format      = strtolower((string) ($config['format'] ?? 'auto'));
        $this->healthPath  = (string) ($config['health_path'] ?? '/health');
        $this->timeout     = (int) ($config['timeout'] ?? 30);
        $this->enabled     = (bool) ($config['enabled'] ?? true);

        if (! in_array($this->format, ['json', 'multipart', 'auto'], true)) {
            $this->format = 'auto';
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->baseUrl !== '';
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function getLastLines(): array
    {
        return $this->lastLines;
    }

    /**
     * Baca baris OCR dari file gambar.
     *
     * @return array<int, array{text:string, box:array, x:float, y:float, score:float}>
     */
    public function extractLines(string $absolutePath): array
    {
        $this->lastError = '';

        if (! $this->isEnabled()) {
            $this->lastError = 'RapidOCR tidak aktif / URL kosong.';

            return [];
        }

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            $this->lastError = 'File tidak ditemukan / tidak bisa dibaca.';

            return [];
        }

        try {
            $binary   = (string) file_get_contents($absolutePath);
            $mime     = $this->detectMime($absolutePath);
            $dataUri  = 'data:' . $mime . ';base64,' . base64_encode($binary);

            return $this->request($dataUri, $absolutePath);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::warning('[RapidOCR] Gagal memanggil server OCR: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Baca teks gabungan dari file gambar.
     */
    public function extractText(string $absolutePath): string
    {
        return $this->linesToText($this->extractLines($absolutePath));
    }

    /**
     * Baca teks dari binary gambar (tanpa menyimpan ke disk).
     */
    public function extractTextFromBinary(string $binary, string $mime = 'image/jpeg'): string
    {
        $this->lastError = '';

        if (! $this->isEnabled()) {
            $this->lastError = 'RapidOCR tidak aktif / URL kosong.';

            return '';
        }

        try {
            $dataUri = 'data:' . $mime . ';base64,' . base64_encode($binary);

            return $this->linesToText($this->request($dataUri, null));
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
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
            $this->lastError = 'RapidOCR tidak aktif / URL kosong.';

            return false;
        }

        $baseUrls = $this->candidateBaseUrls();

        foreach ($baseUrls as $currentBase) {
            try {
                $url = rtrim($currentBase, '/') . '/' . ltrim($this->healthPath, '/');
                $response = Http::timeout(4)
                    ->connectTimeout(2)
                    ->get($url);

                if ($response->successful()) {
                    $this->lastError = '';
                    if ($currentBase !== $this->baseUrl) {
                        $this->baseUrl = $currentBase;
                    }

                    return true;
                }

                $this->lastError = 'HTTP ' . $response->status() . ' dari ' . $url;
            } catch (\Throwable $e) {
                $this->lastError = $e->getMessage();
            }
        }

        return false;
    }

    /**
     * Gabungkan baris OCR menjadi teks berurutan (kiri→kanan, atas→bawah).
     */
    public function linesToText(array $lines): string
    {
        if (empty($lines)) {
            return '';
        }

        usort($lines, function (array $a, array $b) {
            $ay = (float) ($a['y'] ?? 0);
            $by = (float) ($b['y'] ?? 0);

            if (abs($ay - $by) < 15) {
                return (float) ($a['x'] ?? 0) <=> (float) ($b['x'] ?? 0);
            }

            return $ay <=> $by;
        });

        $out = [];
        $lastY = null;

        foreach ($lines as $item) {
            $text = trim((string) ($item['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $y = (float) ($item['y'] ?? 0);

            if ($lastY !== null && abs($y - $lastY) < 15 && ! empty($out)) {
                $out[count($out) - 1] .= '  ' . $text;
            } else {
                $out[] = $text;
            }

            $lastY = $y;
        }

        return implode("\n", $out);
    }

    /**
     * Kembalikan daftar base URL yang akan dicoba.
     *
     * Bila URL tidak menyertakan port (mis. http://192.168.1.240),
     * tambahkan port default 9003 sebagai kandidat kedua.
     */
    private function candidateBaseUrls(): array
    {
        $urls = [$this->baseUrl];
        $parsed = parse_url($this->baseUrl);

        if (empty($parsed['port']) && ! empty($parsed['host'])) {
            $scheme = $parsed['scheme'] ?? 'http';
            $urls[] = $scheme . '://' . $parsed['host'] . ':9003';
        }

        return array_values(array_unique($urls));
    }

    /**
     * @return array<int, array{text:string, box:array, x:float, y:float, score:float}>
     */
    private function request(string $dataUri, ?string $filePath = null): array
    {
        $modes = match ($this->format) {
            'json'      => ['json'],
            'multipart' => ['multipart'],
            default     => ['json', 'multipart'],
        };

        $baseUrls = $this->candidateBaseUrls();
        $errors   = [];

        foreach ($baseUrls as $currentBase) {
            foreach ($modes as $mode) {
                $endpoint = $mode === 'json' ? $this->jsonPath : $this->multipartPath;
                $targetUrl = rtrim($currentBase, '/') . '/' . ltrim($endpoint, '/');

                try {
                    $response = $mode === 'json'
                        ? $this->postJsonToUrl($targetUrl, $dataUri)
                        : $this->postMultipartToUrl($targetUrl, $dataUri, $filePath);
                } catch (\Throwable $e) {
                    $errors[] = $e->getMessage() . ' (' . $targetUrl . ')';
                    continue;
                }

                if ($response->failed()) {
                    $errors[] = 'HTTP ' . $response->status() . ' dari ' . $targetUrl;
                    Log::warning('[RapidOCR] Request gagal: ' . end($errors));

                    continue;
                }

                $body  = $response->json();
                $items = $this->extractItems($body);

                if (empty($items)) {
                    $errors[] = 'Respons OCR kosong / format tidak dikenali dari ' . $targetUrl;
                    Log::warning('[RapidOCR] ' . end($errors) . ' Body: ' . substr((string) $response->body(), 0, 300));

                    continue;
                }

                $lines = $this->normalizeItems($items);

                $this->lastLines = $lines;
                $this->lastError = '';

                if ($currentBase !== $this->baseUrl) {
                    Log::info("[RapidOCR] Berhasil terhubung via port alternatif: {$currentBase}");
                    $this->baseUrl = $currentBase;
                }

                return $lines;
            }
        }

        $this->lastError = $errors
            ? implode(' | ', array_unique($errors))
            : 'Respons OCR kosong / format tidak dikenali.';

        if (str_contains($this->lastError, 'HTTP 404')) {
            $this->lastError .= '. Catatan: Pastikan port server RapidOCR sudah dicantumkan di .env (misalnya RAPIDOCR_URL=http://192.168.1.240:9003) dan endpoint sesuai.';
        }

        return [];
    }

    private function postJson(string $dataUri)
    {
        return $this->postJsonToUrl($this->url($this->jsonPath), $dataUri);
    }

    private function postJsonToUrl(string $targetUrl, string $dataUri)
    {
        return Http::timeout($this->timeout)
            ->connectTimeout(4)
            ->acceptJson()
            ->post($targetUrl, [
                'image_data' => $dataUri,
            ]);
    }

    private function postMultipart(string $dataUri, ?string $filePath)
    {
        return $this->postMultipartToUrl($this->url($this->multipartPath), $dataUri, $filePath);
    }

    private function postMultipartToUrl(string $targetUrl, string $dataUri, ?string $filePath)
    {
        $request = Http::timeout($this->timeout)
            ->connectTimeout(4)
            ->acceptJson();

        if ($filePath !== null && is_file($filePath)) {
            $request = $request->attach(
                $this->multipartField,
                (string) file_get_contents($filePath),
                basename($filePath)
            );
        } else {
            [$mime, $binary] = $this->decodeDataUri($dataUri);
            $ext = match ($mime) {
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'image/bmp'  => 'bmp',
                default      => 'jpg',
            };

            $request = $request->attach($this->multipartField, $binary, 'image.' . $ext);
        }

        return $request->post($targetUrl);
    }

    /**
     * @return array{0:string,1:string} [mime, binary]
     */
    private function decodeDataUri(string $dataUri): array
    {
        if (preg_match('#^data:([^;]+);base64,(.*)$#s', $dataUri, $m) === 1) {
            return [$m[1], base64_decode($m[2])];
        }

        return ['image/jpeg', base64_decode($dataUri) ?: ''];
    }

    /**
     * Ambil array item dari berbagai bentuk respons server.
     */
    private function extractItems($body): array
    {
        if (! is_array($body)) {
            return [];
        }

        foreach (['data', 'results', 'result', 'ocr', 'items'] as $key) {
            if (! array_key_exists($key, $body) || ! is_array($body[$key])) {
                continue;
            }

            $value = $body[$key];

            // Satu item asosiatif (mis. {"text": "...", "box": [...]})
            if (isset($value['text']) || isset($value['rec_txt'])) {
                return [$value];
            }

            // Bungkus tambahan: {"data": {"results": [...]}}.
            foreach (['results', 'items', 'lines', 'data'] as $nested) {
                if (isset($value[$nested]) && is_array($value[$nested])) {
                    return array_values($value[$nested]);
                }
            }

            return array_values($value);
        }

        if (isset($body['text']) || isset($body['rec_txt'])) {
            return [$body];
        }

        return array_values(array_filter($body, 'is_array'));
    }

    /**
     * Samakan bentuk item: text, box, x, y, score.
     */
    private function normalizeItems(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $text = trim((string) ($item['text'] ?? $item['rec_txt'] ?? $item['value'] ?? ''));

            $box = $item['box'] ?? $item['dt_boxes'] ?? $item['points'] ?? [];

            // Format RapidOCR asli: [box, "teks", score].
            if ($text === '' && isset($item[1]) && is_string($item[1])) {
                $text = trim($item[1]);

                if (isset($item[0]) && is_array($item[0])) {
                    $box = $item[0];
                }

                $item['score'] = $item[2] ?? ($item['score'] ?? 0);
            }

            if ($text === '') {
                continue;
            }

            $x = 0.0;
            $y = 0.0;

            if (is_array($box)) {
                if (isset($box[0]) && is_array($box[0])) {
                    $x = (float) ($box[0][0] ?? 0);
                    $y = (float) ($box[0][1] ?? 0);
                } elseif (isset($box[0], $box[1])) {
                    $x = (float) $box[0];
                    $y = (float) $box[1];
                }
            }

            $lines[] = [
                'text'  => $text,
                'box'   => $box,
                'x'     => $x,
                'y'     => $y,
                'score' => (float) ($item['score'] ?? $item['confidence'] ?? 0),
            ];
        }

        usort($lines, function (array $a, array $b) {
            $ay = (float) $a['y'];
            $by = (float) $b['y'];

            if (abs($ay - $by) < 15) {
                return (float) $a['x'] <=> (float) $b['x'];
            }

            return $ay <=> $by;
        });

        return $lines;
    }

    private function url(string $path): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        return $this->baseUrl . '/' . ltrim($path, '/');
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
