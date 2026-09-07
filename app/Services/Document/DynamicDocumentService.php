<?php

namespace App\Services\Document;

use App\Exports\DynamicAiExport;
use App\Services\Ai\AiManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class DynamicDocumentService
{
    protected ?AiManager $ai;

    public function __construct(?AiManager $ai = null)
    {
        $this->ai = $ai;
    }

    /**
     * Generate an adaptive, AI-customized PDF document based on prompt, data, and context.
     */
    public function generateAdaptivePdf(string $userPrompt, string $title, array $data, ?string $summary = null): array
    {
        $this->ensureStorageDirectoryExists();

        // 1. AI designs the layout blueprint
        $docSpec = $this->designDocumentLayout($userPrompt, $title, $data, $summary);

        $filename = 'Laporan_' . Str::slug($docSpec['title'] ?? $title) . '_' . date('Ymd_His') . '.pdf';
        $relativeFolder = 'ai-generated';
        $relativePath = "{$relativeFolder}/{$filename}";
        $fullPath = storage_path("app/public/{$relativePath}");

        try {
            $orientation = $docSpec['orientation'] ?? ((count($docSpec['headers'] ?? []) > 6) ? 'landscape' : 'portrait');

            $pdf = Pdf::loadView('rag_chat.templates.ai_adaptive_document', [
                'doc' => $docSpec,
            ])->setPaper('a4', $orientation);

            $pdf->save($fullPath);

            return [
                'success' => true,
                'type' => 'pdf',
                'filename' => $filename,
                'path' => $relativePath,
                'url' => asset("storage/{$relativePath}"),
                'download_route' => route('rag-chat.download', ['filename' => $filename]),
                'doc_spec' => $docSpec,
            ];
        } catch (\Throwable $e) {
            Log::error("Failed to generate Adaptive PDF: {$e->getMessage()}");
            // Fallback to basic generation
            return $this->generateBasicPdf($title, $summary, $data);
        }
    }

    /**
     * Backward-compatible proxy to generateAdaptivePdf
     */
    public function generatePdf(string $title, ?string $summary, array $data): array
    {
        return $this->generateAdaptivePdf($title, $title, $data, $summary);
    }

    /**
     * Ask AI to design optimal document layout blueprint tailored to the data & user request.
     */
    public function designDocumentLayout(string $userPrompt, string $title, array $data, ?string $summary = null): array
    {
        $rawHeaders = [];
        if (!empty($data)) {
            $first = reset($data);
            $rawHeaders = array_keys($first);
        }

        $rowCount = count($data);
        $sampleDataJson = json_encode(array_slice($data, 0, 10), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($this->ai !== null) {
            $systemPrompt = <<<PROMPT
You are an expert Document Designer & Typographer for PT. Chutex International Indonesia (HR & Enterprise Operations).
Your job is to structure and format a professional PDF document blueprint tailored to the user's request and provided data.

DOCUMENT TYPE IDENTIFICATION:
1. "formal_letter": If the request is for a Surat Keterangan Kerja, Surat Peringatan (SP), Surat Tugas, Memo Internal, or single-employee notification.
2. "executive_summary": If the request is for an analytical recap, KPI audit, department summary, or management metrics.
3. "financial_report": If data involves salaries, payroll breakdown, overtime allowances, deductions, or compensation.
4. "tabular_report": If data is a multi-row record list (e.g. employee list, contracts, attendance, orders, violations).

OUTPUT FORMAT: Return ONLY a valid JSON object matching this schema:
{
    "title": "Capitalized Formal Document Title in Indonesian (e.g. SURAT KETERANGAN KERJA / REKAPITULASI DATA KARYAWAN)",
    "ref_number": "Optional formal ref no if letter/memo (e.g. '045/HR-CII/SKK/08/2026'), otherwise null",
    "doc_type": "formal_letter" | "executive_summary" | "financial_report" | "tabular_report",
    "orientation": "portrait" | "landscape",
    "theme_color": "#1e3a8a" (or #0f766e for operations, #881337 for warnings/SP, #334155 for general),
    "meta": {
        "Departemen": "Nama Departemen or General",
        "Periode": "Bulan / Tahun or Hari ini",
        "Klasifikasi": "Internal / Rahasia"
    },
    "kpi_cards": [
        {"label": "Metric Label (e.g. Total Karyawan / Total Jam Lembur)", "value": "120 Orang / Rp 1.500.000"}
    ],
    "opening_paragraphs": [
        "Official opening paragraph in Indonesian if formal letter or executive report (can be empty array if pure tabular)"
    ],
    "entity_details": {
        "Key (e.g. NIK / Nama / Jabatan)": "Value (if single entity/employee focus, else empty object)"
    },
    "headers": [
        {"key": "column_field_name", "label": "Friendly Column Header", "align": "left" | "center" | "right", "width": "20%"}
    ],
    "closing_paragraphs": [
        "Official closing statement in Indonesian if formal letter or summary conclusions"
    ],
    "notes": "Important footnotes, legal notes, or analytical recommendations",
    "signatures": [
        {"caption": "Dibuat Oleh,", "name": "Staff HR & GA", "role": "PT. Chutex International Indonesia"},
        {"caption": "Mengetahui / Menyetujui,", "name": "HR & GA Manager", "role": "Head of Human Resources"}
    ]
}
PROMPT;

            $userContent = <<<USERCONTENT
User Prompt: "{$userPrompt}"
Document Title: "{$title}"
Summary Note: "{$summary}"
Total Rows: {$rowCount}
Original Data Fields: {$sampleDataJson}
USERCONTENT;

            try {
                $blueprint = $this->ai->chatJson([
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userContent],
                ], ['temperature' => 0.1, 'timeout' => 20]);

                if (is_array($blueprint) && !empty($blueprint['title'])) {
                    // Inject actual full data rows and standard company meta
                    $blueprint['company_name'] = 'PT. CHUTEX INTERNATIONAL INDONESIA';
                    $blueprint['company_subtitle'] = 'Human Resources & Enterprise Operation Management System';
                    $blueprint['generated_at'] = now()->translatedFormat('d F Y H:i');
                    $blueprint['rows'] = $data;

                    // Ensure headers mapping
                    if (empty($blueprint['headers'])) {
                        $blueprint['headers'] = array_map(function ($k) {
                            return [
                                'key' => $k,
                                'label' => ucwords(str_replace(['_', '-'], ' ', $k)),
                                'align' => 'left',
                            ];
                        }, $rawHeaders);
                    }

                    return $blueprint;
                }
            } catch (\Throwable $e) {
                Log::warning('AI Document layout design failed, falling back: ' . $e->getMessage());
            }
        }

        // Default Fallback Layout Blueprint
        $formattedHeaders = array_map(function ($k) {
            return [
                'key' => $k,
                'label' => ucwords(str_replace(['_', '-'], ' ', $k)),
                'align' => is_numeric($k) ? 'right' : 'left',
            ];
        }, $rawHeaders);

        return [
            'title' => strtoupper($title),
            'ref_number' => null,
            'doc_type' => 'tabular_report',
            'orientation' => count($rawHeaders) > 6 ? 'landscape' : 'portrait',
            'theme_color' => '#1e3a8a',
            'company_name' => 'PT. CHUTEX INTERNATIONAL INDONESIA',
            'company_subtitle' => 'Human Resources & Enterprise Operation Management System',
            'generated_at' => now()->translatedFormat('d F Y H:i'),
            'meta' => [
                'Total Data' => "{$rowCount} Baris",
                'Diterbitkan' => now()->format('d/m/Y'),
            ],
            'kpi_cards' => [
                ['label' => 'Total Record Data', 'value' => (string) $rowCount],
            ],
            'opening_paragraphs' => $summary ? [$summary] : [],
            'entity_details' => [],
            'headers' => $formattedHeaders,
            'rows' => $data,
            'closing_paragraphs' => [],
            'notes' => 'Dokumen resmi ini diproses secara otomatis oleh sistem HR-CII AI Assistant.',
            'signatures' => [
                ['caption' => 'Disahkan Oleh,', 'name' => auth()->user()->name ?? 'HR Management', 'role' => 'HR & General Affair'],
            ],
        ];
    }

    /**
     * Fallback basic PDF generator
     */
    protected function generateBasicPdf(string $title, ?string $summary, array $data): array
    {
        $headers = !empty($data) ? array_keys(reset($data)) : [];
        $filename = 'Laporan_' . Str::slug($title) . '_' . date('Ymd_His') . '.pdf';
        $relativeFolder = 'ai-generated';
        $relativePath = "{$relativeFolder}/{$filename}";
        $fullPath = storage_path("app/public/{$relativePath}");

        $pdf = Pdf::loadView('rag_chat.templates.pdf_report', [
            'title' => $title,
            'summary' => $summary,
            'headers' => $headers,
            'rows' => $data,
            'generated_at' => now()->format('d/m/Y H:i:s'),
            'authorized_by' => auth()->user()->name ?? 'HR Management',
        ])->setPaper('a4', count($headers) > 6 ? 'landscape' : 'portrait');

        $pdf->save($fullPath);

        return [
            'success' => true,
            'type' => 'pdf',
            'filename' => $filename,
            'path' => $relativePath,
            'url' => asset("storage/{$relativePath}"),
            'download_route' => route('rag-chat.download', ['filename' => $filename]),
        ];
    }

    /**
     * Generate an Excel spreadsheet based on title and data array.
     */
    public function generateExcel(string $title, array $data): array
    {
        $this->ensureStorageDirectoryExists();

        if (empty($data)) {
            throw new RuntimeException('Cannot generate Excel with empty data.');
        }

        $headers = array_map(function ($h) {
            return ucwords(str_replace(['_', '-'], ' ', $h));
        }, array_keys(reset($data)));

        // Format data rows
        $rows = [];
        foreach ($data as $item) {
            $rows[] = array_values($item);
        }

        $filename = 'Rekap_' . Str::slug($title) . '_' . date('Ymd_His') . '.xlsx';
        $relativeFolder = 'ai-generated';
        $relativePath = "{$relativeFolder}/{$filename}";

        try {
            $export = new DynamicAiExport($title, $headers, $rows);
            Excel::store($export, "public/{$relativePath}");

            return [
                'success' => true,
                'type' => 'excel',
                'filename' => $filename,
                'path' => $relativePath,
                'url' => asset("storage/{$relativePath}"),
                'download_route' => route('rag-chat.download', ['filename' => $filename]),
            ];
        } catch (\Throwable $e) {
            Log::error("Failed to generate Excel: {$e->getMessage()}");
            throw new RuntimeException("Excel Generation Error: {$e->getMessage()}");
        }
    }

    /**
     * Ensure storage directory exists in storage/app/public/ai-generated
     */
    protected function ensureStorageDirectoryExists(): void
    {
        $dir = storage_path('app/public/ai-generated');
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true, true);
        }
    }
}
