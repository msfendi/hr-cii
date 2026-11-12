<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Setting MANUAL organization chart.
 * Satu baris = satu node organisasi: id_dept (DEPT.ID_DEPT) + level
 * (company/department) + parent_id (baris lain).
 * Karyawan di bawah node diambil dari BIODATA/PKWT aktif sesuai id_dept.
 */
class OrgChartSetting extends Model
{
    // Sesuai permintaan: level hanya Company dan Department.
    public const LEVELS = [
        'company' => 'Company',
        'department' => 'Department',
    ];

    /**
     * Nama bawaan untuk level Company bila kolom label dikosongkan.
     * Level tertinggi memakai NAMA COMPANY, bukan nama department (ID_DEPT).
     */
    public const COMPANY_NAME_FALLBACK = 'PT. CHUTEX INTERNATIONAL INDONESIA';

    protected $fillable = [
        'id_dept',
        'label',
        'level',
        'parent_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'parent_id' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getLevelLabelAttribute(): string
    {
        return self::LEVELS[$this->level] ?? ucfirst((string) $this->level);
    }

    /**
     * Nama node untuk ditampilkan di chart.
     * Level company: pakai label (nama company), bukan nama department.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->level === 'company') {
            $label = trim((string) $this->label);

            return $label !== '' ? $label : self::COMPANY_NAME_FALLBACK;
        }

        $label = trim((string) $this->label);

        return $label !== '' ? $label : (string) $this->id_dept;
    }
}
