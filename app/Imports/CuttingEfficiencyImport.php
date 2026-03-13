<?php

namespace App\Imports;

use App\Models\CuttingEfficiency;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CuttingEfficiencyImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return CuttingEfficiency::updateOrCreate(
            [
                'npk' => $row['npk'],
                'date' => $row['date']
            ],
            [
                'efficiency' => $row['efficiency']
            ]
        );
    }
}
