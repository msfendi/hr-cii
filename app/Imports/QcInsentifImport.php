<?php

namespace App\Imports;

use App\Models\EmployeeQcAssignment;
use App\Models\QcEfficiency;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\BeforeImport;

class QcInsentifImport implements WithMultipleSheets, WithEvents
{
    protected $periodId;

    public function __construct($periodId)
    {
        $this->periodId = $periodId;
    }

    public function registerEvents(): array
    {
        return [
            // Sebelum sheet dibaca: hapus data lama pada period_id ini,
            // lalu sheet-sheet di bawah meng-insert data baru dari Excel.
            BeforeImport::class => function (BeforeImport $event) {
                QcEfficiency::where('period_id', $this->periodId)->delete();
                EmployeeQcAssignment::where('period_id', $this->periodId)->delete();
            },
        ];
    }

    public function sheets(): array
    {
        return [
            0 => new QcEfficiencyImport($this->periodId),
            1 => new EmployeeQcAssignmentImport($this->periodId),
        ];
    }
}
