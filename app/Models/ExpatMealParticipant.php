<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Daftar expat yang makan (sheet "Daftar Expat" pada import): npk, nama
 * expat, tanggal, dan kategori makan (Sarapan / Makan Siang) pada tanggal
 * tersebut. Dipakai sebagai dasar perhitungan biaya makan per expat per hari
 * di ExpatMealController::buildDailyReport().
 */
class ExpatMealParticipant extends Model
{
    protected $table = 'expat_meal_participants';

    protected $fillable = [
        'npk',
        'nama_expat',
        'tanggal',
        'kategori',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];
}
