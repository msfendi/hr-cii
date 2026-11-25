<?php

namespace App\Services\IzinMeninggalkan;

use App\Models\IjinMeninggalkanRequest;
use App\Services\GateSecurity\GateDocument;
use App\Services\WorkAssignment\EmployeeLookupService;
use Illuminate\Support\Facades\DB;

/**
 * Adapter gate untuk Izin Meninggalkan Tugas / Pekerjaan (CII.FM.HR.02.06).
 *
 * Saat tahap 'in' (karyawan kembali) dicatat, selain mengisi `jam_kembali`,
 * adapter memicu sinkronisasi ke tabel absensi lama (`ijin_meninggalkan_pekerjaans`).
 */
class IzinMeninggalkanGateAdapter implements GateDocument
{
    public const DOC_TYPE = 'izin-meninggalkan';

    private const STAGE_OUT = 'out';
    private const STAGE_IN  = 'in';

    public function __construct(private IjinMeninggalkanRequest $request)
    {
    }

    public static function docType(): string
    {
        return self::DOC_TYPE;
    }

    public static function label(): string
    {
        return 'Izin Meninggalkan';
    }

    public static function find(int $id, ?string $token): ?self
    {
        if ($id <= 0) {
            return null;
        }

        $request = IjinMeninggalkanRequest::find($id);

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
        if (!$this->isReady()) {
            return null;
        }

        return $this->request->securityStage();
    }

    public function summary(): array
    {
        $req        = $this->request;
        $pemohon    = $this->applicant();
        $jamKembali = $req->jam_kembali ? substr((string) $req->jam_kembali, 0, 5) : '-';

        return [
            ['label' => 'Pemohon', 'value' => $pemohon['name']],
            ['label' => 'NPK', 'value' => $pemohon['npk']],
            ['label' => 'Tanggal', 'value' => $this->fmtDate($req->tanggal)],
            ['label' => 'Jam Keluar (rencana)', 'value' => $req->jam_keluar ? substr((string) $req->jam_keluar, 0, 5) : '-'],
            ['label' => 'Rencana Kembali', 'value' => $req->rencana_kembali ? substr((string) $req->rencana_kembali, 0, 5) : '-'],
            ['label' => 'Jam Kembali (aktual)', 'value' => $jamKembali],
            ['label' => 'Alasan', 'value' => (string) ($req->alasan ?: '-')],
            [
                'label' => 'Status Approval',
                'value' => $this->isReady() ? 'Disetujui semua level' : 'Belum lengkap',
            ],
        ];
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
        return DB::connection('cii')->transaction(function () use ($stage, $officerNpk, $officerName, $note) {
            $req = IjinMeninggalkanRequest::lockForUpdate()->find($this->request->id);

            if (!$req) {
                return ['ok' => false, 'message' => 'Pengajuan tidak ditemukan.'];
            }

            $this->request = $req;

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

            if ($stage === self::STAGE_IN) {
                $req->jam_kembali = $now->format('H:i:s');
            }

            $req->save();

            if ($stage === self::STAGE_IN) {
                IzinMeninggalkanLegacySync::sync($req);
            }

            return [
                'ok'      => true,
                'message' => $stage === self::STAGE_OUT
                    ? 'Karyawan tercatat KELUAR oleh security ' . $officerName . '.'
                    : 'Karyawan tercatat KEMBALI oleh security ' . $officerName . '.',
                'stage'   => $stage,
            ];
        });
    }

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
