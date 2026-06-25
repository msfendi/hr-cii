<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IjinMeninggalkanPekerjaan extends Model
{
    protected $table = 'ijin_meninggalkan_pekerjaans';

    protected $fillable = [
        'npk',
        'tanggal',
        'jam_keluar',
        'rencana_kembali',
        'jam_kembali',
        'reason',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];
}
