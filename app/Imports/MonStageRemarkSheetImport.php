<?php

namespace App\Imports;

use App\Models\MonStageRemark;
use App\Services\MonStageDataService;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Import baris-baris di sheet data ("Template Stage Remark", index 0) dari
 * file template mon_stage_remarks. Dipanggil HANYA untuk sheet index 0 lewat
 * MonStageRemarkImport::sheets() -- sheet tersembunyi "Lists" tidak lewat
 * class ini sama sekali.
 *
 * Setiap baris valid di-create-or-update (upsert) berdasarkan kombinasi
 * cpo + ocf_no + department_id -- kalau kombinasi tsb sudah ada, remark-nya
 * di-update; kalau belum ada, dibuatkan baris baru. Kolom `cpo` opsional
 * di file (kalau kosong / kolomnya tidak ada, disimpan NULL).
 */
class MonStageRemarkSheetImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    use Importable;

    public function __construct(private MonStageDataService $service) {}

    public function model(array $row)
    {
        $ocfNo        = strtoupper(trim((string) $row['ocf_no']));
        $departmentId = trim((string) $row['department_id']);
        $remark       = isset($row['remark']) ? trim((string) $row['remark']) : null;
        $cpo          = trim((string) ($row['cpo'] ?? ''));

        // Create-or-update berdasarkan kombinasi cpo + ocf_no + department_id.
        // cpo kosong -> NULL (updateOrCreate otomatis memakai IS NULL).
        // Di-handle manual (bukan `return new MonStageRemark(...)`) supaya
        // package tidak selalu insert baris baru.
        MonStageRemark::updateOrCreate(
            [
                'cpo'           => $cpo !== '' ? $cpo : null,
                'ocf_no'        => $ocfNo,
                'department_id' => $departmentId,
            ],
            [
                'remark' => $remark,
            ]
        );

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
        foreach (['ocf_no', 'cpo', 'department_id'] as $key) {
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
            'ocf_no'        => ['required', 'string', 'max:100'],
            'cpo'           => ['nullable', 'string', 'max:100'],
            'department_id' => ['required', 'string', Rule::in(MonStageDataService::DEPARTMENTS)],
            'remark'        => ['nullable', 'string'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'ocf_no.required'        => 'ocf_no wajib diisi.',
            'department_id.required' => 'department_id wajib diisi.',
            'department_id.in'       => 'department_id harus salah satu dari: ' . implode(', ', MonStageDataService::DEPARTMENTS),
        ];
    }
}
