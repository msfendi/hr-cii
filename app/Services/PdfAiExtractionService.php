<?php

namespace App\Services;

use App\Models\ExtractedData;
use App\Models\ExtractedTable;
use App\Models\ExtractedTableRow;
use App\Models\PdfDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\PdfToText\Pdf as PdfToText;

class PdfAiExtractionService
{
    /**
     * Proses penuh: extract text dari PDF -> kirim ke AI -> simpan hasil ke DB
     */
    public function process(PdfDocument $document): PdfDocument
    {
        $document->update(['status' => 'processing']);

        try {
            // 1. Extract text mentah dari PDF pakai poppler (pdftotext)
            $rawText = $this->extractText($document->file_path);
            $document->update(['raw_text' => $rawText]);

            // 2. Kirim text ke AI, minta dikembalikan sebagai JSON terstruktur
            $structured = $this->askAiToStructureText($rawText);

            // 3. Simpan key-value pairs
            $this->saveKeyValues($document, $structured['fields'] ?? []);

            // 4. Simpan tabel-tabel yang terdeteksi
            $this->saveTables($document, $structured['tables'] ?? []);

            $document->update(['status' => 'processed']);
        } catch (\Throwable $e) {
            Log::error('PDF AI extraction gagal: ' . $e->getMessage());
            $document->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }

        return $document->fresh(['extractedData', 'extractedTables.rows']);
    }

    /**
     * Extract teks mentah dari file PDF menggunakan poppler-utils (pdftotext).
     * Di Windows/Laragon dan Linux, binary "pdftotext" harus ada di PATH.
     */
    protected function extractText(string $absolutePath): string
    {
        // PENTING: versi terbaru spatie/pdf-to-text TIDAK punya method setBinPath().
        // Path binary custom harus dikasih lewat constructor: new Pdf($binPath).
        // Kalau env ini kosong, package otomatis mencoba beberapa lokasi umum
        // (di Linux: /usr/bin/pdftotext, dst) - makanya di Linux boleh dikosongkan.
        $binaryPath = env('PDF_TO_TEXT_BINARY_PATH');

        if (!empty($binaryPath) && !is_file($binaryPath)) {
            throw new \RuntimeException(
                "Binary pdftotext tidak ditemukan di path: {$binaryPath}. " .
                "Pastikan PDF_TO_TEXT_BINARY_PATH di .env menunjuk langsung ke file " .
                "pdftotext.exe (bukan foldernya), lalu jalankan: php artisan config:clear"
            );
        }

        $pdf = new PdfToText($binaryPath ?: null);

        return $pdf
            ->setPdf($absolutePath)
            ->setOptions(['layout']) // -layout menjaga posisi kolom, penting untuk tabel
            ->text();
    }

