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
                'salary'       => $row['salary'] ?? 0,
                'bank_name'    => isset($row['bank_name'])
                    ? ucwords(strtolower(trim($row['bank_name'])))
                    : "",
                'bank_account' => $row['bank_account'] ?? "",
            ]
        );
    }
}
