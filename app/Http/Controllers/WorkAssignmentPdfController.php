<?php

namespace App\Http\Controllers;

use App\Models\WorkAssignmentRequest;
use App\Services\WorkAssignment\EmployeeLookupService;
use App\Services\WorkAssignment\WorkAssignmentLogo;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Generate Surat Ijin Dinas Luar (CII.FM.HR.02.08) sebagai PDF.
 *
 * Layout mengikuti form resmi:
 *   Header  : logo + judul "SURAT IJIN DINAS LUAR" + kontrol dokumen
 *   Isian   : 7 baris bernomor + jam kembali
 *   TTD     : 4 kolom (Yang diberi Tugas / Manager Departemen / HR Manager / GM)
 *   Catatan : "Keterangan: Harus ditandatangani oleh G.M / Manager yang bersangkutan..."
 *
 * PDF dibuat dengan dompdf (landscape A4) supaya resemble form kertas.
 */
class WorkAssignmentPdfController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Tampilkan PDF (inline di browser).
     */
    public function show($id)
    {
        $req = WorkAssignmentRequest::find($id);

        if (!$req) {
            abort(404, 'Pengajuan tidak ditemukan.');
        }

        return response($this->build($req), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="surat-ijin-dinas-' . $req->id . '.pdf"',
        ]);
    }

    /**
     * Download PDF.
     */
    public function download($id)
    {
        $req = WorkAssignmentRequest::find($id);

        if (!$req) {
            abort(404, 'Pengajuan tidak ditemukan.');
        }

        return response($this->build($req), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="surat-ijin-dinas-' . $req->id . '.pdf"',
        ]);
    }

    /**
     * Susun isi PDF.
     */
    private function build(WorkAssignmentRequest $req): string
    {
        $employee = EmployeeLookupService::lookup((string) $req->npk);
        $emp      = $employee['ok'] ? $employee['data'] : [];

        // Kolom tanda tangan: samakan dengan urutan chain approval.
        // signatories diambil dari chain supaya orangnya benar-benar yang
        // menyetujui — bukan placeholder.
        $signatories = $this->signatories($req);

        $dates = array_map(
            fn ($d) => Carbon::parse($d)->format('d-m-Y'),
            $req->dates()
        );

        $shift = null;
        try {
            $shift = DB::connection('cii')
                ->table('employee_shifts as es')
                ->join('shifts as s', 's.id', '=', 'es.shift_id')
                ->where('es.npk', (string) $req->npk)
                ->whereDate('es.shift_date', $req->start_date?->format('Y-m-d') ?? Carbon::parse($req->dates()[0] ?? now())->format('Y-m-d'))
                ->value('s.name');
        } catch (\Throwable $e) {
            Log::warning('[WorkAssignmentPdf] gagal lookup shift: ' . $e->getMessage());
        }

        $data = [
            'doc_no'       => 'CII.FM.HR.02.08',
            'active_date'  => '13 Mei 2025',
            'rev'          => '00',
            'rev_date'     => '-',
            'title'        => 'SURAT IJIN DINAS LUAR',
            'id'           => $req->id,
            'logoPath'     => WorkAssignmentLogo::path(),

            'nama'         => $emp['nama'] ?? $req->npk,
            'npk'          => $req->npk,
            'jabatan'      => $emp['jabatan'] ?? '-',
            'bagian'       => $emp['bagian'] ?? '-',
            'shift'        => $shift ?: 'Normal',
            'departemen'   => $emp['departemen'] ?? '-',

            'tanggal'      => implode(', ', $dates),
            'total_days'   => $req->total_days,
            'dinas_ke'     => $req->dinas_ke,
            'keperluan'    => $req->keperluan,

            'jam_keluar'      => $req->jam_keluar ? substr((string) $req->jam_keluar, 0, 5) : '-',
            'rencana_kembali' => $req->rencana_kembali ? substr((string) $req->rencana_kembali, 0, 5) : '-',
            'jam_kembali'     => $req->jam_kembali ? substr((string) $req->jam_kembali, 0, 5) : '-',

            'kota'          => 'Sukoharjo, ' . Carbon::now()->format('d-m-Y'),
            'signatories'    => $signatories,
            'signature_columns' => (array) config('work_assignment.signature_columns', []),
        ];

        try {
            $pdf = Pdf::loadView('work_assignment.pdf.surat_ijin_dinas', $data)
                ->setPaper('a4', 'landscape');

            return $pdf->output();
        } catch (\Throwable $e) {
            Log::error("[WorkAssignmentPdf] gagal render #{$req->id}: " . $e->getMessage());

            abort(500, 'Gagal membuat PDF: ' . $e->getMessage());
        }
    }

    /**
     * Siapkan 4 kolom tanda tangan dari chain approval.
     *
     * Kolom diambil berurutan dari chain. Orang yang menyetujui ditampilkan
     * di bawah kolomnya, jadi jelas siapa yang sudah menyetujui apa.
     *
     * @return array<int, array{label:string, name:string, jabatan:string, npk:string, decision:string, decided_at:?string}>
     */
    private function signatories(WorkAssignmentRequest $req): array
    {
        $columns = (array) config('work_assignment.signature_columns', []);

        if (empty($columns)) {
            $columns = [
                ['key' => 'yang_diberi_tugas',  'label' => 'Yang diberi Tugas'],
                ['key' => 'manager_departemen', 'label' => 'Manager Departemen'],
                ['key' => 'hr_manager',         'label' => 'HR Manager'],
                ['key' => 'general_manager',    'label' => 'General Manager'],
            ];
        }

        $chain   = $req->chain();
        $answers = [];

        foreach ($req->answered() as $answer) {
            $answers[(int) ($answer['level'] ?? 0)] = $answer;
        }

        $result = [];
        $used   = [];

        foreach ($columns as $col) {
            $key   = (string) ($col['key'] ?? '');
            $label = (string) ($col['label'] ?? $key);

            // Cari step di chain yang role-nya cocok.
            $step = null;

            foreach ($chain as $i => $s) {
                if (in_array($i, $used, true)) {
                    continue;
                }

                if ((string) ($s['role_key'] ?? '') === $key) {
                    $step = $s;
                    $used[] = $i;
                    break;
                }
            }

            // Kalau role tidak ada, pakai step berikutnya yang belum dipakai
            // (chain bisa punya fewer/more level dari kolom tanda tangan).
            if ($step === null) {
                foreach ($chain as $i => $s) {
                    if (!in_array($i, $used, true)) {
                        $step = $s;
                        $used[] = $i;
                        break;
                    }
                }
            }

            if ($step === null) {
                $result[] = [
                    'label'      => $label,
                    'name'       => '',
                    'jabatan'    => '',
                    'npk'        => '',
                    'decision'   => '',
                    'decided_at' => null,
                ];

                continue;
            }

            $level    = (int) ($step['level'] ?? 0);
            $answer   = $answers[$level] ?? null;
            $decision = '';

            if ($answer !== null) {
                $decision = strtolower((string) ($answer['decision'] ?? '')) === 'approved'
                    ? 'Disetujui'
                    : 'Ditolak';
            }

            $result[] = [
                'label'      => $label,
                'name'       => (string) ($step['name'] ?? ''),
                'jabatan'    => (string) ($step['jabatan'] ?? ''),
                'npk'        => (string) ($step['npk'] ?? ''),
                'decision'   => $decision,
                'decided_at' => $answer['decided_at'] ?? null,
            ];
        }

        return $result;
    }
}
