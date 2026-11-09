<?php

namespace App\Imports;

use App\Models\CuttingEfficiency;
use App\Models\EmployeeCuttingAssignment;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\BeforeImport;

class CuttingInsentifImport implements WithMultipleSheets, WithEvents
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
                CuttingEfficiency::where('period_id', $this->periodId)->delete();
                EmployeeCuttingAssignment::where('period_id', $this->periodId)->delete();
            },
        ];
    }

    public function sheets(): array
    {
        return [
            'cutting_efficiencies' => new CuttingEfficiencyImport($this->periodId),
            'employee_cutting_assignments' => new EmployeeCuttingAssignmentImport($this->periodId),
        ];
    }
}
