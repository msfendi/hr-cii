<?php

namespace App\Imports;

use App\Models\HeatEfficiency;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\BeforeImport;

class HeatInsentifImport implements WithMultipleSheets, WithEvents
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
                HeatEfficiency::where('period_id', $this->periodId)->delete();
            },
        ];
    }

    public function sheets(): array
    {
        return [
            'heat_efficiencies' => new HeatEfficiencyImport($this->periodId),
            // 'employee_heat_assignments' => new EmployeeHeatAssignmentImport($this->periodId),
        ];
    }
}
