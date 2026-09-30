<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\MonProdQc;
use App\Services\MonStageDataService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import sheet data (index 0) untuk mon_prod_qc.
 * Kolom: code_prod, department_id, jumlah.
 *
 * Kolom department_id di Excel berisi NAMA departemen (mis. "QC"), bukan
 * angka, jadi dicari dulu ke tabel departemen untuk mendapatkan ID-nya.
 * Angka ID langsung juga tetap diterima.
 *
 * Baris di-createOrUpdate berdasarkan code_prod + department_id.
 */
class MonProdQcSheetImport implements ToCollection, WithHeadingRow
{
    /** Kolom di tabel departemen yang dicocokkan dengan isi Excel. */
    private const DEPT_COLUMN = 'name';

    public function __construct(private MonStageDataService $service) {}

    public function collection(Collection $rows)
    {
        // peta: nama departemen (huruf kecil) => id
        $departments = Department::query()
            ->pluck('id', self::DEPT_COLUMN)
            ->mapWithKeys(fn($id, $name) => [mb_strtolower(trim((string) $name)) => $id]);

        $validIds = $departments->values()->flip();
        $unknown  = [];

        DB::transaction(function () use ($rows, $departments, $validIds, &$unknown) {
            foreach ($rows as $index => $row) {
                $codeProd = trim((string) ($row['code_prod'] ?? ''));
                $deptRaw  = trim((string) ($row['department_id'] ?? ''));

                // lewati baris kosong / tidak lengkap
                if ($codeProd === '' || $deptRaw === '') {
                    continue;
                }

                $departmentId = $departments->get(mb_strtolower($deptRaw));

                // fallback: isi Excel sudah berupa ID yang valid
                if ($departmentId === null && ctype_digit($deptRaw) && $validIds->has((int) $deptRaw)) {
                    $departmentId = (int) $deptRaw;
                }

                if ($departmentId === null) {
                    $unknown[] = 'Baris ' . ($index + 2) . ": departemen \"{$deptRaw}\" tidak ditemukan";
                    continue;
                }

                MonProdQc::updateOrCreate(
                    [
                        'code_prod'     => $codeProd,
                        'department_id' => $departmentId,
                    ],
                    [
                        'jumlah' => (int) ($row['jumlah'] ?? 0),
                    ]
                );
            }

            // ada departemen tak dikenal -> batalkan seluruh import
            if ($unknown) {
                throw ValidationException::withMessages(['file' => $unknown]);
            }
        });
    }
}
