<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BreakMaster extends Model
{
    use HasFactory;

    protected $fillable = [
        'sesi',
        'time_start',
        'time_end',
    ];

    public function departments()
    {
        return $this->hasMany(DeptBreaktime::class, 'id_break');
    }
}
