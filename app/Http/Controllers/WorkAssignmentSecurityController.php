<?php

namespace App\Http\Controllers;

use App\Models\WorkAssignmentRequest;
use App\Services\WorkAssignment\EmployeeLookupService;
use App\Services\WorkAssignment\WorkAssignmentSecurityGate;
use Illuminate\Http\Request;
use Throwable;

/**
 * Halaman Security — gate check Surat Ijin Dinas Luar.
 *
 * Alur:
 *   1. Security login dengan NPK (cukup terdaftar di PKWT).
 *   2. Scan QR pengajuan (kamera atau ketik kode manual).
 *   3. Sistem tampilkan data pengajuan + status approval + catatan gate.
 *   4. Security approve: scan pertama = KELUAR, scan berikutnya = KEMBALI
 *      (sekaligus mengisi jam_kembali).
 *
 * Berbasis session seperti halaman pemohon, terpisah dari auth Laravel.
 */
class WorkAssignmentSecurityController extends Controller
{
    private const SESSION_NPK  = 'work_assignment_security_npk';
    private const SESSION_NAME = 'work_assignment_security_name';

    // ─────────────────────────────────────────────────────────────
    // Login security
    // ─────────────────────────────────────────────────────────────

    public function login()
    {
        if (session(self::SESSION_NPK)) {
            return redirect()->route('work-assignment.security.scan');
        }

        return view('work_assignment.security.login');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'npk' => 'required|string|max:50',
        ]);

        $result = WorkAssignmentSecurityGate::verifySecurityNpk((string) $request->input('npk'));

        if (!$result['ok']) {
            return back()->withInput()->with('wa_error', $result['message']);
        }

        session([
            self::SESSION_NPK  => $result['npk'],
            self::SESSION_NAME => $result['name'],
        ]);

        return redirect()->route('work-assignment.security.scan');
    }

    public function logout()
    {
        session()->forget([self::SESSION_NPK, self::SESSION_NAME]);

        return redirect()->route('work-assignment.security.login');
    }

    // ─────────────────────────────────────────────────────────────
    // Halaman scan
    // ─────────────────────────────────────────────────────────────

    public function scan()
    {
        if (!session(self::SESSION_NPK)) {
            return redirect()->route('work-assignment.security.login');
        }

        return view('work_assignment.security.scan', [
            'securityNpk'  => session(self::SESSION_NPK),
            'securityName' => session(self::SESSION_NAME),
        ]);
    }

    /**
     * Cari pengajuan dari hasil scan (AJAX).
     */
    public function lookup(Request $request)
    {
        if (!session(self::SESSION_NPK)) {
            return response()->json(['ok' => false, 'message' => 'Sesi security berakhir.'], 401);
        }

        $result = WorkAssignmentSecurityGate::resolveFromScan((string) $request->input('code', ''));

        if (!$result['ok']) {
            return response()->json(['ok' => false, 'message' => $result['message']], 422);
        }

        $wa = $result['request'];

        return response()->json([
            'ok'      => true,
            'request' => $this->payload($wa),
        ]);
    }

    /**
     * Catat gate check security (AJAX).
     */
    public function approve(Request $request)
    {
        $npk = session(self::SESSION_NPK);

        if (!$npk) {
            return response()->json(['ok' => false, 'message' => 'Sesi security berakhir.'], 401);
        }

        $data = $request->validate([
            'code' => 'required|string',
            'note' => 'nullable|string|max:255',
        ]);

        $found = WorkAssignmentSecurityGate::resolveFromScan($data['code']);

        if (!$found['ok']) {
            return response()->json(['ok' => false, 'message' => $found['message']], 422);
        }

        try {
            $result = WorkAssignmentSecurityGate::record(
                $found['request'],
                (string) $npk,
                (string) session(self::SESSION_NAME),
                $data['note'] ?? null
            );
        } catch (Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Gagal menyimpan: ' . $e->getMessage(),
            ], 500);
        }

        if (!$result['ok']) {
            return response()->json(['ok' => false, 'message' => $result['message']], 422);
        }

        return response()->json([
            'ok'      => true,
            'message' => $result['message'],
            'request' => $this->payload($found['request']->fresh()),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Payload tampilan
    // ─────────────────────────────────────────────────────────────

    /**
     * Bentuk data pengajuan untuk halaman security.
     *
     * @return array<string, mixed>
     */
    private function payload(WorkAssignmentRequest $wa): array
    {
        $summary = WorkAssignmentSecurityGate::summary($wa);

        // `submitted_by` berisi NPK — ambil nama asli dari master karyawan.
        $names = EmployeeLookupService::namesFor([(string) $wa->npk]);
        $nama  = $names[(string) $wa->npk] ?? (string) $wa->npk;

        return [
            'id'            => $wa->id,
            'npk'           => $wa->npk,
            'nama'          => $nama,
            'dinas_ke'      => $wa->dinas_ke,
            'keperluan'     => $wa->keperluan,
            'start_date'    => $wa->start_date?->format('d-m-Y'),
            'end_date'      => $wa->end_date?->format('d-m-Y'),
            'total_days'    => (int) $wa->total_days,
            'jam_keluar'    => $wa->jam_keluar ? substr((string) $wa->jam_keluar, 0, 5) : null,
            'rencana_kembali' => $wa->rencana_kembali ? substr((string) $wa->rencana_kembali, 0, 5) : null,
            'jam_kembali'   => $summary['jam_kembali'] ? substr((string) $summary['jam_kembali'], 0, 5) : null,
            'status'        => $wa->status,
            'approved_all'  => $wa->isReadyForSecurity(),
            'stage'         => $summary['stage'],
            'out'           => $summary['out'],
            'in'            => $summary['in'],
            'can_approve'   => $summary['stage'] !== null && $wa->isReadyForSecurity(),
        ];
    }
}