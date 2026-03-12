<?php

namespace App\Imports;

use App\Models\PayrollMaster;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PayrollMasterImport implements ToModel, WithHeadingRow
{

    public function model(array $row)
    {

        return PayrollMaster::updateOrCreate(

            [
                'npk' => $row['npk']
            ],

            [
                'salary' => $row['salary'] ?? 0,
                'allowance' => $row['allowance'] ?? 0,
                'pph21' => $row['pph21'] ?? 0
            ]

        );
    }
}
