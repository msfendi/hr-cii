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
     * Timeline approval: chain digabung dengan jawaban yang sudah masuk.
     *
     * Setiap entri:
     *   level, role_label, name, jabatan, npk,
     *   decision  → null | approved | rejected | waiting | skipped
     *   reason    → alasan penolakan (kalau ada)
     *   decided_at, decided_by
     *
     * @return array<int, array<string, mixed>>
     */
    public function timeline(): array
    {
        $answers = [];

        foreach ($this->answered() as $answer) {
            $level = $answer['level'] ?? null;
            if ($level !== null) {
                $answers[(int) $level] = $answer;
            }
        }

        $timeline = [];

        // Level tertinggi yang sudah ditolak → semua level di atasnya tidak
        // akan pernah diminta (chain berhenti).
        $rejectedAt = null;

        foreach ($this->chain() as $step) {
            $level = (int) ($step['level'] ?? 0);
            $answer = $answers[$level] ?? null;

            if ($answer !== null && strtolower((string) ($answer['decision'] ?? '')) === 'rejected') {
                $rejectedAt = $rejectedAt === null ? $level : min($rejectedAt, $level);
            }
        }

        $current = (int) $this->current_level;

        foreach ($this->chain() as $step) {
            $level   = (int) ($step['level'] ?? 0);
            $answer  = $answers[$level] ?? null;

            if ($answer !== null) {
                $decision = strtolower((string) ($answer['decision'] ?? '')) ?: 'waiting';
            } elseif ($rejectedAt !== null && $level > $rejectedAt) {
                // Di atas level yang ditolak → tidak akan pernah diminta.
                $decision = 'skipped';
            } elseif ($level < $current) {
                // Level terlewati tanpa jawaban → dilewati (mis. chain berubah).
                $decision = 'skipped';
            } elseif ($level === $current) {
                $decision = 'waiting';
            } else {
                $decision = 'upcoming';
            }

            $timeline[] = [
                'level'      => $level,
                'role_label' => (string) ($step['role_label'] ?? ''),
                'name'       => (string) ($step['name'] ?? ''),
                'jabatan'    => (string) ($step['jabatan'] ?? ($step['role_label'] ?? '')),
                'npk'        => (string) ($step['npk'] ?? ''),
                'decision'   => $decision,
                'reason'     => $answer ? ($answer['reason'] ?? null) : null,
                'decided_at' => $answer ? ($answer['decided_at'] ?? null) : null,
                'decided_by' => $answer ? ($answer['decided_by'] ?? null) : null,
            ];
        }

        return $timeline;
    }

    /**
     * Ringkasan status approval untuk ditampilkan.
     *
     * @return array{status:string,label:string,tone:string,done:int,total:int}
     */
    public function progress(): array
    {
        $timeline = $this->timeline();
        $total    = count($timeline);
        $done     = 0;
        $rejected = false;

        foreach ($timeline as $step) {
            if ($step['decision'] === 'approved') {
                $done++;
            } elseif ($step['decision'] === 'rejected') {
                $rejected = true;
            }
        }

        if ($rejected) {
            $status = 'rejected';
            $label  = 'Ditolak';
            $tone   = 'danger';
        } elseif ($total > 0 && $done >= $total) {
            $status = 'approved';
            $label  = 'Disetujui';
            $tone   = 'success';
        } elseif ($this->status === 'cancelled') {
            $status = 'cancelled';
            $label  = 'Dibatalkan';
            $tone   = 'secondary';
        } else {
            $status = 'pending';
            $label  = 'Menunggu Persetujuan';
            $tone   = 'warning';
        }

        return [
            'status' => $status,
            'label'  => $label,
            'tone'   => $tone,
            'done'   => $done,
            'total'  => $total,
        ];
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
