<?php

namespace App\Http\Controllers;

use App\Services\GateSecurity\GateDocument;
use App\Services\GateSecurity\GateOfficer;
use App\Services\GateSecurity\GateQr;
use App\Services\GateSecurity\GateRegistry;
use Illuminate\Http\Request;
use Throwable;

/**
 * Halaman Security Gate — pemeriksaan dokumen di pos security lewat scan QR.
 *
 * Generik: controller ini tidak tahu dokumen apa yang discan. Semua perbedaan
 * (kolom, aturan siap-belum siap, tahap keluar/kembali) diserahkan ke adapter
 * yang dipilih GateRegistry. Menambah jenis dokumen baru tidak menyentuh file
 * ini.
 *
 * Alur: petugas login NPK → scan QR → sistem tampilkan ringkasan dokumen →
 * petugas menyetujui tahap yang sedang berlaku (keluar, lalu kembali).
 *
 * Berbasis session, terpisah dari auth Laravel — sama seperti halaman pemohon.
 */
class GateSecurityController extends Controller
{
    private const SESSION_NPK  = 'gate_security_officer_npk';
    private const SESSION_NAME = 'gate_security_officer_name';

    // ─────────────────────────────────────────────────────────────
    // Login petugas
    // ─────────────────────────────────────────────────────────────

    public function login()
    {
        if (session(self::SESSION_NPK)) {
            return redirect()->route('gate-security.scan');
        }

        return view('gate_security.login');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'npk' => 'required|string|max:50',
        ]);

        $result = GateOfficer::verify((string) $request->input('npk'));

        if (!$result['ok']) {
            return back()->withInput()->with('gate_error', $result['message']);
        }

        session([
            self::SESSION_NPK  => $result['npk'],
            self::SESSION_NAME => $result['name'],
        ]);

        return redirect()->route('gate-security.scan');
    }

    public function logout()
    {
        session()->forget([self::SESSION_NPK, self::SESSION_NAME]);

        return redirect()->route('gate-security.login');
    }

    // ─────────────────────────────────────────────────────────────
    // Halaman scan
    // ─────────────────────────────────────────────────────────────

    public function scan()
    {
        if (!session(self::SESSION_NPK)) {
            return redirect()->route('gate-security.login');
        }

        return view('gate_security.scan', [
            'officerNpk'  => session(self::SESSION_NPK),
            'officerName' => session(self::SESSION_NAME),
            'documents'   => GateRegistry::supported(),
        ]);
    }

    /**
     * Cari dokumen dari isi QR (AJAX).
     */
    public function lookup(Request $request)
    {
        if (!session(self::SESSION_NPK)) {
            return response()->json(['ok' => false, 'message' => 'Sesi petugas berakhir.'], 401);
        }

        $result = GateRegistry::resolvePayload((string) $request->input('code', ''));

        if (!$result['ok']) {
            return response()->json(['ok' => false, 'message' => $result['message']], 422);
        }

        return response()->json([
            'ok'       => true,
            'document' => $this->payload($result['document']),
        ]);
    }

    /**
     * Catat gate check (AJAX).
     */
    public function approve(Request $request)
    {
        $officerNpk = session(self::SESSION_NPK);

        if (!$officerNpk) {
            return response()->json(['ok' => false, 'message' => 'Sesi petugas berakhir.'], 401);
        }

        $data = $request->validate([
            'code' => 'required|string',
            'note' => 'nullable|string|max:255',
        ]);

        $found = GateRegistry::resolvePayload($data['code']);

        if (!$found['ok']) {
            return response()->json(['ok' => false, 'message' => $found['message']], 422);
        }

        $document = $found['document'];

        if (!$document->isReady()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Dokumen belum sepenuhnya disetujui. '
                    . 'Security hanya bisa memproses dokumen yang approval-nya sudah lengkap.',
            ], 422);
        }

        $stage = $document->stage();

        if ($stage === null) {
            return response()->json([
                'ok'      => false,
                'message' => 'Tidak ada tahap gate yang menunggu untuk dokumen ini.',
            ], 422);
        }

        try {
            $result = $document->recordGate(
                $stage,
                (string) $officerNpk,
                (string) session(self::SESSION_NAME),
                $data['note'] ?? null
            );
        } catch (Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Gagal menyimpan: ' . $e->getMessage(),
            ], 500);
        }

        if (empty($result['ok'])) {
            return response()->json(['ok' => false, 'message' => $result['message']], 422);
        }

        // Muat ulang supaya ringkasan menampilkan catatan yang baru saja dibuat.
        $reloaded = GateRegistry::resolvePayload($data['code']);

        return response()->json([
            'ok'       => true,
            'message'  => $result['message'],
            'document' => $reloaded['ok'] ? $this->payload($reloaded['document']) : null,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Payload tampilan (generik)
    // ─────────────────────────────────────────────────────────────

    /**
     * Bentuk data dokumen untuk halaman gate.
     *
     * @return array<string, mixed>
     */
    private function payload(GateDocument $document): array
    {
        $stage = $document->stage();
        $ready = $document->isReady();

        $action = null;

        if ($ready && $stage !== null) {
            $action = [
                'stage' => $stage,
                'label' => $stage === 'out' ? 'Konfirmasi KELUAR' : 'Konfirmasi KEMBALI',
                'icon'  => $stage === 'out' ? 'fa-sign-out-alt' : 'fa-sign-in-alt',
            ];
        }

        return [
            'doc'         => $document::docType(),
            'doc_label'   => $document::label(),
            'id'          => $document->id(),
            'code'        => GateQr::payloadFor($document),
            'applicant'   => $document->applicant(),
            'ready'       => $ready,
            'stage'       => $stage,
            'summary'     => $document->summary(),
            'gates'       => $document->gates(),
            'action'      => $action,
        ];
    }
}