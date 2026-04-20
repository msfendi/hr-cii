<?php

namespace App\Imports;

use App\Models\ExpatMaster;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ExpatMasterImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return ExpatMaster::updateOrCreate(
            ['npk' => $row['npk']],
            [
                'name' => $row['name'],
                'position' => $row['position'],
                'joining_date' => $row['joining_date'],
                'end_date' => $row['end_date'],
                'passport_number' => $row['passport_number'],
                'passport_expiry' => $row['passport_expiry'],
                'kitas_expiry' => $row['kitas_expiry'],
                'rptka_expiry' => $row['rptka_expiry'],
                'merp_expiry' => $row['merp_expiry'],
                'house_address' => $row['house_address'],
                'house_startdate' => $row['house_startdate'],
                'lease_enddate' => $row['lease_enddate'],
            ]
        );
    }
}
