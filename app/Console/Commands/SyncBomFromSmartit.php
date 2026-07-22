<?php

namespace App\Console\Commands;

use App\Models\MonBom;
use App\Support\SqlServerChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncBomFromSmartit extends Command
{
    /**
     * php artisan monitoring:sync-bom
     * php artisan monitoring:sync-bom --year=2026
     * php artisan monitoring:sync-bom --from=2026-01-01 --to=2026-12-31
     */
    protected $signature = 'monitoring:sync-bom
        {--year= : Tahun tgl_prod yang disinkron, default tahun berjalan}
        {--from= : Override tanggal mulai (Y-m-d)}
        {--to= : Override tanggal akhir (Y-m-d)}';

    protected $description = 'Sinkronisasi sheet BOM dari database smartit (get_bom_2026.txt) ke tabel mon_boms';

    public function handle(): int
    {
        $year = $this->option('year') ?: now()->year;
        $from = $this->option('from') ?: "{$year}-01-01";
        $to = $this->option('to') ?: "{$year}-12-31";

        $this->info("Mengambil data BOM dari smartit, tgl_prod {$from} s/d {$to} ...");

        // Query persis dari get_bom_2026.txt, tanggal di-parameterisasi.
        $sql = <<<SQL
            select bm.bom_id, p.code_prod, p.tgl_prod, bm.barang_code, b.barang_name, b.satuan_code, bm.uraian, bm.spesifikasi, bm.cons, bm.scrap_percent, bm.departemen, bm.komponen, b2.barang_name as barang_jadi
            from prd_bom bm
            join prd_plan_hd p on p.prod_id = bm.prod_id
            join ms_barang b on b.barang_code = bm.barang_code
            join ms_barang b2 on b2.barang_code = p.barang_code
            where tgl_prod >= ? and tgl_prod <= ?
            order by tgl_prod, p.code_prod asc
        SQL;

        $rows = DB::connection('smartit')->select($sql, [$from, $to]);

        $this->info('Baris diterima dari smartit: ' . count($rows));

        $now = now();
        $inserted = 0;

        // 15 kolom per baris (13 data + updated_at + created_at) -> hitung
        // otomatis biar total parameter per batch aman < limit SQL Server (2100).
        $chunkSize = SqlServerChunk::rows(columnsPerRow: 15);

        DB::transaction(function () use ($rows, $chunkSize, $now, $from, $to, &$inserted) {
            // Kosongkan dulu HANYA baris pada rentang tanggal yang sedang
            // disinkron (bukan truncate seluruh tabel), supaya data
            // tahun/rentang lain yang sudah tersinkron sebelumnya tidak ikut
            // terhapus. Baru setelah itu insert data baru dari smartit.
            MonBom::whereBetween('tgl_prod', [$from, $to])->delete();

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

        $this->info("Selesai. {$inserted} baris BOM diinsert ke mon_boms (data lama pada rentang tanggal ini dihapus lebih dulu).");

        return self::SUCCESS;
    }
}
