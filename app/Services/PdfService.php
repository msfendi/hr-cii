<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class PdfService
{
    public static function protect($input, $output, $password)
    {
        $process = new Process([
            'C:\\Program Files\\qpdf 12.3.2\\bin\\qpdf.exe',
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
