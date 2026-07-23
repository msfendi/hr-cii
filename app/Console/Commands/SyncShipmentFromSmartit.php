<?php

namespace App\Console\Commands;

use App\Models\MonShipment;
use App\Support\SqlServerChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncShipmentFromSmartit extends Command
{
    protected $signature = 'monitoring:sync-shipment';

    protected $description = 'Sinkronisasi FULL data Shipment (pengeluaran BC) dari smartit (tanpa filter tanggal) ke tabel mon_shipments';

    public function handle(): int
    {
        $this->info('Mengambil SEMUA data Shipment dari smartit ...');

        // Query tanpa WHERE tanggal
        $sql = <<<SQL
            SELECT * FROM (
                SELECT
                    b.hs_code,
                    h.doc_id,
                    h.jenis_doc,
                    h.no_aju,
                    h.no_doc,
                    CONVERT(date, h.tgl_doc) AS tgl_doc,
                    h.no_bukti,
                    CONVERT(date, h.tgl_bukti) AS tgl_bukti,
                    CASE
                        WHEN SUBSTRING(h.doc_id,1,2) IN ('RM','RK') THEN 'Retur'
                        ELSE (
                            SELECT TOP 1 value_order
                            FROM ms_order
                            WHERE value_order = p.jenis_so
                               OR value_order = p.jenis_subkon
                        )
                    END AS jenis_ps,
                    p.no_so AS no_ps,
                    h.no_invoice,
                    p.file_number,
                    s.supplier_name + ' (' + h.supplier_code + ')' AS supplier_name,
                    d.barang_code,
                    ISNULL(d.barang_doc,b.barang_name) AS barang_name,
                    b.barang_category,
                    d.satuan_doc,
                    SUM(d.jumlah_doc) AS jumlah_doc,
                    ISNULL(h.valas,p.valas) AS valas,
                    SUM(d.nilai_barang) AS nilai_barang,
                    SUM(d.nilai_fob) AS nilai_fob,
                    SUM(d.jumlah_aktual) AS jumlah_aktual,
                    d.keterangan,
                    h.so_id AS ps_id,
                    pd.spesifikasi,
                    pd.uraian,
                    h.berat,
                    SUM(d.jumlah_barang) AS jumlah_barang,
                    pd.satuan_order,
                    b.satuan_code,
                    pd.dt_so_id,
                    (
                        SELECT TOP 1 jhd.no_bukti
                        FROM acc_journal_dt jdt
                        INNER JOIN acc_journal_hd jhd
                            ON jhd.trans_id = jdt.trans_id
                        WHERE jdt.ref_id = h.doc_id
                    ) AS acc_bukti,
                    (
                        SELECT TOP 1 jhd.tgl_trans
                        FROM acc_journal_dt jdt
                        INNER JOIN acc_journal_hd jhd
                            ON jhd.trans_id = jdt.trans_id
                        WHERE jdt.ref_id = h.doc_id
                    ) AS acc_tgl
                FROM doc_export_hd h
                INNER JOIN doc_export_dt d
                    ON d.doc_id = h.doc_id
                INNER JOIN ms_barang b
                    ON b.barang_code = d.barang_code
                INNER JOIN ms_supplier s
                    ON s.supplier_code = h.supplier_code
                LEFT JOIN prd_so_dt pd
                    ON d.dt_so_id = pd.dt_so_id
                LEFT JOIN prd_so_hd p
                    ON pd.so_id = p.so_id
                WHERE
                    h.jenis_doc <> 'Non Pabean'
                    AND b.barang_status <> 'void'
                    AND h.status = 'Finish'
                GROUP BY
                    b.hs_code, p.jenis_subkon, h.doc_id, h.jenis_doc, pd.satuan_order,
                    h.no_aju, h.no_doc, h.tgl_doc, h.no_bukti, h.tgl_bukti, p.jenis_so,
                    p.no_so, h.no_invoice, p.file_number, h.supplier_code, s.supplier_name,
                    d.barang_code, b.barang_name, d.satuan_doc, d.barang_doc, p.valas,
                    h.valas, d.keterangan, h.so_id, pd.spesifikasi, pd.uraian, pd.dt_so_id,
                    h.berat, b.satuan_code, b.barang_category

                UNION ALL

                SELECT
                    b.hs_code,
                    h.doc_id,
                    h.jenis_doc,
                    h.no_aju,
                    h.no_doc,
                    CONVERT(date, h.tgl_doc) AS tgl_doc,
                    h.no_bukti,
                    CONVERT(date, h.tgl_bukti) AS tgl_bukti,
                    'Retur' AS jenis_ps,
                    p.no_po AS no_ps,
                    h.no_invoice,
                    p.file_number,
                    s.supplier_name + ' (' + h.supplier_code + ')' AS supplier_name,
                    d.barang_code,
                    ISNULL(d.barang_doc,b.barang_name) AS barang_name,
                    b.barang_category,
                    d.satuan_doc,
                    SUM(d.jumlah_doc) AS jumlah_doc,
                    ISNULL(h.valas,p.valas) AS valas,
                    SUM(d.nilai_barang) AS nilai_barang,
                    SUM(d.nilai_fob) AS nilai_fob,
                    SUM(d.jumlah_aktual) AS jumlah_aktual,
                    d.keterangan,
                    h.so_id AS ps_id,
                    pd.spesifikasi,
                    pd.uraian,
                    h.berat,
                    SUM(d.jumlah_barang) AS jumlah_barang,
                    pd.satuan_order,
                    b.satuan_code,
                    pd.dt_po_id,
                    (
                        SELECT TOP 1 jhd.no_bukti
                        FROM acc_journal_dt jdt
                        INNER JOIN acc_journal_hd jhd
                            ON jhd.trans_id = jdt.trans_id
                        WHERE jdt.ref_id = h.doc_id
                    ) AS acc_bukti,
                    (
                        SELECT TOP 1 jhd.tgl_trans
                        FROM acc_journal_dt jdt
                        INNER JOIN acc_journal_hd jhd
                            ON jhd.trans_id = jdt.trans_id
                        WHERE jdt.ref_id = h.doc_id
                    ) AS acc_tgl
                FROM doc_export_hd h
                INNER JOIN doc_export_dt d
                    ON d.doc_id = h.doc_id
                INNER JOIN ms_barang b
                    ON b.barang_code = d.barang_code
                INNER JOIN ms_supplier s
                    ON s.supplier_code = h.supplier_code
                INNER JOIN doc_import_hd ih
                    ON h.so_id = ih.doc_id
                LEFT JOIN doc_import_dt id
                    ON ih.doc_id = id.doc_id
                LEFT JOIN prd_po_dt pd
                    ON id.dt_po_id = pd.dt_po_id
                LEFT JOIN prd_po_hd p
                    ON pd.po_id = p.po_id
                WHERE
                    h.jenis_doc <> 'Non Pabean'
                    AND b.barang_status <> 'void'
                    AND h.status = 'Finish'
                GROUP BY
                    b.hs_code, h.doc_id, h.jenis_doc, pd.satuan_order, h.no_aju, h.no_doc,
                    h.tgl_doc, h.no_bukti, h.tgl_bukti, p.jenis_po, p.no_po, h.no_invoice,
                    p.file_number, h.supplier_code, s.supplier_name, d.barang_code,
                    b.barang_name, d.satuan_doc, d.barang_doc, p.valas, h.valas,
                    d.keterangan, h.so_id, pd.spesifikasi, pd.uraian, pd.dt_po_id, h.berat,
                    b.satuan_code, b.barang_category
            ) m
            ORDER BY m.tgl_bukti
        SQL;

        $rows = DB::connection('smartit')->select($sql);

        $this->info('Baris diterima dari smartit: ' . count($rows));

        $now = now();
        $inserted = 0;

        $chunkSize = SqlServerChunk::rows(columnsPerRow: 35);

        DB::transaction(function () use ($rows, $chunkSize, $now, &$inserted) {
            MonShipment::truncate();

            foreach (array_chunk($rows, $chunkSize) as $chunk) {
                $data = array_map(function ($r) use ($now) {
                    return [
                        'row_key'        => self::rowKey($r),
                        'hs_code'        => $r->hs_code,
                        'doc_id'         => $r->doc_id,
                        'jenis_doc'      => $r->jenis_doc,
                        'no_aju'         => $r->no_aju,
                        'no_doc'         => $r->no_doc,
                        'tgl_doc'        => $r->tgl_doc,
                        'no_bukti'       => $r->no_bukti,
                        'tgl_bukti'      => $r->tgl_bukti,
                        'jenis_ps'       => $r->jenis_ps,
                        'no_ps'          => $r->no_ps,
                        'no_invoice'     => $r->no_invoice,
                        'file_number'    => $r->file_number,
                        'supplier_name'  => $r->supplier_name,
                        'barang_code'    => $r->barang_code,
                        'barang_name'    => $r->barang_name,
                        'barang_category' => $r->barang_category,
                        'satuan_doc'     => $r->satuan_doc,
                        'jumlah_doc'     => $r->jumlah_doc,
                        'valas'          => $r->valas,
                        'nilai_barang'   => $r->nilai_barang,
                        'nilai_fob'      => $r->nilai_fob,
                        'jumlah_aktual'  => $r->jumlah_aktual,
                        'keterangan'     => $r->keterangan,
                        'ps_id'          => $r->ps_id,
                        'spesifikasi'    => $r->spesifikasi,
                        'uraian'         => $r->uraian,
                        'berat'          => $r->berat,
                        'jumlah_barang'  => $r->jumlah_barang,
                        'satuan_order'   => $r->satuan_order,
                        'satuan_code'    => $r->satuan_code,
                        'dt_so_id'       => $r->dt_so_id,
                        'acc_bukti'      => $r->acc_bukti,
                        'acc_tgl'        => $r->acc_tgl,
                        'updated_at'     => $now,
                        'created_at'     => $now,
                    ];
                }, $chunk);

                MonShipment::insert($data);
                $inserted += count($data);
            }
        });

        $this->info("Selesai. {$inserted} baris Shipment diinsert ke mon_shipments (seluruh data lama dihapus).");

        return self::SUCCESS;
    }

    private static function rowKey(object $r): string
    {
        return md5(implode('|', [
            $r->doc_id,
            $r->barang_code,
            $r->dt_so_id,
            $r->satuan_doc,
            $r->keterangan,
        ]));
    }
}
