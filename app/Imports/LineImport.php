<?php

namespace App\Imports;

use App\Models\Line;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class LineImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return Line::updateOrCreate(
            [
                'line_number' => $row['line_number']
            ],
            [
                'line_name' => $row['line_name']
            ]
        );
    }
}
