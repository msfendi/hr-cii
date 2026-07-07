<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobVacancy extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'position',
        'department_id',
        'total_needed',
        'employment_type',
        'job_description',
        'criteria',
        'required_documents',
        'open_date',
        'close_date',
        'status',
        'created_by',
        // Tautan ke tabel recruitment_positions (koneksi 'cii') supaya
        // update/hapus lowongan bisa langsung menyinkronkan baris terkait.
        'recruitment_position_id',
    ];

    protected $casts = [
        'criteria' => 'array',
        'required_documents' => 'array',
        'open_date' => 'date',
        'close_date' => 'date',
    ];

    protected $appends = [
        'computed_status',
        'computed_status_label',
        'employment_type_label',
        'days_left',
    ];

    /*
    |--------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------
    | Catatan: department TIDAK direlasikan lewat Eloquent karena data
    | department berada di koneksi database lain ('cii', tabel DEPT).
    | Nama department diambil manual di JobVacancyController::data().
    */

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Baris terkait di recruitment_positions (koneksi 'cii') yang selalu
     * disinkronkan setiap kali lowongan dibuat/diubah/ditutup/dihapus.
     */
    public function recruitmentPosition()
    {
        return $this->belongsTo(RecruitmentPosition::class, 'recruitment_position_id');
    }

    /*
    |--------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------
    */

    public function getEmploymentTypeLabelAttribute(): string
    {
        return match ($this->employment_type) {
            'full_time' => 'Full Time',
            'part_time' => 'Part Time',
            'contract' => 'Kontrak',
            'internship' => 'Magang',
            'daily_worker' => 'Harian Lepas',
            default => '-',
        };
    }

    /**
     * Status aktual lowongan berdasarkan kombinasi status manual & tanggal berlaku.
     */
    public function getComputedStatusAttribute(): string
    {
        if ($this->status === 'closed') {
            return 'closed';
        }

        if ($this->status === 'draft') {
            return 'draft';
        }

        $today = Carbon::today();

        if ($this->close_date && $today->gt($this->close_date)) {
            return 'expired';
        }

        if ($this->open_date && $today->lt($this->open_date)) {
            return 'upcoming';
        }

        return 'open';
    }

    public function getComputedStatusLabelAttribute(): string
    {
        return match ($this->computed_status) {
            'open' => 'Dibuka',
            'upcoming' => 'Akan Dibuka',
            'expired' => 'Berakhir',
            'closed' => 'Ditutup',
            'draft' => 'Draft',
            default => '-',
        };
    }

    /**
     * Sisa hari sampai lowongan ditutup. Null jika tidak ada close_date.
     */
    public function getDaysLeftAttribute(): ?int
    {
        if (!$this->close_date) {
            return null;
        }

        $today = Carbon::today();

        if ($today->gt($this->close_date)) {
            return 0;
        }

        return (int) $today->diffInDays($this->close_date, false);
    }

    /*
    |--------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------
    */

    public function scopeFilter($query, array $filters)
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $v) {
                $q->where('position', 'like', "%{$v}%");
            })
            ->when($filters['department'] ?? null, function ($q, $v) {
                $q->where('department_id', $v);
            })
            ->when($filters['employment_type'] ?? null, function ($q, $v) {
                $q->where('employment_type', $v);
            })
            ->when($filters['status'] ?? null, function ($q, $v) {
                $today = Carbon::today()->toDateString();

                return match ($v) {
                    'open' => $q->where('status', 'open')
                        ->whereDate('open_date', '<=', $today)
                        ->whereDate('close_date', '>=', $today),
                    'upcoming' => $q->where('status', 'open')
                        ->whereDate('open_date', '>', $today),
                    'expired' => $q->where('status', 'open')
                        ->whereDate('close_date', '<', $today),
                    'closed' => $q->where('status', 'closed'),
                    'draft' => $q->where('status', 'draft'),
                    default => $q,
                };
            });
    }
}
