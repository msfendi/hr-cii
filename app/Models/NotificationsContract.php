<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationsContract extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'npk',
        'employee_name',
        'contract_end_date',
        'days_remaining',
        'type',
        'status',
        'notified_at',
        'read_at'
    ];

    protected $casts = [
        'contract_end_date' => 'date',
        'notified_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    // RELATIONSHIPS
    public function employeesContract()
    {
        return $this->belongsTo(EmployeesContract::class);
    }

    // SCOPES
    public function scopeUnread($query)
    {
        return $query->where('status', 'unread');
    }

    public function scopeRead($query)
    {
        return $query->where('status', 'read');
    }

    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeExpiring($query)
    {
        return $query->where('type', 'contract_expiring');
    }

    public function scopeExpired($query)
    {
        return $query->where('type', 'contract_expired');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeLast7Days($query)
    {
        return $query->where('created_at', '>=', now()->subDays(7));
    }

    // ACCESSORS & MUTATORS
    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'unread' => 'Belum Dibaca',
            'read' => 'Sudah Dibaca',
            'archived' => 'Diarsipkan',
            default => 'Unknown'
        };
    }

    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'unread' => 'badge-danger',
            'read' => 'badge-secondary',
            'archived' => 'badge-light',
            default => 'badge-secondary'
        };
    }

    public function getTypeLabel()
    {
        return match($this->type) {
            'contract_expiring' => 'Kontrak Akan Habis',
            'contract_expired' => 'Kontrak Sudah Habis',
            default => 'Unknown'
        };
    }

    // METHODS
    public function markAsRead()
    {
        $this->update([
            'status' => 'read',
            'read_at' => now()
        ]);
    }

    public function markAsArchived()
    {
        $this->update([
            'status' => 'archived'
        ]);
    }

    public function toArray()
    {
        return array_merge(parent::toArray(), [
            'status_label' => $this->status_label,
            'status_badge' => $this->status_badge,
            'type_label' => $this->getTypeLabel(),
            'formatted_date' => \Carbon\Carbon::parse($this->created_at)->format('d M Y H:i'),
            'end_date_formatted' => \Carbon\Carbon::parse($this->contract_end_date)->format('d M Y'),
        ]);
    }
}
