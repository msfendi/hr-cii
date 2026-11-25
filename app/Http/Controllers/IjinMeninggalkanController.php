<?php

namespace App\Http\Controllers;

use App\Models\IjinMeninggalkanRequest;
use App\Services\GateSecurity\GateQr;
use App\Services\IzinMeninggalkan\IzinMeninggalkanGateAdapter;
use App\Services\WorkAssignment\ApprovalChainBuilder;
use App\Services\WorkAssignment\EmployeeLookupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * Halaman pemohon Izin Meninggalkan Tugas / Pekerjaan (CII.FM.HR.02.06).
 *
 * Berbasis session seperti Izin Dinas: pemohon login dengan NPK + tanggal
 * lahir, terpisah dari auth Laravel. Kalau halaman dibuka dari sidebar (user
 * Laravel sudah login), NPK diteruskan otomatis supaya tidak terlempar ke
 * form login.
 */
class IjinMeninggalkanController extends Controller
{
    private const SESSION_KEY = 'ijin_meninggalkan_npk';

    // ─────────────────────────────────────────────────────────────
    // Auth pemohon
    // ─────────────────────────────────────────────────────────────

    public function login()
    {
        return view('izin_meninggalkan.login');
    }

    /**
     * Login manual: NPK + password (password = tanggal lahir format ymd),
     * pola sama dengan Izin Dinas dan pengajuan cuti.
     */
    public function verifyManual(Request $request)
    {
        $request->validate([
            'npk'      => 'required|string|max:50',
            'password' => 'required',
        ]);

        $npk = trim((string) $request->input('npk'));

        try {
            $birth = DB::connection('cii')->table('PKWT')->where('NPK', $npk)->value('TGLLAHIR');
        } catch (\Throwable $e) {
            // DB sedang tidak bisa dihubungi — jangan tampilkan 500 ke pemohon.
            Log::error('[IzinMeninggalkan] lookup NPK gagal: ' . $e->getMessage());
            Alert::error('Error', 'Data karyawan sedang tidak dapat diakses. Coba lagi beberapa saat.');

            return back()->withInput();
        }

        if (!$birth) {
            Alert::error('Error', 'NPK tidak ditemukan atau data tanggal lahir tidak tersedia.');

            return back()->withInput();
        }

        if ((string) $request->input('password') !== date('ymd', strtotime((string) $birth))) {
            Alert::error('Error', 'Password salah. Gunakan format tanggal lahir (YYMMDD).');

            return back()->withInput();
        }

        $lookup = EmployeeLookupService::lookup($npk);

        if (!$lookup['ok']) {
            Alert::error('Error', $lookup['message'] ?? 'Data karyawan tidak valid.');

            return back()->withInput();
        }

        session([self::SESSION_KEY => $npk]);

        return redirect()->route('izin-meninggalkan.dashboard');
    }

    public function logout()
    {
        session()->forget(self::SESSION_KEY);

        return redirect()->route('izin-meninggalkan.login');
    }

    // ─────────────────────────────────────────────────────────────
    // Dashboard — riwayat pengajuan milik pemohon
    // ─────────────────────────────────────────────────────────────

