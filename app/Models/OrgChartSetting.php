<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

    /**
     * Status kolom `label` di DB (di-cache per request; null = belum dicek).
     */
    protected static ?bool $labelColumnExists = null;

    /**
     * Apakah kolom `label` ada di tabel org_chart_settings?
     */
    public static function hasLabelColumn(): bool
    {
        if (self::$labelColumnExists !== null) {
            return self::$labelColumnExists;
        }

        $exists = false;

        try {
            $exists = Schema::hasTable('org_chart_settings')
                && Schema::hasColumn('org_chart_settings', 'label');
        } catch (\Throwable $e) {
            Log::warning('OrgChartSetting cek kolom label gagal: ' . $e->getMessage());
        }

        return self::$labelColumnExists = $exists;
    }

    /**
     * Pastikan kolom `label` tersedia (idempotent, aman dipanggil berulang).
     *
     * Database bisa belum menjalankan migration
     * 2026_11_11_000004_add_label_to_org_chart_settings sehingga menyimpan
     * setting level Company gagal dengan "Invalid column name 'label'".
     * Bila user DB tidak punya hak ALTER, fungsi ini mengembalikan false dan
     * pemanggil tetap harus jalan (nama Company memakai nilai bawaan).
     */
    public static function ensureLabelColumn(): bool
    {
        if (self::hasLabelColumn()) {
            return true;
        }

        try {
            Schema::table('org_chart_settings', function (Blueprint $table) {
                $table->string('label', 150)->nullable();
            });
        } catch (\Throwable $e) {
            Log::warning('OrgChartSetting gagal menambah kolom label otomatis: ' . $e->getMessage());
        }

        self::$labelColumnExists = null;

        if (! self::hasLabelColumn()) {
            // Jangan coba DDL berulang-ulang di request yang sama.
            self::$labelColumnExists = false;
        }

        return (bool) self::$labelColumnExists;
    }

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
