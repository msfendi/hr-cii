<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveTypes extends Model
{
    use HasFactory;

    protected $table = 'leave_types';

    /**
     * Jenis cuti yang WAJIB melampirkan dokumen pendukung.
     */
    public const ATTACH_REQUIRED_CODES = [
        'menikah',
        'menikahkan_anak',
        'menghitankan_babtis_anak',
        'suami_istri_meninggal',
        'keluarga_meninggal',
        'anak_meninggal',
        'menantu_meninggal',
        'orang_tua_meninggal',
    ];

    /**
     * Rentang maksimum (hari kalender) dari tanggal pertama untuk pengajuan
     * bermode tanggal bebas.
     */
    public const DATE_WINDOW_DAYS = 30;

    protected $fillable = [
        'name',
        'code',
        'default_days',
        'is_active',
        'gender_type',
        'count_calendar_days',
        'max_requests',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'count_calendar_days' => 'boolean',
        'max_requests'        => 'integer',
    ];

    public function reasons()
    {
        return $this->hasMany(LeaveReason::class, 'leave_type_id');
    }

    /**
     * Apakah jenis cuti ini memakai saldo (balance) tahunan.
     * Hanya Cuti Tahunan yang memakai saldo; jenis lain dibatasi per pengajuan.
     */
    public function usesBalance(): bool
    {
        return strtolower(trim((string) $this->code)) === 'tahunan';
    }

    /**
     * Apakah jenis cuti ini "Cuti Pribadi (Unpaid)".
     */
    public function isPersonal(): bool
    {
        return strtolower(trim((string) $this->code)) === 'pribadi';
    }

    /**
     * Apakah perhitungan hari cuti menghitung hari kalender
     * (termasuk akhir pekan & hari libur). Contoh: melahirkan & keguguran.
     */
    public function countsCalendarDays(): bool
    {
        return (bool) $this->count_calendar_days;
    }

    /**
     * Apakah jenis cuti ini memakai pemesanan tanggal bebas (custom dates)?
     *
     * - Cuti Tahunan: memakai tanggal bebas (jumlah hari mengikuti sisa saldo).
     * - Cuti Mel particulate & Cuti Keguguran: memakai rentang tanggal (hitung hari kalender).
     * - Jenis cuti lainnya: memakai tanggal bebas (tidak harus berurutan).
     */
    public function usesCustomDates(): bool
    {
        return !$this->countsCalendarDays();
    }

    /**
     * Jenis cuti tanpa batas sama sekali (Cuti Pribadi / Unpaid):
     * tidak ada batas hari per pengajuan, tidak ada batas jumlah pengajuan,
     * dan tidak ada batas rentang tanggal.
     */
    public function isUnlimited(): bool
    {
        return $this->isPersonal() || (int) $this->default_days <= 0;
    }

    /**
     * Apakah jumlah hari per pengajuan WAJIB persis sama dengan default_days.
     *
     * Berlaku untuk cuti non-saldo, non-kalender, dan bukan unlimited
     * (mis. Cuti Menikah = 3 hari, Cuti Keluarga Meninggal = 2 hari).
     */
    public function requiresExactDays(): bool
    {
        return $this->usesCustomDates()
            && !$this->usesBalance()
            && !$this->isUnlimited();
    }

    /**
     * Apakah semua tanggal yang dipilih harus berada dalam rentang
     * {@see self::DATE_WINDOW_DAYS} hari dari tanggal pertama.
     *
     * Berlaku untuk semua jenis cuti bermode tanggal bebas, kecuali Cuti Pribadi
     * (Unpaid) yang tidak memiliki batasan.
     */
    public function requiresDateWindow(): bool
    {
        return $this->usesCustomDates() && !$this->isUnlimited();
    }

    /**
     * Panjang jendela waktu (hari kalender) yang diizinkan.
     */
    public function dateWindowDays(): int
    {
        return self::DATE_WINDOW_DAYS;
    }

    /**
     * Batas maksimal hari dalam SATU pengajuan untuk jenis cuti non-saldo.
     * Nilai 0 berarti tanpa batas (unlimited).
     */
    public function maxDaysPerRequest(): int
    {
        if ($this->isUnlimited()) {
            return 0;
        }

        return (int) $this->default_days;
    }

    /**
     * Batas jumlah pengajuan untuk jenis cuti ini (seumur hidup).
     * Null berarti belum ada batas.
     */
    public function maxRequests(): ?int
    {
        if ($this->max_requests === null) {
            return null;
        }

        $max = (int) $this->max_requests;

        return $max > 0 ? $max : null;
    }

    /**
     * Apakah jenis cuti ini wajib melampirkan dokumen pendukung?
     */
    public function requiresAttachment(): bool
    {
        return in_array((string) $this->code, self::ATTACH_REQUIRED_CODES, true);
    }
}
