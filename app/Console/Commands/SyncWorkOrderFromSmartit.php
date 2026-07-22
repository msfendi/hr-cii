<?php

namespace App\Console\Commands;

use App\Models\MonWorkOrder;
use App\Support\SqlServerChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncWorkOrderFromSmartit extends Command
{
    /**
     * php artisan monitoring:sync-work-order
     */
    protected $signature = 'monitoring:sync-work-order';

    protected $description = 'Sinkronisasi data Work Order/BOM (NEED qty) dari database smartit (get_ppic_bom.txt) ke tabel mon_work_orders';

    public function handle(): int
    {
        $this->info('Mengambil data Work Order/BOM dari smartit ...');

        // ================================================================
        // Sesuai get_ppic_bom.txt: SELECT h.*, pb.barang_name AS product_name,
        // m.*, b.barang_name, b.satuan_code, request, total, actual_cons.
        //
        // h.* dan m.* sama-sama punya kolom prod_id / barang_code /
        // jumlah_prod / create_by / create_date / modify_by / modify_date,
        // makanya di sini semua kolom itu diberi ALIAS eksplisit supaya tidak
        // saling menimpa saat diambil sebagai object PHP (nama kolom kembar
        // hanya akan menyisakan nilai yang terakhir kalau tidak dialiaskan).
        //
        // m.bom_id sudah unik per baris komponen BOM, jadi dipakai langsung
        // sebagai wo_id -- tidak perlu lagi DISTINCT untuk menghindari
        // duplikat baris.
        // ================================================================
        $sql = <<<SQL
            ;WITH RequestSummary AS
            (
                SELECT
                    r.prod_id,
                    r.departemen,
                    d.barang_code,
                    SUM(d.jumlah) AS request
                FROM prd_request_hd r
                INNER JOIN prd_request_dt d
                    ON d.request_id = r.request_id
                GROUP BY
                    r.prod_id,
                    r.departemen,
                    d.barang_code
            ),
            RequestCount AS
            (
                SELECT
                    prod_id,
                    COUNT(*) AS total_request
                FROM prd_request_hd
                GROUP BY prod_id
            )

            SELECT
                m.bom_id                                       AS wo_id,

                -- ===== prd_plan_hd (h) =====
                h.prod_id                                      AS prod_id,
                h.code_prod                                    AS code_prod,
                h.barang_code                                  AS product_code,
                h.jumlah_prod                                  AS jumlah_prod,
                h.tgl_prod                                     AS tgl_prod,
                h.tgl_doc                                      AS tgl_doc,
                h.target_hari                                  AS target_hari,
                h.note                                         AS note,
                h.warehouse_id                                 AS warehouse_id,
                h.status                                       AS status,
                h.create_by                                    AS plan_create_by,
                h.create_date                                  AS plan_create_date,
                h.modify_by                                    AS plan_modify_by,
                h.modify_date                                  AS plan_modify_date,
                h.flag_type                                    AS flag_type,

                pb.barang_name                                 AS product_name,

                -- ===== prd_bom (m) =====
                m.prod_id                                      AS bom_prod_id,
                m.barang_code                                  AS barang_code,
                m.uraian                                       AS uraian,
                m.spesifikasi                                  AS spesifikasi,
                m.departemen                                   AS departemen,
                m.cons                                         AS cons,
                m.scrap_percent                                AS scrap_percent,
                m.create_by                                    AS bom_create_by,
                m.create_date                                  AS bom_create_date,
                m.modify_by                                    AS bom_modify_by,
                m.modify_date                                  AS bom_modify_date,
                m.auto_create                                  AS auto_create,
                m.komponen                                     AS komponen,
                m.jumlah_prod                                  AS bom_jumlah_prod,

                -- ===== ms_barang (b) -- material =====
                b.barang_name                                  AS barang_name,
                b.satuan_code                                  AS satuan_code,

                ISNULL(rs.request,0) AS request,

                CASE
                    WHEN m.auto_create = 'yes'
                        THEN ISNULL(m.cons * h.jumlah_prod,0)
                    ELSE
                        ISNULL(rs.request,0)
                END AS total,

                CASE
                    WHEN h.jumlah_prod = 0 THEN 0
                    ELSE ISNULL(rs.request,0) / h.jumlah_prod
                END AS actual_cons

            FROM prd_plan_hd h

            INNER JOIN ms_barang pb
                ON pb.barang_code = h.barang_code

            INNER JOIN prd_bom m
                ON m.prod_id = h.prod_id

            INNER JOIN ms_barang b
                ON b.barang_code = m.barang_code

            INNER JOIN RequestCount rc
                ON rc.prod_id = h.prod_id

            LEFT JOIN RequestSummary rs
                ON rs.prod_id = h.prod_id
               AND rs.barang_code = m.barang_code
               AND (
                    rs.departemen = m.departemen
                    OR m.departemen = ''
                    OR m.departemen IS NULL
               )

            WHERE
                h.status = 'Unfinish'
                AND rc.total_request > 0
        SQL;

        $rows = DB::connection('smartit')->select($sql);

        $this->info('Baris diterima dari smartit: ' . count($rows));

        $now = now();
        $upserted = 0;

        $updateColumns = [
            'prod_id',
            'code_prod',
            'product_code',
            'jumlah_prod',
            'tgl_prod',
            'tgl_doc',
            'target_hari',
            'note',
            'warehouse_id',
            'status',
            'plan_create_by',
            'plan_create_date',
            'plan_modify_by',
            'plan_modify_date',
            'flag_type',
            'product_name',
            'bom_prod_id',
            'barang_code',
            'uraian',
            'spesifikasi',
            'departemen',
            'cons',
            'scrap_percent',
            'bom_create_by',
            'bom_create_date',
            'bom_modify_by',
            'bom_modify_date',
            'auto_create',
            'komponen',
            'bom_jumlah_prod',
            'barang_name',
            'satuan_code',
            'request',
            'total',
            'actual_cons',
            'updated_at',
        ];

        // 38 kolom per baris (37 field data + created_at) -- SQL Server
        // membatasi jumlah parameter per statement ke 2100, jadi ukuran
        // chunk dihitung otomatis dari jumlah kolom lewat SqlServerChunk.
        $chunkSize = SqlServerChunk::rows(columnsPerRow: 38);

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $data = array_map(function ($r) use ($now) {
                return [
                    'wo_id'             => $r->wo_id,

                    'prod_id'           => $r->prod_id,
                    'code_prod'         => $r->code_prod,
                    'product_code'      => $r->product_code,
                    'jumlah_prod'       => $r->jumlah_prod,
                    'tgl_prod'          => $r->tgl_prod,
                    'tgl_doc'           => $r->tgl_doc,
                    'target_hari'       => $r->target_hari,
                    'note'              => $r->note,
                    'warehouse_id'      => $r->warehouse_id,
                    'status'            => $r->status,
                    'plan_create_by'    => $r->plan_create_by,
                    'plan_create_date'  => $r->plan_create_date,
                    'plan_modify_by'    => $r->plan_modify_by,
                    'plan_modify_date'  => $r->plan_modify_date,
                    'flag_type'         => $r->flag_type,

                    'product_name'      => $r->product_name,

                    'bom_prod_id'       => $r->bom_prod_id,
                    'barang_code'       => $r->barang_code,
                    'uraian'            => $r->uraian,
                    'spesifikasi'       => $r->spesifikasi,
                    'departemen'        => $r->departemen,
                    'cons'              => $r->cons,
                    'scrap_percent'     => $r->scrap_percent,
                    'bom_create_by'     => $r->bom_create_by,
                    'bom_create_date'   => $r->bom_create_date,
                    'bom_modify_by'     => $r->bom_modify_by,
                    'bom_modify_date'   => $r->bom_modify_date,
                    'auto_create'       => $r->auto_create,
                    'komponen'          => $r->komponen,
                    'bom_jumlah_prod'   => $r->bom_jumlah_prod,

                    'barang_name'       => $r->barang_name,
                    'satuan_code'       => $r->satuan_code,

                    'request'           => $r->request,
                    'total'             => $r->total,
                    'actual_cons'       => $r->actual_cons,

                    'updated_at'        => $now,
                    'created_at'        => $now,
                ];
            }, $chunk);

            MonWorkOrder::upsert($data, uniqueBy: ['wo_id'], update: $updateColumns);

            $upserted += count($data);
        }

        $this->info("Selesai. {$upserted} baris Work Order/BOM di-upsert ke mon_work_orders.");

        return self::SUCCESS;
    }
}
