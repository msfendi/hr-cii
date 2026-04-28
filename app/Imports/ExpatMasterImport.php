<?php

namespace App\Imports;

use App\Models\ExpatMaster;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ExpatMasterImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return ExpatMaster::updateOrCreate(
            ['npk' => $row['npk']],
            [
                'name' => $row['name'],
                'position' => $row['position'],
                'place' => $row['place'],
                'nationality' => $row['nationality'],
                'direct_report' => $row['direct_report'],
                'npwp' => $row['npwp'],
                'joining_date' =>
                !empty($row['joining_date'])
                    ? Date::excelToDateTimeObject($row['joining_date'])
                    : null,
                'end_date' =>
                !empty($row['end_date'])
                    ? Date::excelToDateTimeObject($row['end_date'])
                    : null,
                'passport_number' => $row['passport_number'],

                'passport_expiry' =>
                !empty($row['passport_expiry'])
                    ? Date::excelToDateTimeObject($row['passport_expiry'])
                    : null,

                'kitas_expiry' =>
                !empty($row['kitas_expiry'])
                    ? Date::excelToDateTimeObject($row['kitas_expiry'])
                    : null,

                'rptka_expiry' =>
                !empty($row['rptka_expiry'])
                    ? Date::excelToDateTimeObject($row['rptka_expiry'])
                    : null,

                'merp_expiry' =>
                !empty($row['merp_expiry'])
                    ? Date::excelToDateTimeObject($row['merp_expiry'])
                    : null,
                'house_address' => $row['house_address'],

                'house_startdate' =>
                !empty($row['house_startdate'])
                    ? Date::excelToDateTimeObject($row['house_startdate'])
                    : null,
                'lease_enddate' =>
                !empty($row['lease_enddate'])
                    ? Date::excelToDateTimeObject($row['lease_enddate'])
                    : null,
            ]
        );
    }
}
