<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\Sheets\ExpatSummarySheet;
use App\Exports\Sheets\ExpatCostSheet;
use App\Exports\Sheets\ExpatOnleaveSheet;
use App\Exports\Sheets\ExpatMealSheet;

class ExpatRekapExport implements WithMultipleSheets
{
    protected $start;
    protected $end;

    public function __construct($start, $end)
    {
        $this->start = $start;
        $this->end   = $end;
    }

    public function sheets(): array
    {
        return [
            new ExpatSummarySheet($this->start, $this->end),
            new ExpatCostSheet($this->start, $this->end),
            new ExpatMealSheet($this->start, $this->end),
            new ExpatOnleaveSheet($this->start, $this->end),
        ];
    }
}
