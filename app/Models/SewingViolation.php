<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SewingViolation extends Model
{
    protected $table = 'sewing_violations';

    protected $fillable = [
        'id_dept',
        'pelanggaran',
        'tanggal'
    ];
}
