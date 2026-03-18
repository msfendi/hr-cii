<?php

namespace App\Imports;

use App\Models\EvaluationJobscope;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EvaluationJobscopeImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return EvaluationJobscope::updateOrCreate(
            [
                'job_code' => $row['job_code']
            ],
            [
                'job_name' => $row['job_name'],
                'description' => $row['description'],
                'qr_code' => $row['qr_code']
            ]
        );
    }
}
