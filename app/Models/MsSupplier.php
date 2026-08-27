<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Master supplier, sync dari smartit.ms_supplier (`monitoring:sync-ms-supplier`).
 * Table: mon_ms_suppliers.
 *
 * `negara_id` menyimpan kode negara (mis. "ID", "VN") yang cocok ke
 * MsNegara::negara_code -- dipakai untuk resolve negara dari
 * mon_shipments.supplier_name (lihat MonitoringRekonsiliasiService).
 */
class MsSupplier extends Model
{
    protected $table = 'mon_ms_suppliers';

    protected $fillable = [
        'supplier_code',
        'supplier_name',
        'npwp',
        'phone',
        'pic',
        'email',
        'rekening',
        'category',
        'kode_sub_ap',
        'kode_sub_ar',
        'negara_id',
        'supplier_status',
        'create_by',
        'create_date',
        'modify_by',
        'modify_date',
        'ppb',
        'tpb',
        'nib',
        'tgl_tpb',
    ];

    protected $casts = [
        'create_date' => 'datetime',
        'modify_date' => 'datetime',
        'tgl_tpb'     => 'date',
        'ppb'         => 'integer',
    ];

    public function negara()
    {
        return $this->belongsTo(MsNegara::class, 'negara_id', 'negara_code');
    }
}
