<?php

namespace App\Console\Commands;

use App\Services\RecruitmentAi\RapidOcrClient;
use Illuminate\Console\Command;

class RecruitmentOcrCheck extends Command
{
    protected $signature = 'recruitment:ocr-check {file? : Path gambar untuk uji OCR} {--url= : Override URL server OCR} {--doc= : Tipe dokumen (mis. scan_ktp, scan_kk)} {--orient : Otomatis sesuaikan orientasi sebelum OCR}';

    protected $description = 'Cek koneksi server RapidOCR dan (opsional) baca satu gambar.';

    public function handle(): int
    {
        $url = $this->option('url');

        if ($url) {
            config(['recruitment_ai.rapidocr.base_url' => rtrim((string) $url, '/')]);
        }

        $ocr    = new RapidOcrClient();
        $config = config('recruitment_ai.rapidocr', []);

        $this->line('URL      : ' . ($config['base_url'] ?? '(kosong)'));
        $this->line('Format   : ' . ($config['format'] ?? 'auto'));
        $this->line('JSON path: ' . ($config['json_path'] ?? '/ocr/json'));
        $this->line('Multipart: ' . ($config['multipart_path'] ?? '/ocr'));
        $this->line('Mode     : ' . ($config['mode'] ?? 'context'));
        $this->line('AI aktif : ' . (config('recruitment_ai.ai_enabled') ? 'ya' : 'tidak'));
        $this->newLine();

        if (! $ocr->isEnabled()) {
            $this->error('RapidOCR tidak aktif atau RAPIDOCR_URL kosong.');

            return self::FAILURE;
        }

        $health = $ocr->healthCheck();

        if ($health) {
            $this->info('Health check: OK');
        } else {
            $this->warn('Health check: GAGAL — ' . $ocr->getLastError());
            $this->line('Health endpoint bisa saja tidak disediakan; uji dengan file di bawah.');
        }

        $file = $this->argument('file');

        if (! $file) {
            return $health ? self::SUCCESS : self::FAILURE;
        }

        if (! is_file($file)) {
            $this->error('File tidak ditemukan: ' . $file);

            return self::FAILURE;
        }

        if ($this->option('orient') || $this->option('doc')) {
            $docType = $this->option('doc') ?: 'scan_ktp';
            $orienter = new DocumentImageOrienter();
            $changed = $orienter->orientImageForDocument($file, $docType);
            if ($changed) {
                $this->info("Orientasi gambar disesuaikan untuk dokumen '{$docType}'");
            }
        }

        $start = microtime(true);
        $lines = $ocr->extractLines($file);
        $elapsed = round(microtime(true) - $start, 2);

        $this->newLine();
        $this->line("Membaca: {$file}");
        $this->line('Baris  : ' . count($lines) . " ({$elapsed}s)");

        if (empty($lines)) {
            $this->error('Gagal OCR: ' . ($ocr->getLastError() ?: 'tidak ada teks terbaca'));

            return self::FAILURE;
        }

        foreach (array_slice($lines, 0, 25) as $line) {
            $this->line('- ' . $line['text']);
        }

        if (count($lines) > 25) {
            $this->line('... (' . (count($lines) - 25) . ' baris lagi)');
        }

        return self::SUCCESS;
    }
}
