<?php

namespace App\Imports;

use App\Models\ParentDept;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

class ParentDeptImport implements ToModel, WithStartRow
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

        $name = strtoupper(trim((string)$row[0]));

        $exists = ParentDept::where('parent_dept_name', $name)->first();
        if ($exists) {
            return null;
        }

        return new ParentDept([
            'parent_dept_name' => $name,
        ]);
    }
}
