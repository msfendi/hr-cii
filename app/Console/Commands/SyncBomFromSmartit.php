<?php

namespace App\Console\Commands;

use App\Models\MonBom;
use App\Support\SqlServerChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncBomFromSmartit extends Command
{
    protected $signature = 'monitoring:sync-bom';

    protected $description = 'Sinkronisasi FULL data BOM dari smartit (tanpa filter tanggal) ke tabel mon_boms';

    public function handle(): int
    {
        $this->info('Mengambil SEMUA data BOM dari smartit ...');

        // Query tanpa WHERE tanggal
        $sql = <<<SQL
            select bm.bom_id, p.code_prod, p.tgl_prod, bm.barang_code, b.barang_name, b.satuan_code, bm.uraian, bm.spesifikasi, bm.cons, bm.scrap_percent, bm.departemen, bm.komponen, b2.barang_name as barang_jadi
            from prd_bom bm
            join prd_plan_hd p on p.prod_id = bm.prod_id
            join ms_barang b on b.barang_code = bm.barang_code
            join ms_barang b2 on b2.barang_code = p.barang_code
            order by tgl_prod, p.code_prod asc
        SQL;

        $rows = DB::connection('smartit')->select($sql);

        $this->info('Baris diterima dari smartit: ' . count($rows));

        $now = now();
        $inserted = 0;

        $chunkSize = SqlServerChunk::rows(columnsPerRow: 15);

        DB::transaction(function () use ($rows, $chunkSize, $now, &$inserted) {
            MonBom::truncate();

            foreach (array_chunk($rows, $chunkSize) as $chunk) {
                $data = array_map(function ($r) use ($now) {
                    return [
                        'bom_id'        => $r->bom_id,
                        'code_prod'     => $r->code_prod,
                        'tgl_prod'      => $r->tgl_prod,
                        'barang_code'   => $r->barang_code,
                        'barang_name'   => $r->barang_name,
                        'satuan_code'   => $r->satuan_code,
                        'uraian'        => $r->uraian,
                        'spesifikasi'   => $r->spesifikasi,
                        'cons'          => $r->cons,
                        'scrap_percent' => $r->scrap_percent,
                        'departemen'    => $r->departemen,
                        'komponen'      => $r->komponen,
                        'barang_jadi'   => $r->barang_jadi,
                        'updated_at'    => $now,
                        'created_at'    => $now,
                    ];
                }, $chunk);

                MonBom::insert($data);
                $inserted += count($data);
            }
        });

        $this->info("Selesai. {$inserted} baris BOM diinsert ke mon_boms (seluruh data lama dihapus).");

        return self::SUCCESS;
    }
}
