<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonPurchaseOrder extends Model
{
    protected $table = 'mon_purchase_orders';

    protected $guarded = ['id'];

    protected $casts = [
        'tgl_po' => 'date',
        'jumlah_order' => 'decimal:4',
        'jumlah_doc' => 'decimal:4',
        'sisa' => 'decimal:4',
    ];

    /**
     * jenis_po yang dianggap "pembelian material" untuk pivot MATERIAL PURCHASE.
     */
    public const MATERIAL_JENIS_PO = ['PO', 'Material Supply'];
}
