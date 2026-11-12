<?php

namespace App\Http\Controllers;

use App\Models\WorkAssignmentRequest;
use App\Services\WorkAssignment\ApprovalChainBuilder;
use App\Services\WorkAssignment\EmployeeLookupService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            (int) config('work_assignment.back_days', 30),
            (int) config('work_assignment.forward_days', 90)
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
    // Submit
    // ─────────────────────────────────────────────────────────────

    public function submit(Request $request)
    {
        $npk = session(self::SESSION_KEY);
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
            (int) config('work_assignment.back_days', 30),
            (int) config('work_assignment.forward_days', 90)
        );

        $maxDates = (int) config('work_assignment.max_dates', 31);

        $validated = $request->validate([
            'duty_dates'        => "required|array|min:1|max:$maxDates",
            'duty_dates.*'      => "required|date|after_or_equal:$minDate|before_or_equal:$maxDate",
            'dinas_ke'          => 'required|string|max:255',
            'keperluan'         => 'required|string|max:2000',
            'jam_keluar'        => 'required|date_format:H:i',
            'rencana_kembali'   => 'required|date_format:H:i|after:jam_keluar',
        ], [
            'duty_dates.required' => 'Minimal satu tanggal dinas harus dipilih.',
            'duty_dates.max'      => "Maksimal $maxDates tanggal dalam satu pengajuan.",
            'duty_dates.*.after_or_equal'  => 'Tanggal dinas terlalu jauh ke belakang.',
            'duty_dates.*.before_or_equal' => 'Tanggal dinas terlalu jauh ke depan.',
            'rencana_kembali.after' => 'Rencana kembali harus setelah jam keluar.',
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
    // Helper
    // ─────────────────────────────────────────────────────────────

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
