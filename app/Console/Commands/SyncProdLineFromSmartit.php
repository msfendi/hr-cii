<?php

namespace App\Console\Commands;

use App\Models\MonProdLine;
use App\Support\SqlServerChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncProdLineFromSmartit extends Command
{
    /**
     * php artisan monitoring:sync-prod-line
     * php artisan monitoring:sync-prod-line --year=2026
     * php artisan monitoring:sync-prod-line --from=2026-01-01 --to=2026-12-31
     */
    protected $signature = 'monitoring:sync-prod-line
        {--year= : Tahun tgl_produksi yang disinkron, default tahun berjalan}
        {--from= : Override tanggal mulai (Y-m-d)}
        {--to= : Override tanggal akhir (Y-m-d)}';

    protected $description = 'Sinkronisasi sheet Production Line dari database smartit (get_prod_line_2026.txt) ke tabel mon_prod_lines';

    public function handle(): int
    {
        $year = $this->option('year') ?: now()->year;
        $from = $this->option('from') ?: "{$year}-01-01";
        $to = $this->option('to') ?: "{$year}-12-31";

        $this->info("Mengambil data Production Line dari smartit, tgl_produksi {$from} s/d {$to} ...");

        // Query persis dari get_prod_line_2026.txt, hanya bagian WHERE tgl_produksi
        // di akhir yang diparameterisasi (query aslinya tidak difilter tanggal sama sekali,
        // filter ditambahkan di sini supaya sinkronisasi bisa dijalankan per-tahun/per-range).
        $sql = <<<SQL
            SELECT hd.line_id, phd.code_prod, hd.department_id, hd.tgl_produksi, hd.barang_code, b.barang_name, b.barang_category, hd.jumlah, hd.destination, hd.no_surat_jalan,
                   hd.create_by, FORMAT(hd.create_date, 'yyyy-MM-dd HH:mm') AS create_date
                        ,isnull((select decimal_status from ms_satuan where satuan_code = b.satuan_code), 2) as digit, hd.total_nilai
            FROM line_hd hd
            inner join ms_barang b on b.barang_code = hd.barang_code
            INNER JOIN prd_plan_hd phd on hd.prod_id = phd.prod_id
            WHERE hd.tgl_produksi >= ? AND hd.tgl_produksi <= ?
            ORDER BY hd.tgl_produksi ASC
        SQL;

        $rows = DB::connection('smartit')->select($sql, [$from, $to]);

        $this->info('Baris diterima dari smartit: ' . count($rows));

        $now = now();
        $upserted = 0;
        $updateColumns = [
            'code_prod',
            'department_id',
            'tgl_produksi',
            'barang_code',
            'barang_name',
            'barang_category',
            'jumlah',
            'destination',
            'no_surat_jalan',
            'create_by',
            'create_date',
            'digit',
            'total_nilai',
            'updated_at',
        ];

        // 15 kolom per baris -> hitung otomatis biar total parameter per batch
        // aman di bawah limit SQL Server (2100 bound parameters per query).
        $chunkSize = SqlServerChunk::rows(columnsPerRow: 15);

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

            MonProdLine::upsert($data, uniqueBy: ['line_id'], update: $updateColumns);

            $upserted += count($data);
        }

        $this->info("Selesai. {$upserted} baris Production Line di-upsert ke mon_prod_lines.");

        return self::SUCCESS;
    }
}
