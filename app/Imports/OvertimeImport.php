<?php

namespace App\Imports;

use App\Models\Overtime;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

class OvertimeImport implements ToModel, WithStartRow
{
    /**
     * @return int
     */
    public function startRow(): int
    {
        return 2;
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Must contain NPK and Date
        if (!isset($row[0]) || empty($row[0]) || !isset($row[4]) || empty($row[4])) {
            return null;
        }

        try {
            // Handle date from excel (could be number or string)
            if (is_numeric($row[4])) {
                $overtimeDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row[4]);
            } else {
                $overtimeDate = Carbon::parse($row[4]);
            }

            Carbon::setLocale('id');

            return new Overtime([
                'NPK' => $row[0],
                'NAMA_KARYAWAN' => $row[1],
                'BAGIAN' => $row[2],
                'DAY' => Carbon::instance($overtimeDate)->translatedFormat('l'),
                'OVERTIME_DATE' => $overtimeDate,
                'JUMLAH_JAM_LEMBUR' => $row[5] ?? null,
            ]);
        } catch (\Exception $e) {
            return null;
        }
    }
}
