<?php

namespace App\Imports;

use App\Models\EvaluationQuestionnaire;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EvaluationQuestionnaireImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return EvaluationQuestionnaire::updateOrCreate(
            [
                'jobscope_id' => $row['jobscope_id']
            ],
            [
                'question' => $row['question'],
                'optiona' => $row['optiona'],
                'optionb' => $row['optionb'],
                'optionc' => $row['optionc'],
                'optiond' => $row['optiond'],
                'correct_answer' => $row['correct_answer']
            ]
        );
    }
}
