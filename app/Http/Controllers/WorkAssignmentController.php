<?php

namespace App\Http\Controllers;

use App\Models\WorkAssignmentRequest;
use App\Services\WorkAssignment\ApprovalChainBuilder;
use App\Services\WorkAssignment\EmployeeLookupService;
use App\Services\WorkAssignment\WorkAssignmentAttachment;
use App\Services\WorkAssignment\WorkAssignmentQr;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * WORK ASSIGNMENT REQUEST (Surat Ijin Dinas Luar — CII.FM.HR.02.08)
 *
 * Alur pemohon:
 *   login (NPK + tgl lahir) → dashboard (riwayat + tombol pengajuan)
 *                            → form (data karyawan otomatis dari session)
 *                            → submit → kembali ke dashboard
 *
 * Satu row per pengajuan di `work_assignment_requests`. Data karyawan tidak
 * disimpan (hanya npk) — diambil dari join master saat dibaca.
 *
 * Tabel `ijin_meninggalkan_pekerjaans` (fitur lain) tidak dipakai/diubah.
 */
class WorkAssignmentController extends Controller
{
    private const SESSION_KEY = 'work_assignment_npk';

    // ─────────────────────────────────────────────────────────────
    // Auth pemohon
    // ─────────────────────────────────────────────────────────────

    public function login()
    {
        return view('work_assignment.login');
    }

    /**
     * Login manual: NPK + password (password = tanggal lahir format ymd),
     * pola sama dengan pengajuan cuti.
     */
    public function verifyManual(Request $request)
    {
        $request->validate([
            'npk'      => 'required|string|max:50',
            'password' => 'required',
        ]);

        $npk = trim((string) $request->input('npk'));

        $birth = DB::connection('cii')->table('PKWT')->where('NPK', $npk)->value('TGLLAHIR');

        if (!$birth) {
            Alert::error('Error', 'NPK tidak ditemukan atau data tanggal lahir tidak tersedia.');
            return back()->withInput();
        }

        if ((string) $request->input('password') !== date('ymd', strtotime((string) $birth))) {
            Alert::error('Error', 'Password salah. Gunakan format tanggal lahir (YYMMDD).');
            return back()->withInput();
        }

        // Pastikan data karyawan lengkap + aktif sebelum masuk dashboard
        $lookup = EmployeeLookupService::lookup($npk);
        if (!$lookup['ok']) {
            Alert::error('Error', $lookup['message'] ?? 'Data karyawan tidak valid.');
            return back()->withInput();
        }

        session([self::SESSION_KEY => $npk]);

        return redirect()->route('work-assignment.dashboard');
    }

    public function logout()
    {
        session()->forget(self::SESSION_KEY);

        return redirect()->route('work-assignment.login');
    }

    // ─────────────────────────────────────────────────────────────
    // Dashboard — daftar pengajuan milik pemohon
    // ─────────────────────────────────────────────────────────────

