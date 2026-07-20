<?php

namespace App\Imports;

use App\Models\MonOrder;
use App\Support\SqlServerChunk;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithStartRow;

class OrdersImport implements ToCollection, WithStartRow, SkipsEmptyRows, WithChunkReading, ShouldQueue
{
    use Importable;

    public function __construct(
        protected string $importBatch,
        protected int $totalRows = 0
    ) {}

    public function startRow(): int
    {
        return 2;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function collection(Collection $rows): void
    {
        $now = now();
        $insert = [];
        $processedInChunk = 0;
        $lastLabel = null;

        foreach ($rows as $row) {
            if (blank($row[1] ?? null)) {
                continue;
            }

            $processedInChunk++;
            // dipakai untuk teks progress: "Style: ..." biar user tahu lagi diproses data apa
            $lastLabel = trim((string) ($row[6] ?? $row[1] ?? ''));

            $insert[] = [
                'uraian'               => (string) $row[1],
                'ocf_no'                => $this->str($row[2] ?? null),
                'buyer_po'              => $this->str($row[3] ?? null),
                'buyer'                 => $this->str($row[4] ?? null),
                'brand'                 => $this->str($row[5] ?? null),
                'style'                 => $this->str($row[6] ?? null),
                'item'                  => $this->str($row[7] ?? null),
                'qty_ord'               => $this->num($row[8] ?? null),
                'destination'           => $this->str($row[9] ?? null),
                'artwork'               => $this->str($row[10] ?? null),
                'sewing_process'        => $this->str($row[11] ?? null),
                'production_delivery'   => $this->date($row[12] ?? null),
                'buyer_delivery'        => $this->date($row[13] ?? null),
                'prod_start'            => $this->date($row[14] ?? null),
                'prod_end'              => $this->date($row[15] ?? null),
                'shipment_mode'         => $this->str($row[16] ?? null),
                'material_fab'          => $this->str($row[17] ?? null),
                'fabric'                => $this->str($row[18] ?? null),
                'sample'                => $this->str($row[19] ?? null),
                'thread'                => $this->str($row[20] ?? null),
                'pad_htl'               => $this->str($row[21] ?? null),
                'main_label'            => $this->str($row[22] ?? null),
                'care_label'            => $this->str($row[23] ?? null),
                'button_snap'           => $this->str($row[24] ?? null),
                'tape'                  => $this->str($row[25] ?? null),
                'hangtag'               => $this->str($row[26] ?? null),
                'price_ticket'          => $this->str($row[27] ?? null),
                'size_strip'            => $this->str($row[28] ?? null),
                'polybag'               => $this->str($row[29] ?? null),
                'sticker'               => $this->str($row[30] ?? null),
                'hanger'                => $this->str($row[31] ?? null),
                'sizer'                 => $this->str($row[32] ?? null),
                'carton_box'            => $this->str($row[33] ?? null),
                'vessel_book'           => $this->str($row[34] ?? null),
                'payment_terms'         => $this->str($row[35] ?? null),
                'column17'              => $this->str($row[36] ?? null),
                'fob'                   => $this->str($row[37] ?? null),
                'price'                 => $this->num($row[38] ?? null),
                'column18'              => $this->str($row[39] ?? null),
                'column19'              => $this->str($row[40] ?? null),
                'cmt'                   => $this->str($row[41] ?? null),
                'price20'               => $this->str($row[42] ?? null),
                'column21'              => $this->str($row[43] ?? null),
                'sample2'               => $this->str($row[44] ?? null),
                'smv'                   => $this->num($row[45] ?? null),
                'planned_qty'           => $this->num($row[46] ?? null),
                'sewing_start_date'     => $this->date($row[47] ?? null),
                'remarks'               => $this->str($row[48] ?? null),
                'import_batch'          => $this->importBatch,
                'created_at'            => $now,
                'updated_at'            => $now,
            ];
        }

        if (!empty($insert)) {
            $chunkSize = SqlServerChunk::rows(columnsPerRow: 51);
            foreach (array_chunk($insert, $chunkSize) as $chunk) {
                MonOrder::insert($chunk);
            }
        }

        $this->updateProgress($processedInChunk, $lastLabel);

        unset($insert, $rows);
    }

    /**
     * Dipanggil Laravel Excel kalau job chunk ini gagal (mis. error DB).
     */
    public function failed(\Throwable $exception): void
    {
        $key = "order_import:{$this->importBatch}";
        $state = Cache::get($key, ['processed' => 0, 'total' => $this->totalRows]);
        $state['status'] = 'error';
        $state['message'] = $exception->getMessage();
        Cache::put($key, $state, now()->addHour());
    }

    private function updateProgress(int $addedRows, ?string $lastLabel): void
    {
        $key = "order_import:{$this->importBatch}";
        $state = Cache::get($key, [
            'processed' => 0,
            'total'     => $this->totalRows,
            'status'    => 'processing',
            'last'      => null,
        ]);

        $state['processed'] = ($state['processed'] ?? 0) + $addedRows;
        if ($lastLabel) {
            $state['last'] = $lastLabel;
        }
        $state['status'] = ($state['total'] > 0 && $state['processed'] >= $state['total'])
            ? 'done'
            : 'processing';

        Cache::put($key, $state, now()->addHour());
    }

    private function str($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return trim((string) $value);
    }

    private function num($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }
        return (float) $value;
    }

    private function date($value): ?string
    {
        if (blank($value)) {
            return null;
        }
        try {
            if (is_numeric($value)) {
                return Carbon::instance(
                    \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)
                )->format('Y-m-d');
            }
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
