<?php

namespace App\Imports;

use App\Models\MonProdQc;
use App\Services\MonStageDataService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import sheet data (index 0) untuk mon_prod_qc.
 * Kolom: code_prod, department_id, jumlah.
 *
 * Baris di-createOrUpdate berdasarkan kombinasi code_prod + department_id:
 * jika sudah ada -> update jumlah, jika belum -> buat baru.
 */
class MonProdQcSheetImport implements ToCollection, WithHeadingRow
{
    public function __construct(private MonStageDataService $service) {}

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $codeProd     = trim((string) ($row['code_prod'] ?? ''));
                $departmentId = $row['department_id'] ?? null;

                // lewati baris kosong / tidak lengkap
                if ($codeProd === '' || $departmentId === null || $departmentId === '') {
                    continue;
                }

                MonProdQc::updateOrCreate(
                    [
                        'code_prod'     => $codeProd,
                        'department_id' => (int) $departmentId,
                    ],
                    [
                        'jumlah' => (int) ($row['jumlah'] ?? 0),
                    ]
                );
            }
        });
    }
}
