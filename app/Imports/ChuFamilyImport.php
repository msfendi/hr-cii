<?php

namespace App\Imports;

use App\Models\ChuFamily;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ChuFamilyImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return ChuFamily::updateOrCreate(
            ['name' => $row['name']],
            [
                'name' => $row['name'],
                'gender' => $row['gender'],
                'place' => $row['place'],
                'birth_date' =>
                !empty($row['birth_date'])
                    ? Date::excelToDateTimeObject($row['birth_date'])
                    : null,
                'nationality' => $row['nationality'],
                'passport_number' => $row['passport_number'],
                'passport_expiry' =>
                !empty($row['passport_expiry'])
                    ? Date::excelToDateTimeObject($row['passport_expiry'])
                    : null,
                'visa_type' => $row['visa_type'],
                'visa_expiry' =>
                !empty($row['visa_expiry'])
                    ? Date::excelToDateTimeObject($row['visa_expiry'])
                    : null,
                'kitas_expiry' =>
                !empty($row['kitas_expiry'])
                    ? Date::excelToDateTimeObject($row['kitas_expiry'])
                    : null,
                'rptka_expiry' =>
                !empty($row['rptka_expiry'])
                    ? Date::excelToDateTimeObject($row['rptka_expiry'])
                    : null,
            ]
        );
    }
}
