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
 * - Saat import, semua data operasi jumper pada period_id ini dihapus
 *   terlebih dulu, lalu diganti dengan data baru dari Excel
 *   (hapus + insert dalam 1 transaksi, jadi kalau gagal data lama aman)
 * - NPK + operasi yang sama (abaikan nomor urut/huruf besar-kecil) tidak
 *   akan terhitung dobel
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

            // Hapus data lama pada period ini
            LineJumperOperation::where('period_id', $this->periodId)->delete();

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
