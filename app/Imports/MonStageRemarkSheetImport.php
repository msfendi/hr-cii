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
 * Setiap baris valid langsung di-insert sebagai baris baru (tidak
 * upsert/replace -- 1 OCF boleh punya beberapa remark per department dari
 * waktu ke waktu, riwayatnya tetap disimpan semua).
 */
class MonStageRemarkSheetImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows
{
    use Importable;

    public function __construct(private MonStageDataService $service)
    {
    }

    public function model(array $row)
    {
        return new MonStageRemark([
            'ocf_no'        => strtoupper(trim((string) $row['ocf_no'])),
            'department_id' => trim((string) $row['department_id']),
            'remark'        => isset($row['remark']) ? trim((string) $row['remark']) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'ocf_no'        => ['required', 'string', 'max:100'],
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
