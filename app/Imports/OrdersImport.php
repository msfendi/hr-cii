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
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

class OrdersImport implements
    ToCollection,
    WithStartRow,
    SkipsEmptyRows,
    WithChunkReading,
    WithMultipleSheets,
    ShouldQueue,
    WithHeadingRow
{
    use Importable;

    /**
     * Hanya import sheet dengan nama ORDER
     */
    public function sheets(): array
    {
        return [
            'ORDER' => $this,
        ];
    }

    /**
     * Header berada di baris 1
     * Data dimulai dari baris 2
     */
    public function startRow(): int
    {
        return 2;
    }

    public function __construct(
        protected string $importBatch,
        protected int $totalRows = 0
    ) {
        /**
         * PENTING:
         * Default formatter Laravel Excel untuk WithHeadingRow
         * adalah 'slug', yang mengubah semua nama header menjadi
         * lowercase + underscore (contoh: "BUYER PO" -> "buyer_po").
         *
         * Karena kode di bawah ini mengambil kolom berdasarkan
         * NAMA HEADER ASLI persis seperti di file Excel
         * (mis. $row['BUYER PO'], $row['QTY ORD (PCS)']),
         * formatter HARUS di-set ke 'none' agar key array
         * tetap sama persis dengan header di Excel.
         *
         * Catatan: karena proses ini berjalan via queue (ShouldQueue),
         * setelan ini idealnya JUGA didaftarkan secara global di
         * AppServiceProvider::boot() atau config/excel.php, karena
         * queue worker adalah proses PHP terpisah dan baris ini
         * (constructor) tidak ikut ter-restore saat job di-unserialize
         * di worker. Baris di sini hanya sebagai jaring pengaman
         * untuk proses import yang berjalan sinkron / langsung.
         */
        HeadingRowFormatter::default('none');
    }

    /**
     * Ukuran chunk pembacaan Excel
     */
    public function chunkSize(): int
    {
        return 1000;
    }

    /**
     * Proses data dari sheet ORDER
     */
    public function collection(Collection $rows): void
    {
        $now = now();

        $insert = [];

        $processedInChunk = 0;

        $lastLabel = null;

        foreach ($rows as $row) {

            // Abaikan baris jika kolom uraian kosong
            if (blank($row['uraian'] ?? null)) {
                continue;
            }

            // Cek apakah kolom Catatan berisi "CANCEL" (case insensitive, trim whitespace)
            $catatan = $this->str($row['Catatan'] ?? null);
            if ($catatan !== null && strtoupper(trim($catatan)) === 'CANCEL') {
                continue;
            }

            $processedInChunk++;

            // Untuk informasi progress
            $lastLabel = trim(
                (string) ($row['ITEM'] ?? $row['uraian'] ?? '')
            );

            $insert[] = [
                'uraian'               => (string) $row['uraian'],
                'ocf_no'               => $this->str($row['OCF'] ?? null),
                'sub_ref'              => $this->str($row['Sub Ref'] ?? null),
                'buyer_po'             => $this->str($row['BUYER PO'] ?? null),
                'buyer'                => $this->str($row['BUYER'] ?? null),
                'brand'                => $this->str($row['BRAND'] ?? null),
                'style'                => $this->str($row['STYLE'] ?? null),
                'item'                 => $this->str($row['ITEM'] ?? null),
                'qty_ord'              => $this->num($row['QTY ORD (PCS)'] ?? null),
                'destination'          => $this->str($row['DESTINATION'] ?? null),
                'artwork'              => $this->str($row['ARTWORK'] ?? null),
                'sewing_process'       => $this->str($row['SEWING PROCESS'] ?? null),
                'production_delivery'  => $this->date($row['PRODUCTION DELIVERY'] ?? null),
                'buyer_delivery'       => $this->date($row['BUYER DELIVERY'] ?? null),
                'prod_start'           => $this->date($row['PROD START'] ?? null),
                'prod_end'             => $this->date($row['PROD END'] ?? null),
                'shipment_mode'        => $this->str($row['SHIPMENT MODE'] ?? null),
                'material_fab'         => $this->str($row['MATERIAL (FAB)'] ?? null),
                'fabric'               => $this->str($row['FABRIC'] ?? null),
                'sample'               => $this->str($row['SAMPLE'] ?? null),
                'thread'               => $this->str($row['THREAD'] ?? null),
                'pad_htl'              => $this->str($row['PAD / HTL'] ?? null),
                'main_label'           => $this->str($row['MAIN LABEL'] ?? null),
                'care_label'           => $this->str($row['CARE LABEL'] ?? null),
                'button_snap'          => $this->str($row['BUTTON/SNAP'] ?? null),
                'tape'                  => $this->str($row['TAPE'] ?? null),
                'hangtag'               => $this->str($row['HANGTAG'] ?? null),
                'price_ticket'          => $this->str($row['PRICE TICKET'] ?? null),
                'size_strip'            => $this->str($row['SIZE STRIP'] ?? null),
                'polybag'               => $this->str($row['POLYBAG'] ?? null),
                'sticker'               => $this->str($row['STICKER'] ?? null),
                'hanger'                => $this->str($row['HANGER'] ?? null),
                'sizer'                 => $this->str($row['SIZER'] ?? null),
                'carton_box'            => $this->str($row['CARTON BOX'] ?? null),
                'vessel_book'           => $this->str($row['VESSEL BOOK'] ?? null),
                'payment_terms'         => $this->str($row['Payment terms'] ?? null),
                'column17'              => $this->str($row['Column17'] ?? null),
                'fob'                   => $this->str($row['FOB'] ?? null),
                'price'                 => $this->num($row['PRICE'] ?? null),
                'column18'              => $this->str($row['Column18'] ?? null),
                'column19'              => $this->str($row['Column19'] ?? null),
                'cmt'                   => $this->str($row['CMT'] ?? null),
                'price20'               => $this->str($row['PRICE20'] ?? null),
                'column21'              => $this->str($row['Column21'] ?? null),
                'sample2'              => $this->str($row['SAMPLE2'] ?? null),
                'smv'                   => $this->num($row['SMV'] ?? null),
                'planned_qty'          => $this->num($row['Planned Qty'] ?? null),
                'sewing_start_date'    => $this->date($row['Sewing Start Date'] ?? null),
                'remarks'              => $this->str($row['REMARKS'] ?? null),
                'column1'              => $this->str($row['Column1'] ?? null),
                'column2'              => $this->str($row['Column2'] ?? null),
                'column3'              => $this->str($row['Column3'] ?? null),
                'catatan'              => $this->str($row['Catatan'] ?? null),
                'season'              => $this->str($row['SEASON'] ?? null),


                'import_batch'         => $this->importBatch,

                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        }

        /**
         * SQL Server memiliki batas maksimum parameter
         *
         * 2100 parameter / jumlah kolom per row
         */
        if (!empty($insert)) {

            $chunkSize = SqlServerChunk::rows(
                columnsPerRow: 55
            );

            foreach (array_chunk($insert, $chunkSize) as $chunk) {
                MonOrder::insert($chunk);
            }
        }

        /**
         * Update progress import
         */
        $this->updateProgress(
            $processedInChunk,
            $lastLabel
        );

        unset($insert, $rows);
    }

    /**
     * Dipanggil ketika proses import gagal
     */
    public function failed(\Throwable $exception): void
    {
        $key = "order_import:{$this->importBatch}";

        $state = Cache::get(
            $key,
            [
                'processed' => 0,
                'total'     => $this->totalRows,
            ]
        );

        $state['status'] = 'error';

        $state['message'] = $exception->getMessage();

        Cache::put(
            $key,
            $state,
            now()->addHour()
        );
    }

    /**
     * Update progress import
     */
    private function updateProgress(
        int $addedRows,
        ?string $lastLabel
    ): void {

        $key = "order_import:{$this->importBatch}";

        $state = Cache::get(
            $key,
            [
                'processed' => 0,
                'total'     => $this->totalRows,
                'status'    => 'processing',
                'last'      => null,
            ]
        );

        $state['processed'] =
            ($state['processed'] ?? 0) + $addedRows;

        if ($lastLabel) {
            $state['last'] = $lastLabel;
        }

        $state['status'] =
            ($state['total'] > 0 &&
                $state['processed'] >= $state['total'])
            ? 'done'
            : 'processing';

        Cache::put(
            $key,
            $state,
            now()->addHour()
        );
    }

    /**
     * Convert value menjadi string
     */
    private function str($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim((string) $value);
    }

    /**
     * Convert value menjadi angka
     */
    private function num($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (float) $value;
    }

    /**
     * Convert Excel date atau string date
     * menjadi format Y-m-d
     */
    private function date($value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {

            if (is_numeric($value)) {

                return Carbon::instance(
                    ExcelDate::excelToDateTimeObject($value)
                )->format('Y-m-d');
            }

            return Carbon::parse($value)
                ->format('Y-m-d');
        } catch (\Throwable $e) {

            return null;
        }
    }
}
