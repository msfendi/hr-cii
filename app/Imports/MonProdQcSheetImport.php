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
 * Setiap baris valid di-create atau di-update berdasarkan kombinasi
 * cpo + code_prod + department_id. Kolom `cpo` opsional di file (kalau
 * kosong / kolomnya tidak ada, disimpan NULL).
 */
class MonProdQcSheetImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    use Importable;

    public function __construct(private MonStageDataService $service) {}

    public function model(array $row)
    {
        $cpo = trim((string) ($row['cpo'] ?? ''));

        // Create-or-update berdasarkan kombinasi cpo + code_prod + department_id.
        // cpo kosong -> NULL (updateOrCreate otomatis memakai IS NULL).
        MonProdQc::updateOrCreate(
            [
                'cpo'           => $cpo !== '' ? $cpo : null,
                'code_prod'     => strtoupper(trim((string) $row['code_prod'])),
                'department_id' => trim((string) $row['department_id']),
            ],
            [
                'jumlah'        => (int) $row['jumlah'],
            ]
        );

        // Sudah disimpan lewat updateOrCreate, jadi return null agar
        // Maatwebsite tidak melakukan insert/save lagi.
        return null;
    }

    /**
     * Excel membaca sel yang isinya angka (mis. kode CPO "12345") sebagai
     * int/float, padahal rule `string` menolaknya. Samakan semua kolom
     * teks jadi string (di-trim) sebelum divalidasi; cpo kosong -> null
     * supaya lolos rule `nullable`.
     */
    public function prepareForValidation($data, int $index)
    {
        foreach (['code_prod', 'cpo', 'department_id'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $data[$key] = trim((string) $data[$key]);
            }
        }

        if (array_key_exists('cpo', $data) && $data['cpo'] === '') {
            $data['cpo'] = null;
        }

        return $data;
    }

    public function rules(): array
    {
        return [
            'code_prod'     => ['required', 'string', 'max:100'],
            'cpo'           => ['nullable', 'string', 'max:100'],
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
