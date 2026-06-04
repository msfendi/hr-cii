<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PKWTExport implements WithMultipleSheets
{
    /**
     * @return array
     */
    public function sheets(): array
    {
        return [
            new PKWTCountGenderExport(),
            new PKWTActiveExport(),
            new PKWTNonActiveExport(),
        ];
    }
}
