<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventInvitation extends Model
{
    protected $fillable = [
        'event_id',
        'npk',
        'nama',
        'departemen',
        'status',
        'ucapan',
        'ip_address',
        'responded_at',
    ];

    protected $casts = [
        'event_id'     => 'integer',
        'responded_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /* ------------------------------------------------------------ */
    /*  Scopes                                                        */
    /* ------------------------------------------------------------ */

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeHadir($query)
    {
        return $query->where('status', 'hadir');
    }

    public function scopeTidakHadir($query)
    {
        return $query->where('status', 'tidak_hadir');
    }

    public function scopePending($query)
    {
        return $query->whereNull('status');
    }

    /* ------------------------------------------------------------ */
    /*  Accessors                                                     */
    /* ------------------------------------------------------------ */

    public function getIsConfirmedAttribute(): bool
    {
        return !is_null($this->status);
    }

    public function getIsHadirAttribute(): bool
    {
        return $this->status === 'hadir';
    }
}
