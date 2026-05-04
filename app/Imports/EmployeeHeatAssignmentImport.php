<?php

namespace App\Imports;

use App\Models\EmployeeHeatAssignment;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class EmployeeHeatAssignmentImport implements ToModel, WithHeadingRow
{
    protected $periodId;

    public function __construct($periodId)
    {
        $this->periodId = $periodId;
    }

    public function model(array $row)
    {
        return EmployeeHeatAssignment::updateOrCreate(
            [
                'npk' => $row['npk'],
                'dept' => $row['dept'],
                'start_date' =>
                !empty($row['start_date'])
                    ? Date::excelToDateTimeObject($row['start_date'])
                    : null,
                'period_id'   => $this->periodId,
            ],
            [
                'role' => $row['role'],
                'end_date' =>
                !empty($row['end_date'])
                    ? Date::excelToDateTimeObject($row['end_date'])
                    : null,
            ]
        );
    }
}
