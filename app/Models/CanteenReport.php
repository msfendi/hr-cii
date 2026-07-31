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
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
