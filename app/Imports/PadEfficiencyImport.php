<?php

namespace App\Imports;

use App\Models\PadEfficiency;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PadEfficiencyImport implements ToModel, WithHeadingRow
{
    protected $periodId;

    public function __construct($periodId)
    {
        $this->periodId = $periodId;
    }

    public function model(array $row)
    {
        return PadEfficiency::updateOrCreate(
            [
                'npk' => $row['npk'],
                'dept' => $row['dept'],
                'date' => $row['date'],
                'period_id'   => $this->periodId,
            ],
            [
                'efficiency' => $row['efficiency'],
                'piece' => $row['piece']
            ]
        );
    }
}
