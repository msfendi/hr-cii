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
        // Must contain NPK
        if (!isset($row[0])) {
            return null;
        }

        Carbon::setLocale('id');

        return new Overtime([
            'NPK' => $row[0],
            'NAMA_KARYAWAN' => $row[1],
            'BAGIAN' => $row[2],
            'DAY' => Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row[4]))->translatedFormat('l'),
            'OVERTIME_DATE' => \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row[4]),
            'JUMLAH_JAM_LEMBUR' => $row[5] ?? null,
        ]);
    }
}
