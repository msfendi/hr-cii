<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EpoTemplateExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return new Collection([
            [
                1,
                'MANOJ SRIVASTAV',
                'Male',
                'Ballia, UTTAR PRADESH',
                '1963-10-01',
                63,
                'India',
                'Compliance Manager',
                'PT CHUTEX',
                '2026-01-28',
                '2026-02-05',
                750000,
                1500000,
                'EPO'
            ]
        ]);
    }

    public function headings(): array
    {
        return [
            'No',
            'Expat Name',
            'Gender',
            'Place',
            'Date of Birth',
            'Age on 2026',
            'Nationality',
            'Position',
            'Department',
            'Termination Date',
            'Must Leave Indonesia No Later Than',
            'EPO Cost',
            'RPTKA Cancellation Cost',
            'REMARKS'
        ];
    }
}
