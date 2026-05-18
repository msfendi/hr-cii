<?php

namespace App\Exports;

use App\Models\Epo;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EpoExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Epo::select(
            'id',
            'expat_name',
            'gender',
            'place',
            'birth_date',
            'nationality',
            'position',
            'department',
            'termination_date',
            'must_leave_date',
            'epo_cost',
            'rptka_cancellation_cost',
            'remarks'
        )->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'Expat Name',
            'Gender',
            'Place',
            'Date of Birth',
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
