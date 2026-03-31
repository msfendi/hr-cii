<?php

namespace App\Imports;

use App\Models\LineEfficiency;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class LineEfficiencyImport implements ToModel, WithHeadingRow
{
    protected $periodId;

    public function __construct($periodId)
    {
        $this->periodId = $periodId;
    }

    public function model(array $row)
    {
        return LineEfficiency::updateOrCreate(
            [
                'line_number' => $row['line_number'],
                'date' => $row['date'],
                'period_id'   => $this->periodId,
            ],
            [
                'efficiency' => $row['efficiency']
            ]
        );
    }
}
