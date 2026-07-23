<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Representasi tabel ms_barangs -- hasil sinkronisasi master data barang
 * (`ms_barang`) dari database smartit (lihat SyncMsBarangFromSmartit).
 *
 * Dipakai terutama untuk `barang_category`, yang menjadi dasar pengelompokan
 * chart MATERIAL ACHIEVEMENT (Fabric / Aksesoris / Packing) dan scope
 * kategori pada tahapan produksi (Cutting = Bahan Setengah Jadi,
 * Sewing/Packing/Warehouse/Shipment = Barang Jadi) di
 * MonitoringRekonsiliasiService.
 */
class MsBarang extends Model
{
    protected $table = 'ms_barangs';

    protected $fillable = [
        'barang_code',
        'barang_name',
        'satuan_code',
        'barang_category',
        'header_code',
        'hs_code',
        'kode_sub_ap',
        'kode_sub_ar',
        'barang_status',
        'create_by',
        'create_date',
        'modify_by',
        'modify_date',
        'satuan_hs',
        'konversi',
        'kode',
    ];

    protected $casts = [
        'create_date' => 'datetime',
        'modify_date' => 'datetime',
        'konversi'    => 'decimal:4',
    ];

    /** Kategori material yang dipakai untuk memisah card MATERIAL ACHIEVEMENT. */
    public const GROUP_FABRIC    = ['Bahan Baku Lokal', 'Bahan Baku Import'];
    public const GROUP_AKSESORIS = ['Bahan Penolong'];
    public const GROUP_PACKING   = ['Packaging'];

    /** Kategori material di tahap Cutting (WIP) vs tahap produk jadi. */
    public const CATEGORY_WIP    = 'Bahan Setengah Jadi';
    public const CATEGORY_JADI   = 'Barang Jadi';
}
