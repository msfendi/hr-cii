<?php

namespace App\Imports;

use App\Models\LineJumperOperation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Kolom Excel: npk | operation | machine
 *
 * - 1 baris = 1 operasi yang dikuasai jumper pada periode tsb
 * - NPK + operasi yang sama (abaikan nomor urut/huruf besar-kecil) tidak
 *   akan terhitung dobel, import ulang cukup meng-update machine
 */
class LineJumperInsentifImport implements ToCollection, WithHeadingRow
{
    protected int $periodId;

    public int $imported = 0;
    public int $skipped  = 0;

    public function __construct($periodId)
    {
        $this->periodId = (int) $periodId;
    }

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {

                $npk       = trim((string) ($row['npk'] ?? ''));
                $operation = trim(preg_replace('/\s+/', ' ', (string) ($row['operation'] ?? '')));
                $machine   = trim((string) ($row['machine'] ?? ''));

                if ($npk === '' || $operation === '') {
                    $this->skipped++;
                    continue;
                }

                $key = LineJumperOperation::makeOperationKey($operation);

                if ($key === '') {
                    $this->skipped++;
                    continue;
                }

                LineJumperOperation::updateOrCreate(
                    [
                        'period_id'     => $this->periodId,
                        'npk'           => $npk,
                        'operation_key' => $key,
                    ],
                    [
                        'operation' => $operation,
                        'machine'   => $machine !== '' ? $machine : null,
                    ]
                );

                $this->imported++;
            }
        });
    }
}
