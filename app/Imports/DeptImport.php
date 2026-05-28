<?php

namespace App\Imports;

use App\Models\Dept;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Illuminate\Support\Facades\DB;

class DeptImport implements ToModel, WithStartRow
{
    public function startRow(): int
    {
        return 2;
    }

    public function model(array $row)
    {
        if (!isset($row[0]) || trim((string)$row[0]) === '') {
            return null;
        }

        $namaDept = strtoupper(trim((string)$row[0]));
        $idParentDept = isset($row[1]) && trim((string)$row[1]) !== '' ? (int)trim((string)$row[1]) : null;
        $isSewing = isset($row[2]) && (int)trim((string)$row[2]) === 1 ? 0 : 1; // 1=Ya -> IS_SEWING=0 di db (logic existing)
        $section = isset($row[3]) && trim((string)$row[3]) !== '' ? strtoupper(trim((string)$row[3])) : 'CHUTEX';

        $exists = Dept::where('DEPARTEMENT', $namaDept)->first();
        if ($exists) {
            $exists->update([
                'id_parent_dept' => $idParentDept,
                'IS_SEWING' => $isSewing,
                'SECTION' => $section
            ]);
            return null;
        }

        $idDept = DB::connection('cii')->table('DEPT')->max('ID_DEPT') + 1;

        return new Dept([
            'ID_DEPT' => $idDept,
            'DEPARTEMENT' => $namaDept,
            'id_parent_dept' => $idParentDept,
            'IS_SEWING' => $isSewing,
            'SECTION' => $section,
        ]);
    }
}
