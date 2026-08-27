<?php

namespace App\Console\Commands;

use App\Models\MonProdLine;
use App\Support\SqlServerChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncProdLineFromSmartit extends Command
{
    protected $signature = 'monitoring:sync-prod-line';

    protected $description = 'Sinkronisasi FULL data Production Line dari smartit (tanpa filter tanggal) ke tabel mon_prod_lines';

    public function handle(): int
    {
        $this->info('Mengambil SEMUA data Production Line dari smartit ...');

        // Query tanpa WHERE tanggal
        $sql = <<<SQL
            SELECT hd.line_id, phd.code_prod, hd.department_id, hd.tgl_produksi, hd.barang_code, b.barang_name, b.barang_category, hd.jumlah, hd.destination, hd.no_surat_jalan,
                   hd.create_by, FORMAT(hd.create_date, 'yyyy-MM-dd HH:mm') AS create_date
                        ,isnull((select decimal_status from ms_satuan where satuan_code = b.satuan_code), 2) as digit, hd.total_nilai
            FROM line_hd hd
            inner join ms_barang b on b.barang_code = hd.barang_code
            INNER JOIN prd_plan_hd phd on hd.prod_id = phd.prod_id
            ORDER BY hd.tgl_produksi ASC
        SQL;

        $rows = DB::connection('smartit')->select($sql);

        $this->info('Baris diterima dari smartit: ' . count($rows));

        $now = now();
        $inserted = 0;

        $chunkSize = SqlServerChunk::rows(columnsPerRow: 15);

        DB::transaction(function () use ($rows, $chunkSize, $now, &$inserted) {
            MonProdLine::truncate();

            foreach (array_chunk($rows, $chunkSize) as $chunk) {
                $data = array_map(function ($r) use ($now) {
                    return [
                        'line_id'          => $r->line_id,
                        'code_prod'        => $r->code_prod,
                        'department_id'    => $r->department_id,
                        'tgl_produksi'     => $r->tgl_produksi,
                        'barang_code'      => $r->barang_code,
                        'barang_name'      => $r->barang_name,
                        'barang_category'  => $r->barang_category,
                        'jumlah'           => $r->jumlah,
                        'destination'      => $r->destination,
                        'no_surat_jalan'   => $r->no_surat_jalan,
                        'create_by'        => $r->create_by,
                        'create_date'      => $r->create_date,
                        'digit'            => $r->digit,
                        'total_nilai'      => $r->total_nilai,
                        'updated_at'       => $now,
                        'created_at'       => $now,
                    ];
                }, $chunk);

                MonProdLine::insert($data);
                $inserted += count($data);
            }
        });

        $this->info("Selesai. {$inserted} baris Production Line diinsert ke mon_prod_lines (seluruh data lama dihapus).");

        return self::SUCCESS;
    }
}
