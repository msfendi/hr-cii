<?php

namespace App\Services\RecruitmentAi;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Ekstraksi data dari dokumen rekrutmen memakai model AI ber-vision.
 *
 * Alur:
 *  1. Dokumen (gambar / PDF) dikirim ke model sehingga model bisa MEMBACA
 *     isinya (bukan sekadar teksnya).
 *  2. Untuk PDF, teks layer juga diambil (pdftotext) dan disertakan sebagai
 *     konteks tambahan — membantu akurasi untuk PDF born-digital.
 *  3. Model diminta menjawab JSON terstruktur berisi nilai tiap field +
 *     confidence + catatan.
 *  4. Hasil dinormalisasi (tanggal Y-m-d, angka, pilihan select) supaya
 *     bisa langsung dipakai mengisi form.
 *
 * Mendukung beberapa provider (lihat config/recruitment_ai.php). Provider
 * dicoba berurutan sampai ada yang berhasil — jadi ketika provider utama
 * kena kuota/rate-limit, fitur tetap jalan lewat provider cadangan.
 */
class DocumentVisionExtractor
{
    /** Một.watch lebar gambar (px) sebelum dikirim, supaya payload ringkas */
    private const MAX_IMAGE_WIDTH = 1600;

    /** Maks ukuran PDF (byte) yang dikirim inline */
    private const MAX_INLINE_PDF_BYTES = 15 * 1024 * 1024;

    /** Pesan error terakhir (untuk ditampilkan ke user) */
    private string $lastError = '';

    /** Klien RapidOCR (pre-processing teks dari gambar) */
    private RapidOcrClient $rapidOcr;

    /** Penyesuai orientasi gambar dokumen */
    private DocumentImageOrienter $imageOrienter;

    /** Cache OCR per file (satu request bisa memproses beberapa dokumen). */
    private array $ocrCache = [];

    public function __construct(?RapidOcrClient $rapidOcr = null, ?DocumentImageOrienter $imageOrienter = null)
    {
        $this->rapidOcr      = $rapidOcr ?? new RapidOcrClient();
        $this->imageOrienter = $imageOrienter ?? new DocumentImageOrienter();
    }

