<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ExcelZipEncryptService
{
    protected string $sevenZipPath;

    public function __construct()
    {
        $OSSevenzipPath = PHP_OS_FAMILY === 'Windows'
            ? 'C:\\Program Files\\7-Zip\\7z.exe'
            : '/usr/bin/7z';

        $this->sevenZipPath = $OSSevenzipPath;
    }

    /**
     * Encrypt Excel file into password-protected ZIP (AES-256)
     *
     * @param string $filePath full path .xlsx
     * @param string $password
     * @param bool $deleteOriginal
     * @return string path zip hasil
     */
    public function encrypt(string $filePath, string $password, bool $deleteOriginal = true): string
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        $zipName = pathinfo($filePath, PATHINFO_FILENAME) . '.zip';
        $zipPath = dirname($filePath) . '/' . $zipName;

        $process = new Process([
            $this->sevenZipPath,
            'a',
            '-tzip',
            $zipPath,
            $filePath,
            '-p' . $password,
            '-mem=AES256'
        ]);

        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        if ($deleteOriginal && file_exists($filePath)) {
            unlink($filePath);
        }

        return $zipPath;
    }
}