    public function dashboard()
    {
        $npk = $this->resolveNpk();

        if (!$npk) {
            return redirect()->route('izin-meninggalkan.login');
        }

        $lookup = EmployeeLookupService::lookup($npk);

        if (!$lookup['ok']) {
            session()->forget(self::SESSION_KEY);
            Alert::error('Error', $lookup['message'] ?? 'Data karyawan tidak ditemukan.');

            return redirect()->route('izin-meninggalkan.login');
        }

        $requests = IjinMeninggalkanRequest::forNpk($npk)
            ->orderByDesc('id')
            ->get();

        return view('izin_meninggalkan.dashboard', [
            'employee' => $lookup['data'],
            'requests' => $requests,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Form pengajuan
    // ─────────────────────────────────────────────────────────────

    public function form()
    {
        $npk = $this->resolveNpk();

        if (!$npk) {
            Alert::error('Error', 'Silakan login terlebih dahulu.');

            return redirect()->route('izin-meninggalkan.login');
        }

        $lookup = EmployeeLookupService::lookup($npk);

        if (!$lookup['ok']) {
            Alert::error('Error', $lookup['message'] ?? 'Data karyawan tidak ditemukan.');

            return redirect()->route('izin-meninggalkan.login');
        }

        $chain = ApprovalChainBuilder::build(
            EmployeeLookupService::deptIdFor($npk),
            EmployeeLookupService::sectionIdFor($npk)
        );

        return view('izin_meninggalkan.form', [
            'employee' => $lookup['data'],
            'chain'    => $chain,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Submit
    // ─────────────────────────────────────────────────────────────

    public function submit(Request $request)
    {
        $npk = $this->resolveNpk();

        if (!$npk) {
            Alert::error('Error', 'Sesi berakhir. Silakan login kembali.');

            return redirect()->route('izin-meninggalkan.login');
        }

        $lookup = EmployeeLookupService::lookup($npk);

        if (!$lookup['ok']) {
            Alert::error('Error', $lookup['message'] ?? 'Data karyawan tidak valid.');

            return redirect()->route('izin-meninggalkan.login');
        }

        $validated = $request->validate([
            'tanggal'        => 'required|date',
            'jam_keluar'     => 'required|date_format:H:i',
            'rencana_kembali' => 'nullable|date_format:H:i|after:jam_keluar',
            'alasan'         => 'required|string|max:2000',
        ], [
            'tanggal.required'        => 'Tanggal wajib diisi.',
            'jam_keluar.required'     => 'Jam meninggalkan tugas wajib diisi.',
            'rencana_kembali.after'   => 'Rencana kembali harus setelah jam keluar.',
            'alasan.required'         => 'Alasan wajib diisi.',
        ]);

        // Rantai approval dibangun sebelum row disimpan, supaya bisa ditolak
        // lebih awal kalau departemen belum punya setelan approval.
        $chain = ApprovalChainBuilder::build(
            EmployeeLookupService::deptIdFor($npk),
            EmployeeLookupService::sectionIdFor($npk)
        );

        if (empty($chain)) {
            Alert::error(
                'Error',
                'Rantai approval untuk departemen Anda belum disetel. Hubungi HR.'
            );

            return back()->withInput();
        }

        try {
            $record = IjinMeninggalkanRequest::create([
                'token'          => Str::random(32),
                'npk'            => $lookup['data']['npk'],
                'tanggal'        => $validated['tanggal'],
                'jam_keluar'     => $validated['jam_keluar'],
                'rencana_kembali' => $validated['rencana_kembali'] ?? null,
                'jam_kembali'    => null,   // diisi gate security saat karyawan kembali
                'alasan'         => $validated['alasan'],
                'approval_chain' => $chain,
                'answers'        => [],
                'status'         => 'pending',
                'current_level'  => 1,
                'void'           => 'false',
                'submitted_by'   => $npk,
            ]);
        } catch (\Throwable $e) {
            Log::error('[IzinMeninggalkan] submit gagal: ' . $e->getMessage());
            Alert::error('Error', 'Gagal menyimpan pengajuan: ' . $e->getMessage());

            return back()->withInput();
        }

        Alert::success(
            'Berhasil',
            'Izin meninggalkan tugas #' . $record->id . ' berhasil diajukan.'
        );

        return redirect()->route('izin-meninggalkan.dashboard');
    }

    /**
     * Kembalikan QR pengajuan sebagai data URI SVG (dipakai modal riwayat).
     */
    public function qr(Request $request, int $id)
    {
        $npk = $this->resolveNpk();

        if (!$npk) {
            return response()->json(['ok' => false, 'message' => 'Sesi berakhir.'], 401);
        }

        $req = IjinMeninggalkanRequest::find($id);

        if (!$req || (string) $req->npk !== (string) $npk) {
            return response()->json(['ok' => false, 'message' => 'Pengajuan tidak ditemukan.'], 404);
        }

        $payload = GateQr::payloadFor(new IzinMeninggalkanGateAdapter($req));
        $uri     = GateQr::dataUri($payload, 240);

        if ($uri === null) {
            return response()->json(['ok' => false, 'message' => 'Gagal membuat QR.'], 500);
        }

        return response()->json([
            'ok'     => true,
            'id'     => $req->id,
            'image'  => $uri,
            'stage'  => $req->securityStage(),
            'status' => $req->status,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────

    /**
     * NPK pemohon: dari session, atau dari user Laravel yang sudah login.
     */
    private function resolveNpk(): ?string
    {
        $npk = session(self::SESSION_KEY);

        if ($npk) {
            return (string) $npk;
        }

        $npk = trim((string) (Auth::user()->npk ?? ''));

        if ($npk === '') {
            return null;
        }

        session([self::SESSION_KEY => $npk]);

        return $npk;
    }
}