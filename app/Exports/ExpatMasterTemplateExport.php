<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;

class ExpatMasterTemplateExport implements FromArray
{
    public function array(): array
    {
        return [[
            'npk',
            'name',
            'position',
            'place',
            'nationality',
            'direct_report',
            'npwp',
            'joining_date',
            'end_date',
            'passport_number',
            'passport_expiry',
            'kitas_expiry',
            'rptka_expiry',
            'merp_expiry',
            'house_address',
            'house_startdate',
            'lease_enddate'
        ]];
    }
}
