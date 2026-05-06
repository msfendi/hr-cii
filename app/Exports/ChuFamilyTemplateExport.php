<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class ChuFamilyTemplateExport implements FromArray
{
    public function array(): array
    {
        return [[
            'name',
            'gender',
            'place',
            'birth_date',
            'nationality',
            'passport_number',
            'passport_expiry',
            'visa_type',
            'visa_expiry',
            'kitas_expiry',
            'rptka_expiry'
        ]];
    }
}
