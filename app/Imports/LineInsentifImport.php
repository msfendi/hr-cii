<?php

namespace App\Imports;

use App\Models\EmployeeLineAssignment;
use App\Models\LineEfficiency;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\BeforeImport;

class LineInsentifImport implements WithMultipleSheets, WithEvents
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
                LineEfficiency::where('period_id', $this->periodId)->delete();
                EmployeeLineAssignment::where('period_id', $this->periodId)->delete();
            },
        ];
    }

    public function sheets(): array
    {
        return [
            'line_efficiencies' => new LineEfficiencyImport($this->periodId),
            'employee_line_assignments' => new EmployeeLineAssignmentImport($this->periodId),
        ];
    }
}
