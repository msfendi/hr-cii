<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class EvaluationTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new Sheets\EvaluationJobscopeSheet(),
            new Sheets\EvaluationQuestionnaireSheet(),
        ];
    }
}