    /**
     * Kirim teks PDF ke AI, minta AI mengembalikan JSON berisi:
     * - fields: key-value pairs umum (Vessel/Voy, ETA, No. Invoice, dsb)
     * - tables: daftar tabel dengan headers + rows
     *
     * Provider yang dipakai ditentukan oleh env AI_PROVIDER (anthropic|deepseek|gemini).
     */
    protected function askAiToStructureText(string $rawText): array
    {
        $provider = strtolower((string) env('AI_PROVIDER', 'anthropic'));

        $content = match ($provider) {
            'deepseek' => $this->callDeepSeek($rawText),
            'gemini', 'google' => $this->callGemini($rawText),
            'anthropic', 'claude' => $this->callAnthropic($rawText),
            default => throw new \RuntimeException(
                "AI_PROVIDER '{$provider}' tidak dikenal. Gunakan salah satu: anthropic, deepseek, gemini."
            ),
        };

        // Bersihkan kalau AI membungkus dengan ```json ... ```
        $clean = trim(preg_replace('/^```json|```$/m', '', $content));

        $parsed = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                "Gagal parse JSON dari AI ({$provider}): " . json_last_error_msg() .
                ' — respons mentah: ' . substr($clean, 0, 300)
            );
        }

        return $parsed;
    }

    protected function extractionSystemPrompt(): string
    {
        return <<<PROMPT
Kamu adalah sistem ekstraksi data dokumen. Kamu akan menerima teks hasil extract dari sebuah PDF (dokumen shipping/logistik/invoice, dsb).

Tugasmu:
1. Temukan semua pasangan field-value yang ada di dokumen (misal "Vessel/Voy", "ETA", "ETD", "Port of Loading", "No. Invoice", "Consignee", dst). Sertakan SEMUA field yang kamu temukan, jangan dibatasi daftar tertentu.
2. Temukan semua tabel di dalam teks (baris data dengan kolom berulang). Untuk tiap tabel, tentukan header kolomnya dan isi setiap barisnya.

Balas HANYA dengan JSON valid (tanpa markdown, tanpa penjelasan tambahan), dengan format persis seperti ini:

{
  "fields": [
    {"key": "Vessel/Voy", "value": "KM SINAR BALI / 123"},
    {"key": "ETA", "value": "2026-08-25"}
  ],
  "tables": [
    {
      "name": "Cargo Manifest",
      "headers": ["No", "Nama Barang", "Qty", "Berat"],
      "rows": [
        {"No": "1", "Nama Barang": "Beras", "Qty": "100", "Berat": "5000kg"},
        {"No": "2", "Nama Barang": "Gula", "Qty": "50", "Berat": "2500kg"}
      ]
    }
  ]
}

Jika tidak ada tabel, "tables" berupa array kosong []. Jika tidak ada field, "fields" berupa array kosong [].
PROMPT;
    }

    /**
     * Provider: Anthropic Claude (api.anthropic.com/v1/messages)
     */
    protected function callAnthropic(string $rawText): string
    {
        $apiKey = config('services.anthropic.api_key');
        if (empty($apiKey)) {
            throw new \RuntimeException('ANTHROPIC_API_KEY belum diisi di .env.');
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('services.anthropic.model', 'claude-sonnet-4-6'),
            'max_tokens' => 4096,
            'system' => $this->extractionSystemPrompt(),
            'messages' => [
                ['role' => 'user', 'content' => $rawText],
            ],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Panggilan API Anthropic gagal: ' . $response->body());
        }

        return $response->json('content.0.text', '{}');
    }

    /**
     * Provider: DeepSeek (OpenAI-compatible, api.deepseek.com/chat/completions)
     */
    protected function callDeepSeek(string $rawText): string
    {
        $apiKey = config('services.deepseek.api_key');
        if (empty($apiKey)) {
            throw new \RuntimeException('DEEPSEEK_API_KEY belum diisi di .env.');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(120)->post('https://api.deepseek.com/chat/completions', [
            'model' => config('services.deepseek.model', 'deepseek-v4-flash'),
            'messages' => [
                ['role' => 'system', 'content' => $this->extractionSystemPrompt()],
                ['role' => 'user', 'content' => $rawText],
            ],
            'response_format' => ['type' => 'json_object'],
            'stream' => false,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Panggilan API DeepSeek gagal: ' . $response->body());
        }

        return $response->json('choices.0.message.content', '{}');
    }

    /**
     * Provider: Google AI Studio / Gemini (generativelanguage.googleapis.com)
     */
    protected function callGemini(string $rawText): string
    {
        $apiKey = config('services.gemini.api_key');
        if (empty($apiKey)) {
            throw new \RuntimeException('GEMINI_API_KEY belum diisi di .env.');
        }

        $model = config('services.gemini.model', 'gemini-3.5-flash');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $response = Http::withHeaders([
            'x-goog-api-key' => $apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(120)->post($url, [
            'systemInstruction' => [
                'parts' => [['text' => $this->extractionSystemPrompt()]],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $rawText]]],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
            ],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Panggilan API Gemini gagal: ' . $response->body());
        }

        return $response->json('candidates.0.content.parts.0.text', '{}');
    }

    protected function saveKeyValues(PdfDocument $document, array $fields): void
    {
        foreach ($fields as $field) {
            if (empty($field['key'])) {
                continue;
            }

            ExtractedData::create([
                'pdf_document_id' => $document->id,
                'category_id' => $document->category_id,
                'data_key' => $field['key'],
                'data_value' => $field['value'] ?? null,
            ]);
        }
    }

    protected function saveTables(PdfDocument $document, array $tables): void
    {
        foreach ($tables as $index => $table) {
            if (empty($table['headers'])) {
                continue;
            }

            $extractedTable = ExtractedTable::create([
                'pdf_document_id' => $document->id,
                'table_name' => $table['name'] ?? null,
                'headers' => $table['headers'],
                'table_index' => $index,
            ]);

            foreach (($table['rows'] ?? []) as $rowIndex => $row) {
                ExtractedTableRow::create([
                    'extracted_table_id' => $extractedTable->id,
                    'row_index' => $rowIndex,
                    'row_data' => $row,
                ]);
            }
        }
    }
}
