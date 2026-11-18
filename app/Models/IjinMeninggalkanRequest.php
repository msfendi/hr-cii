<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Permohonan Izin Meninggalkan Tugas / Pekerjaan (CII.FM.HR.02.06).
 *
 * Satu row per pengajuan. Rantai approver disimpan sebagai snapshot JSON
 * (`approval_chain`) dan keputusan per level di `answers` — pola yang sama
 * dengan Izin Dinas, berbeda dari cuti yang memakai satu row per level.
 *
 * Baris ini BUKAN data absensi. Salinan ke `ijin_meninggalkan_pekerjaans`
 * (tabel yang dibaca payroll) baru dibuat setelah security mencatat karyawan
 * kembali; id hasil salinan disimpan di `legacy_ijin_id`.
 */
class IjinMeninggalkanRequest extends Model
{
    protected $table = 'ijin_meninggalkan_requests';

    protected $fillable = [
        'token',
        'npk',
        'tanggal',
        'jam_keluar',
        'rencana_kembali',
        'jam_kembali',
        'alasan',
        'status',
        'current_level',
        'approval_chain',
        'answers',
        'security_check',
        'legacy_ijin_id',
        'void',
        'submitted_by',
    ];

    protected $casts = [
        'tanggal'        => 'date',
        'approval_chain' => 'array',
        'answers'        => 'array',
        'security_check' => 'array',
        'current_level'  => 'integer',
        'legacy_ijin_id' => 'integer',
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
     * Level berikutnya yang belum dijawab, atau null kalau selesai.
     *
     * @return array<string, mixed>|null
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
     * decision → null | approved | rejected | waiting | upcoming | skipped
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

        // Level yang paling rendah yang menolak → level di atasnya berhenti.
        $rejectedAt = null;

        foreach ($answers as $level => $answer) {
            if (strtolower((string) ($answer['decision'] ?? '')) === 'rejected') {
                $rejectedAt = $rejectedAt === null ? $level : min($rejectedAt, $level);
            }
        }

        $current  = (int) $this->current_level;
        $timeline = [];

        foreach ($this->chain() as $step) {
            $level  = (int) ($step['level'] ?? 0);
            $answer = $answers[$level] ?? null;

            if ($answer !== null) {
                $decision = strtolower((string) ($answer['decision'] ?? '')) ?: 'waiting';
            } elseif ($rejectedAt !== null && $level > $rejectedAt) {
                $decision = 'skipped';
            } elseif ($level < $current) {
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
     * Ringkasan status untuk badge di daftar & detail.
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
            $label  = 'Menunggu';
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

    /** True kalau pengajuan masih bisa diproses. */
    public function isOpen(): bool
    {
        return $this->status === 'pending'
            && !in_array(strtolower((string) $this->void), ['true', '1'], true);
    }

    /** Scope pengajuan milik seorang pemohon. */
    public function scopeForNpk($query, string $npk)
    {
        return $query->where('npk', $npk);
    }
}