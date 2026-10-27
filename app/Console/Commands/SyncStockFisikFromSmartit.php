<?php

namespace App\Console\Commands;

use App\Support\SqlServerChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncStockFisikFromSmartit extends Command
{
    protected $signature = 'monitoring:sync-stock-fisik';

    protected $description = 'Sinkronisasi FULL stok fisik (dokumen impor BC + hasil produksi) dari smartit ke tabel mon_stock_fisik';

    public function handle(): int
    {
        // Dipanggil juga dari web (tombol Sync All): longgarkan batas memori & waktu
        // karena ribuan baris dengan kolom teks panjang.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $this->info('Mengambil stok fisik dari smartit ...');

        $sql = <<<'SQL'
            SET NOCOUNT ON;
            IF OBJECT_ID('tempdb..#fisik') IS NOT NULL DROP TABLE #fisik;
            SELECT * INTO #fisik FROM (
            select *, (saldo * harga_satuan) as nilai_harga, (saldo * harga_satuan_fob) as nilai_harga_fob
            ,isnull((select decimal_status from ms_satuan where satuan_code = sub.satuan_code), 2) as digit
            FROM (
            SELECT
            dt.dt_doc_id, dt.barang_code, b.barang_name, b.satuan_code, b.barang_category,
            isnull(sdt.uraian, '')+' '+isnull(sdt.spesifikasi, '') as spesifikasi,
            hd.jenis_doc+' / '+ISNULL(hd.no_doc,hd.no_bukti)+' / '+CONVERT(VARCHAR,ISNULL(hd.tgl_doc,hd.tgl_bukti), 120) as jenis_doc,
            dt.keterangan, hd.jenis_doc as jenis_doc1, hd.no_doc, hd.tgl_doc,
            dt.jumlah_barang
            - ( select ISNULL(SUM(rdt.jumlah),0) from prd_request_dt rdt INNER JOIN prd_request_hd hh on rdt.request_id=hh.request_id WHERE (hh.status = 'finish' or hh.status = 'void') and rdt.dt_po_id=dt.dt_doc_id)
            - ( select ISNULL(SUM(exdt.jumlah_barang),0) from doc_export_dt exdt inner join doc_export_hd hhh on exdt.doc_id=hhh.doc_id WHERE hhh.status = 'finish' and exdt.dt_out_id=dt.dt_doc_id
            ) as saldo,
                case when hd.valas = (select top 1 case when valas is null or valas = '' or valas = 'no' then 'IDR' else 'USD' end as valas from ms_profil)
            then
            (dt.nilai_barang / nullif(dt.jumlah_barang,0))
            else
            cast(( (dt.nilai_barang / nullif(dt.jumlah_barang,0)) ) AS DECIMAL(18,7))
            / ISNULL((SELECT TOP 1 nilai FROM acc_kurs WHERE valas = (select top 1 case when valas is null or valas = '' or valas = 'no' then 'IDR' else 'USD' end as valas from ms_profil) and hd.tgl_doc between start_date and end_date), 1)
            * ISNULL((SELECT TOP 1 nilai FROM acc_kurs WHERE valas = hd.valas and hd.tgl_doc between start_date and end_date), 1)

            end
            as harga_satuan,
            case when hd.valas = (select top 1 case when valas is null or valas = '' or valas = 'no' then 'IDR' else 'USD' end as valas from ms_profil)
            then
            (dt.nilai_fob / nullif(dt.jumlah_barang,0))
            else
            cast(( (dt.nilai_fob / nullif(dt.jumlah_barang,0)) ) AS DECIMAL(18,7))
            / ISNULL((SELECT TOP 1 nilai FROM acc_kurs WHERE valas = (select top 1 case when valas is null or valas = '' or valas = 'no' then 'IDR' else 'USD' end as valas from ms_profil) and hd.tgl_doc between start_date and end_date), 1)
            * ISNULL((SELECT TOP 1 nilai FROM acc_kurs WHERE valas = hd.valas and hd.tgl_doc between start_date and end_date), 1)

            end
            as harga_satuan_fob, isnull(sdt.uraian, '')+' '+isnull(sdt.spesifikasi, '') as uraian
            FROM doc_import_dt dt
            INNER JOIN ms_barang b on dt.barang_code=b.barang_code
            INNER JOIN ms_barang_hd bh ON bh.header_name = b.barang_category AND bh.header_code <> '0'
            INNER JOIN doc_import_hd hd on dt.doc_id=hd.doc_id
            left JOIN prd_po_dt sdt on dt.dt_po_id=sdt.dt_po_id
            left JOIN prd_po_hd phd on sdt.po_id=phd.po_id
            WHERE
            hd.status = 'finish' and b.barang_status <> 'void' and b.barang_status like '%active%'
            union all

            select dt_doc_id, barang_code, barang_name, satuan_code, barang_category, spesifikasi, jenis_doc, keterangan, jenis_doc1, no_doc, tgl_doc,
            jumlah
            - ( select ISNULL(SUM(rdt.jumlah),0) from prd_request_dt rdt INNER JOIN prd_request_hd hh on rdt.request_id=hh.request_id WHERE (hh.status = 'finish' or hh.status = 'void') and rdt.dt_po_id=m.dt_doc_id and rdt.barang_code = m.barang_code)
            - ( select ISNULL(SUM(exdt.jumlah_barang),0) from doc_export_dt exdt inner join doc_export_hd hhh on exdt.doc_id=hhh.doc_id WHERE hhh.status = 'finish' and exdt.dt_out_id=m.dt_doc_id and exdt.barang_code = m.barang_code)
            as saldo, harga_satuan, 0 as harga_satuan_fob, '' as uraian
            from (
            select ph.prod_id as dt_doc_id, rh.barang_code, b.barang_name, b.satuan_code, b.barang_category,
            '' as spesifikasi, ph.code_prod+' / '+CONVERT(VARCHAR,ph.tgl_prod, 120) as jenis_doc,
            rh.keterangan, ph.code_prod as jenis_doc1, '' as no_doc, ph.tgl_prod as tgl_doc,
            sum(jumlah) as jumlah, CAST(
                SUM(ISNULL(nilai_barang, 0)) / NULLIF(SUM(jumlah), 0)
                AS DECIMAL(18,7)
            ) AS harga_satuan
            from prd_result_hd rh
            INNER JOIN prd_plan_hd ph on rh.prod_id=ph.prod_id
            INNER JOIN ms_barang b on rh.barang_code=b.barang_code
            INNER JOIN ms_barang_hd bh ON bh.header_name = b.barang_category AND bh.header_code <> '0'
            where b.barang_status <> 'void' and b.barang_status like '%active%'
            group by ph.prod_id, rh.barang_code, b.barang_code, b.barang_name, b.barang_category, b.satuan_code, ph.code_prod, rh.keterangan, ph.tgl_prod

            )m
            )sub) z;
            SELECT f.* FROM #fisik f WHERE f.saldo > 0
        SQL;

        $rows = DB::connection('smartit')->select($sql);

        $this->info('Baris diterima dari smartit: ' . count($rows));

        $now = now();
        $inserted = 0;

        // 18 kolom dari smartit + tipe + created_at + updated_at
        $chunkSize = SqlServerChunk::rows(columnsPerRow: 21);

        DB::transaction(function () use ($rows, $chunkSize, $now, &$inserted) {
            DB::table('mon_stock_fisik')->truncate();

            foreach (array_chunk($rows, $chunkSize) as $chunk) {
                $data = array_map(function ($r) use ($now) {
                    $jenisDoc = trim((string) ($r->jenis_doc ?? ''));

                    return [
                        // dokumen = jenis_doc diawali BC, selain itu hasil produksi
                        'tipe'             => stripos($jenisDoc, 'BC') === 0 ? 'dokumen' : 'produksi',
                        'dt_doc_id'        => $r->dt_doc_id,
                        'barang_code'      => trim((string) $r->barang_code),
                        'barang_name'      => $r->barang_name,
                        'satuan_code'      => $r->satuan_code,
                        'barang_category'  => $r->barang_category,
                        'spesifikasi'      => $r->spesifikasi,
                        'jenis_doc'        => $r->jenis_doc,
                        'keterangan'       => $r->keterangan,
                        'jenis_doc1'       => $r->jenis_doc1,
                        'no_doc'           => $r->no_doc,
                        'tgl_doc'          => $r->tgl_doc,
                        'saldo'            => $r->saldo,
                        'harga_satuan'     => $r->harga_satuan,
                        'harga_satuan_fob' => $r->harga_satuan_fob,
                        'uraian'           => $r->uraian,
                        'nilai_harga'      => $r->nilai_harga,
                        'nilai_harga_fob'  => $r->nilai_harga_fob,
                        'digit'            => $r->digit ?? 2,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ];
                }, $chunk);

                DB::table('mon_stock_fisik')->insert($data);
                $inserted += count($data);
            }
        });

        $this->info("Selesai. {$inserted} baris stok fisik diinsert ulang ke mon_stock_fisik (data lama dihapus).");

        return self::SUCCESS;
    }
}
