<?php

namespace App\Imports;

use App\Models\LineEfficiency;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class LineEfficiencyImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return LineEfficiency::updateOrCreate(
            [
                'line_number' => $row['line_number'],
                'date' => $row['date']
            ],
            [
                'efficiency' => $row['efficiency']
            ]
        );
    }
}
