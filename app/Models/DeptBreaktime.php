<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeptBreaktime extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_dept',
        'id_break',
    ];

    public function breakMaster()
    {
        return $this->belongsTo(BreakMaster::class, 'id_break');
    }
}
