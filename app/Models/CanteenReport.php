<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CanteenReport extends Model
{
    /**
     * Koneksi database khusus canteen (SQL Server via ODBC, host/kredensial
     * sama seperti koneksi 'cii', database berbeda). Lihat config/database.php.
     */
    protected $connection = 'canteen';

    protected $table = 'canteen';

    public $timestamps = true;

    protected $fillable = [
        'canteen_no',
        'npk',
        'name',
        'dept',
        'date',
        // PERBAIKAN V32 (2026-11-09): kolom tambahan agar tidak diam-diam
        // diabaikan oleh mass-assignment. Tanpa ini, window_name /
        // location_name / latitude / longitude / photo_face TIDAK AKAN
        // tersimpan walau controller mengirimkannya.
        'window_name',
        'location_name',
        'latitude',
        'longitude',
        'photo_face',
        // PERBAIKAN V41 (2026-11-10): identitas unik per transaksi scan
        // kantin. Tanpa ini di fillable, kolom id_ticket akan diam-diam
        // diabaikan mass-assignment dan tidak pernah tersimpan.
        'id_ticket',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
