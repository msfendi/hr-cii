<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class FoodOrder extends Model
{
    protected $fillable = [
        'npk',
        'food_menu_id',
        'canteen_id',
        'order_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'date',
    ];

    public function foodMenu()
    {
        return $this->belongsTo(FoodMenu::class);
    }

    public function canteen()
    {
        return $this->belongsTo(Canteen::class);
    }

    /**
     * Pesanan hanya boleh diubah/dibatalkan SEBELUM hari-H.
     * Kalau order_date == hari ini atau sudah lewat -> terkunci.
     */
    public function canBeEdited(): bool
    {
        return Carbon::parse($this->order_date)->startOfDay()->gt(now()->startOfDay());
    }
}
