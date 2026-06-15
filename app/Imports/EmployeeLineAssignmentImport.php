<?php

namespace App\Imports;

use App\Models\EmployeeLineAssignment;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Str;

class EmployeeLineAssignmentImport implements ToModel, WithHeadingRow
{
    protected $periodId;

    public function __construct($periodId)
    {
        $this->periodId = $periodId;
    }

    public function model(array $row)
    {
        $startDate = !empty($row['date'])
            ? Date::excelToDateTimeObject($row['date'])
            : null;

        $sectionId = null;

        $biodataUnion = DB::connection('cii')
            ->table('BIODATA')
            ->select(
                'NPK',
                'ID_DEPT',
                'SECTION',
                'NAMA_KARYAWAN',
                'IS_STAFF',
                DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE')
            )
            ->unionAll(
                DB::connection('cii')
                    ->table('BIODATA_KELUAR')
                    ->select(
                        'NPK',
                        'ID_DEPT',
                        'SECTION',
                        'NAMA_KARYAWAN',
                        'IS_STAFF',
                        DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE')
                    )
            );

        $biodata = DB::query()
            ->fromSub($biodataUnion, 'bio')
            ->where('NPK', $row['npk'])
            ->first();

        if ($biodata && is_numeric($biodata->SECTION)) {
            $sectionId = (int) $biodata->SECTION;
        }

        return EmployeeLineAssignment::updateOrCreate(
            [
                'npk'         => $row['npk'],
                'line_number' => $row['line_number'],
                'start_date'  => $startDate,
                'period_id'   => $this->periodId,
            ],
            [
                'name'       => $row['name'],
                'role'       => Str::lower($row['role']),
                'end_date'   => $startDate,
                'work_hours' => $row['work_hours'],
                'section_id' => $sectionId,
            ]
        );
    }
}
