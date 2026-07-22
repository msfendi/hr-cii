<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Representasi tabel mon_work_orders -- hasil sinkronisasi query
 * get_ppic_bom.txt dari database smartit (lihat SyncWorkOrderFromSmartit).
 *
 * Satu baris = satu komponen BOM (prd_bom) untuk satu Work Order/Plan
 * (prd_plan_hd) yang statusnya masih 'Unfinish'.
 */
class MonWorkOrder extends Model
{
    protected $table = 'mon_work_orders';

    protected $fillable = [
        'wo_id',

        // prd_plan_hd (h)
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

        // ms_barang (produk jadi)
        'product_name',

        // prd_bom (m)
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

        // ms_barang (material)
        'barang_name',
        'satuan_code',

        // hasil perhitungan
        'request',
        'total',
        'actual_cons',
    ];

    protected $casts = [
        'jumlah_prod'      => 'decimal:4',
        'tgl_prod'         => 'date',
        'tgl_doc'          => 'date',
        'target_hari'      => 'decimal:2',
        'plan_create_date' => 'datetime',
        'plan_modify_date' => 'datetime',
        'cons'             => 'decimal:6',
        'scrap_percent'    => 'decimal:4',
        'bom_create_date'  => 'datetime',
        'bom_modify_date'  => 'datetime',
        'bom_jumlah_prod'  => 'decimal:4',
        'request'          => 'decimal:4',
        'total'            => 'decimal:4',
        'actual_cons'      => 'decimal:6',
    ];
}
