<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class FaceAttendanceLog extends Model
{
    use HasFactory;

    protected $table = 'face_attendance_logs';

    /**
     * Kolom yang boleh diisi massal.
     *
     * Skema mengikuti tabel yang ada di server:
     *   id, att_id, npk, pin, action, scan_date, latitude, longitude,
     *   accuracy, inside_polygon, work_location_id, face_match,
     *   confidence, liveness_passed, status, rejection_reason,
     *   ip_address, user_agent, created_at, updated_at, face_image
     *
     * Kolom lama (barcode, name, action_type, inoutmode, location_name,
     * location_id) TIDAK didaftarkan karena tidak ada di tabel server.
     * Gunakan accessor actionType() / locationName() untuk membaca nilai
     * yang setara secara kompatibel.
     */
    protected $fillable = [
        // ---- Skema server (utama) ----
        'att_id',
        'npk',
        'pin',
        'action',
        'scan_date',
        'latitude',
        'longitude',
        'accuracy',
        'inside_polygon',
        'work_location_id',
        'face_match',
        'confidence',
        'liveness_passed',
        'status',
        'rejection_reason',
        'ip_address',
        'user_agent',
        'face_image',
        // PERBAIKAN: created_at / updated_at ikut boleh diisi massal supaya
        // FaceAttendanceLog::insert() benar-benar menyimpannya (sebelumnya
        // diam-diam dibuang oleh mass-assignment).
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'scan_date'       => 'datetime',
        'latitude'        => 'float',
        'longitude'       => 'float',
        'accuracy'        => 'float',
        'confidence'      => 'float',
        'inside_polygon'  => 'boolean',
        'face_match'      => 'boolean',
        'liveness_passed' => 'boolean',
        // PERBAIKAN: JANGAN cast att_id ke integer.
        // Di server kolomnya nvarchar(64) (bisa berisi GUID/ID eksternal).
        // Cast ke integer membuat "0" dan merusak nilainya saat insert.
        //'att_id'          => 'integer',
    ];

    /**
     * URL-friendly base64 accessor: pastikan ada prefix data:image/jpeg;base64,
     */
    public function getFaceImageUrlAttribute(): ?string
    {
        if (empty($this->face_image)) {
            return null;
        }

        $img = $this->face_image;
        if (str_starts_with($img, 'data:')) {
            return $img;
        }

        return 'data:image/jpeg;base64,' . $img;
    }

    /**
     * Label aksi presensi, mendukung kedua skema kolom
     * (`action` di skema server, `action_type` di skema lama).
     */
    public function getActionLabelAttribute(): string
    {
        if (!empty($this->action)) {
            return $this->action;
        }

        if (!empty($this->action_type)) {
            return $this->action_type;
        }

        // Fallback dari inoutmode
        if ($this->inoutmode !== null) {
            return ((int) $this->inoutmode) === 0 ? 'Clock In (Masuk)' : 'Clock Out (Pulang)';
        }

        return '-';
    }

    public function scopeForNpk($query, $npk)
    {
        return $query->where('npk', (string) $npk);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('scan_date', $date);
    }

    /** Akhiran tipe aksi: IN / OUT (kompatibel kedua skema kolom). */
    public function actionType(): string
    {
        $val = $this->action ?? ($this->attributes['action_type'] ?? null);

        if ($val === null || $val === '') {
            if (isset($this->attributes['inoutmode'])) {
                return ((int) $this->attributes['inoutmode']) === 0 ? 'IN' : 'OUT';
            }
            return '-';
        }

        $up = strtoupper((string) $val);
        if (in_array($up, ['IN', 'CLOCK IN', 'MASUK', 'I', '0'], true)) {
            return 'IN';
        }
        if (in_array($up, ['OUT', 'CLOCK OUT', 'PULANG', 'O', '1'], true)) {
            return 'OUT';
        }

        return $up;
    }

    /** Nama lokasi kerja; fallback ke koordinat. */
    public function locationName(): ?string
    {
        $name = $this->attributes['location_name'] ?? null;
        if (!empty($name)) {
            return $name;
        }

        if ($this->latitude !== null && $this->longitude !== null) {
            return sprintf('%.6f, %.6f', (float) $this->latitude, (float) $this->longitude);
        }

        return null;
    }

    public function scopeInMonth($query, $month)
    {
        $start = Carbon::parse($month . '-01')->startOfMonth();
        $end = Carbon::parse($month . '-01')->endOfMonth();

        return $query->whereBetween('scan_date', [$start, $end]);
    }

    public function getLocationLabelAttribute(): string
    {
        if (!empty($this->location_name)) {
            return $this->location_name;
        }

        if ($this->latitude !== null && $this->longitude !== null) {
            return sprintf('%.6f, %.6f', $this->latitude, $this->longitude);
        }

        return '-';
    }
}
