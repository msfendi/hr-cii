<?php

namespace App\Imports;

use App\Models\MonProdQc;
use App\Services\MonStageDataService;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Import baris-baris di sheet data ("Template Prod QC", index 0) dari file
 * template mon_prod_qc. Dipanggil HANYA untuk sheet index 0 lewat
 * MonProdQcImport::sheets() -- sheet tersembunyi "Lists" tidak lewat class
 * ini sama sekali.
 *
 * Setiap baris valid langsung di-insert sebagai baris baru.
 */
class MonProdQcSheetImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    use Importable;

    public function __construct(private MonStageDataService $service)
    {
    }

    public function model(array $row)
    {
        return new MonProdQc([
            'code_prod'     => strtoupper(trim((string) $row['code_prod'])),
            'department_id' => trim((string) $row['department_id']),
            'jumlah'        => (int) $row['jumlah'],
        ]);
    }

    public function rules(): array
    {
        return [
            'code_prod'     => ['required', 'string', 'max:100'],
            'department_id' => ['required', 'string', Rule::in(MonStageDataService::DEPARTMENTS)],
            'jumlah'        => ['required', 'numeric', 'min:0'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'code_prod.required'     => 'code_prod wajib diisi.',
            'department_id.required' => 'department_id wajib diisi.',
            'department_id.in'       => 'department_id harus salah satu dari: ' . implode(', ', MonStageDataService::DEPARTMENTS),
            'jumlah.required'        => 'jumlah wajib diisi.',
            'jumlah.numeric'         => 'jumlah harus berupa angka.',
        ];
    }
}
