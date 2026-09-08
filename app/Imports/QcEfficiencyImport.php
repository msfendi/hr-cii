<?php

namespace App\Imports;

use App\Models\QcEfficiency;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class QcEfficiencyImport implements ToModel, WithHeadingRow
{
    protected $periodId;

    public function __construct($periodId)
    {
        $this->periodId = $periodId;
    }

    public function model(array $row)
    {
        return QcEfficiency::updateOrCreate(
            [
                'line_number' => $row['line_number'],
                'date' =>
                !empty($row['date'])
                    ? Date::excelToDateTimeObject($row['date'])
                    : null,
                'period_id'   => $this->periodId,
            ],
            [
                'efficiency' => $row['efficiency'],
                'days'       => $row['days'],
                'buyer'      => $row['buyer'] ?? null,
            ]
        );
    }
}
