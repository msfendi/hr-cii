<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeesContractTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            [
                '12345',
                '1',
                '2023-01-01',
                '2023-12-31',
                '12',
                'AKTIF',
                '5000000',
                '1000000',
                'Contract',
                '0',
                '0'
            ]
        ];
    }

    public function headings(): array
    {
        return [
            'npk',
            'contract_ke',
            'start_date',
            'end_date',
            'month_duration',
            'status_contract',
            'salary',
            'allowance',
            'type',
            'daily_salary',
            'pph21'
        ];
    }
}
