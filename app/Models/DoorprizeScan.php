<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoorprizeScan extends Model
{
    protected $fillable = [
        'npk',
        'scanned_by',
        'scanned_at',
        'is_winner',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'is_winner'  => 'boolean',
    ];

    public function scannedBy()
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_winner', false);
    }
}
