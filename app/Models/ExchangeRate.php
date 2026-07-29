<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'rate_date',
        'currency_from',
        'currency_to',
        'kurs_jual',
        'kurs_beli',
        'kurs_tengah',
        'source',
        'raw_response',
    ];

    protected $casts = [
        'rate_date'    => 'date',
        'kurs_jual'    => 'decimal:2',
        'kurs_beli'    => 'decimal:2',
        'kurs_tengah'  => 'decimal:2',
        'raw_response' => 'array',
    ];

    public function scopeUsdToIdr(Builder $query): Builder
    {
        return $query->where('currency_from', 'USD')->where('currency_to', 'IDR');
    }

    public function scopeOnDate(Builder $query, $date): Builder
    {
        return $query->whereDate('rate_date', $date);
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('rate_date');
    }
}