    /**
     * Halaman utama setelah login: riwayat pengajuan + tombol pengajuan baru.
     */
    public function dashboard()
    {
        $npk = session(self::SESSION_KEY);

        // Lihat catatan di form(): sesi pemohon diteruskan dari user Laravel.
        if (!$npk) {
            $npk = self::npkFromAuthUser();

            if ($npk) {
                session([self::SESSION_KEY => $npk]);
            }
        }

        if (!$npk) {
            return redirect()->route('work-assignment.login');
        }

        $lookup = EmployeeLookupService::lookup($npk);
        if (!$lookup['ok']) {
            session()->forget(self::SESSION_KEY);
            Alert::error('Error', $lookup['message'] ?? 'Data karyawan tidak ditemukan.');
            return redirect()->route('work-assignment.login');
        }

        $requests = WorkAssignmentRequest::forNpk($npk)
            ->orderByDesc('id')
            ->get();

        return view('work_assignment.dashboard', [
            'employee'  => $lookup['data'],
            'requests' => $requests,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Form
    // ─────────────────────────────────────────────────────────────

    public function form()
    {
        $npk = session(self::SESSION_KEY);

        // Kalau dibuka dari sidebar (user sudah login Laravel), teruskan sesi
        // pemohon dengan NPK user tersebut — supaya tak terlempar ke login
        // pemohon yang minta tanggal lahir.
        if (!$npk) {
            $npk = self::npkFromAuthUser();

            if ($npk) {
                session([self::SESSION_KEY => $npk]);
            }
        }

        if (!$npk) {
            Alert::error('Error', 'Silakan login terlebih dahulu.');
            return redirect()->route('work-assignment.login');
        }

        $lookup = EmployeeLookupService::lookup($npk);
        if (!$lookup['ok']) {
            session()->forget(self::SESSION_KEY);
            Alert::error('Error', $lookup['message'] ?? 'Data karyawan tidak ditemukan.');
            return redirect()->route('work-assignment.login');
        }

        $employee = $lookup['data'];
        [$minDate, $maxDate] = EmployeeLookupService::allowedDateRange(
            (int) config('work_assignment.back_days', 0),
            (int) config('work_assignment.forward_days', 0)
        );

        $chain = ApprovalChainBuilder::build(
            self::deptIdFor($npk),
            self::sectionIdFor($npk)
        );

        return view('work_assignment.form', [
            'employee'    => $employee,
            'signColumns' => config('work_assignment.signature_columns', []),
            'chain'       => $chain,
            'minDate'     => $minDate,
            'maxDate'     => $maxDate,
            'maxDates'    => (int) config('work_assignment.max_dates', 31),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Progress (untuk auto-refresh halaman pemohon)
    // ─────────────────────────────────────────────────────────────

    /**
     * Status approval terbaru untuk semua pengajuan milik pemohon.
     *
     * Dipakai halaman pemohon untuk refresh otomatis — supaya status
     * approval terlihat berubah tanpa harus reload halaman.
     */
    public function progress(Request $request)
    {
        $npk = session(self::SESSION_KEY);
        if (!$npk) {
            return response()->json(['ok' => false, 'message' => 'Sesi berakhir.'], 401);
        }

        $requests = WorkAssignmentRequest::forNpk($npk)
            ->orderByDesc('id')
            ->get();

        $items = [];

        foreach ($requests as $req) {
            $progress = $req->progress();

            $items[] = [
                'id'            => $req->id,
                'status'       => $progress['status'],
                'label'        => $progress['label'],
                'tone'         => $progress['tone'],
                'done'         => $progress['done'],
                'total'        => $progress['total'],
                'current_level' => (int) $req->current_level,
                'updated_at'   => $req->updated_at?->format('Y-m-d H:i:s'),
                'timeline'     => array_map(fn ($s) => [
                    'level'      => $s['level'],
                    'name'       => $s['name'],
                    'role_label' => $s['role_label'],
                    'decision'   => $s['decision'],
                    'decided_at' => $s['decided_at'],
                    'reason'     => $s['reason'],
                ], $req->timeline()),
            ];
        }

        return response()->json([
            'ok'      => true,
            'items'   => $items,
            'checked' => now()->toIso8601String(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Submit
    // ─────────────────────────────────────────────────────────────

    public function submit(Request $request)
    {
        $npk = session(self::SESSION_KEY);

        // Lihat catatan di form(): sesi pemohon diteruskan dari user Laravel.
        if (!$npk) {
            $npk = self::npkFromAuthUser();

            if ($npk) {
                session([self::SESSION_KEY => $npk]);
            }
        }

        if (!$npk) {
            Alert::error('Error', 'Sesi berakhir. Silakan login kembali.');
            return redirect()->route('work-assignment.login');
        }

        $lookup = EmployeeLookupService::lookup($npk);
        if (!$lookup['ok']) {
            Alert::error('Error', $lookup['message'] ?? 'Data karyawan tidak valid.');
            return redirect()->route('work-assignment.login');
        }

        [$minDate, $maxDate] = EmployeeLookupService::allowedDateRange(
            (int) config('work_assignment.back_days', 0),
            (int) config('work_assignment.forward_days', 0)
        );

        $maxDates = (int) config('work_assignment.max_dates', 31);

        // Batas bawah/atas hanya aktif kalau dikonfigurasi (minDate/maxDate tak kosong).
        $dateRules = ['required', 'date'];

        if (!empty($minDate)) {
            $dateRules[] = 'after_or_equal:' . $minDate;
        }

        if (!empty($maxDate)) {
            $dateRules[] = 'before_or_equal:' . $maxDate;
        }

        $validated = $request->validate([
            'duty_dates'        => "required|array|min:1|max:$maxDates",
            'duty_dates.*'      => implode('|', $dateRules),
            'dinas_ke'          => 'required|string|max:255',
            'keperluan'         => 'required|string|max:2000',
            'jam_keluar'        => 'required|date_format:H:i',
            'rencana_kembali'   => 'required|date_format:H:i|after:jam_keluar',
            'attach_files'      => 'nullable|array|max:5',
            'attach_files.*'    => 'file|mimes:' . implode(',', WorkAssignmentAttachment::ALLOWED)
                                  . '|max:' . WorkAssignmentAttachment::MAX_KB,
        ], [
            'duty_dates.required' => 'Minimal satu tanggal dinas harus dipilih.',
            'duty_dates.max'      => "Maksimal $maxDates tanggal dalam satu pengajuan.",
            'duty_dates.*.after_or_equal'  => 'Tanggal dinas terlalu jauh ke belakang.',
            'duty_dates.*.before_or_equal' => 'Tanggal dinas terlalu jauh ke depan.',
            'rencana_kembali.after' => 'Rencana kembali harus setelah jam keluar.',
            'attach_files.max'    => 'Maksimal 5 lampiran per pengajuan.',
            'attach_files.*.mimes' => 'Format lampiran harus PDF, gambar, Word, atau Excel.',
            'attach_files.*.max'  => 'Ukuran setiap lampiran maksimal 5 MB.',
        ]);

        // Normalisasi tanggal: unik + urut kronologis
        $dates = array_values(array_unique($validated['duty_dates']));
        sort($dates);

        $employee = $lookup['data'];
        $deptId   = self::deptIdFor($npk);
        $sectionId = self::sectionIdFor($npk);

        try {
            $record = WorkAssignmentRequest::create([
                'token'   => Str::random(32),
                'npk'     => $employee['npk'],

                // Tanggal dinas (multi-date)
                'duty_dates' => $dates,
                'start_date' => $dates[0],
                'end_date'   => $dates[count($dates) - 1],
                'total_days' => count($dates),

                // Isi surat
                'dinas_ke'        => $validated['dinas_ke'],
                'keperluan'       => $validated['keperluan'],
                'jam_keluar'      => $validated['jam_keluar'],
                'rencana_kembali' => $validated['rencana_kembali'],
                'jam_kembali'    => null,   // diisi Security setelah karyawan kembali

                // Lampiran (path relatif + nama asli di disk `public`)
                'attach_files'   => WorkAssignmentAttachment::storeWithNames(
                    $request->file('attach_files', []),
                    $npk
                ),

                // Chain approval = snapshot; answers mulai kosong
                'approval_chain' => ApprovalChainBuilder::build($deptId, $sectionId),
                'answers'        => [],

                'status'        => 'pending',
                'current_level' => 1,
                'void'          => 'false',
                'submitted_by'  => $npk,
            ]);
        } catch (\Throwable $e) {
            Alert::error('Error', 'Gagal menyimpan pengajuan: ' . $e->getMessage());
            return back()->withInput();
        }

        Alert::success(
            'Berhasil',
            'Work Assignment #' . $record->id . ' berhasil diajukan (' . count($dates) . ' tanggal).'
        );

        return redirect()->route('work-assignment.dashboard');
    }

    // ─────────────────────────────────────────────────────────────
    // Lampiran
    // ─────────────────────────────────────────────────────────────

    /**
     * Unduh lampiran milik pemohon yang sedang login.
     *
     * Otorisasi: pengajuan harus milik NPK pemohon (bukan milik orang lain).
     */
    public function downloadAttachment($id, $index)
    {
        $npk = session(self::SESSION_KEY);

        if (!$npk) {
            abort(403, 'Sesi berakhir. Silakan login kembali.');
        }

        $req = WorkAssignmentRequest::forNpk($npk)->find($id);

        if (!$req) {
            abort(404, 'Pengajuan tidak ditemukan.');
        }

        $files = $req->attach_files ?? [];
        $path  = WorkAssignmentAttachment::pathOf($files[(int) $index] ?? null);

        if (!$path) {
            abort(404, 'Lampiran tidak ditemukan.');
        }

        if (!Storage::disk(WorkAssignmentAttachment::DISK)->exists($path)) {
            abort(404, 'File lampiran sudah tidak ada di storage.');
        }

        return Storage::disk(WorkAssignmentAttachment::DISK)->download($path);
    }

    // ─────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────

    /**
     * NPK dari user yang login Laravel (kalau ada).
     *
     * Dipakai supaya halaman pemohon bisa dibuka langsung dari sidebar
     * tanpa login pemohon terpisah (NPK + tanggal lahir).
     */
    private static function npkFromAuthUser(): ?string
    {
        $user = Auth::user();

        if (!$user) {
            return null;
        }

        $npk = trim((string) ($user->npk ?? ''));

        return $npk !== '' ? $npk : null;
    }

    // ─────────────────────────────────────────────────────────────
    // QR code pengajuan (untuk scan security di gate)
    // ─────────────────────────────────────────────────────────────

    /**
     * Kembalikan QR pengajuan sebagai data URI SVG (dipakai modal riwayat).
     *
     * Hanya pemilik pengajuan yang boleh melihat QR miliknya.
     */
    public function qr(Request $request, int $id)
    {
        $npk = session(self::SESSION_KEY) ?: self::npkFromAuthUser();

        if (!$npk) {
            return response()->json(['ok' => false, 'message' => 'Sesi berakhir.'], 401);
        }

        $wa = WorkAssignmentRequest::find($id);

        if (!$wa || (string) $wa->npk !== (string) $npk) {
            return response()->json(['ok' => false, 'message' => 'Pengajuan tidak ditemukan.'], 404);
        }

        $uri = WorkAssignmentQr::dataUri($wa, 240);

        if ($uri === null) {
            return response()->json(['ok' => false, 'message' => 'Gagal membuat QR.'], 500);
        }

        return response()->json([
            'ok'      => true,
            'id'      => $wa->id,
            'image'   => $uri,
            'stage'   => $wa->securityStage(),
            'status'  => $wa->status,
        ]);
    }

    private static function deptIdFor(string $npk): ?string
    {
        $id = DB::connection('cii')->table('BIODATA')->where('NPK', $npk)->value('ID_DEPT');

        return $id === null ? null : (string) $id;
    }

    private static function sectionIdFor(string $npk): ?string
    {
        $id = DB::connection('cii')->table('BIODATA')->where('NPK', $npk)->value('SECTION');

        return $id === null ? null : (string) $id;
    }
}
