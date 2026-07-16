<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoorprizeWinner extends Model
{
    protected $fillable = [
        'npk',
        'name',
        'department',
        'photo',
        'batch_label',
        'drawn_by',
        'won_at',
        'is_void',
        'void_reason',
        'voided_at',
        'voided_by',
    ];

    protected $casts = [
        'won_at'    => 'datetime',
        'voided_at' => 'datetime',
        'is_void'   => 'boolean',
    ];

    public function drawnBy()
    {
        return $this->belongsTo(User::class, 'drawn_by');
    }

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * Pemenang yang masih sah (belum dihanguskan).
     */
    public function scopeActive($query)
    {
        return $query->where('is_void', false);
    }

    /**
     * Pemenang yang sudah dihanguskan.
     */
    public function scopeVoided($query)
    {
        return $query->where('is_void', true);
    }
}
