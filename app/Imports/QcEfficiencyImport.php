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
        // third_party opsional: kosong / kolom tidak ada => null
        $thirdParty = isset($row['third_party']) && trim((string) $row['third_party']) !== ''
            ? trim((string) $row['third_party'])
            : null;

        return QcEfficiency::updateOrCreate(
            [
                'line_number' => $row['line_number'],
                'buyer' => $row['buyer'],
                'third_party' => $thirdParty,
                'date' =>
                !empty($row['date'])
                    ? Date::excelToDateTimeObject($row['date'])
                    : null,
                'period_id'   => $this->periodId,
            ],
            [
                'efficiency' => $row['efficiency'],
                'days'       => $row['days'],
            ]
        );
    }
}
