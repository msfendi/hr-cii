<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;

class PayrollMasterTemplateExport implements WithHeadings
{

    public function headings(): array
    {
        return [
            'npk',
            'salary',
            'allowance'
        ];
    }
}
