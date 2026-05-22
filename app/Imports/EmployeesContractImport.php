<?php

namespace App\Imports;

use App\Models\EmployeesContract;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Str;

class EmployeesContractImport implements ToModel, WithHeadingRow
{
    public int $inserted = 0;
    public int $skipped  = 0;

    /**
     * Heading row mapping (kolom Excel → field model):
     * npk | contract_ke | start_date | end_date | month_duration | status_contract | salary | allowance
     */
    public function model(array $row)
    {
        // Skip baris kosong
        if (empty($row['npk'])) {
            $this->skipped++;
            return null;
        }

        // Parse tanggal — bisa berupa serial Excel atau string
        $startDate = $this->parseDate($row['start_date'] ?? null);
        $endDate   = $this->parseDate($row['end_date']   ?? null);

        if (!$startDate || !$endDate) {
            $this->skipped++;
            return null;
        }

        $this->inserted++;

        return EmployeesContract::updateOrCreate(
            [
                'npk'             => strtoupper(trim($row['npk'])),
                'contract_ke'     => (int) ($row['contract_ke'] ?? 1)
            ],
            [
                'npk'             => strtoupper(trim($row['npk'])),
                'contract_ke'     => (int) ($row['contract_ke'] ?? 1),
                'start_date'      => $startDate,
                'end_date'        => $endDate,
                'month_duration'  => (string) ($row['month_duration'] ?? ''),
                'day_duration'    => (string) ($row['day_duration'] ?? ''),
                'status_contract' => strtoupper(trim($row['status_contract'] ?? 'AKTIF')),
                'type'            => 'CONTRACT',
                'salary'          => (float) ($row['salary']    ?? 0),
                'allowance'       => (float) ($row['allowance'] ?? 0),
                'daily_salary'    => (float) ($row['daily_salary'] ?? 0),
                'pph21'           => (float) ($row['pph21'] ?? 0),
            ]
        );
    }

    private function parseDate($value): ?string
    {
        if (empty($value)) return null;

        try {
            // Jika serial Excel (integer)
            if (is_numeric($value)) {
                $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                return Carbon::instance($date)->toDateString();
            }
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
