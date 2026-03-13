<?php

namespace App\Imports;

use App\Models\PadEfficiency;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PadEfficiencyImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return PadEfficiency::updateOrCreate(
            [
                'npk' => $row['npk'],
                'dept' => $row['dept'],
                'date' => $row['date']
            ],
            [
                'efficiency' => $row['efficiency'],
                'piece' => $row['piece']
            ]
        );
    }
}
