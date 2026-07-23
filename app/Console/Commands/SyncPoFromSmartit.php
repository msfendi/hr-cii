<?php

namespace App\Console\Commands;

use App\Models\MonPurchaseOrder;
use App\Support\SqlServerChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncPoFromSmartit extends Command
{
    protected $signature = 'monitoring:sync-po';

    protected $description = 'Sinkronisasi FULL data PO dari smartit (tanpa filter tanggal) ke tabel mon_purchase_orders';

    public function handle(): int
    {
        $this->info('Mengambil SEMUA data PO dari smartit ...');

        // Query tanpa WHERE tanggal
        $sql = <<<SQL
            SELECT
                m.*,
                ISNULL(m.jumlah_order,0) - ISNULL(m.jumlah_doc,0) AS sisa,
                total_order - ISNULL(total_in,0) AS total_sisa,
                (
                    ISNULL(total_in,0)
                    -
                    (
                        ISNULL(total_req,0)
                        +
                        ISNULL(total_doc,0)
                    )
                ) AS total_gudang,
                (
                    ISNULL(total_req,0)
                    -
                    ISNULL(total_prod,0)
                ) AS total_wip
            FROM
            (
                SELECT
                    b.header_code AS klaim_fsc,
                    p.dt_po_id,
                    p.po_id,
                    h.jenis_po,
                    h.no_po,
                    h.tgl_po,
                    h.tgl_pengiriman,
                    s.supplier_name,
                    p.barang_code,
                    b.barang_name,
                    p.satuan_order,
                    p.uraian,
                    p.spesifikasi,
                    p.jumlah_order,
                    p.harga_satuan,
                    p.harga_total,
                    p.harga_fob,
                    p.total_fob,
                    p.ppn,
                    p.pph,
                    p.discount,
                    p.biaya,
                    h.valas,
                    h.note,
                    h.create_by,
                    FORMAT(h.create_date, 'yyyy-MM-dd HH:mm') AS create_date,
                    CASE WHEN p.ncv = 0 THEN 'No' ELSE 'Yes' END AS ncv,
                    ISNULL((SELECT SUM(d.jumlah_doc) FROM doc_import_dt d WHERE d.dt_po_id = p.dt_po_id),0) AS jumlah_doc,
                    ISNULL((SELECT SUM(d.jumlah_barang) FROM doc_import_dt d WHERE d.dt_po_id = p.dt_po_id),0) AS total_in,
                    ISNULL((
                        SELECT SUM(rd2.on_production)
                        FROM prd_request_dt rd2
                        JOIN prd_request_hd rh2 ON rh2.request_id = rd2.request_id
                        JOIN doc_import_dt id2 ON id2.dt_doc_id = rd2.dt_po_id
                        JOIN prd_po_dt pd2 ON pd2.dt_po_id = id2.dt_po_id
                        WHERE pd2.dt_po_id = p.dt_po_id AND rh2.status = 'Finish'
                    ),0) AS out_req,
                    ISNULL((
                        SELECT SUM(rd2.on_production)
                        FROM prd_request_dt rd2
                        JOIN prd_request_hd rh2 ON rh2.request_id = rd2.request_id
                        JOIN doc_import_dt id2 ON id2.dt_doc_id = rd2.dt_po_id
                        JOIN prd_po_dt pd2 ON pd2.dt_po_id = id2.dt_po_id
                        WHERE pd2.dt_po_id = p.dt_po_id AND rh2.status = 'Finish'
                    ),0) AS total_req,
                    ISNULL((
                        SELECT SUM(jumlah) FROM (
                            SELECT rd2.on_production AS jumlah
                            FROM prd_result_dt rd2
                            JOIN prd_result_hd rhd2 ON rhd2.result_id = rd2.result_id
                            JOIN doc_import_dt id2 ON id2.dt_doc_id = rd2.dt_po_id
                            JOIN prd_po_dt pd2 ON pd2.dt_po_id = id2.dt_po_id
                            WHERE pd2.dt_po_id = p.dt_po_id AND (rhd2.line_id IS NULL OR rhd2.line_id = '')
                            UNION ALL
                            SELECT rd2.jumlah AS jumlah
                            FROM line_dt rd2
                            JOIN doc_import_dt id2 ON id2.dt_doc_id = rd2.dt_po_id
                            JOIN prd_po_dt pd2 ON pd2.dt_po_id = id2.dt_po_id
                            WHERE pd2.dt_po_id = p.dt_po_id
                        ) x
                    ),0) AS out_prod,
                    ISNULL((
                        SELECT SUM(jumlah) FROM (
                            SELECT rd2.on_production AS jumlah
                            FROM prd_result_dt rd2
                            JOIN prd_result_hd rhd2 ON rhd2.result_id = rd2.result_id
                            JOIN doc_import_dt id2 ON id2.dt_doc_id = rd2.dt_po_id
                            JOIN prd_po_dt pd2 ON pd2.dt_po_id = id2.dt_po_id
                            WHERE pd2.dt_po_id = p.dt_po_id AND (rhd2.line_id IS NULL OR rhd2.line_id = '')
                            UNION ALL
                            SELECT rd2.jumlah AS jumlah
                            FROM line_dt rd2
                            JOIN doc_import_dt id2 ON id2.dt_doc_id = rd2.dt_po_id
                            JOIN prd_po_dt pd2 ON pd2.dt_po_id = id2.dt_po_id
                            WHERE pd2.dt_po_id = p.dt_po_id
                        ) x
                    ),0) AS total_prod,
                    ISNULL((
                        SELECT SUM(rd2.jumlah_barang)
                        FROM doc_export_dt rd2
                        JOIN doc_export_hd rh2 ON rh2.doc_id = rd2.doc_id
                        JOIN doc_import_dt id2 ON id2.dt_doc_id = rd2.dt_out_id
                        JOIN prd_po_dt pd2 ON pd2.dt_po_id = id2.dt_po_id
                        WHERE pd2.dt_po_id = p.dt_po_id AND rh2.status = 'Finish'
                    ),0) AS out_doc,
                    ISNULL((
                        SELECT SUM(rd2.jumlah_barang)
                        FROM doc_export_dt rd2
                        JOIN doc_export_hd rh2 ON rh2.doc_id = rd2.doc_id
                        JOIN doc_import_dt id2 ON id2.dt_doc_id = rd2.dt_out_id
                        JOIN prd_po_dt pd2 ON pd2.dt_po_id = id2.dt_po_id
                        WHERE pd2.dt_po_id = p.dt_po_id AND rh2.status = 'Finish'
                    ),0) AS total_doc,
                    ISNULL((SELECT decimal_status FROM ms_satuan WHERE satuan_code = p.satuan_order),2) AS digit,
                    (
                        SELECT SUM(p2.jumlah_order)
                        FROM prd_po_dt p2
                        INNER JOIN prd_po_hd h2 ON p2.po_id = h2.po_id
                        WHERE h2.jenis_po <> 'Subkon'
                    ) AS total_order,
                    (
                        SELECT SUM(p2.harga_total)
                        FROM prd_po_dt p2
                        INNER JOIN prd_po_hd h2 ON p2.po_id = h2.po_id
                        WHERE h2.jenis_po <> 'Subkon'
                    ) AS total_harga
                FROM prd_po_dt p
                INNER JOIN ms_barang b ON p.barang_code = b.barang_code
                INNER JOIN prd_po_hd h ON p.po_id = h.po_id
                INNER JOIN ms_supplier s ON s.supplier_code = h.supplier_code
                WHERE h.jenis_po <> 'Subkon'
            ) m
            ORDER BY tgl_po
        SQL;

        $rows = DB::connection('smartit')->select($sql);

        $this->info('Baris diterima dari smartit: ' . count($rows));

        $now = now();
        $inserted = 0;

        $chunkSize = SqlServerChunk::rows(columnsPerRow: 44);

        DB::transaction(function () use ($rows, $chunkSize, $now, &$inserted) {
            MonPurchaseOrder::truncate();

            foreach (array_chunk($rows, $chunkSize) as $chunk) {
                $data = array_map(function ($r) use ($now) {
                    return [
                        'klaim_fsc'     => $r->klaim_fsc,
                        'dt_po_id'      => $r->dt_po_id,
                        'po_id'         => $r->po_id,
                        'jenis_po'      => $r->jenis_po,
                        'no_po'         => $r->no_po,
                        'tgl_po'        => $r->tgl_po,
                        'tgl_pengiriman' => $r->tgl_pengiriman ?? null,
                        'supplier_name' => $r->supplier_name,
                        'barang_code'   => $r->barang_code,
                        'barang_name'   => $r->barang_name,
                        'satuan_order'  => $r->satuan_order,
                        'uraian'        => $r->uraian,
                        'spesifikasi'   => $r->spesifikasi,
                        'jumlah_order'  => $r->jumlah_order,
                        'harga_satuan'  => $r->harga_satuan,
                        'harga_total'   => $r->harga_total,
                        'harga_fob'     => $r->harga_fob,
                        'total_fob'     => $r->total_fob,
                        'ppn'           => $r->ppn,
                        'pph'           => $r->pph,
                        'discount'      => $r->discount,
                        'biaya'         => $r->biaya,
                        'valas'         => $r->valas,
                        'note'          => $r->note,
                        'create_by'     => $r->create_by,
                        'create_date'   => $r->create_date,
                        'ncv'           => $r->ncv,
                        'jumlah_doc'    => $r->jumlah_doc,
                        'total_in'      => $r->total_in,
                        'out_req'       => $r->out_req,
                        'total_req'     => $r->total_req,
                        'out_prod'      => $r->out_prod,
                        'total_prod'    => $r->total_prod,
                        'out_doc'       => $r->out_doc,
                        'total_doc'     => $r->total_doc,
                        'digit'         => $r->digit,
                        'total_order'   => $r->total_order,
                        'total_harga'   => $r->total_harga,
                        'sisa'          => $r->sisa,
                        'total_sisa'    => $r->total_sisa,
                        'total_gudang'  => $r->total_gudang,
                        'total_wip'     => $r->total_wip,
                        'updated_at'    => $now,
                        'created_at'    => $now,
                    ];
                }, $chunk);

                MonPurchaseOrder::insert($data);
                $inserted += count($data);
            }
        });

        $this->info("Selesai. {$inserted} baris PO diinsert ke mon_purchase_orders (seluruh data lama dihapus).");

        return self::SUCCESS;
    }
}