    /**
     * Ekstrak satu dokumen.
     *
     * @param  string  $document     kode dokumen (kunci registry)
     * @param  string  $absolutePath path absolut file di disk
     * @param  string  $originalName nama file asli (untuk konteks)
     * @param  array<string, array>|null  $fields  override definisi field (mis. retry khusus pendidikan)
     * @return array{fields: array<string, array{value:mixed, confidence:float, note:string|null}>, note:string|null}
     */
    public function extract(string $document, string $absolutePath, string $originalName = '', ?array $fields = null): array
    {
        $fields ??= RecruitmentAiFieldRegistry::fieldsForDocument($document);

        if (empty($fields)) {
            return ['fields' => [], 'note' => null];
        }

        $mode       = (string) config('recruitment_ai.rapidocr.mode', 'first');
        $ocrFields  = [];
        $ocrNote    = null;

        // 1. Ekstraksi teks lokal (PDF layer pdftotext atau RapidOCR image)
        $isPdf   = $this->detectMime($absolutePath, $originalName) === 'application/pdf';
        // Untuk PDF: ambil teks layer (lebih reliable dari OCR).
        // Untuk gambar: gunakan RapidOCR (lokal, offline, data tidak keluar server).
        $lines   = $isPdf ? [] : $this->ocrLines($absolutePath, $document);
        $docText = $isPdf ? $this->extractPdfText($absolutePath) : $this->ocrText($absolutePath, $document);

        /*
         * OCR-first: dokumen cetak (KTP/KK/ijazah/SKCK/Akta) dibaca secara lokal lalu
         * diurai parser deterministik. Kalau hasilnya cukup (confidence tinggi), AI TIDAK DIPANGGIL
         * sama sekali — data KTP/KK tidak pernah keluar server, proses instan (<0.5s),
         * dan tidak terpengaruh kuota / timeout provider AI.
         */
        if (! empty($lines)) {
            try {
                $parsed = (new OcrDocumentParser())->parse($document, $lines);

                if (is_array($parsed) && ! empty($parsed['fields'])) {
                    $ocrFields = $this->normalizeFields($parsed['fields'], $fields);
                    $ocrNote   = (string) ($parsed['note'] ?? 'OCR lokal');

                    if (in_array($mode, ['first', 'only'], true) && $this->isOcrSufficient($document, $ocrFields)) {
                        $this->lastError = '';

                        return [
                            'fields' => $ocrFields,
                            'note'   => $ocrNote . ' (sukses deterministik)',
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[RecruitmentAi] Parser OCR gagal: ' . $e->getMessage());

                $ocrFields = [];
                $ocrNote   = null;
            }
        }

        $aiEnabled = (bool) config('recruitment_ai.ai_enabled', true);

        // Mode OCR-only / AI dimatikan: jangan pernah menunggu provider AI.
        if (! $aiEnabled || $mode === 'only') {
            if (! empty($ocrFields)) {
                return [
                    'fields' => $this->markAsCheckNeeded($ocrFields),
                    'note'   => trim(($ocrNote ?? 'OCR') . ' (AI nonaktif · perlu dicek)'),
                ];
            }

            $reason = $this->rapidOcr->isEnabled()
                ? ($this->rapidOcr->getLastError() ?: 'teks tidak terbaca / dokumen belum didukung parser OCR')
                : 'RapidOCR tidak aktif';

            return [
                'fields' => [],
                'note'   => 'Dokumen belum terbaca tanpa AI: ' . $reason . '. Silakan isi data secara manual.',
            ];
        }

        // 2. AI Optional: Jika OCR belum sufficient atau dokumen unstructured (CV/surat lamaran).
        // Untuk CV/surat lamaran atau dokumen yang sudah memiliki teks layer/OCR: panggil AI secara TEXT-ONLY!
        // Ini 10x lebih hemat token, bebas timeout vision base64, dan sangat cepat.
        $isTextDoc = in_array($document, ['cv', 'surat_lamaran'], true) || ($docText !== '' && strlen($docText) > 40);

        $errors = [];

        foreach (config('recruitment_ai.providers', []) as $name => $config) {
            if (empty($config['api_key'])) {
                continue;
            }

            try {
                return $this->extractWithProvider(
                    $name,
                    $config,
                    $document,
                    $absolutePath,
                    $originalName,
                    $fields,
                    $isTextDoc ? $docText : null
                );
            } catch (\Throwable $e) {
                $errors[$name] = $e->getMessage();

                Log::warning("[RecruitmentAi] Provider {$name} gagal: " . $e->getMessage());
            }
        }

        // 3. Fallback jika provider AI down / model off / kuota habis:
        // Field deterministik OCR yang terbaca tetap dikembalikan dengan badge "perlu dicek",
        // JANGAN melempar exception fatal yang membuat form error / menggantung!
        if (! empty($ocrFields)) {
            $this->lastError = '';

            return [
                'fields' => $this->markAsCheckNeeded($ocrFields),
                'note'   => trim(($ocrNote ?? 'OCR') . ' (AI offline · periksa data)'),
            ];
        }

        $this->lastError = $errors
            ? 'AI tidak tersedia atau gagal: ' . implode(' | ', array_map(fn ($m) => substr($m, 0, 120), $errors))
            : 'Belum ada provider AI yang dikonfigurasi.';

        return [
            'fields' => [],
            'note'   => $this->lastError . '. Silakan isi manual.',
        ];
    }

    /**
     * Turunkan confidence field agar diberi badge "perlu dicek" (< low_confidence).
     */
    private function markAsCheckNeeded(array $fields): array
    {
        $lowConfThreshold = (float) config('recruitment_ai.low_confidence', 0.75);
        $targetConf = min(0.70, $lowConfThreshold - 0.05);

        foreach ($fields as $k => $f) {
            if (isset($f['confidence'])) {
                $fields[$k]['confidence'] = min((float) $f['confidence'], $targetConf);
            }
        }

        return $fields;
    }

    /**
     * Apakah hasil parser OCR sudah cukup untuk mengisi form tanpa AI?
     */
    private function isOcrSufficient(string $document, array $fields): bool
    {
        return match ($document) {
            'scan_ktp' => isset($fields['nik']['value'], $fields['nama_lengkap']['value'])
                && strlen((string) $fields['nik']['value']) === 16,

            'scan_kk' => $this->isKkOcrSufficient($fields),

            'ijazah' => ! empty($fields['education']['value']),

            'scan_skck' => isset($fields['nik']['value'], $fields['nama_lengkap']['value']),

            'scan_akta_kelahiran' => isset($fields['nama_lengkap']['value'])
                && (isset($fields['tanggal_lahir']['value']) || isset($fields['ayah']['value']['nama']) || isset($fields['ibu']['value']['nama'])),

            default => false,
        };
    }

    /**
     * KK hanya dipakai bila struktur keluarga terbaca utuh. Tabel KK sering
     * miring / terpotong; kalau nama tergabung atau hubungan tidak lengkap,
     * lebih aman fallback ke AI (atau tampilkan sebagian untuk dicek).
     */
    private function isKkOcrSufficient(array $fields): bool
    {
        $members = (array) ($fields['kk_members']['value'] ?? []);

        if (count($members) < 2) {
            return false;
        }

        $hasHead  = false;
        $hasOther = false;

        foreach ($members as $member) {
            if (! is_array($member)) {
                return false;
            }

            $nik = preg_replace('/\D/u', '', (string) ($member['nik'] ?? '')) ?? '';

            if (strlen($nik) !== 16) {
                return false;
            }

            $nama = trim((string) ($member['nama'] ?? ''));

            if ($nama === '') {
                return false;
            }

            $upperNama = strtoupper($nama);

            if (str_contains($upperNama, 'TANDA TANGAN') || str_contains($upperNama, 'NIP')) {
                return false;
            }

            // Nama yang tergabung tanpa spasi (KHARISMAAYUPURWANTI) adalah
            // tanda OCR gagal memisahkan sel.
            if (! str_contains($nama, ' ') && strlen($nama) > 14) {
                return false;
            }

            $relation = strtoupper((string) ($member['hubungan'] ?? ''));

            if (str_contains($relation, 'KEPALA')) {
                $hasHead = true;
            }

            if (str_contains($relation, 'ISTRI') || str_contains($relation, 'SUAMI') || str_contains($relation, 'ANAK')) {
                $hasOther = true;
            }
        }

        if (! $hasHead || ! $hasOther) {
            return false;
        }

        $alamat = trim((string) ($fields['alamat_sekarang']['value'] ?? ''));

        return $alamat === '' || ! $this->isMessyOcrAddress($alamat);
    }

    private function isMessyOcrAddress(string $value): bool
    {
        $upper = strtoupper($value);

        if (preg_match('/\b(AIAMAT|ALAMAL|GOLONGAN|JENIS\s+PEKERJAAN)\b/u', $upper) === 1) {
            return true;
        }

        if (preg_match('/\b(KECAMATAN|KABUPATEN\s*\/\s*KOTA)\s+(KABUPATEN|KOTA|PROVINSI|GOLONGAN|RT\/RW|DESA|KELURAHAN|$)/u', $upper) === 1) {
            return true;
        }

        return substr_count($upper, 'ALAMAT') > 1;
    }

    /**
     * Ekstrak memakai satu provider (mendukung Vision atau Text-Only).
     */
    private function extractWithProvider(
        string $name,
        array $config,
        string $document,
        string $absolutePath,
        string $originalName,
        array $fields,
        ?string $documentText = null
    ): array {
        $type = $config['type'] ?? 'gemini';

        // Bila teks dokumen sudah tersedia dari OCR atau PDF layer (misal CV / surat lamaran),
        // gunakan mode Text-Only: 10x lebih hemat token, tidak memerlukan endpoint vision,
        // bebas kendala upload gambar base64 besar, dan proses jauh lebih cepat.
        if ($documentText !== null && trim($documentText) !== '') {
            $prompt = $this->buildPrompt($document, $fields, (bool) ($config['supports_schema'] ?? false));
            $textMessage = "Berikut teks hasil pembacaan dokumen '{$originalName}':\n\n```\n" . substr($documentText, 0, 16000) . "\n```\n\n" . $prompt;
            $parts = [['text' => $textMessage]];
        } else {
            $parts = $this->buildParts($config, $absolutePath, $originalName, $document);
            $parts[] = ['text' => $this->buildPrompt($document, $fields, (bool) ($config['supports_schema'] ?? false))];
        }

        $rawText = $type === 'openai'
            ? $this->callOpenAiCompatible($config, $parts)
            : $this->callGemini($config, $parts, $fields);

        $decoded = json_decode($this->stripFence($rawText), true);

        if (! is_array($decoded)) {
            Log::warning('[RecruitmentAi] Response bukan JSON valid: ' . substr($rawText, 0, 500));

            throw new RuntimeException('Format jawaban AI tidak dapat dibaca.');
        }

        $note = $decoded['note'] ?? null;
        if ($documentText !== null) {
            $note = ($note ? $note . ' · ' : '') . 'AI text-only (' . $name . ')';
        }

        return [
            'fields' => $this->normalizeFields($decoded['fields'] ?? [], $fields),
            'note'   => $note,
        ];
    }

    /* ---------------------------------------------------------------------
     | Panggilan API
     |---------------------------------------------------------------------*/

    /**
     * Panggil Gemini (retry + model cadangan).
     */
    private function callGemini(array $config, array $parts, array $fields = []): string
    {
        $models = array_values(array_unique(array_filter(array_merge(
            [$config['model'] ?? ''],
            (array) ($config['fallback_models'] ?? [])
        ))));

        $attempts = max(1, (int) env('RECRUITMENT_AI_RETRY_TIMES', 2));
        $sleepMs  = max(0, (int) env('RECRUITMENT_AI_RETRY_SLEEP_MS', 2500));
        $timeout  = (int) ($config['timeout'] ?? 45);

        $lastError = 'AI tidak dapat dihubungi.';

        foreach ($models as $model) {
            $url = rtrim($config['base_url'], '/') . '/models/' . $model . ':generateContent';

            $payload = [
                'contents' => [['role' => 'user', 'parts' => $parts]],
                'generationConfig' => [
                    'temperature'      => 0.1,
                    'maxOutputTokens'  => (int) ($config['max_output_tokens'] ?? 4096),
                    'responseMimeType' => 'application/json',
                ],
            ];

            // Schema hanya didukung Gemini — untuk provider lain, formatnya
            // diminta lewat prompt.
            if ($config['supports_schema'] ?? false) {
                $payload['generationConfig']['responseSchema'] = $this->buildSchema($fields);
            }

            for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                $response = Http::withHeaders([
                    'Content-Type'   => 'application/json',
                    'x-goog-api-key' => $config['api_key'],
                ])
                    ->timeout($timeout)
                    ->connectTimeout(5)
                    ->post($url, $payload);

                if ($response->successful()) {
                    $text = (string) $response->json('candidates.0.content.parts.0.text', '');

                    if ($text !== '') {
                        return $text;
                    }

                    $lastError = 'AI tidak mengembalikan hasil (finish_reason: '
                        . $response->json('candidates.0.finishReason', 'unknown') . ').';
                } else {
                    $status = $response->status();
                    $detail = (string) ($response->json('error.message') ?: $response->body());
                    $lastError = 'AI gagal membaca dokumen (' . $status . '): ' . substr($detail, 0, 220);

                    Log::warning("[RecruitmentAi] Gemini {$model} percobaan {$attempt} [{$status}]: " . substr($detail, 0, 160));

                    // 429 = kuota habis → langsung pindah provider berikutnya
                    if ($status === 429) {
                        break;
                    }
                }

                if ($attempt < $attempts) {
                    usleep($sleepMs * 1000 * $attempt);
                }
            }
        }

        throw new RuntimeException($lastError);
    }

    /**
     * Panggil API OpenAI-compatible (chat/completions).
     */
    private function callOpenAiCompatible(array $config, array $parts): string
    {
        $hasImage = false;
        foreach ($parts as $part) {
            if (isset($part['inline_data'])) {
                $hasImage = true;
                break;
            }
        }

        if (! $hasImage && count($parts) === 1 && isset($parts[0]['text'])) {
            $content = $parts[0]['text'];
        } else {
            $content = [];
            foreach ($parts as $part) {
                if (isset($part['text'])) {
                    $content[] = ['type' => 'text', 'text' => $part['text']];
                } elseif (isset($part['inline_data'])) {
                    $mime = $part['inline_data']['mime_type'] ?? 'image/png';
                    $content[] = [
                        'type'      => 'image_url',
                        'image_url' => ['url' => 'data:' . $mime . ';base64,' . $part['inline_data']['data']],
                    ];
                }
            }
        }

        $payload = [
            'model'    => $config['model'] ?? '',
            'messages' => [['role' => 'user', 'content' => $content]],
            'temperature' => 0.1,
            'max_tokens'  => (int) ($config['max_output_tokens'] ?? 4096),
            'stream'      => false,
        ];

        // Minta jawaban JSON (banyak provider OpenAI-compatible mendukung ini).
        if (! empty($config['supports_json_object'])) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . ($config['api_key'] ?? ''),
        ])
            ->timeout((int) ($config['timeout'] ?? 45))
            ->connectTimeout(5)
            ->post(rtrim($config['base_url'], '/') . '/chat/completions', $payload);

        if ($response->failed()) {
            $detail = (string) ($response->json('error.message') ?: $response->body());

            throw new RuntimeException('AI gagal membaca dokumen (' . $response->status() . '): ' . substr($detail, 0, 220));
        }

        $text = (string) ($response->json('choices.0.message.content') ?? '');

        if ($text === '') {
            $text = (string) ($response->json('choices.0.message.reasoning_content') ?? '');
        }

        if ($text === '') {
            throw new RuntimeException('AI tidak mengembalikan hasil.');
        }

        return $text;
    }

