<?php

namespace App\Imports;

use App\Models\Holiday;
use Maatwebsite\Excel\Concerns\ToModel;

class HolidayImport implements ToModel
{
    public function model(array $row)
    {

        return new Holiday([
            'holiday_date' => $row[0],
            'name' => $row[1],
            'is_national' => 1
        ]);
    }
}
