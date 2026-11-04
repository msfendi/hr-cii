<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class EmployeeLateExport implements FromView, ShouldAutoSize
{
    protected $data;
    protected string $label;

    public function __construct($data, string $label)
    {
        $this->data  = $data;
        $this->label = $label;
    }

    public function view(): View
    {
        return view('employee_late.export_excel', [
            'data'  => $this->data,
            'label' => $this->label,
        ]);
    }
}
