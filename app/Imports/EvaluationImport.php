<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class EvaluationImport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'jobscope' => new EvaluationJobscopeImport(),
            'questionnaire' => new EvaluationQuestionnaireImport(),
        ];
    }
}
