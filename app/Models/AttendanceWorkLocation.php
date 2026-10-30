<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceWorkLocation extends Model
{
    use HasFactory;

    protected $table = 'attendance_work_locations';

    protected $fillable = [
        'name',
        'description',
        'polygon_geojson',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Parse GeoJSON Polygon to array of coordinates [[lng, lat], ...]
     */
    public function getCoordinatesAttribute()
    {
        if (empty($this->polygon_geojson)) {
            return [];
        }

        $data = json_decode($this->polygon_geojson, true);
        if (isset($data['geometry']['coordinates'][0])) {
            return $data['geometry']['coordinates'][0];
        }

        return [];
    }

    /**
     * Hitung jumlah titik polygon
     */
    public function getPointCountAttribute()
    {
        return count($this->coordinates);
    }
}
