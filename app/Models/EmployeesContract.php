<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmployeesContract extends Model
{
    use HasFactory;

    protected $connection = 'cii';
    protected $table      = 'employees_contract';
    protected $primaryKey = 'id';
    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'id',
        'npk',
        'contract_ke',
        'start_date',
        'end_date',
        'month_duration',
        'day_duration',
        'status_contract',
        'salary',
        'type',
        'daily_salary',
        'allowance',
        'pph21',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'salary'     => 'decimal:2',
        'allowance'  => 'decimal:2',
        'daily_salary'  => 'decimal:2',
        'pph21'      => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    // ─── Accessors ────────────────────────────────────────────

    public function getSisaHariAttribute(): int
    {
        return $this->end_date
            ? (int) now()->startOfDay()->diffInDays($this->end_date, false)
            : 0;
    }

    public function getUrgensiAttribute(): string
    {
        $s = $this->sisa_hari;
        if ($s <= 7)  return 'urgent';
        if ($s <= 14) return 'soon';
        if ($s <= 30) return 'upcoming';
        return 'normal';
    }

    /**
     * Selisih end_date vs cut-off gaji tgl 7 & 20 (same month).
     * Positif  = end_date sudah melewati cut-off.
     * Negatif  = end_date masih sebelum cut-off.
     */
    public function getCutoffSelisihAttribute(): array
    {
        if (!$this->end_date) return ['ke7' => 0, 'ke20' => 0];
        $day = (int) $this->end_date->format('d');
        return ['ke7' => $day - 7, 'ke20' => $day - 20];
    }

    // ─── Relationships ────────────────────────────────────────

    public function biodata()
    {
        return $this->belongsTo(Biodata::class, 'npk', 'NPK');
    }

    public function notifications()
    {
        return $this->hasMany(NotificationsContract::class);
    }

    // SCOPES - Untuk mencari kontrak yang akan habis
    public function scopeActive($query)
    {
        return $query->whereNotIn('status_contract', ['habis', 'diakhiri']);
    }

    public function scopeExpiringInDays($query, $days = 5)
    {
        $startDate = now();
        $endDate = now()->addDays($days);

        return $query->active()
            ->whereDate('end_date', '>=', $startDate)
            ->whereDate('end_date', '<=', $endDate);
    }

    public function scopeExpiringToday($query)
    {
        return $query->active()
            ->whereDate('end_date', today());
    }

    // METHODS
    public function getDaysRemaining()
    {
        if ($this->status_contract === 'habis' || $this->status_contract === 'diakhiri') {
            return 0;
        }

        return $this->sisa_hari;
    }

    public function getIsExpiringAttribute()
    {
        return $this->getDaysRemaining() <= 5 && $this->getDaysRemaining() >= 0;
    }

    public function getIsExpiredAttribute()
    {
        return $this->end_date->isPast() && $this->status_contract !== 'habis';
    }

    public function getEmployeeName()
    {
        return $this->biodata?->NAMA_KARYAWAN ?? 'N/A';
    }
}
