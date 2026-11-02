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
     *
     * PERBAIKAN (2026-11-03): accessor ini sebelumnya HANYA membaca
     * `geometry.coordinates[0]`, yaitu bentuk GeoJSON Feature. Padahal
     * polygon bisa tersimpan dalam tiga bentuk berbeda tergantung editor
     * peta yang dipakai saat menyimpan:
     *
     *   1. Feature        : {"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[...]]]}}
     *   2. Geometry       : {"type":"Polygon","coordinates":[[[...]]]}
     *   3. Array polos    : [[lng,lat],[lng,lat],...]
     *
     * Pada bentuk 2 dan 3 accessor lama mengembalikan [] sehingga peta
     * mini di modal "Detail Presensi Wajah" tidak pernah menggambar
     * polygon sama sekali (fitur "polygon GPS tidak ada"). Sekarang
     * ketiga bentuk ditangani, termasuk FeatureCollection dan MultiPolygon.
     */
    public function getCoordinatesAttribute()
    {
        if (empty($this->polygon_geojson)) {
            return [];
        }

        $data = is_array($this->polygon_geojson)
            ? $this->polygon_geojson
            : json_decode($this->polygon_geojson, true);

        if (!is_array($data) || empty($data)) {
            return [];
        }

        return $this->extractRing($data);
    }

    /**
     * Ambil ring terluar (outer ring) dari berbagai bentuk GeoJSON.
     *
     * @param array $data Hasil decode GeoJSON.
     * @return array Daftar [lng, lat].
     */
    private function extractRing(array $data): array
    {
        // Bentuk 3: array polos berisi titik.
        if (isset($data[0]) && is_array($data[0]) && isset($data[0][0]) && is_scalar($data[0][0])) {
            return array_map(function ($pt) {
                return [(float) ($pt[0] ?? 0), (float) ($pt[1] ?? 0)];
            }, $data);
        }

        // FeatureCollection -> ambil geometry dari feature pertama.
        if (($data['type'] ?? '') === 'FeatureCollection') {
            $first = $data['features'][0] ?? null;
            if (is_array($first)) {
                return $this->extractRing($first);
            }
            return [];
        }

        // Feature -> turun ke geometry.
        if (($data['type'] ?? '') === 'Feature' && isset($data['geometry'])) {
            return $this->extractRing($data['geometry']);
        }

        // Geometry Polygon / MultiPolygon.
        $coords = $data['coordinates'] ?? null;
        if (is_array($coords) && !empty($coords)) {
            // MultiPolygon: coordinates[[[ [lng,lat], ... ]]]
            if (isset($coords[0][0][0]) && is_array($coords[0][0][0])) {
                $coords = $coords[0];
            }
            // Polygon: coordinates[[ [lng,lat], ... ]] -> ambil outer ring.
            if (isset($coords[0]) && is_array($coords[0]) && isset($coords[0][0]) && is_array($coords[0][0])) {
                $coords = $coords[0];
            }

            if (isset($coords[0]) && is_array($coords[0])) {
                return array_map(function ($pt) {
                    return [(float) ($pt[0] ?? 0), (float) ($pt[1] ?? 0)];
                }, $coords);
            }
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
