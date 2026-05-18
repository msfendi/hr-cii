<?php

namespace App\Imports;

use App\Models\Epo;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EpoImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return new Epo([
            'expat_name' => trim($row['expat_name']),
            'gender' => $row['gender'] ?? null,
            'place' => $row['place'] ?? null,

            'date_of_birth' => $this->parseDate($row['date_of_birth']),

            'nationality' => $row['nationality'] ?? null,
            'position' => $row['position'] ?? null,
            'department' => $row['department'] ?? null,

            'termination_date' => $this->parseDate($row['termination_date']),
            'must_leave_date' => $this->parseDate($row['must_leave_indonesia_no_later_than']),

            'epo_cost' => $this->parseCurrency($row['epo_cost']),
            'rptka_cancellation_cost' => $this->parseCurrency($row['rptka_cancellation_cost']),

            'remarks' => $row['remarks'] ?? null,
        ]);
    }

    private function parseDate($date)
    {
        if (!$date) return null;

        try {
            return Carbon::parse($date);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseCurrency($value)
    {
        if (!$value) return 0;

        $value = str_replace(['Rp', ',', ' '], '', $value);

        return (float) $value;
    }
}
