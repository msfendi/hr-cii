<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Master menu makanan expat (sheet "Makanan" pada import).
 * shared = true berarti harga makanan ini otomatis ikut dihitung sebagai
 * biaya makan bersama untuk setiap expat yang tercatat hadir pada kategori
 * (Sarapan / Makan Siang) & tanggal yang sama.
 */
class ExpatMealMenu extends Model
{
    protected $table = 'expat_meal_menus';

    protected $fillable = [
        'tanggal',
        'makanan',
        'kategori',
        'harga',
        'shared',
    ];

    protected $casts = [
        'harga'  => 'decimal:2',
        'shared' => 'boolean',
        'tanggal' => 'date',
    ];
}
