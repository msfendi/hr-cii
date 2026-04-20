<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ExpatOnleaveImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return new ExpatCost([
            'npk' => $row['npk'],

            'onleave_start' =>
            !empty($row['onleave_start'])
                ? Date::excelToDateTimeObject($row['onleave_start'])
                : null,

            'onleave_end' =>
            !empty($row['onleave_end'])
                ? Date::excelToDateTimeObject($row['onleave_end'])
                : null,

            'transactions_date' =>
            !empty($row['transactions_date'])
                ? Date::excelToDateTimeObject($row['transactions_date'])
                : null,

            'leave_type' => $row['leave_type'],
            'component' => $row['component'],
            'amount' => $row['amount'],
            'remark' => $row['remark'],
        ]);
    }
}
