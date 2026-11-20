<?php

namespace App\Services\WorkAssignment;

use App\Services\GateSecurity\GateDocument;
use App\Models\WorkAssignmentRequest;

/**
 * Adapter gate untuk Surat Ijin Dinas Luar (Work Assignment).
 *
 * Menjembatani tabel `work_assignment_requests` ke kontrak GateDocument,
 * supaya halaman Security Gate tidak perlu tahu kolom apa pun milik dokumen
 * ini. Catatan gate tetap disimpan di kolom `security_check` pada tabel itu —
 * bukan tabel bersama — karena tiap dokumen punya siklus hidupnya sendiri.
 */
class WorkAssignmentGateAdapter implements GateDocument
{
    public const DOC_TYPE = 'izin-dinas';

    /** Tahap gate. */
    private const STAGE_OUT = 'out';
    private const STAGE_IN  = 'in';

    public function __construct(private WorkAssignmentRequest $request)
    {
    }

    public static function docType(): string
    {
        return self::DOC_TYPE;
    }

    public static function label(): string
    {
        return 'Izin Dinas';
    }

    /**
     * Cari pengajuan dari isi QR.
     *
     * Token wajib cocok kalau QR membawanya — mencegah nomor urut ditebak.
     */
    public static function find(int $id, ?string $token): ?self
    {
        if ($id <= 0) {
            return null;
        }

        $request = WorkAssignmentRequest::find($id);

        if (!$request) {
            return null;
        }

        if ($token !== null && (string) $request->token !== (string) $token) {
            return null;
        }

        return new self($request);
    }

    public function id(): int
    {
        return (int) $this->request->id;
    }

    public function token(): string
    {
        return (string) $this->request->token;
    }

    public function applicant(): array
    {
        $npk = (string) $this->request->npk;

        try {
            $names = EmployeeLookupService::namesFor([$npk]);
            $name  = $names[$npk] ?? null;
        } catch (\Throwable) {
            $name = null;
        }

        return [
            'npk'  => $npk,
            'name' => $name !== null && $name !== '' ? (string) $name : $npk,
        ];
    }

    public function isReady(): bool
    {
        return $this->request->isReadyForSecurity();
    }

    public function stage(): ?string
    {
        // Belum boleh diproses → tidak ada tahap yang menunggu.
        if (!$this->isReady()) {
            return null;
        }

        return $this->request->securityStage();
    }

    public function summary(): array
    {
        $req    = $this->request;
        $dates  = $req->dates();
        $jumlah = $dates !== [] ? count($dates) : (int) $req->total_days;

        $tanggal = $dates === []
            ? $this->fmtDate($req->start_date) . ' — ' . $this->fmtDate($req->end_date)
            : implode(', ', array_map(fn ($d) => $this->fmtDate($d), array_slice($dates, 0, 3)))
              . (count($dates) > 3 ? ' …' : '');

        $pemohon = $this->applicant();
        $jamKembali = $req->jam_kembali ? substr((string) $req->jam_kembali, 0, 5) : '-';

        $rows = [
            ['label' => 'Pemohon', 'value' => $pemohon['name']],
            ['label' => 'NPK', 'value' => $pemohon['npk']],
            ['label' => 'Dinas Ke', 'value' => (string) ($req->dinas_ke ?: '-')],
            ['label' => 'Keperluan', 'value' => (string) ($req->keperluan ?: '-')],
            ['label' => 'Tanggal', 'value' => $tanggal],
            ['label' => 'Durasi', 'value' => $jumlah . ' hari'],
            ['label' => 'Jam Keluar (rencana)', 'value' => $req->jam_keluar ? substr((string) $req->jam_keluar, 0, 5) : '-'],
            ['label' => 'Rencana Kembali', 'value' => $req->rencana_kembali ? substr((string) $req->rencana_kembali, 0, 5) : '-'],
            ['label' => 'Jam Kembali (aktual)', 'value' => $jamKembali],
            [
                'label' => 'Status Approval',
                'value' => $this->isReady() ? 'Disetujui semua level' : 'Belum lengkap',
            ],
        ];

        return $rows;
    }

    public function gates(): array
    {
        return [
            'out' => $this->request->securityOut(),
            'in'  => $this->request->securityIn(),
        ];
    }

    public function recordGate(string $stage, string $officerNpk, string $officerName, ?string $note = null): array
    {
        $req = $this->request;

        if (!$this->isReady()) {
            return [
                'ok'      => false,
                'message' => 'Pengajuan belum sepenuhnya disetujui. '
                    . 'Security hanya bisa memproses pengajuan yang sudah approved semua level.',
            ];
        }

        $expected = $this->stage();

        if ($expected === null) {
            return [
                'ok'      => false,
                'message' => 'Pengajuan ini sudah lengkap — keluar dan kembali sudah tercatat.',
            ];
        }

        if ($stage !== $expected) {
            return [
                'ok'      => false,
                'message' => $expected === self::STAGE_OUT
                    ? 'Tahap sekarang masih KELUAR. Karyawan belum tercatat keluar.'
                    : 'Tahap sekarang KEMBALI. Karyawan sudah tercatat keluar.',
            ];
        }

        $now   = now();
        $check = $req->security_check ?? [];

        $check[$stage] = [
            'npk'    => $officerNpk,
            'name'   => $officerName,
            'status' => 'approved',
            'at'     => $now->format('Y-m-d H:i:s'),
            'note'   => $note,
        ];

        $req->security_check = $check;

        // Gate kembali → isi jam_kembali sesuai waktu scan.
        if ($stage === self::STAGE_IN) {
            $req->jam_kembali = $now->format('H:i:s');
        }

        $req->save();

        return [
            'ok'      => true,
            'message' => $stage === self::STAGE_OUT
                ? 'Karyawan tercatat KELUAR oleh security ' . $officerName . '.'
                : 'Karyawan tercatat KEMBALI oleh security ' . $officerName . '.',
            'stage'   => $stage,
        ];
    }

    /** Format tanggal d-m-Y. */
    private function fmtDate(mixed $value): string
    {
        if (empty($value)) {
            return '-';
        }

        try {
            return $value instanceof \DateTimeInterface
                ? $value->format('d-m-Y')
                : \Carbon\Carbon::parse((string) $value)->format('d-m-Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}