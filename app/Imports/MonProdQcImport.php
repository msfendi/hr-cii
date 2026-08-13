<?php

namespace App\Imports;

use App\Services\MonStageDataService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Import Excel untuk mon_prod_qc, dari file hasil template
 * MonProdQcTemplateExport (kolom: code_prod, department_id, jumlah).
 *
 * Sama seperti MonStageRemarkImport: file template-nya punya sheet data
 * (index 0) + sheet tersembunyi "Lists" (index 1, sumber dropdown). Tanpa
 * WithMultipleSheets, sheet "Lists" ikut divalidasi dan gagal. Import ini
 * dibatasi HANYA ke sheet index 0.
 */
class MonProdQcImport implements WithMultipleSheets
{
    public function __construct(private MonStageDataService $service)
    {
    }

    public function sheets(): array
    {
        return [
            0 => new MonProdQcSheetImport($this->service),
        ];
    }
}
