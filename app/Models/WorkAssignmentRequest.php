<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Work Assignment Request (Surat Ijin Dinas Luar — CII.FM.HR.02.08)
 *
 * Satu row = satu pengajuan. Data karyawan tidak disimpan (hanya npk);
 * ambil dari join master saat dibutuhkan. Jawaban approval disimpan di
 * `answers` (array JSON), chain approver di `approval_chain` (JSON).
 */
class WorkAssignmentRequest extends Model
{
    use HasFactory;

    protected $table = 'work_assignment_requests';

    /** Eksplisit supaya konsisten dengan DB::connection('cii') di dalam transaksi. */
    protected $connection = 'cii';

    protected $fillable = [
        'token',
        'npk',
        'duty_dates',
        'start_date',
        'end_date',
        'total_days',
        'dinas_ke',
        'keperluan',
        'jam_keluar',
        'rencana_kembali',
        'jam_kembali',
        'approval_chain',
        'answers',
        'status',
        'current_level',
        'void',
        'submitted_by',
    ];

    protected $casts = [
        'duty_dates'      => 'array',
        'approval_chain' => 'array',
        'answers'        => 'array',
        'start_date'     => 'date',
        'end_date'       => 'date',
        'total_days'     => 'integer',
        'current_level'  => 'integer',
    ];

    /**
     * Chain approver (snapshot), urut level.
     *
     * @return array<int, array<string, mixed>>
     */
    public function chain(): array
    {
        $chain = $this->approval_chain ?? [];

        usort($chain, fn ($a, $b) => ($a['level'] ?? 0) <=> ($b['level'] ?? 0));

        return $chain;
    }

    /**
     * Jawaban yang sudah masuk, urut level.
     *
     * @return array<int, array<string, mixed>>
     */
    public function answered(): array
    {
        $answers = $this->answers ?? [];

        usort($answers, fn ($a, $b) => ($a['level'] ?? 0) <=> ($b['level'] ?? 0));

        return $answers;
    }

    /**
     * Level berikutnya yang belum dijawab, atau null kalau sudah selesai.
     */
    public function nextLevel(): ?array
    {
        $answeredLevels = array_column($this->answered(), 'level');

        foreach ($this->chain() as $step) {
            if (!in_array($step['level'] ?? null, $answeredLevels, true)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * True kalau pengajuan masih bisa diproses.
     */
    public function isOpen(): bool
    {
        return $this->status === 'pending'
            && !in_array(strtolower((string) $this->void), ['true', '1'], true);
    }

    /**
     * Tanggal dinas (array Y-m-d).
     *
     * @return array<int, string>
     */
    public function dates(): array
    {
        return $this->duty_dates ?? [];
    }

    /**
     * Scope pengajuan milik seorang pemohon.
     */
    public function scopeForNpk($query, string $npk)
    {
        return $query->where('npk', $npk);
    }
}
