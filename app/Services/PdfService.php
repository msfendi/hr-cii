<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class PdfService
{
    public static function protect($input, $output, $password)
    {
        $qpdfBinary = PHP_OS_FAMILY === 'Windows'
            ? 'C:\\Program Files\\qpdf 12.3.2\\bin\\qpdf.exe'
            : '/usr/bin/qpdf';

        $process = new Process([
            $qpdfBinary,
            '--encrypt',
            $password,
            $password,
            '256',
            '--print=none',
            '--modify=none',
            '--extract=n',
            '--',
            $input,
            $output,
        ]);

        $process->run();

        // dd(
        //     $process->isSuccessful(),
        //     $process->getOutput(),
        //     $process->getErrorOutput(),
        //     file_exists($output)
        // );
    }
}
