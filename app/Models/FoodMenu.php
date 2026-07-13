<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class FoodMenu extends Model
{
    protected $fillable = [
        'canteen_id',
        'name',
        'description',
        'photo',
        'price',
        'available_start',
        'available_end',
        'available_dates',
        'quota',
        'is_active',
    ];

    protected $casts = [
        'available_dates' => 'array',
        'is_active'        => 'boolean',
        'price'            => 'decimal:2',
    ];

    public function canteen()
    {
        return $this->belongsTo(Canteen::class);
    }

    public function foodOrders()
    {
        return $this->hasMany(FoodOrder::class);
    }

    /**
     * Cek apakah menu ini tersedia di tanggal tertentu
     * berdasarkan rentang tanggal umum dan/atau daftar tanggal khusus.
     */
    public function isAvailableOn(Carbon $date): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->available_start && $date->lt(Carbon::parse($this->available_start)->startOfDay())) {
            return false;
        }

        if ($this->available_end && $date->gt(Carbon::parse($this->available_end)->endOfDay())) {
            return false;
        }

        // Jika daftar tanggal khusus diisi, menu hanya tersedia pada tanggal-tanggal tersebut
        if (!empty($this->available_dates)) {
            if (!in_array($date->toDateString(), $this->available_dates, true)) {
                return false;
            }
        }

        return true;
    }

    public function remainingQuota(Carbon $date): ?int
    {
        if (is_null($this->quota)) {
            return null; // unlimited
        }

        $ordered = $this->foodOrders()
            ->whereDate('order_date', $date->toDateString())
            ->where('status', '!=', 'cancelled')
            ->count();

        return max(0, $this->quota - $ordered);
    }
}
