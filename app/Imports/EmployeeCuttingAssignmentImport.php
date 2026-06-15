<?php

namespace App\Imports;

use App\Models\EmployeeCuttingAssignment;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class EmployeeCuttingAssignmentImport implements ToModel, WithHeadingRow
{
    protected $periodId;

    public function __construct($periodId)
    {
        $this->periodId = $periodId;
    }

    public function model(array $row)
    {
        return EmployeeCuttingAssignment::updateOrCreate(
            [
                'npk' => $row['npk'],
                'start_date' =>
                !empty($row['date'])
                    ? Date::excelToDateTimeObject($row['date'])
                    : null,
                'period_id'   => $this->periodId,
            ],
            [
                'role' => $row['role'],
                'end_date' =>
                !empty($row['date'])
                    ? Date::excelToDateTimeObject($row['date'])
                    : null,
            ]
        );
    }
}
