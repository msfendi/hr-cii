<?php

namespace App\Imports;

use App\Services\MonStageDataService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Import Excel untuk mon_stage_remarks, dari file hasil template
 * MonStageRemarkTemplateExport (kolom: ocf_no, department_id, remark).
 *
 * PENTING: file template-nya punya 2 sheet -- sheet data ("Template Stage
 * Remark", index 0) dan sheet tersembunyi "Lists" (index 1, sumber
 * dropdown ocf_no/department_id, header-nya "A_list"/"B_list" bukan
 * "ocf_no"/"department_id"). Tanpa WithMultipleSheets, Laravel Excel
 * memproses SEMUA sheet -- baris di sheet "Lists" ikut divalidasi dan
 * gagal ("ocf_no field is required", dst). Implementasi WithMultipleSheets
 * di bawah membatasi import HANYA ke sheet index 0, sheet "Lists" di-skip
 * sepenuhnya.
 */
class MonStageRemarkImport implements WithMultipleSheets
{
    public function __construct(private MonStageDataService $service)
    {
    }

    public function sheets(): array
    {
        return [
            0 => new MonStageRemarkSheetImport($this->service),
        ];
    }
}
