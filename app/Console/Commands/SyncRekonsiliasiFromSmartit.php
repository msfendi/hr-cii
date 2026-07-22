<?php

namespace App\Console\Commands;

use App\Models\MonRekonsiliasi;
use App\Support\SqlServerChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncRekonsiliasiFromSmartit extends Command
{
    /**
     * php artisan monitoring:sync-rekonsiliasi
     * php artisan monitoring:sync-rekonsiliasi --year=2026
     * php artisan monitoring:sync-rekonsiliasi --from=2026-01-01 --to=2026-12-31
     */
    protected $signature = 'monitoring:sync-rekonsiliasi
        {--year= : Tahun tgl_po yang disinkron, default tahun berjalan}
        {--from= : Override tanggal mulai (Y-m-d)}
        {--to= : Override tanggal akhir (Y-m-d)}';

    protected $description = 'Sinkronisasi sheet Rekonsiliasi dari database smartit (get_rekonsiliasi.txt) ke tabel mon_rekonsiliasis';

    public function handle(): int
    {
        $year = $this->option('year') ?: now()->year;
        $from = $this->option('from') ?: "{$year}-01-01";
        $to = $this->option('to') ?: "{$year}-12-31";

        $this->info("Mengambil data Rekonsiliasi dari smartit, tgl_po {$from} s/d {$to} ...");

        // ========== QUERY YANG SUDAH DIPERBAIKI ==========
        // CTE dipindahkan ke awal, tidak lagi berada di dalam subquery.
        $sql = <<<SQL
            WITH jumlah_doc AS (
                SELECT dt_po_id, SUM(jumlah_doc) AS jumlah_doc, SUM(jumlah_barang) AS jumlah_barang
                FROM (
                    SELECT DISTINCT dt_doc_id, idt.dt_po_id, idt.jumlah_doc, idt.jumlah_barang
                    FROM doc_import_dt idt
                    INNER JOIN prd_request_dt rdt ON rdt.dt_po_id = idt.dt_doc_id
                    INNER JOIN prd_request_hd rhd ON rhd.request_id = rdt.request_id
                ) m
                GROUP BY dt_po_id
            ),
            out_req AS (
                SELECT dt_po_id, SUM(on_production) AS out_req
                FROM (
                    SELECT DISTINCT idt.dt_doc_id, idt.dt_po_id, rdt.on_production
                    FROM doc_import_dt idt
                    INNER JOIN prd_request_dt rdt ON rdt.dt_po_id = idt.dt_doc_id
                    INNER JOIN prd_request_hd rhd ON rhd.request_id = rdt.request_id
                    WHERE rhd.status = 'Finish'
                ) m
                GROUP BY dt_po_id
            ),
            out_prod AS (
                SELECT m.dt_po_id, SUM(m.jumlah) AS out_prod
                FROM (
                    SELECT DISTINCT * FROM (
                        SELECT idt.dt_doc_id, idt.dt_po_id, rdt.on_production AS jumlah
                        FROM doc_import_dt idt
                        INNER JOIN prd_result_dt rdt ON rdt.dt_po_id = idt.dt_doc_id
                        INNER JOIN prd_result_hd rhd ON rhd.result_id = rdt.result_id
                        WHERE rhd.line_id IS NULL OR rhd.line_id = ''
                        UNION ALL
                        SELECT idt.dt_doc_id, idt.dt_po_id, ldt.jumlah
                        FROM doc_import_dt idt
                        INNER JOIN line_dt ldt ON ldt.dt_po_id = idt.dt_doc_id
                    ) m
                ) m
                GROUP BY m.dt_po_id
            ),
            out_doc AS (
                SELECT dt_po_id, SUM(jumlah_barang) AS out_doc
                FROM (
                    SELECT DISTINCT rd2.dt_doc_id, id2.dt_po_id, rd2.jumlah_barang
                    FROM doc_export_dt rd2
                    INNER JOIN doc_export_hd rh2 ON rh2.doc_id = rd2.doc_id
                    INNER JOIN doc_import_dt id2 ON id2.dt_doc_id = rd2.dt_out_id
                    WHERE rh2.status = 'Finish'
                ) m
                GROUP BY dt_po_id
            )
            SELECT DISTINCT
                podt.dt_po_id,
                pohd.valas,
                pohd.no_po,
                pohd.jenis_po,
                pohd.tgl_po,
                pohd.termin,
                pohd.tgl_pengiriman,
                s.supplier_name,
                podt.barang_code,
                b.barang_name,
                podt.satuan_order,
                b.satuan_code,
                b.header_code AS klaim_fsc,
                podt.uraian,
                podt.spesifikasi,
                CASE WHEN podt.ncv = 0 THEN 'No' ELSE 'Yes' END AS ncv,
                podt.jumlah_order,
                ISNULL(jd.jumlah_doc, 0) AS jumlah_doc,
                ISNULL(rq.out_req, 0) AS out_req,
                ISNULL(op.out_prod, 0) AS out_prod,
                podt.jumlah_order - ISNULL(jd.jumlah_doc, 0) AS sisa,
                ISNULL(rq.out_req, 0) - ISNULL(op.out_prod, 0) AS saldo_wip,
                ISNULL(sat.decimal_status, 2) AS digit,
                ISNULL(od.out_doc, 0) AS out_doc,
                pohd.create_by,
                FORMAT(pohd.create_date, 'yyyy-MM-dd HH:mm') AS create_date,
                podt.harga_total,
                ISNULL(jd.jumlah_barang, 0) - (ISNULL(rq.out_req, 0) + ISNULL(od.out_doc, 0)) AS saldo_gudang
            FROM prd_po_dt podt
            INNER JOIN prd_po_hd pohd ON pohd.po_id = podt.po_id
            INNER JOIN ms_barang b ON b.barang_code = podt.barang_code
            INNER JOIN ms_supplier s ON s.supplier_code = pohd.supplier_code
            LEFT JOIN ms_satuan sat ON sat.satuan_code = podt.satuan_order
            INNER JOIN jumlah_doc jd ON jd.dt_po_id = podt.dt_po_id
            LEFT JOIN out_req rq ON rq.dt_po_id = podt.dt_po_id
            LEFT JOIN out_prod op ON op.dt_po_id = podt.dt_po_id
            LEFT JOIN out_doc od ON od.dt_po_id = podt.dt_po_id
            WHERE pohd.tgl_po >= ? AND pohd.tgl_po <= ?
            ORDER BY pohd.tgl_po
        SQL;

        $rows = DB::connection('smartit')->select($sql, [$from, $to]);

        $this->info('Baris diterima dari smartit: ' . count($rows));

        $now = now();
        $inserted = 0;

        $chunkSize = SqlServerChunk::rows(columnsPerRow: 29);

        DB::transaction(function () use ($rows, $chunkSize, $now, $from, $to, &$inserted) {
            // Kosongkan dulu HANYA baris pada rentang tanggal yang sedang
            // disinkron (bukan truncate seluruh tabel), supaya data
            // tahun/rentang lain yang sudah tersinkron sebelumnya tidak ikut
            // terhapus. Baru setelah itu insert data baru dari smartit.
            MonRekonsiliasi::whereBetween('tgl_po', [$from, $to])->delete();

            foreach (array_chunk($rows, $chunkSize) as $chunk) {
                $data = array_map(function ($r) use ($now) {
                    return [
                        'dt_po_id'       => $r->dt_po_id,
                        'valas'          => $r->valas,
                        'no_po'          => $r->no_po,
                        'jenis_po'       => $r->jenis_po,
                        'tgl_po'         => $r->tgl_po,
                        'termin'         => $r->termin,
                        'tgl_pengiriman' => $r->tgl_pengiriman ?? null,
                        'supplier_name'  => $r->supplier_name,
                        'barang_code'    => $r->barang_code,
                        'barang_name'    => $r->barang_name,
                        'satuan_order'   => $r->satuan_order,
                        'satuan_code'    => $r->satuan_code,
                        'klaim_fsc'      => $r->klaim_fsc,
                        'uraian'         => $r->uraian,
                        'spesifikasi'    => $r->spesifikasi,
                        'ncv'            => $r->ncv,
                        'jumlah_order'   => $r->jumlah_order,
                        'jumlah_doc'     => $r->jumlah_doc,
                        'out_req'        => $r->out_req,
                        'out_prod'       => $r->out_prod,
                        'sisa'           => $r->sisa,
                        'saldo_wip'      => $r->saldo_wip,
                        'digit'          => $r->digit,
                        'out_doc'        => $r->out_doc,
                        'create_by'      => $r->create_by,
                        'create_date'    => $r->create_date,
                        'harga_total'    => $r->harga_total,
                        'saldo_gudang'   => $r->saldo_gudang,
                        'updated_at'     => $now,
                        'created_at'     => $now,
                    ];
                }, $chunk);

                MonRekonsiliasi::insert($data);

                $inserted += count($data);
            }
        });

        $this->info("Selesai. {$inserted} baris Rekonsiliasi diinsert ke mon_rekonsiliasis (data lama pada rentang tanggal ini dihapus lebih dulu).");

        return self::SUCCESS;
    }
}
