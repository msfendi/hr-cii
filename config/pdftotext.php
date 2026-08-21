<?php

return [

    // Kosongkan di .env kalau di server Linux dan "pdftotext" sudah ada di PATH.
    // Di Windows/Laragon, isi dengan path lengkap ke pdftotext.exe.
    // Contoh .env:
    // PDFTOTEXT_PATH="C:\laragon\bin\poppler\poppler-24.02.0\Library\bin\pdftotext.exe"
    'bin_path' => env('PDFTOTEXT_PATH', null),

];
