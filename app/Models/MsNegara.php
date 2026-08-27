<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Master negara, sync dari smartit.ms_negara (`monitoring:sync-ms-negara`).
 * Table: mon_ms_negaras.
 */
class MsNegara extends Model
{
    protected $table = 'mon_ms_negaras';

    protected $fillable = [
        'negara_code',
        'negara_name',
        'create_by',
        'create_date',
        'modify_by',
        'modify_date',
    ];

    protected $casts = [
        'create_date' => 'datetime',
        'modify_date' => 'datetime',
    ];
}
