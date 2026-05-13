<?php

namespace App\Imports;

use App\Models\EmployeeShift;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class EmployeeShiftImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Check if required columns exist in the row
            if (!isset($row['npk']) || !isset($row['shift']) || !isset($row['shift_date'])) {
                continue;
            }

            // Extract shift ID from format "ID - Name" or just "ID"
            $shiftParts = explode('-', $row['shift']);
            $shiftId = trim($shiftParts[0]);

            // Parse shift date (handle both Excel serialized date and standard string)
            $shiftDate = null;
            if (is_numeric($row['shift_date'])) {
                $shiftDate = Date::excelToDateTimeObject($row['shift_date'])->format('Y-m-d');
            } else {
                try {
                    $shiftDate = Carbon::parse($row['shift_date'])->format('Y-m-d');
                } catch (\Exception $e) {
                    continue; // Skip invalid dates
                }
            }

            if ($row['npk'] && $shiftId && $shiftDate) {
                EmployeeShift::updateOrCreate(
                    [
                        'npk' => $row['npk'],
                        'shift_date' => $shiftDate
                    ],
                    [
                        'shift_id' => $shiftId
                    ]
                );
            }
        }
    }
}
