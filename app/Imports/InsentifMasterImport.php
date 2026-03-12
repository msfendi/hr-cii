<?php

namespace App\Imports;

use App\Models\InsentifMaster;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class InsentifMasterImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return InsentifMaster::updateOrCreate(
            [
                'npk'  => $row['npk'],
                'type' => $row['type'],
                'date' => $row['date'],
            ],
            [
                'efficiency' => $row['efficiency'],
                'piece'      => $row['piece']
            ]
        );
    }
}
