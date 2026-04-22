<?php

namespace App\Imports;

use App\Models\CuttingEfficiency;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class CuttingEfficiencyImport implements ToModel, WithHeadingRow
{
    protected $periodId;

    public function __construct($periodId)
    {
        $this->periodId = $periodId;
    }

    public function model(array $row)
    {
        return CuttingEfficiency::updateOrCreate(
            [
                'npk' => $row['npk'],
                'date' =>
                !empty($row['date'])
                    ? Date::excelToDateTimeObject($row['date'])
                    : null,
                'period_id'   => $this->periodId,
            ],
            [
                'efficiency' => $row['efficiency']
            ]
        );
    }
}
