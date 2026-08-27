<?php

namespace App\Console\Commands;

use App\Models\MonSubkon;
use App\Support\SqlServerChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncSubkonFromSmartit extends Command
{
    protected $signature = 'monitoring:sync-subkon';

    protected $description = 'Sinkronisasi data Subkon (Kirim Subkon & Terima Subkon) dari smartit ke tabel mon_subkons';

    public function handle(): int
    {
        $this->info('Mengambil data Subkon dari smartit ...');

        $sql = <<<SQL
            select * from(
            select po_id as id_order,no_po as no_order,tgl_po as tgl_order, 'Kirim Subkon' as jenis, p.supplier_code, s.supplier_name
            ,isnull((
            select sum(sdt.jumlah_order)
            from prd_so_dt sdt
            where so_id = p.po_id
            ),0) as qty_material_order
            ,isnull((
            select sum(sdt.jumlah_order)
            from prd_po_dt sdt
            where po_id = p.po_id
            ),0) as qty_result_order
            ,isnull((
            select sum(edt.jumlah_barang)
            from doc_export_dt edt
            inner join doc_export_hd ehd on ehd.doc_id = edt.doc_id
            inner join prd_so_dt sdt on sdt.dt_so_id = edt.dt_so_id
            where
                sdt.so_id = p.po_id and ehd.status = 'finish' and ehd.doc_id not like 'RM-%' and ehd.doc_id not like 'RK-%'
            ),0) as qty_material_aktual
            ,isnull((
            select sum(idt.jumlah_barang)
            from doc_import_dt idt
            inner join doc_import_hd ihd on ihd.doc_id = idt.doc_id
            inner join prd_po_dt pdt on pdt.dt_po_id = idt.dt_po_id
            where pdt.po_id = p.po_id and ihd.status = 'finish' and ihd.doc_id not like 'RM-%' and ihd.doc_id not like 'RK-%'
            ),0) as qty_result_aktual
            from prd_po_hd p
            inner join ms_supplier s on s.supplier_code = p.supplier_code
            where (jenis_subkon != '' and jenis_subkon is not null)
            union all
            select so_id as id_order, no_so as no_order,tgl_so as tgl_order, 'Terima Subkon' as jenis, p.supplier_code, s.supplier_name
            ,isnull((
            select sum(sdt.jumlah_order)
            from prd_po_dt sdt
            where po_id = p.so_id
            ),0) as qty_material_order
            ,isnull((
            select sum(sdt.jumlah_order)
            from prd_so_dt sdt
            where so_id = p.so_id
            ),0) as qty_result_order
            ,isnull((
            select sum(edt.jumlah_barang)
            from doc_import_dt edt
            inner join doc_import_hd ehd on ehd.doc_id = edt.doc_id
            inner join prd_po_dt sdt on sdt.dt_po_id = edt.dt_po_id
            where sdt.po_id = p.so_id and ehd.status = 'finish' and ehd.doc_id not like 'RM-%' and ehd.doc_id not like 'RK-%'
            ),0) as qty_material_aktual
            ,isnull((
            select sum(idt.jumlah_barang)
            from doc_export_dt idt
            inner join doc_export_hd ihd on ihd.doc_id = idt.doc_id
            inner join prd_so_dt pdt on pdt.dt_so_id = idt.dt_so_id
            where pdt.so_id = p.so_id and ihd.status = 'finish' and ihd.doc_id not like 'RM-%' and ihd.doc_id not like 'RK-%'
            ),0) as qty_result_aktual
            from prd_so_hd p
            inner join ms_supplier s on s.supplier_code = p.supplier_code
            where (jenis_subkon != '' and jenis_subkon is not null)
            )m
            order by tgl_order desc
        SQL;

        $rows = DB::connection('smartit')->select($sql);

        $this->info('Baris diterima dari smartit: ' . count($rows));

        $now = now();
        $inserted = 0;

        $chunkSize = SqlServerChunk::rows(columnsPerRow: 12);

        DB::transaction(function () use ($rows, $chunkSize, $now, &$inserted) {
            // Hapus semua data lama sebelum insert ulang (sama seperti sync-rekonsiliasi).
            MonSubkon::truncate();

            foreach (array_chunk($rows, $chunkSize) as $chunk) {
                $data = array_map(function ($r) use ($now) {
                    return [
                        'id_order'            => $r->id_order,
                        'no_order'            => $r->no_order,
                        'tgl_order'           => $r->tgl_order,
                        'jenis'               => $r->jenis,
                        'supplier_code'       => $r->supplier_code,
                        'supplier_name'       => $r->supplier_name,
                        'qty_material_order'  => $r->qty_material_order,
                        'qty_result_order'    => $r->qty_result_order,
                        'qty_material_aktual' => $r->qty_material_aktual,
                        'qty_result_aktual'   => $r->qty_result_aktual,
                        'updated_at'          => $now,
                        'created_at'          => $now,
                    ];
                }, $chunk);

                MonSubkon::insert($data);
                $inserted += count($data);
            }
        });

        $this->info("Selesai. {$inserted} baris Subkon diinsert ke mon_subkons (seluruh data lama dihapus).");

        return self::SUCCESS;
    }
}
