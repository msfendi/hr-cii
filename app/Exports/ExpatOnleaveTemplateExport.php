<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;

class ExpatOnleaveTemplateExport implements FromArray
{
    public function array(): array
    {
        return [[
            'npk',
            'onleave_start',
            'onleave_end',
            'leave_type',
            'component',
            'component_type',
            'amount',
            'transactions_date',
            'remark'
        ]];
    }
}