    /* ---------------------------------------------------------------------
     | Penyusun payload
     |---------------------------------------------------------------------*/

    /**
     * Susun parts multimodal: dokumen + (bila PDF) teks layer.
     */
    private function buildParts(array $config, string $absolutePath, string $originalName, ?string $document = null): array
    {
        $parts    = [];
        $mimeType = $this->detectMime($absolutePath, $originalName);
        $size     = @filesize($absolutePath) ?: 0;

        if ($mimeType === 'application/pdf') {
            $text = $this->extractPdfText($absolutePath);
            $canInline = ($config['supports_pdf_inline'] ?? false) && $size <= self::MAX_INLINE_PDF_BYTES;

            if ($canInline) {
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => 'application/pdf',
                        'data'      => base64_encode((string) file_get_contents($absolutePath)),
                    ],
                ];

                if ($text !== '') {
                    $parts[] = ['text' => "Teks yang terbaca pada layer PDF (bisa jadi tidak lengkap, gunakan gambar sebagai acuan utama):\n\n" . $text];
                }
            } elseif ($text !== '') {
                // PDF tidak dikirim inline → andalkan teks layer
                $parts[] = ['text' => "Berikut isi dokumen (teks yang terbaca dari PDF):\n\n" . $text];
            } else {
                throw new RuntimeException('PDF tidak dapat dibaca (tidak ada teks layer dan tidak bisa dikirim sebagai gambar).');
            }

