<?php

namespace App\Imports;

use App\Models\Attendance;
use App\Models\Audit;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

class AttendanceImport implements ToModel, WithStartRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */

    public function startRow(): int
    {
        return 2;
    }

    public function model(array $row)
    {
        // return new Audit([
        //     'NPK' => $row[2],
        //     'TANGGAL' => Carbon::parse(Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row[0])))->format('Y-m-d'),
        //     'SUBDIVISI' => $row[3],
        //     'JAM_PAGI' => Carbon::createFromTimestamp($row[6] * 24 * 60 * 60)->format('H:i') ?? '-',
        //     'JAM_SIANG' => Carbon::createFromTimestamp($row[7] * 24 * 60 * 60)->format('H:i') ?? '-',
        //     'JAM_MALAM' => Carbon::createFromTimestamp($row[8] * 24 * 60 * 60)->format('H:i') ?? '-',
        //     'STATUS' => $row[9] ?? '-',
        //     'VOID' => 'false'
        // ]);

        return new Audit([
            'NPK' => $row[3],
            'NAMA_KARYAWAN' => $row[2],
            'TANGGAL' => Carbon::parse(Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row[1])))->format('Y-m-d'),
            'SUBDIVISI' => $row[4],
            'KODE_BAGIAN' => $row[5],
            'JAM_PAGI' => $row[8] ?? null,
            'JAM_SIANG' => $row[9] ?? null,
            'JAM_MALAM' => $row[10] ?? null,
            'STATUS' => $row[11] ?? null,
        ]);
    }
}