            return $parts;
        }

        // ── Gambar ──
        [$binary, $imageMime] = $this->prepareImage($absolutePath, $mimeType);

        $parts[] = [
            'inline_data' => [
                'mime_type' => $imageMime,
                'data'      => base64_encode($binary),
            ],
        ];

        // ── RapidOCR pre-processing ──
        // Kirim gambar ASLI (bukan yang sudah di-resize) ke RapidOCR supaya
        // teks kecil pada KTP/KK tetap terbaca. Teks hasil OCR ditambahkan
        // sebagai konteks agar model AI bisa mencocokkan apa yang terlihat
        // di gambar dengan teks mentah — meningkatkan akurasi terutama
        // pada scan yang blur / resolusi rendah.
        $ocrText = $this->ocrText($absolutePath, $document);

        if ($ocrText !== '') {
            $parts[] = ['text' => "Teks yang terbaca oleh OCR dari dokumen ini (bisa jadi tidak lengkap/tidak urut, gunakan gambar sebagai acuan utama):\n\n" . $ocrText];
        }

        return $parts;
    }

    /**
     * Kompres / resize gambar dengan GD supaya payload kecil & model lebih mudah
     * membaca (scan KTP resolusi tinggi justru memperlambat).
     *
     * @return array{0:string, 1:string} [binary, mime]
     */
    private function prepareImage(string $path, string $mimeType): array
    {
        if (! function_exists('imagecreatetruecolor')) {
            return [(string) file_get_contents($path), $mimeType];
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));

        if ($image === false) {
            return [(string) file_get_contents($path), $mimeType];
        }

        $width  = imagesx($image);
        $height = imagesy($image);

        if ($width > self::MAX_IMAGE_WIDTH) {
            $newWidth  = self::MAX_IMAGE_WIDTH;
            $newHeight = (int) round($height * ($newWidth / $width));
            $resized   = imagecreatetruecolor($newWidth, $newHeight);

            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        ob_start();
        imagejpeg($image, null, 85);
        $binary = ob_get_clean();
        imagedestroy($image);

        return [(string) $binary, 'image/jpeg'];
    }

    /**
     * Ambil teks layer PDF lewat pdftotext (kalau binary tersedia).
     */
    private function extractPdfText(string $path): string
    {
        $binary = config('recruitment_ai.pdf_text_binary');

        if (empty($binary) || ! is_file($binary)) {
            return '';
        }

        try {
            $process = new Process([$binary, '-layout', $path, '-']);
            $process->setTimeout(60);
            $process->run();

            if (! $process->isSuccessful()) {
                return '';
            }

            return trim($process->getOutput());
        } catch (\Throwable $e) {
            Log::warning('[RecruitmentAi] Gagal ambil teks PDF: ' . $e->getMessage());

            return '';
        }
    }

    /**
     * Prompt + daftar field yang boleh diekstrak.
     */
    private function buildPrompt(string $document, array $fields, bool $withSchema = false): string
    {
        $documents = RecruitmentAiFieldRegistry::documents();
        $docLabel  = $documents[$document]['label'] ?? $document;

        $lines = [];
        foreach ($fields as $key => $definition) {
            $lines[] = '- ' . $key . ' (' . $definition['label'] . ', tipe ' . $definition['type'] . ')';

            if (in_array($definition['type'], ['select', 'radio'], true) && ! empty($definition['options'])) {
                $labels = $definition['labels'] ?? [];
                $options = array_map(
                    fn ($option) => isset($labels[$option])
                        ? $option . ' (' . $labels[$option] . ')'
                        : $option,
                    $definition['options']
                );
                $lines[] = '    pilihan yang valid: ' . implode(', ', $options);
            } elseif (! empty($definition['dynamic'])) {
                $lines[] = '    isi sesuai yang tertulis di dokumen (pilihan mengikuti data master perusahaan).';
            }

            if ($definition['type'] === 'group') {
                foreach ($definition['items'] as $itemKey => $item) {
                    $lines[] = '    - ' . $key . '.' . $itemKey . ' (' . $item['label'] . ')';
                }
            }

            if ($definition['type'] === 'list') {
                foreach ($definition['items'] as $itemKey => $item) {
                    $lines[] = '    - ' . $key . '[].' . $itemKey . ' (' . $item['label'] . ')';
                }
            }
        }

        $schemaHint = $withSchema
            ? 'Jawab HANYA dengan objek JSON {"fields": {...}, "note": "..."}.'
            : 'Jawab HANYA dengan objek JSON. Bentuknya: {"fields": {"nama_field": {"value": <nilai atau null>, "confidence": <0.0-1.0>, "note": <teks atau null>}}, "note": <teks atau null>}.';

        $extra = [];

        if ($document === 'scan_kk') {
            $extra = [
                '11. KHUSUS KK: baca SEMUA baris tabel anggota keluarga (jangan hanya kepala keluarga). Untuk SETIAP baris isi item kk_members: no (nomor urut baris), nama, nik, tempat_lahir, tgl_lahir, gender, pendidikan, pekerjaan, hubungan (WAJIB: salin persis kolom "Status Hubungan Dalam Keluarga", mis. Kepala Keluarga/Istri/Anak), nama_ayah, nama_ibu.',
                '12. nama_ayah dan nama_ibu = isi kolom "Nama Ayah"/"Nama Ibu" pada baris anggota tersebut. Kalau kolom itu tidak ada/kosong, value = null. Jangan mengambil nama orang tua dari baris lain.',
                '13. Identitas pelamar TIDAK diambil dari KK (identitas berpusat pada KTP). Dari KK cukup isi kk_members, nomor KK, dan alamat yang tertulis di KK.',
                '14. KHUSUS KK: baris dengan hubungan "Anak" adalah anak dari kepala keluarga/pasangan, BUKAN anak dari pelamar. Jangan menebak data anak pelamar dari baris KK; resolver akan menentukan dari kolom hubungan.',
            ];
        }

        if (in_array($document, ['cv', 'ijazah'], true)) {
            $extra[] = '15. KHUSUS RIWAYAT PENDIDIKAN: cari bagian Pendidikan/Education. Tulis SEMUA jenjang yang tercantum (SD, SMP, SMA/SMK, D3, D4, S1, S2, S3) sebagai baris terpisah di education — jangan hanya jenjang terakhir. Contoh: CV memuat SMA lalu S1 → education berisi 2 baris. Field pendidikan = jenjang terakhir saja.';
        }

        $extra[] = '16. Untuk kegiatan_ekstra (dari CV/surat lamaran): tulis hanya bila dokumen benar-benar menyebutkan kegiatan organisasi/ekstrakurikuler atau prestasi. Ringkas dan jangan mengarang.';

        if (isset($fields['status_pernikahan'])) {
            $extra[] = '17. Status Perkawinan: "BELUM KAWIN"/"BELUM MENIKAH" = BM; "KAWIN"/"MENIKAH"/"KAWIN TERCATAT" = M; "CERAI HIDUP" = CH; "CERAI MATI" = CM. Jangan menebak kode lain.';
        }

        return implode("\n", [
            'Kamu adalah ekstraktor data dokumen rekrutmen (KTP, KK, Akta Lahir, Ijazah, SKCK, CV, Surat Lamaran, Blanko Kesehatan).',
            '',
            'Dokumen yang sedang dibaca: ' . $docLabel . '.',
            '',
            'Tugasmu: baca dokumen dan isi HANYA field di bawah ini yang datanya benar-benar ada di dokumen.',
            'Aturan:',
            '0. Jawab LANGSUNG dengan JSON saja. Jangan menulis penjelasan, alasan, atau catatan lain sebelum/sesudah JSON.',
            '1. JANGAN mengarang data. Jika tidak ada di dokumen, value = null dan confidence = 0.',
            '2. Salin persis seperti tertulis (termasuk ejaan/salah tulis di dokumen).',
            '3. Tanggal tulis format YYYY-MM-DD. Bulan tulis format YYYY-MM. Angka tanpa pemisah ribuan.',
            '4. Untuk field select, value HARUS salah satu dari pilihan yang valid (tulis apa adanya, contoh: "Milik Sendiri").',
            '5. Field bertipe list: isi array berisi SEMUA baris/entri yang terbaca, jangan hanya entri terakhir. Contoh: CV memuat 3 riwayat pendidikan → keluarkan 3 baris.',
            '6. Field bertipe group: isi object dengan kunci-kunci di atas (nilai yang tidak ada = null).',
            '7. confidence = 0.0 sampai 1.0 (1.0 = sangat yakin terbaca jelas, 0.5 = kurang jelas/ambigu).',
            '8. note: isi singkat jika ada yang perlu diperiksa ulang, selain itu null.',
            '9. Setiap baris pada field list WAJIB memuat semua kunci. Jika nilai tidak tertulis tetapi jelas bisa disimpulkan (contoh: institusi "SMA NEGERI 1 SIDOARJO" → tingkat "SMA", "UNIVERSITAS NEGERI SURABAYA" jurusan teknik → tingkat "S1"), isi hasil simpulan dengan confidence 0.6 dan sebutkan dasarnya di note.',
            '10. Nama sekolah SD/SMP/SMA/SMK menentukan jenjang pada field tingkat. Politeknik = D3/D4, Universitas/Institut = S1 kecuali dokumen menyebut D3/D4/S2/S3.',
            ...$extra,
            '',
            'Field yang boleh diisi:',
            implode("\n", $lines),
            '',
            $schemaHint,
        ]);
    }

    /**
     * Bentuk responseSchema Gemini dari definisi field.
     */
    private function buildSchema(array $fields): array
    {
        $properties = [];

        foreach ($fields as $key => $definition) {
            $properties[$key] = [
                'type'       => 'object',
                'properties' => [
                    'value'      => $this->valueSchema($definition),
                    'confidence' => ['type' => 'number'],
                    'note'       => ['type' => 'string', 'nullable' => true],
                ],
                'required'   => ['value', 'confidence'],
            ];
        }

        return [
            'type'       => 'object',
            'properties' => [
                'fields' => [
                    'type'       => 'object',
                    'properties' => $properties,
                ],
                'note' => ['type' => 'string'],
            ],
            'required' => ['fields'],
        ];
    }

    /**
     * Skema untuk nilai sebuah field.
     */
    private function valueSchema(array $definition): array
    {
        return match ($definition['type']) {
            'number'  => ['type' => 'number', 'nullable' => true],
            'boolean' => ['type' => 'boolean', 'nullable' => true],
            'group'   => [
                'type'       => 'object',
                'nullable'   => true,
                'properties' => $this->itemProperties($definition['items'] ?? []),
            ],
            'list'    => [
                'type'     => 'array',
                'nullable' => true,
                'items'    => [
                    'type'       => 'object',
                    'properties' => $this->itemProperties($definition['items'] ?? []),
                ],
            ],
            default   => ['type' => 'string', 'nullable' => true],
        };
    }

    /**
     * Properti untuk item group/list.
     */
    private function itemProperties(array $items): array
    {
        $properties = [];

        foreach ($items as $key => $item) {
            $properties[$key] = match ($item['type'] ?? 'text') {
                'number'  => ['type' => 'number', 'nullable' => true],
                'boolean' => ['type' => 'boolean', 'nullable' => true],
                'date'    => ['type' => 'string', 'nullable' => true],
                default   => ['type' => 'string', 'nullable' => true],
            };
        }

        return $properties;
    }

    /* ---------------------------------------------------------------------
     | Normalisasi hasil AI
     |---------------------------------------------------------------------*/

    /**
     * Rapikan hasil AI: buang nilai kosong, format tanggal/angka, validasi select.
     */
    private function normalizeFields(array $raw, array $definitions): array
    {
        $result = [];

        foreach ($definitions as $key => $definition) {
            $entry = $raw[$key] ?? null;

            if (! is_array($entry)) {
                // Provider tanpa schema mungkin mengembalikan nilai langsung.
                if (is_scalar($entry) && (string) $entry !== '') {
                    $result[$key] = ['value' => $this->normalizeValue($entry, $definition), 'confidence' => 0.6, 'note' => null];
                }
                continue;
            }

            if (! array_key_exists('value', $entry)) {
                continue;
            }

            $value = $entry['value'];

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $normalized = $this->normalizeValue($value, $definition);

            // Model kadang salah memetakan label status perkawinan ke kode
            // (contoh: "BELUM KAWIN" → "CM"). Koreksi dari teks asli + note.
            if ($key === 'status_pernikahan') {
                $normalized = $this->normalizeMaritalStatus($value, $entry['note'] ?? null);
            }

            $result[$key] = [
                'value'      => $normalized,
                'confidence' => max(0.0, min(1.0, (float) ($entry['confidence'] ?? 0))),
                'note'       => $entry['note'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Normalisasi satu nilai sesuai tipe field.
     */
    private function normalizeValue($value, array $definition)
    {
        switch ($definition['type']) {
            case 'number':
                return is_numeric($value) ? (float) $value : null;

            case 'boolean':
                if (is_bool($value)) {
                    return $value;
                }
                $v = strtolower(trim((string) $value));
                return in_array($v, ['ya', 'yes', 'true', '1', 'lulus', 'lulus ya'], true) ? true : null;

            case 'date':
                return $this->normalizeDate((string) $value);

            case 'month':
                return $this->normalizeMonth((string) $value);

            case 'select':
            case 'radio':
                return $this->normalizeSelect((string) $value, $definition['options'] ?? []);

            case 'group':
                $out = [];
                foreach (($definition['items'] ?? []) as $itemKey => $item) {
                    if (! isset($value[$itemKey])) {
                        continue;
                    }
                    $out[$itemKey] = $this->normalizeValue($value[$itemKey], $item);
                }
                return array_filter($out, fn ($v) => $v !== null && $v !== '');

            case 'list':
                $rows = [];
                foreach ((array) $value as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $clean = [];
                    foreach (($definition['items'] ?? []) as $itemKey => $item) {
                        if (! isset($row[$itemKey])) {
                            continue;
                        }
                        $clean[$itemKey] = $this->normalizeValue($row[$itemKey], $item);
                    }
                    $clean = array_filter($clean, fn ($v) => $v !== null && $v !== '' && $v !== []);
                    if (! empty($clean)) {
                        $rows[] = $clean;
                    }
                }
                return $rows;

            default:
                return $this->normalizeText($value, $definition);
        }
    }

    /**
     * Normalisasi teks: buang pemisah pada nomor telepon / nomor identitas.
     *
     * Nomor HP "0812-3456-7890" atau "+62 812 3456 7890" disimpan sebagai
     * "081234567890" (tanpa tanda hubung/spasi).
     */
    private function normalizeText($value, array $definition): ?string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        // Placeholder seperti "-" / "N/A" bukan data.
        if (in_array(strtoupper($text), ['-', 'N/A', 'NA', 'N.A.', 'TIDAK ADA', 'TIDAK', 'NULL'], true)
            || preg_match('/^[-\x{2013}\x{2014}_.\s]+$/u', $text) === 1) {
            return null;
        }

        $format = $definition['format'] ?? null;

        if ($format === 'phone') {
            $digits = preg_replace('/\D+/', '', $text);

            if ($digits === null || strlen($digits) < 8) {
                return null;
            }

            if (str_starts_with($digits, '62')) {
                $digits = '0' . substr($digits, 2);
            } elseif (str_starts_with($digits, '8')) {
                $digits = '0' . $digits;
            }

            return $digits;
        }

        if ($format === 'digits') {
            $digits = preg_replace('/\D+/', '', $text);

            return $digits !== null && $digits !== '' ? $digits : null;
        }

        return $text;
    }

    /**
     * Tanggal assorted (dd-mm-yyyy, dd/mm/yyyy, "12 Maret 1995") → Y-m-d.
     */
    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }

        if (preg_match('#^(\d{1,2})\s*[/\-.]\s*(\d{1,2})\s*[/\-.]\s*(\d{4})$#', $value, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        // "12 Maret 1995" dan sejenisnya
        $timestamp = strtotime(str_replace(' ', '-', $value));

        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    /**
     * "Maret 2020" / "2020" / "03/2020" → YYYY-MM.
     */
    private function normalizeMonth(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{1,2})/', $value, $m)) {
            return sprintf('%04d-%02d', $m[1], $m[2]);
        }

        if (preg_match('#^(\d{1,2})[/\-](\d{4})$#', $value, $m)) {
            return sprintf('%04d-%02d', $m[2], $m[1]);
        }

        if (preg_match('/^(\d{4})$/', $value, $m)) {
            return $m[1] . '-01';
        }

        $timestamp = strtotime('1 ' . $value);

        return $timestamp !== false ? date('Y-m', $timestamp) : null;
    }

    /**
     * Samakan nilai select dengan opsi yang valid (case-insensitive).
     */
    private function normalizeSelect(string $value, array $options): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        foreach ($options as $option) {
            if (strcasecmp($option, $value) === 0) {
                return $option;
            }
        }

        // Cocok longgar: "S1 (Sarjana)" → "S1"
        foreach ($options as $option) {
            if (stripos($value, (string) $option) === 0) {
                return $option;
            }
        }

        return $value;
    }

    /**
     * Peta status perkawinan dari teks dokumen ke kode form.
     *
     * Label di KTP/KK tidak selalu sama dengan kode (BM/M/CH/CM), dan model
     * kadang salah memilih kode. Prioritaskan kata yang benar-benar tertulis.
     */
    private function normalizeMaritalStatus($value, ?string $note = null): ?string
    {
        $text = strtoupper(trim((string) $value . ' ' . (string) $note));

        // "BELUM KAWIN" mengandung "KAWIN", jadi cek status belum menikah dulu.
        if (str_contains($text, 'BELUM')
            || str_contains($text, 'TIDAK KAWIN')
            || str_contains($text, 'TIDAK MENIKAH')) {
            return 'BM';
        }

        if (str_contains($text, 'CERAI MATI')) {
            return 'CM';
        }

        if (str_contains($text, 'CERAI HIDUP')) {
            return 'CH';
        }

        if (str_contains($text, 'KAWIN') || str_contains($text, 'MENIKAH')) {
            return 'M';
        }

        if (preg_match('/\b(BM|M|CH|CM)\b/', $text, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    /* ---------------------------------------------------------------------
     | RapidOCR pre-processing
     |---------------------------------------------------------------------*/

    /**
     * Ambil baris OCR untuk satu file, dengan cache per path.
     *
     * Satu instance extractor dipakai untuk beberapa dokumen dalam satu
     * request; cache mencegah file yang sama dipanggil OCR berkali-kali
     * (mis. saat provider AI fallback).
     */
    private function ocrLines(string $absolutePath, ?string $document = null): array
    {
        // 1. Langsung sesuaikan orientasi gambar (EXIF & rasio KTP/KK) sebelum OCR
        if ($document !== null) {
            try {
                $this->imageOrienter->orientImageForDocument($absolutePath, $document);
            } catch (\Throwable $e) {
                Log::warning('[RecruitmentAi] Gagal menyesuaikan orientasi gambar: ' . $e->getMessage());
            }
        }

        $key = md5($absolutePath . '|' . (@filemtime($absolutePath) ?: 0));

        if (array_key_exists($key, $this->ocrCache)) {
            return $this->ocrCache[$key];
        }

        try {
            $lines = $this->rapidOcr->extractLines($absolutePath);
        } catch (\Throwable $e) {
            Log::info('[RecruitmentAi] RapidOCR dilewati: ' . $e->getMessage());
            $lines = [];
        }

        // 2. Pemulihan sudut 180° jika orientasi terbalik (misal KTP terbalik atas-bawah)
        if ($document !== null && $this->rapidOcr->isEnabled()) {
            $meaningful = $this->imageOrienter->hasMeaningfulText($document, $lines);
            if (! $meaningful) {
                try {
                    if ($this->imageOrienter->rotateDegrees($absolutePath, 180)) {
                        $retryLines = $this->rapidOcr->extractLines($absolutePath);
                        if ($this->imageOrienter->hasMeaningfulText($document, $retryLines)) {
                            Log::info("[RecruitmentAi] Orientasi dokumen '{$document}' berhasil dipulihkan dengan rotasi 180°.");
                            $lines = $retryLines;
                            $key   = md5($absolutePath . '|' . (@filemtime($absolutePath) ?: 0));
                        } else {
                            // Kembalikan ke rotasi semula bila tetap tidak bermakna
                            $this->imageOrienter->rotateDegrees($absolutePath, 180);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('[RecruitmentAi] Fallback rotasi 180° gagal: ' . $e->getMessage());
                }
            }
        }

        return $this->ocrCache[$key] = $lines;
    }

    /**
     * Teks gabungan OCR untuk konteks prompt AI.
     */
    private function ocrText(string $absolutePath, ?string $document = null): string
    {
        return $this->rapidOcr->linesToText($this->ocrLines($absolutePath, $document));
    }

    /* ---------------------------------------------------------------------
     | Util
     |---------------------------------------------------------------------*/

    /**
     * Ambil objek JSON pertama yang seimbang dari jawaban model.
     *
     * Beberapa model menulis paragraf penjelasan dulu sebelum JSON-nya
     * (atauvice versa), jadi jawaban dibungkus di dalam teks biasa —
     * di sini objek JSON-nya dicari dengan menghitung kurung kurawal,
     * bukan regex naif.
     */
    private function extractJson(string $text): ?string
    {
        $start = strpos($text, '{');

        if ($start === false) {
            $start = strpos($text, '[');
        }

        if ($start === false) {
            return null;
        }

        $open      = $text[$start];
        $close     = $open === '{' ? '}' : ']';
        $depth     = 0;
        $len       = strlen($text);
        $inString  = false;
        $escaped   = false;

        for ($i = $start; $i < $len; $i++) {
            $ch = $text[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escaped = true;
                    continue;
                }
                if ($ch === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($ch === '"') {
                $inString = true;
                continue;
            }

            if ($ch === $open) {
                $depth++;
            } elseif ($ch === $close) {
                $depth--;

                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * Buang pagar markdown ```json ... ``` lalu ambil objek JSON pertama
     * yang seimbang (beberapa model membungkus jawabannya dengan teks lain).
     */
    private function stripFence(string $text): string
    {
        $clean = trim($text);
        $clean = preg_replace('/^```(?:json)?/i', '', $clean);
        $clean = preg_replace('/```$/', '', $clean);
        $clean = trim((string) $clean);

        $json = $this->extractJson($clean);

        return $json ?? $clean;
    }

    /**
     * Deteksi mime type file (pakai finfo, fallback ke ekstensi).
     */
    private function detectMime(string $path, string $originalName): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $path);
            finfo_close($finfo);

            if (is_string($mime) && $mime !== '') {
                if ($mime === 'application/x-pdf' || $mime === 'application/pdf') {
                    return 'application/pdf';
                }
                if (str_starts_with($mime, 'image/')) {
                    return $mime;
                }
            }
        }

        $ext = strtolower(pathinfo($originalName ?: $path, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf'      => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png'      => 'image/png',
            'webp'     => 'image/webp',
            default    => 'application/octet-stream',
        };
    }
}
