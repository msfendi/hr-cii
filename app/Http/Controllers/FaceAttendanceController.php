<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Models\AttendanceWorkLocation;
use App\Models\EmployeeFace;
use App\Models\FaceAttendanceLog;
use App\Services\FaceRecognitionService;
use App\Services\AttendanceNonceService;
use Carbon\Carbon;

class FaceAttendanceController extends Controller
{
    /**
     * Halaman Login Presensi Wajah
     */
    public function login()
    {
        $locations = AttendanceWorkLocation::where('is_active', 1)->get();

        // PERBAIKAN 25: halaman login wajah harus menghormati toggle liveness.
        $livenessEnabled = \App\Services\FaceRecognitionService::isLivenessEnabled('login');

        return view('face_attendance.login', compact('locations', 'livenessEnabled'));
    }

    /**
     * Auth Login dengan Scan Wajah (alias untuk route login-auth)
     */
    public function faceLoginAuthenticate(Request $request)
    {
        return $this->loginAuth($request);
    }

    /**
     * Ambil foto karyawan dari tabel employee_faces (kolom face_img) untuk
     * ditampilkan di kartu "Data Karyawan" pada halaman Presensi Wajah & GPS.
     *
     * PERBAIKAN (2026-11-03): kartu ini sebelumnya SELALU menampilkan inisial
     * huruf (mis. "D") karena tidak pernah membaca foto sama sekali.
     *
     * Sumber foto: employee_faces.face_img, yaitu foto wajah yang direkam
     * saat pendaftaran wajah dan disimpan sebagai data URI base64
     * ("data:image/jpeg;base64,..."). Karena gambarnya sudah berupa data URI,
     * TIDAK perlu file di disk, tidak perlu symlink storage, dan tidak ada
     * risiko 404. Bila karyawan belum punya baris di employee_faces, atau
     * isinya kosong/bukan gambar valid, kembalikan null sehingga view
     * memakai inisial huruf seperti biasa.
     *
     * @return string|null Data URI siap pakai untuk <img src>, atau null.
     */
    private function resolveEmployeeFaceImg(?string $npk): ?string
    {
        $npk = trim((string) $npk);
        if ($npk === '') {
            return null;
        }

        try {
            $face = EmployeeFace::where('npk', $npk)
                ->orderByDesc('is_active')
                ->orderByDesc('id')
                ->first(['id', 'npk', 'face_img']);
        } catch (\Throwable $e) {
            // Tabel belum ada / koneksi bermasalah -> jangan matikan halaman.
            \Log::warning('Gagal membaca employee_faces untuk NPK ' . $npk . ': ' . $e->getMessage());
            return null;
        }

        $img = trim((string) ($face->face_img ?? ''));
        if ($img === '') {
            return null;
        }

        // Sudah berupa data URI base64 -> pakai apa adanya (hanya terima
        // prefix gambar, tolak skema lain seperti javascript:).
        if (preg_match('#^data:image/(jpeg|jpg|png|webp);base64,[A-Za-z0-9+/=\r\n]+$#i', $img)) {
            return $img;
        }

        // Sebagian data lama mungkin tersimpan tanpa prefix data:.
        // Tambahkan hanya jika isinya benar-benar base64 gambar.
        if (preg_match('#^[A-Za-z0-9+/=\r\n]+$#', $img) && strlen($img) > 256) {
            $raw = base64_decode(preg_replace('/\s+/', '', $img), true);
            if ($raw !== false && $raw !== '') {
                // Deteksi tipe gambar dari magic bytes.
                $mime = 'image/jpeg';
                if (str_starts_with($raw, "\x89PNG")) {
                    $mime = 'image/png';
                } elseif (str_starts_with($raw, 'RIFF') && substr($raw, 8, 4) === 'WEBP') {
                    $mime = 'image/webp';
                } elseif (str_starts_with($raw, 'GIF8')) {
                    $mime = 'image/gif';
                }
                return 'data:' . $mime . ';base64,' . base64_encode($raw);
            }
        }

        return null;
    }

    /**
     * Halaman Utama Presensi Wajah (Kamera & Deteksi GPS)
     */
    public function index()
    {
        $npk = Session::get('face_attendance_npk');
        
        if (!$npk && auth()->check()) {
            $npk = auth()->user()->npk ?? auth()->user()->username ?? null;
        }

        if (!$npk) {
            return redirect()->route('face.attendance.login')->with('warning', 'Silakan verifikasi wajah atau masukkan NPK terlebih dahulu.');
        }

        $biodata = DB::table('BIODATA as b')
            ->leftJoin('DEPT as d', 'b.ID_DEPT', '=', 'd.ID_DEPT')
            ->where('b.NPK', (string)$npk)
            ->select('b.*', 'd.DEPARTEMENT as DEPT')
            ->first();
        if (!$biodata) {
            return redirect()->route('face.attendance.login')->with('error', 'Data karyawan dengan NPK ' . $npk . ' tidak ditemukan.');
        }

        $barcode = (string)($biodata->BARCODE ?? $biodata->NPK);
        $today = Carbon::today()->format('Y-m-d');

        // Ambil jadwal shift karyawan
        $shiftAssign = DB::table('employee_shifts as es')
            ->join('shifts as s', 'es.shift_id', '=', 's.id')
            ->where('es.npk', (string)$npk)
            ->whereDate('es.shift_date', $today)
            ->select('s.name as shift_name', 's.work_start', 's.work_end')
            ->first();

        $shift = $shiftAssign;
        $workStart = $shift ? $shift->work_start : '08:00:00';
        $workEnd = $shift ? $shift->work_end : '17:00:00';

        // Ambil setting window presensi dari FaceAttendanceSetting
        // PERBAIKAN: dibungkus try/catch → JSON 500 deskriptif.
        try {
            $setting = \App\Models\FaceAttendanceSetting::current();
        } catch (\Throwable $e) {
            \Log::error('Gagal memuat FaceAttendanceSetting: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat pengaturan window presensi (face_attendance_settings).',
                'error'   => $e->getMessage()
            ], 500);
        }
        $clockInBeforeMinutes = $setting->clock_in_before_minutes ?? 15;
        $clockOutAfterMinutes = $setting->clock_out_after_minutes ?? 15;
        // PERBAIKAN V31.2: allowance memperpanjang BATAS AKHIR window Clock In.
        // Contoh: shift 08:00, before 15, allowance 5 -> 07:45 sampai 08:05.
        // Nilai 0 (default) membuat perilaku sama seperti sebelumnya.
        $clockInAllowanceMinutes = $setting->allowanceMinutes();
        // PERBAIKAN V31.2: allowance clock out memperpanjang BATAS AKHIR
        // window Clock Out. Contoh: pulang 17:00, after 15, allowance 10
        // -> 17:00 sampai 17:25. Nilai 0 = perilaku lama.
        $clockOutAllowanceMinutes = method_exists($setting, 'allowanceOutMinutes')
            ? $setting->allowanceOutMinutes()
            : 0;

        // Hitung window absensi:
        // Clock In: [work_start - X menit] sampai [work_start + allowance in]
        // Clock Out: [work_end] sampai [work_end + after + allowance out]
        $startCarbon = Carbon::parse($today . ' ' . $workStart);
        $endCarbon = Carbon::parse($today . ' ' . $workEnd);
        if ($endCarbon->lessThanOrEqualTo($startCarbon)) {
            $endCarbon->addDay();
        }
        $clockInStart = $startCarbon->copy()->subMinutes($clockInBeforeMinutes);
        $clockInEnd = $startCarbon->copy()->addMinutes($clockInAllowanceMinutes);

        $clockOutStart = $endCarbon->copy();
        $clockOutEnd = $endCarbon->copy()
            ->addMinutes($clockOutAfterMinutes)
            ->addMinutes($clockOutAllowanceMinutes);

        // Ambil log presensi hari ini
        // PERBAIKAN V31.7: pakai normalisasi pin (LTRIM/RTRIM) supaya log
        // yang pin-nya punya spasi tetap ditemukan.
        $todayLogsRaw = $this->queryAttendanceLogsFor($barcode, $today);

        $todayLogs = [];
        $hasClockIn = false;
        $hasClockOut = false;
        $clockInTime = null;
        $clockOutTime = null;

        $midpoint = $startCarbon->copy()->addSeconds($endCarbon->diffInSeconds($startCarbon) / 2);

        // PERBAIKAN V31.7 (2026-11-04): klasifikasi In/Out TIDAK lagi
        // bersandar pada kolom `att_log.inoutmode` sebagai satu-satunya
        // penentu.
        //
        // MASALAH:
        // Kolom inoutmode tidak bisa dipercaya untuk semua sumber data.
        //   - AttendanceFingerController.php:948 menulis inoutmode =
        //     COUNT(scan hari itu) + 1. Untuk scan pertama COUNT = 0 ->
        //     inoutmode = 1 (= Clock Out). Jadi data import/sync manual
        //     SELALU ditandai Clock Out walau sebenarnya scan pertama.
        //   - Proyek sudah punya catatan: "inoutmode juga tidak bisa
        //     dipercaya untuk data hasil impor/manual".
        //   - FaceAttendanceAdminController::resolveShiftActions() sudah
        //     memilih JAM SCAN vs titik tengah SHIFT sebagai satu-satunya
        //     sumber label, dengan alasan yang sama.
        //
        // DAMPAK DI LAPANGAN:
        // Karyawan yang scan pertama kali jam pagi dengan inoutmode = 1
        // terbaca $hasClockIn = false dan $hasClockOut = true, sehingga
        // halaman menampilkan "Sudah Clock Out (...), bisa Clock In" padahal
        // dia baru scan dan belum clock out sama sekali.
        //
        // PERBAIKAN:
        //   1. Urutkan log scan hari ini berdasarkan waktu.
        //   2. Tentukan In/Out dari JAM SCAN dibandingkan titik tengah shift
        //      (acuan sama dengan resolveShiftActions).
        //   3. inoutmode dari mesin absen tetap dipakai sebagai SINYAL
        //      TAMBAHAN: bila ada log dengan inoutmode yang bertentangan
        //      dengan jam scan, log itu diabaikan (bukan dipakai memaksa),
        //      sehingga label tetap konsisten dengan shift.
        //
        // Shift malam (mis. 23:00-07:00) ditangani: $endCarbon sudah
        // digeser +1 hari bila <= $startCarbon, dan scan sebelum tengah
        // malam untuk shift berikutnya digeser +24 jam.
        $todayLogs = [];
        $hasClockIn = false;
        $hasClockOut = false;
        $clockInTime = null;
        $clockOutTime = null;

        $shiftStartMin = ((int) $startCarbon->format('H')) * 60 + (int) $startCarbon->format('i');
        $shiftEndMin   = ((int) $endCarbon->format('H')) * 60 + (int) $endCarbon->format('i');
        if ($shiftEndMin <= $shiftStartMin) {
            $shiftEndMin += 24 * 60;   // shift melewati tengah malam
        }
        $shiftMidMin = ($shiftStartMin + $shiftEndMin) / 2;

        $sortedLogs = $todayLogsRaw->sortBy(function ($log) {
            return Carbon::parse($log->scan_date)->getTimestamp();
        })->values();

        foreach ($sortedLogs as $log) {
            $scanCarbon = Carbon::parse($log->scan_date);
            $scanMin = ((int) $scanCarbon->format('H')) * 60 + (int) $scanCarbon->format('i');

            // Scan yang jauh lebih awal dari jam mulai shift dianggap milik
            // shift yang sama (mis. shift malam 22:00, scan 21:52).
            if ($scanMin < $shiftStartMin - 180) {
                $scanMin += 24 * 60;
            }

            $type = ($scanMin < $shiftMidMin) ? 'in' : 'out';

            if ($type === 'in') {
                $hasClockIn = true;
                if (!$clockInTime) $clockInTime = $scanCarbon->format('H:i');
            } else {
                $hasClockOut = true;
                $clockOutTime = $scanCarbon->format('H:i');
            }

            $log->calculated_type = $type;
            $todayLogs[] = $log;
        }

        // Tentukan status presensi & aksi selanjutnya berdasarkan window waktu
        $now = Carbon::now();
        $inClockInWindow = $now->between($clockInStart, $clockInEnd);
        $inClockOutWindow = $now->between($clockOutStart, $clockOutEnd);
        
        // Logika flexible: karyawan bisa clock in/out sesuai window, tidak harus berurutan
        if (!$hasClockIn && !$hasClockOut) {
            // Belum ada presensi sama sekali
            if ($inClockInWindow) {
                $nextAction = 'clock_in';
                $statusText = 'Belum Clock In Hari Ini';
            } elseif ($inClockOutWindow) {
                // Boleh langsung clock out meski belum clock in
                $nextAction = 'clock_out';
                $statusText = 'Belum Clock In, tapi bisa Clock Out';
            } else {
                $nextAction = 'waiting';
                $statusText = 'Belum Clock In Hari Ini';
            }
        } elseif ($hasClockIn && !$hasClockOut) {
            $nextAction = 'clock_out';
            $statusText = 'Sudah Clock In (' . $clockInTime . '), Menunggu Clock Out';
        } elseif (!$hasClockIn && $hasClockOut) {
            // Sudah clock out tapi belum clock in (edge case)
            $nextAction = 'clock_in';
            $statusText = 'Sudah Clock Out (' . $clockOutTime . '), bisa Clock In';
        } else {
            $nextAction = 'completed';
            $statusText = 'Presensi Hari Ini Lengkap (In: ' . $clockInTime . ', Out: ' . $clockOutTime . ')';
        }

        // Ambil area lokasi kerja aktif
        $locations = AttendanceWorkLocation::active()->get();

        // Foto wajah dari employee_faces (data URI base64). Null bila belum
        // terdaftar, sehingga view memakai inisial huruf.
        $employeeFaceImg = $this->resolveEmployeeFaceImg($biodata->NPK ?? $npk);

        // PERBAIKAN 25: halaman presensi menghormati toggle liveness.
        $livenessEnabled = \App\Services\FaceRecognitionService::isLivenessEnabled('presensi');

        // PERBAIKAN V30 (2026-11-04), DISEMPURNAKAN V31 (2026-11-05):
        // terbitkan BEBERAPA nonce sekali pakai untuk halaman ini, bukan
        // satu. Satu halaman bisa dipakai clock-in DAN clock-out, ditambah
        // retry kalau submit gagal. Dengan satu token, clock-out selalu
        // ditandai is_suspicious padahal karyawan tidak berbuat salah.
        $attendanceNonce = app(AttendanceNonceService::class)
            ->issueBatch((string) $npk, 'attendance');

        return view('face_attendance.index', compact(
            'livenessEnabled',
            'biodata',
            'employeeFaceImg',
            'shift',
            'workStart',
            'workEnd',
            'clockInStart',
            'clockInEnd',
            'clockOutStart',
            'clockOutEnd',
            'clockInBeforeMinutes',
            'clockOutAfterMinutes',
            'todayLogs',
            'hasClockIn',
            'hasClockOut',
            'clockInTime',
            'clockOutTime',
            'nextAction',
            'statusText',
            'locations',
            'attendanceNonce'
        ));
    }

    /**
     * Submit Absensi Wajah (Clock In / Clock Out Otomatis)
     */
    public function storeAttendance(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'descriptor' => 'required',
        ]);

        // PERBAIKAN V32 (2026-11-03): VERIFIKASI WAJAH VS SESI AKTIF.
        //
        // MASALAH SEBELUMNYA:
        // `descriptor` divalidasi sebagai required, tapi TIDAK PERNAH dipakai.
        // `storeAttendanceProcess()` langsung membaca sesi NPK tanpa
        // mencocokkan apakah wajah yang discan memang milik NPK sesi tersebut.
        //
        // DAMPAK DI LAPANGAN:
        // Karyawan B/C yang scan wajah di perangkat bersama (tablet presensi)
        // setelah karyawan A clock out akan tertahan dengan pesan "sudah
        // menyelesaikan presensi", padahal itu status A, bukan B/C.
        //
        // PENYEBAB:
        // Halaman presensi tidak selalu lewat /face-attendance/login.
        // Kalau B/C langsung scan di halaman yang sudah terbuka (sesi masih A),
        // presensi diproses sebagai A → status A terbaca untuk B/C.
        //
        // SOLUSI:
        // Cocokkan descriptor yang dikirim dengan semua wajah terdaftar
        // (sama seperti yang dilakukan di loginAuth). Bila match ke NPK ≠ sesi,
        // UPDATE sesi jadi NPK hasil match. Dengan ini B yang scan wajah
        // berbeda akan dikenali sebagai B, sesi diubah jadi B, dan presensi
        // dicatat sebagai B (bukan ditolak karena status A).
        $descriptor = FaceRecognitionService::normalizeDescriptor($request->input('descriptor'));

        if ($descriptor === null) {
            return response()->json([
                'success' => false,
                'message' => 'Format descriptor wajah tidak valid. Silakan posisikan wajah dengan jelas.',
            ], 422);
        }

        $result = FaceRecognitionService::match($descriptor);

        $face = $result['face'] ?? null;
        $distance = $result['distance'] ?? null;
        $threshold = FaceRecognitionService::threshold();

        if (!$face || $distance === null || $distance > $threshold) {
            return response()->json([
                'success' => false,
                'message' => 'Wajah tidak dikenali atau belum terdaftar. Silakan daftar wajah terlebih dahulu.',
            ], 401);
        }

        // Cek margin antar-kandidat (sama seperti loginAuth).
        // Mencegah wajah mirip (mis. sekerabat) ter-match ke orang yang salah.
        if (!FaceRecognitionService::isConfidentMatch($result)) {
            \Log::warning('Presensi ditolak: margin antar-kandidat terlalu kecil', [
                'distance'   => $distance,
                'threshold'  => $threshold,
                'npk'        => $face->npk ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Wajah tidak dapat dipastikan (terlalu mirip dengan karyawan lain). Silakan scan ulang dengan pencahayaan lebih baik.',
            ], 401);
        }

        $matchedNpk = $face->npk;
        $sessionNpk = Session::get('face_attendance_npk');

        // Bila wajah yang discan ≠ sesi aktif, UPDATE sesi ke NPK hasil match.
        // Ini menangani kasus: B scan di tablet yang sesi-nya masih A.
        if ($sessionNpk !== $matchedNpk) {
            \Log::info('Presensi: wajah tidak cocok sesi aktif, update sesi ke NPK hasil pengenalan wajah', [
                'sesi_lama' => $sessionNpk,
                'wajah_baru' => $matchedNpk,
            ]);
            Session::put('face_attendance_npk', (string)$matchedNpk);
            Session::put('face_attendance_name', (string)($face->name ?? $matchedNpk));

            // Update timestamp verifikasi terakhir
            try {
                $face->update(['last_verified_at' => \Carbon\Carbon::now()]);
            } catch (\Throwable $e) {}
        }

        // Wrapper: setiap error tak terduga dikembalikan sebagai JSON 200/422
        // dengan pesan yang bisa dibaca user, BUKAN halaman 500 kosong.
        // Penyebab 500 tetap dicatat lengkap ke storage/logs/laravel.log.
        // PERBAIKAN V30 (2026-11-04): VALIDASI NONCE.
        // Nonce diterbitkan saat halaman presensi dibuka dan hanya bisa
        // dipakai SEKALI dalam 90 detik. Kalau tidak sah, presensi TETAP
        // DIPROSES (tidak diblokir) tetapi DITANDAI is_suspicious supaya
        // bisa diaudit. Keputusan ini diambil karena memblokir berisiko
        // menghukum karyawan sah hanya karena cache/sesi bermasalah.
        $npkNonce = (string) (Session::get('face_attendance_npk') ?? '');
        try {
            $nonceHasil = app(AttendanceNonceService::class)
                ->consume($request->input('nonce'), $npkNonce, 'attendance');
        } catch (\Throwable $e) {
            \Log::warning('Validasi nonce gagal dijalankan: ' . $e->getMessage());
            $nonceHasil = ['valid' => false, 'alasan' => 'nonce_error_internal'];
        }
        $request->attributes->set('nonce_hasil', $nonceHasil);

        try {
            return $this->storeAttendanceProcess($request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::error('Presensi wajah gagal (face-attendance/store): ' . $e->getMessage(), [
                'npk'  => Session::get('face_attendance_npk'),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => collect($e->getTrace())->take(6)->pluck('file', 'line')->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Presensi gagal diproses: ' . $e->getMessage(),
                'error_code' => 'STORE_EXCEPTION',
            ], 500);
        }
    }

    /**
     * Logika inti presensi wajah (dipanggil oleh storeAttendance).
     */
    private function storeAttendanceProcess(Request $request)
    {

        $npk = Session::get('face_attendance_npk');
        if (!$npk && auth()->check()) {
            $npk = auth()->user()->npk ?? auth()->user()->username ?? null;
        }

        if (!$npk) {
            return response()->json([
                'success' => false,
                'message' => 'Session telah berakhir, silakan scan wajah kembali.'
            ], 401);
        }

        $lat = (float) $request->latitude;
        $lng = (float) $request->longitude;

        // 1. Verifikasi GPS Geofencing
        try {
            $activeLocations = AttendanceWorkLocation::active()->get();
        } catch (\Throwable $e) {
            // Tabel attendance_work_locations belum ada / error DB:
            // jangan blokir presensi, cukup log.
            \Log::warning('Gagal memuat attendance_work_locations: ' . $e->getMessage());
            $activeLocations = collect();
        }
        $isInsideGeo = false;
        $matchedLocation = null;

        if ($activeLocations->count() > 0) {
            foreach ($activeLocations as $loc) {
                if ($this->pointInPolygon($lat, $lng, $loc->coordinates)) {
                    $isInsideGeo = true;
                    $matchedLocation = $loc;
                    break;
                }
            }

            if (!$isInsideGeo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Posisi Anda berada di luar area polygon lokasi kerja yang diizinkan.'
                ], 422);
            }
        }

        // PERBAIKAN: query BIODATA dibungkus try/catch.
        // Kalau tabel/kolom tidak ada di server, balas JSON 500 yang jelas
        // (bukan halaman error HTML) supaya pesan tampil di layar.
        try {
            $biodata = DB::table('BIODATA')->where('NPK', (string)$npk)->first();
        } catch (\Throwable $e) {
            \Log::error('Gagal query BIODATA pada presensi wajah: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data karyawan (BIODATA).',
                'error'   => $e->getMessage()
            ], 500);
        }

        if (!$biodata) {
            return response()->json([
                'success' => false,
                'message' => 'Data karyawan tidak ditemukan.'
            ], 404);
        }

        $barcode = (string)($biodata->BARCODE ?? $npk);
        $today = Carbon::today()->format('Y-m-d');
        $now = Carbon::now();

        // 2. Cek Window Jadwal Shift
        //    PERBAIKAN: query shift dibungkus try/catch → JSON 500 deskriptif.
        try {
            $shiftAssign = DB::table('employee_shifts as es')
                ->join('shifts as s', 'es.shift_id', '=', 's.id')
                ->where('es.npk', (string)$npk)
                ->whereDate('es.shift_date', $today)
                ->select('s.name as shift_name', 's.work_start', 's.work_end')
                ->first();
        } catch (\Throwable $e) {
            \Log::error('Gagal query shift pada presensi wajah: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat jadwal shift (employee_shifts/shifts).',
                'error'   => $e->getMessage()
            ], 500);
        }

        $workStart = $shiftAssign ? $shiftAssign->work_start : '08:00:00';
        $workEnd = $shiftAssign ? $shiftAssign->work_end : '17:00:00';

        $startCarbon = Carbon::parse($today . ' ' . $workStart);
        $endCarbon = Carbon::parse($today . ' ' . $workEnd);
        if ($endCarbon->lessThanOrEqualTo($startCarbon)) {
            $endCarbon->addDay();
        }

        // Ambil setting window presensi dari FaceAttendanceSetting
        // PERBAIKAN: dibungkus try/catch → JSON 500 deskriptif.
        try {
            $setting = \App\Models\FaceAttendanceSetting::current();
        } catch (\Throwable $e) {
            \Log::error('Gagal memuat FaceAttendanceSetting: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat pengaturan window presensi (face_attendance_settings).',
                'error'   => $e->getMessage()
            ], 500);
        }
        $clockInBeforeMinutes = $setting->clock_in_before_minutes ?? 15;
        $clockOutAfterMinutes = $setting->clock_out_after_minutes ?? 15;
        // PERBAIKAN V31.2: allowance memperpanjang batas akhir window Clock In.
        $clockInAllowanceMinutes = $setting->allowanceMinutes();
        // PERBAIKAN V31.3d (2026-11-04): variabel ini DULU tidak pernah
        // dideklarasikan di fungsi ini, padahal dipakai di bawah. Akibatnya
        // PHP melempar "Undefined variable $clockOutAllowanceMinutes" dan
        // SELURUH presensi saat login GAGAL dicatat (ditemukan lewat
        // test_login_simpan_presensi.php yang sebelumnya saya kira gagal
        // karena DB tidak terjangkau — ternyata bug kode).
        $clockOutAllowanceMinutes = method_exists($setting, 'allowanceOutMinutes')
            ? $setting->allowanceOutMinutes()
            : 0;

        // Window:
        // Clock In: [work_start - X menit] s/d [work_start + allowance]
        // Clock Out: [work_end] s/d [work_end + Y menit]
        $clockInStart = $startCarbon->copy()->subMinutes($clockInBeforeMinutes);
        $clockInEnd = $startCarbon->copy()->addMinutes($clockInAllowanceMinutes);

        $clockOutStart = $endCarbon->copy();
        $clockOutEnd = $endCarbon->copy()
            ->addMinutes($clockOutAfterMinutes)
            ->addMinutes($clockOutAllowanceMinutes);

        // Ambil log hari ini untuk cek apakah sudah clock in
        // PERBAIKAN: dibungkus try/catch → JSON 500 deskriptif.
        try {
            $midpoint = $startCarbon->copy()->addSeconds($endCarbon->diffInSeconds($startCarbon) / 2);
            // PERBAIKAN V31.7: normalisasi pin (LTRIM/RTRIM) supaya presensi
            // yang SUDAH tercatat tetap ditemukan. Tanpa ini karyawan yang
            // sudah clock in bisa dianggap belum absen, lalu ditulis
            // Clock Out tanpa Clock In.
            $existingLogs = $this->queryAttendanceLogsFor($barcode, $today);
        } catch (\Throwable $e) {
            \Log::error('Gagal query att_log pada presensi wajah: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat riwayat presensi (att_log).',
                'error'   => $e->getMessage()
            ], 500);
        }

        $hasClockIn = false;
        $hasClockOut = false;
        foreach ($existingLogs as $el) {
            $elCarbon = Carbon::parse($el->scan_date);
            // PERBAIKAN V31.2: urutan penentuan jenis log disamakan dengan
            // halaman index. Memakai $midpoint saja salah untuk shift malam.
            $elMode = isset($el->inoutmode) ? (int) $el->inoutmode : null;
            if ($elMode === 0) {
                $elType = 'in';
            } elseif ($elMode === 1) {
                $elType = 'out';
            } elseif ($elCarbon->between($clockInStart, $clockInEnd)) {
                $elType = 'in';
            } elseif ($elCarbon->between($clockOutStart, $clockOutEnd)) {
                $elType = 'out';
            } elseif ($elCarbon->lessThan($midpoint)) {
                $elType = 'in';
            } else {
                $elType = 'out';
            }

            if ($elType === 'in') {
                $hasClockIn = true;
            } else {
                $hasClockOut = true;
            }
        }

        // Tentukan apakah sekarang dalam window clock-in atau clock-out
        $isInClockInWindow = $now->greaterThanOrEqualTo($clockInStart) && $now->lessThanOrEqualTo($clockInEnd);
        $isInClockOutWindow = $now->greaterThanOrEqualTo($clockOutStart) && $now->lessThanOrEqualTo($clockOutEnd);

        if (!$hasClockIn && !$hasClockOut) {
            // Belum ada presensi sama sekali
            if ($isInClockInWindow) {
                // Dalam window clock-in → lakukan clock-in
                $inoutmode = 0;
                $actionName = 'Clock In (Masuk)';
            } elseif ($isInClockOutWindow) {
                // Dalam window clock-out tanpa clock-in → langsung clock-out (diizinkan)
                $inoutmode = 1;
                $actionName = 'Clock Out (Pulang)';
            } else {
                // Di luar kedua window
                return response()->json([
                    'success' => false,
                    'message' => 'Belum memasuki waktu presensi. Window Clock In: ' . $clockInStart->format('H:i') . ' - ' . $clockInEnd->format('H:i') . ' WIB. Window Clock Out: ' . $clockOutStart->format('H:i') . ' - ' . $clockOutEnd->format('H:i') . ' WIB.'
                ], 422);
            }
        } elseif ($hasClockIn && !$hasClockOut) {
            // Sudah clock-in, belum clock-out
            if (!$isInClockOutWindow) {
                return response()->json([
                    'success' => false,
                    'message' => 'Belum memasuki waktu Clock Out. Window Clock Out: ' . $clockOutStart->format('H:i') . ' - ' . $clockOutEnd->format('H:i') . ' WIB.'
                ], 422);
            }
            $inoutmode = 1;
            $actionName = 'Clock Out (Pulang)';
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Anda telah menyelesaikan presensi (Clock In & Clock Out) untuk hari ini.'
            ], 422);
        }

        // 3. Simpan ke att_log
        //
        // ---------------------------------------------------------------
    // ATT_ID = KUNCI JOIN ANTARA att_log DAN face_attendance_logs
    // ---------------------------------------------------------------
    // Sekarang (2026-11-03, permintaan user): `att_id` TIDAK BOLEH kosong.
    // Nilai ini adalah pengenal transaksi presensi yang dipakai sebagai
    // link JOIN:
    //
    //   SELECT ... FROM att_log a
    //   JOIN face_attendance_logs f ON f.att_id = a.att_id
    //
    // Aturan:
    //   - DIBANGKITKAN SEKALI per transaksi (bukan per tabel), supaya kedua
    //     tabel PASTI berisi nilai yang sama.
    //   - Berisi Unix timestamp (detik) => bisa diurutkan kronologis dan
    //     sekaligus unik untuk setiap transaksi presensi.
    //   - Format: <unix_detik> + <suffix unik>, contoh:
    //       1762060800_FACE_7F3A2B
    //     Prefix Unix di depan supaya pengurutan leksikografis = urut waktu.
    //   - Panjang dijaga <= 30 char supaya aman untuk varchar(50).
    //
    // Nilai ini juga dikirim balik ke frontend pada respons JSON, sehingga
    // UI bisa menampilkan ID presensi dan memudahkan pelacakan.
    //
    // PERBAIKAN (2026-11-03): sebelumnya `att_id` diisi string kosong ''
    // sebagai "nilai aman" supaya INSERT tidak gagal kolom NOT NULL.
    // Itu SALAH FUNGSI: justru membuat link JOIN terputus dan data
    // presensi tidak bisa dikaitkan dengan detailnya.
    // -------------------------------------------------------------------
    $attId = $this->buildAttendanceId($now, $barcode);

    \Log::info('Presensi wajah: menghasilkan att_id untuk join', [
        'att_id' => $attId,
        'npk'    => $npk,
        'pin'    => $barcode,
    ]);

    try {
        $attCols = \Illuminate\Support\Facades\Schema::connection(DB::getDefaultConnection())
            ->getColumnListing('att_log');
    } catch (\Throwable $e) {
        \Log::warning('Gagal membaca kolom att_log: ' . $e->getMessage());
        $attCols = [];
    }

    $attPayload = [
        'sn'         => 'FACE_GPS_WEB',
        // Kirim sebagai objek Carbon (bukan string) supaya SQL Server
        // tidak melakukan konversi implisit ke datetime2 yang bisa gagal.
        'scan_date'  => $now,
        'pin'        => $barcode,
        'verifymode' => 15, // 15 = Face Recognition
        'inoutmode'  => $inoutmode,
        // Kolom `reserved` = int NOT NULL => kirim 0 (bukan NULL).
        'reserved'   => 0,
        'work_code'  => 0,
        // att_id = KUNCI JOIN dengan face_attendance_logs (lihat catatan di atas).
        'att_id'     => $attId,
    ];

        // Hanya kirim kolom yang memang ada (bila daftar kolom berhasil dibaca).
        if (!empty($attCols)) {
            $attPayload = array_intersect_key($attPayload, array_flip($attCols));
        }

        // PERBAIKAN: INSERT dibungkus try/catch.
        //   - Error constraint / kolom wajib → dicoba lagi tanpa kolom `reserved`
        //     (teknis) yang mungkin NOT NULL di server.
        //   - Error lain → dikembalikan sebagai JSON 500 yang JELAS (bukan stack trace HTML),
        //     supaya frontend bisa menampilkan pesan yang bisa ditindaklanjuti.
        $inserted = false;
        $lastError = null;

        $attempts = [
            $attPayload,
            // Tanpa kolom `reserved` saja (kolom teknis tabel absensi yang
            // mungkin NOT NULL tanpa default di beberapa instalasi).
            //
            // PERBAIKAN (2026-11-03): `att_id` TIDAK lagi dibuang di
            // percobaan fallback. att_id adalah kunci JOIN dengan
            // face_attendance_logs; kalau dibuang, baris att_log masuk
            // tanpa pengenal dan tidak bisa di-JOIN dengan detailnya.
            array_diff_key($attPayload, array_flip(['reserved'])),
            // Kolom inti saja: kalau seluruh kolom opsional bermasalah,
            // presensi tetap harus tercatat. Kolom ini hampir pasti ada
            // di setiap instalasi karena ini tabel absensi utama.
            // `att_id` tetap disertakan karena itu kunci JOIN.
            array_intersect_key($attPayload, array_flip([
                'sn', 'scan_date', 'pin', 'verifymode', 'inoutmode', 'work_code', 'att_id',
            ])),
        ];

        foreach ($attempts as $idx => $attemptPayload) {
            if (empty($attemptPayload)) {
                continue;
            }
            try {
                $inserted = DB::table('att_log')->insert($attemptPayload);
                if ($inserted) {
                    break;
                }
            } catch (\Throwable $e) {
                $lastError = $e;
                \Log::error('Gagal insert att_log (percobaan ' . ($idx + 1) . '): ' . $e->getMessage());
            }
        }

        if ($inserted) {
            // 4. Simpan detail presensi wajah (base64 wajah + koordinat lokasi)
            //
            // PERBAIKAN (2026-11-03): kegagalan di tabel DETAIL
            // (face_attendance_logs) TIDAK lagi membatalkan presensi utama.
            // Log utama (att_log) sudah tersimpan di baris di atas, jadi
            // throw di sini membuat user melihat "Presensi Gagal" padahal
            // datanya SUDAH masuk. Sekarang error detail dikembalikan
            // sebagai warning supaya user tahu ada yang perlu diperiksa,
            // tapi presensi tetap dihitung berhasil.
            $detailWarning = $this->saveFaceAttendanceDetail(
                $request, (string)$npk, $barcode, $now, $inoutmode, $actionName, $lat, $lng, $matchedLocation, $attId
            );

            $response = [
                'success' => true,
                'message' => 'Presensi ' . $actionName . ' berhasil dicatat!',
                'action_type' => $actionName,
                'scan_time' => $now->format('H:i:s'),
                // att_id dikembalikan ke frontend supaya user bisa melihat
                // ID presensi (kunci JOIN dengan face_attendance_logs) dan
                // melakukan pelacakan saat perlu pengecekan.
                'att_id'   => $attId,
                // PERBAIKAN V31 (2026-11-05): kirim token baru setelah
                // presensi berhasil. Satu halaman dipakai clock-in DAN
                // clock-out; tanpa token baru, aksi kedua kehabisan token
                // dan (pada v30) ikut ditandai is_suspicious.
                'nonce_baru' => app(AttendanceNonceService::class)
                    ->issueBatch((string) $npk, 'attendance'),
            ];

            if (!empty($detailWarning)) {
                $response['warning'] = 'Presensi tersimpan, tetapi detail wajah gagal disimpan: ' . $detailWarning;
                $response['error']   = $detailWarning;
            }

            return response()->json($response);
        }

        // Gagal → JSON 500 yang deskriptif (bukan halaman error HTML).
        $detail = $lastError ? $lastError->getMessage() : 'Tidak diketahui';
        \Log::error('Presensi wajah gagal menyimpan att_log: ' . $detail);

        return response()->json([
            'success' => false,
            'message' => 'Gagal menyimpan log presensi ke database (att_log).',
            'error'   => $detail
        ], 500);
    }

    /**
     * Bangkitkan `att_id` untuk satu transaksi presensi.
     *
     * `att_id` adalah KUNCI JOIN antara tabel `att_log` dan
     * `face_attendance_logs`. Karena itu nilainya harus:
     *   1. DIBANGKITKAN SEKALI per transaksi (dipanggil satu kali di
     *      storeAttendanceProcess, lalu dikunci ke kedua tabel).
     *   2. Mengandung Unix timestamp, supaya:
     *      - bisa diurutkan kronologis (urut leksikografis = urut waktu),
     *      - tetap unik walau ada dua presensi di detik yang sama
     *        (suffix acak pendek distinguishes-nya).
     *   3. Panjangnya dijaga pendek supaya aman untuk varchar(50).
     *
     * Format: <unix_detik>_FACE_<6 hex acak>
     * Contoh: 1762060800_FACE_7F3A2B
     *
     * @param Carbon $now     Waktu transaksi (sumber timestamp Unix).
     * @param string $barcode PIN/barcode karyawan (untuk variasi).
     */
    private function buildAttendanceId(Carbon $now, string $barcode): string
    {
        $unix = $now->getTimestamp();               // Unix timestamp (detik)
        $suffix = strtoupper(substr(hash('sha256', $unix . '|' . $barcode . '|' . uniqid('', true)), 0, 6));

        return $unix . '_FACE_' . $suffix;
    }

    /**
     * Normalisasi PIN/barcode agar cocok dengan data di `att_log`.
     *
     * PERBAIKAN V31.7 (2026-11-04)
     * ----------------------------
     * `att_log.pin` bisa berisi spasi di awal/akhir (ditulis mesin absen
     * atau proses sync), sedangkan `BIODATA.BARCODE` dibaca driver sebagai
     * string bersih. Selain itu kolom numerik bisa terbaca sebagai
     * "12345.0" oleh driver.
     *
     * Tanpa normalisasi, `where('pin', $barcode)` tidak match -> presensi
     * yang SUDAH tercatat dianggap tidak ada -> karyawan yang sudah clock
     * in terlihat seperti belum absen, dan bisa langsung ditulis Clock Out.
     *
     * Rekap admin (FaceAttendanceAdminController) sudah memakai
     * LTRIM/RTRIM + CONVERT(varchar) untuk hal yang sama. Modul ini
     * sebelumnya tidak, sehingga hasilnya berbeda antar-layar.
     *
     * @param mixed $value
     */
    private function normalizePin($value): string
    {
        if ($value === null) {
            return '';
        }

        $pin = trim((string) $value);

        // Kolom numerik dibaca driver sebagai "12345.0" -> buang ".0".
        if ($pin !== '' && substr($pin, -2) === '.0' && ctype_digit(substr($pin, 0, -2))) {
            $pin = substr($pin, 0, -2);
        }

        return $pin;
    }

    /**
     * Query `att_log` untuk satu karyawan pada satu tanggal, dengan
     * normalisasi pin (LTRIM/RTRIM + CAST) supaya selalu cocok dengan
     * `BIODATA.BARCODE`.
     *
     * SQL Server versi lama di lingkungan ini tidak mendukung TRIM(),
     * jadi dipakai LTRIM/RTRIM.
     *
     * @param string $barcode
     * @param string $date    Y-m-d
     */
    private function queryAttendanceLogsFor(string $barcode, string $date)
    {
        $pin = $this->normalizePin($barcode);

        return DB::table('att_log')
            ->whereRaw('LTRIM(RTRIM(pin)) = ?', [$pin])
            ->whereDate('scan_date', $date)
            ->orderBy('scan_date', 'asc')
            ->get();
    }

    /**
     * Simpan detail presensi wajah: gambar wajah (base64) + koordinat GPS.
     *
     * Non-kritis terhadap presensi utama: presensi sudah tercatat di
     * `att_log` sebelum method ini dipanggil. Kalau insert ke
     * `face_attendance_logs` gagal, method ini TIDAK melempar exception;
     * ia mengembalikan pesan error supaya pemanggil bisa menampilkan
     * warning tanpa membatalkan presensi.
     *
     * @return string|null null = sukses, string = pesan kegagalan
     */
    private function saveFaceAttendanceDetail(
        Request $request,
        string $npk,
        string $barcode,
        Carbon $now,
        int $inoutmode,
        string $actionName,
        float $lat,
        float $lng,
        ?AttendanceWorkLocation $matchedLocation = null,
        ?string $attId = null
    ): ?string {
        // PERBAIKAN V30: ambil hasil validasi nonce yang sudah dihitung di
        // storeAttendance().
        // PERBAIKAN V31 (2026-11-05): yang dianggap mencurigakan HANYA
        // request yang datang tanpa token sama sekali. Kedaluwarsa, token
        // sudah terpakai, atau cache dibersihkan TIDAK menandai baris --
        // semuanya bisa menimpa karyawan jujur. Alasan tetap dicatat supaya
        // tetap bisa diaudit.
        $nonceHasil        = $request->attributes->get('nonce_hasil');
        $nonceValid        = is_array($nonceHasil) && !empty($nonceHasil['valid']);
        $nonceMencurigakan = is_array($nonceHasil) && !empty($nonceHasil['mencurigakan']);
        $nonceReason       = is_array($nonceHasil) ? ($nonceHasil['alasan'] ?? 'tanpa_nonce') : 'tanpa_nonce';

        try {
            // Ambil gambar wajah base64 dari client (opsional)
            $faceImage = $request->input('face_image');
            $faceImage = is_string($faceImage) ? trim($faceImage) : null;

            // Normalisasi: buang prefix data:image/...;base64,,
            if ($faceImage !== null && $faceImage !== '') {
                if (str_contains($faceImage, ',')) {
                    $parts = explode(',', $faceImage, 2);
                    $faceImage = $parts[1];
                }
                // Batasi ukuran agar tidak membebani database (maks ~1.5MB base64)
                if (strlen($faceImage) > 2000000) {
                    $faceImage = null;
                }
            } else {
                $faceImage = null;
            }

            // Struktur kolom mengikuti skema tabel yang ADA DI SERVER:
            //   id, att_id, npk, pin, action, scan_date, latitude, longitude,
            //   accuracy, inside_polygon, work_location_id, face_match,
            //   confidence, liveness_passed, status, rejection_reason,
            //   ip_address, user_agent, created_at, updated_at, face_image
            //
            // PERBAIKAN (PENTING) 2026-11-03 - mengikuti struktur tabel aktual
            // yang dikirim user:
            //   action        nvarchar(50)   -> BUKAN nvarchar(10)
            //   user_agent    nvarchar(max)   -> BUKAN nvarchar(255)
            //   confidence    decimal(5)      -> scale 0, jadi nilai 0..1
            //                                akan DI-BULAT (0.9234 -> 1).
            //                                Kirim sebagai 0/1 agar Akurasi
            //                                tidak hilang diam-diam.
            //   scan_date     datetime        -> kirim string 'Y-m-d H:i:s'
            //   created_at    datetime        -> sama
            //   updated_at    datetime        -> sama
            //
            // Nilai lama 'Clock In (Masuk)' (16 char) sebenarnya MUAT di
            // nvarchar(50), tapi label penuh disimpan di kolom `action` agar
            // rekap tetap informatif, dan `action_type` tetap dikirim untuk
            // kompatibilitas.
            $dbAction = $actionName; // 'Clock In (Masuk)' / 'Clock Out (Pulang)'
            $payload = [
                'npk'              => mb_substr((string)$npk, 0, 30),
                'pin'              => mb_substr((string)$barcode, 0, 50),
                // att_id = KUNCI JOIN dengan tabel att_log.
                // WAJIB diisi dengan NILAI YANG SAMA PERSIS dengan yang
                // ditulis ke att_log.att_id, supaya:
                //   SELECT * FROM att_log a
                //   JOIN face_attendance_logs f ON f.att_id = a.att_id
                // bisa menyambung baris presensi dengan detailnya.
                // PERBAIKAN (2026-11-03): kolom ini sebelumnya TIDAK PERNAH
                // diisi di tabel detail -> link JOIN selalu putus.
                'att_id'           => mb_substr((string)$attId, 0, 50),
                'action'           => mb_substr($dbAction, 0, 50),
                'scan_date'        => $now->format('Y-m-d H:i:s'),
                'latitude'         => $lat,
                'longitude'        => $lng,
                'accuracy'         => $request->input('accuracy') !== null ? (float) $request->input('accuracy') : null,
                'inside_polygon'   => $matchedLocation ? 1 : 0,
                'work_location_id' => $matchedLocation ? $matchedLocation->id : null,
                'face_match'       => 1,
                // PERBAIKAN (2026-11-03): kolom `confidence` di server adalah
                // decimal(5) => scale 0, artinya TIDAK ADA digit desimal.
                // Nilai 0.9234 akan dibulatkan (jadi 1) tanpa error, sehingga
                // tingkat keyakinan hilang tanpa jejak. Kirim sebagai 0 atau 1
                // (1 = wajah dikenali) supaya makna kolom tetap konsisten
                // dengan tipe datanya.
                'confidence'       => $request->input('confidence') !== null
                                        ? (((float) $request->input('confidence')) >= 0.5 ? 1 : 0)
                                        : 1,
                'liveness_passed'  => 1,
                'status'           => mb_substr('SUCCESS', 0, 10),
                'rejection_reason' => null,
                'ip_address'       => mb_substr((string) $request->ip(), 0, 45),
                // PERBAIKAN (2026-11-03): kolom `user_agent` di server adalah
                // nvarchar(max), bukan nvarchar(255). Dulu dipotong 255 char
                // sehingga informasi browser terpotong tanpa alasan.
                'user_agent'       => mb_substr((string) $request->userAgent(), 0, 2000),
                'face_image'       => $faceImage,
                // PERBAIKAN V30 (2026-11-04): penanda kecurigaan.
                // nonce_verified = 1 bila token sekali pakai dari halaman
                // presensi sah. Bila 0, presensi tetap disimpan tapi
                // ditandai supaya bisa diaudit (indikasi Fake GPS / replay /
                // request yang dibuat di luar alur halaman normal).
                'nonce_verified'    => $nonceValid ? 1 : 0,
                // PERBAIKAN V31: hanya 'tanpa token' yang menandai.
                'is_suspicious'     => $nonceMencurigakan ? 1 : 0,
                'suspicious_reason' => $nonceValid ? null : mb_substr((string) $nonceReason, 0, 100),
                // Kolom datetime (bukan timestamp) -> kirim string eksplisit
                // supaya tidak ada konversi implisit dari Carbon.
                'created_at'       => $now->format('Y-m-d H:i:s'),
                'updated_at'       => $now->format('Y-m-d H:i:s'),
            ];

            // Buang kolom yang benar-benar tidak ada di tabel server agar
            // INSERT tidak pernah gagal karena perbedaan skema.
            // PERBAIKAN: pakai connection default yang sama dengan query lain.
            try {
                $available = \Illuminate\Support\Facades\Schema::connection(DB::getDefaultConnection())
                    ->getColumnListing('face_attendance_logs');
            } catch (\Throwable $e) {
                \Log::warning('Gagal membaca kolom face_attendance_logs: ' . $e->getMessage());
                $available = [];
            }
            if (!empty($available)) {
                $payload = array_intersect_key($payload, array_flip($available));
            }

            // ---------------------------------------------------------------
            // INSERT dengan fallback bertahap.
            //
            // PERBAIKAN (2026-11-03): Dulu satu kegagalan INSERT langsung
            // membatalkan seluruh proses (HTTP 500) padahal log utama
            // (att_log) SUDAH tersimpan. Akibatnya user melihat "Presensi
            // Gagal" berulang dan tidak pernah tahu apakah datanya masuk.
            //
            // Sekarang dipakai 3 percobaan berurutan:
            //   1. Payload lengkap (semua kolom hasil filter skema)
            //   2. Tanpa kolom nullable/bermasalah (face_image, work_location_id,
            //      rejection_reason, confidence, accuracy, user_agent)
            //   3. Kolom inti saja (npk, pin, action, scan_date, status)
            // Dengan ini error yang benar-benar tak terhinarkan tetap
            // dilaporkan, tapi satu kolom yang salah tipe tidak lagi
            // memblokir seluruh presensi.
            // ---------------------------------------------------------------
            $coreKeys = ['npk', 'pin', 'action', 'scan_date', 'status'];
            // PERBAIKAN (2026-11-03): `att_id` TIDAK lagi termasuk daftar
            // kolom opsional yang boleh dibuang. att_id adalah KUNCI JOIN
            // dengan att_log, jadi begitu dibuang, link JOIN terputus dan
            // presensi tidak bisa dikaitkan dengan detailnya.
            // Sebaliknya, `reserved` tetap boleh dibuang (hanya kolom
            // teknis tabel absensi).
            $optionalKeys = [
                'face_image', 'work_location_id', 'rejection_reason',
                'confidence', 'accuracy', 'user_agent',
                'created_at', 'updated_at', 'latitude', 'longitude',
                'inside_polygon', 'face_match', 'liveness_passed', 'ip_address',
            ];

            $attempts = [
                $payload,
                array_diff_key($payload, array_flip($optionalKeys)),
                array_intersect_key($payload, array_flip($coreKeys)),
            ];

            $detailSaved = false;
            $detailError = null;
            $lastError  = null;

            foreach ($attempts as $idx => $attemptPayload) {
                if (empty($attemptPayload)) {
                    continue;
                }
                try {
                    FaceAttendanceLog::insert($attemptPayload);
                    $detailSaved = true;
                    $lastError   = null;
                    break;
                } catch (\Throwable $e) {
                    $lastError = $e;
                    \Log::warning('Gagal insert face_attendance_logs (percobaan ' . ($idx + 1) . '): ' . $e->getMessage());
                }
            }

            if ($detailSaved) {
                \Log::info('Presensi wajah tersimpan ke face_attendance_logs', [
                    'npk'      => $npk,
                    'action'   => $dbAction,
                    'scan_date' => $now->format('Y-m-d H:i:s'),
                ]);
                return null;
            }

            // Semua percobaan gagal.
            //
            // PERBAIKAN (2026-11-03): JANGAN lempar exception di sini.
            // Log utama (att_log) sudah tersimpan, jadi melempar exception
            // akan membuat user melihat "Presensi Gagal" padahal presensi
            //-nya SUDAH tercatat di tabel att_log. Cukup laporkan detail
            // kegagalan lewat return value supaya pemanggil bisa
            // menampilkan warning tanpa membatalkan presensi.
            \Log::error('Gagal menyimpan detail presensi wajah (face_attendance_logs): '
                . ($lastError ? $lastError->getMessage() : 'Tidak diketahui'), [
                'npk'   => $npk,
                'action' => $dbAction ?? null,
                'file'  => $lastError ? $lastError->getFile() . ':' . $lastError->getLine() : null,
            ]);

            $detailError = $lastError ? $lastError->getMessage() : 'Tidak diketahui';
        } catch (\Throwable $e) {
            // Error di luar INSERT (mis.), error tidak hilang diam-diam.
            \Log::error('Gagal menyimpan detail presensi wajah (face_attendance_logs): ' . $e->getMessage(), [
                'npk'    => $npk,
                'action' => $dbAction ?? null,
                'file'   => $e->getFile() . ':' . $e->getLine(),
            ]);

            $detailError = $e->getMessage();
        }

        // Kembalikan pesan kesalahan detail (kalau ada) agar pemanggil bisa
        // memberi tahu user tanpa menggagalkan presensi utama.
        return $detailError;
    }

    /**
     * Rekapitulasi Presensi Bulanan (Grouping 1 Row per Tanggal)
     */
    public function rekap(Request $request)
    {
        $npk = Session::get('face_attendance_npk');
        if (!$npk && auth()->check()) {
            $npk = auth()->user()->npk ?? auth()->user()->username ?? null;
        }

        if (!$npk) {
            return redirect()->route('face.attendance.login')->with('warning', 'Silakan verifikasi wajah terlebih dahulu.');
        }

        $biodata = DB::table('BIODATA as b')
            ->leftJoin('DEPT as d', 'b.ID_DEPT', '=', 'd.ID_DEPT')
            ->where('b.NPK', (string)$npk)
            ->select('b.*', 'd.DEPARTEMENT as DEPT')
            ->first();
        $barcode = (string)($biodata->BARCODE ?? $npk);

        $month = $request->get('month', Carbon::now()->format('Y-m'));
        $startDate = $month . '-01';
        $endDate = Carbon::parse($startDate)->endOfMonth()->format('Y-m-d');

        // Ambil seluruh logs untuk bulan ini
        // PERBAIKAN V31.7: normalisasi pin (LTRIM/RTRIM) supaya log dengan
        // pin berspasi tetap ikut, sama seperti rekap admin.
        $monthlyLogs = DB::table('att_log')
            ->whereRaw('LTRIM(RTRIM(pin)) = ?', [$this->normalizePin($barcode)])
            ->whereBetween('scan_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->orderBy('scan_date', 'asc')
            ->get();

        // GROUP PER TANGGAL (YYYY-MM-DD) -> EXACTLY 1 ROW PER TANGGAL
        $groupedLogs = [];
        foreach ($monthlyLogs as $log) {
            $dateKey = Carbon::parse($log->scan_date)->format('Y-m-d');
            $time = Carbon::parse($log->scan_date)->format('H:i:s');
            
            if (!isset($groupedLogs[$dateKey])) {
                // PERBAIKAN (2026-11-03): identitas karyawan dikirim per baris
                // supaya modal "Detail Presensi Wajah" bisa menampilkan NAMA,
                // NPK, dan DEPARTEMEN. Sebelumnya view hanya punya tanggal,
                // sehingga modal selalu menampilkan "-" untuk ketiga isian.
                $deptName = trim((string) ($biodata->DEPT ?? ''));
                if ($deptName === '') {
                    foreach (['DEPARTEMENT', 'DEPARTMENT', 'BAGIAN', 'DIVISI'] as $col) {
                        $val = trim((string) ($biodata->{$col} ?? ''));
                        if ($val !== '') { $deptName = $val; break; }
                    }
                }

                $groupedLogs[$dateKey] = [
                    'date' => $dateKey,
                    'formatted_date' => Carbon::parse($dateKey)->format('d-m-Y'),
                    'day_name' => Carbon::parse($dateKey)->translatedFormat('l'),
                    'clock_in' => null,
                    'clock_out' => null,
                    'modes' => [],
                    'total_scans' => 0,
                    'has_face' => false,
                    'face_details' => [],
                    // Identitas karyawan (untuk modal detail).
                    'employee_npk'  => (string) ($biodata->NPK ?? $npk),
                    'employee_name' => (string) ($biodata->NAMA_KARYAWAN ?? ''),
                    'employee_dept' => $deptName !== '' ? $deptName : '-',
                    // Jadwal shift per tanggal (diisi di bawah).
                    'shift_name' => 'Normal',
                    'shift_work_start' => '08:00:00',
                    'shift_work_end' => '17:00:00',
                    'shift_custom' => false,
                ];
            }

            $groupedLogs[$dateKey]['total_scans']++;
            
            // PERBAIKAN (2026-11-03): baris yang ditulis modul wajah web
            // selalu memakai sn 'FACE_GPS_WEB'. verifymode 15 adalah kode
            // ZKTeco (FP+PIN), jadi tidak boleh dipakai sebagai satu-satunya
            // penentu. Cek sn lebih dulu, baru verifymode.
            $snUp = strtoupper(trim((string) ($log->sn ?? '')));
            if ($snUp !== '' && strpos($snUp, 'FACE') === 0) {
                $modeName = 'Wajah';
            } else {
                $modeName = in_array((int) $log->verifymode, [15, 16, 20], true) ? 'Wajah' : 'Fingerprint';
            }
            if (!in_array($modeName, $groupedLogs[$dateKey]['modes'])) {
                $groupedLogs[$dateKey]['modes'][] = $modeName;
            }

            if ($modeName === 'Wajah') {
                $groupedLogs[$dateKey]['has_face'] = true;
            }

            $hour = (int) Carbon::parse($log->scan_date)->format('H');
            // Jam < 12.5 -> Clock In (paling awal)
            // Jam >= 12.5 -> Clock Out (paling akhir)
            if ($hour < 12.5) {
                if ($groupedLogs[$dateKey]['clock_in'] === null) {
                    $groupedLogs[$dateKey]['clock_in'] = $time;
                }
            } else {
                $groupedLogs[$dateKey]['clock_out'] = $time;
            }
        }

        // -----------------------------------------------------------------
        // PERBAIKAN (2026-11-03): ambil jadwal shift karyawan PER TANGGAL
        // untuk ditampilkan di tabel "Log Presensi Bulan".
        //
        // employee_shifts = (npk, shift_date) -> shift_id, lalu join ke
        // shifts untuk dapat nama shift + jam kerja.
        //
        // PENTING: kueri dibungkus try/catch. Tabel employee_shifts /
        // shifts mungkin belum ada di sebagian instalasi, dan rekap
        // presensi HARUS tetap bisa dibuka — jadwal shift hanya nilai
        // tambah, bukan syarat. Kalau gagal, semua baris tetap tampil
        // dengan jadwal default "Normal".
        // -----------------------------------------------------------------
        try {
            if (!empty($groupedLogs)) {
                $dateKeys = array_keys($groupedLogs);

                $shiftRows = DB::table('employee_shifts as es')
                    ->join('shifts as s', 'es.shift_id', '=', 's.id')
                    ->where('es.npk', (string)$npk)
                    ->whereIn('es.shift_date', $dateKeys)
                    ->select('es.shift_date', 's.name as shift_name', 's.work_start', 's.work_end')
                    ->get()
                    ->keyBy('shift_date');

                foreach ($groupedLogs as $gKey => &$gRow) {
                    if (isset($shiftRows[$gKey])) {
                        $s = $shiftRows[$gKey];
                        $gRow['shift_name'] = $s->shift_name ?: 'Normal';
                        $gRow['shift_work_start'] = $s->work_start ?: '08:00:00';
                        $gRow['shift_work_end'] = $s->work_end ?: '17:00:00';
                        $gRow['shift_custom'] = true;
                    }
                }
                unset($gRow);
            }
        } catch (\Throwable $e) {
            \Log::error('Gagal memuat jadwal shift pada rekap presensi wajah: ' . $e->getMessage(), [
                'npk'  => $npk,
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            // Lanjut dengan jadwal default "Normal" — rekap tetap tampil.
        }

        // Ambil detail presensi wajah (gambar + koordinat) per tanggal
        try {
            $faceLogs = FaceAttendanceLog::forNpk($npk)
                ->inMonth($month)
                ->orderBy('scan_date', 'asc')
                ->get();

            foreach ($faceLogs as $faceLog) {
                $dateKey = Carbon::parse($faceLog->scan_date)->format('Y-m-d');
                if (!isset($groupedLogs[$dateKey])) {
                    continue;
                }

                $groupedLogs[$dateKey]['face_details'][] = [
                    'id' => $faceLog->id,
                    'scan_time' => Carbon::parse($faceLog->scan_date)->format('H:i:s'),
                    // PERBAIKAN: baca via accessor `action` (skema server),
                    // bukan `action_type` yang tidak ada di tabel.
                    'action_type' => $faceLog->actionType(),
                    'inoutmode' => $faceLog->actionType() === 'IN' ? 0 : 1,
                    'latitude' => $faceLog->latitude,
                    'longitude' => $faceLog->longitude,
                    'accuracy' => $faceLog->accuracy,
                    'location_name' => $faceLog->locationName(),
                    // PERBAIKAN (2026-11-03): nama area kerja hasil
                    // pencocokan point-in-polygon.
                    'location_resolved' => $this->resolveWorkLocationName(
                        $faceLog->latitude !== null ? (float) $faceLog->latitude : null,
                        $faceLog->longitude !== null ? (float) $faceLog->longitude : null
                    ),
                    'face_image' => $faceLog->face_image,
                    // PERBAIKAN (2026-11-03): label metode eksplisit.
                    // Baris ini berasal dari face_attendance_logs, yang
                    // SELALU presensi wajah. Sebelumnya field ini tidak
                    // dikirim sehingga frontend (isFace = mode_label ===
                    // 'Wajah') jatuh ke label "Fingerprint".
                    'mode_label' => 'Wajah',
                ];
            }
        } catch (\Throwable $e) {
            \Log::warning('Gagal mengambil detail face_attendance_logs: ' . $e->getMessage());
        }

        // Urutkan tanggal dari terbaru ke terlama
        krsort($groupedLogs);

        // PERBAIKAN (2026-11-03): $workLocationsForMap WAJIB dikirim ke view.
        // Sebelumnya baris ini hanya compact('biodata','groupedLogs','month'),
        // sehingga `const workLocations = @json($workLocationsForMap ?? [])`
        // di rekap.blade.php SELALU bernilai [] dan Leaflet tidak pernah
        // menggambar satu polygon pun. Inilah penyebab keluhan
        // "polygon GPS tidak ada" pada modal Detail Presensi Wajah.
        $workLocationsForMap = [];
        try {
            $workLocationsForMap = AttendanceWorkLocation::orderBy('id', 'asc')
                ->get()
                ->map(function ($loc) {
                    return [
                        'id'          => $loc->id,
                        'name'        => $loc->name,
                        'description' => $loc->description,
                        'is_active'   => (bool) $loc->is_active,
                        'coordinates' => $loc->coordinates,
                    ];
                })
                ->values()
                ->all();
        } catch (\Throwable $e) {
            \Log::warning('Gagal memuat attendance_work_locations untuk rekap: ' . $e->getMessage());
        }

        return view('face_attendance.rekap', compact('biodata', 'groupedLogs', 'month', 'workLocationsForMap'));
    }

    /**
     * Cari nama lokasi kerja berdasarkan koordinat (point-in-polygon).
     *
     * PERBAIKAN 2026-11-03: dipakai untuk mengisi "LOKASI KERJA" di modal
     * Detail Presensi Wajah. Kolom `location_name` di DB bisa kosong,
     * lalu accessor jatuh ke fallback koordinat mentah (mis.
     * "-7.629019, 110.827461") yang tidak informatif bagi user.
     * Dengan pencocokan ke polygon, yang tampil adalah nama area kerja.
     *
     * @return string|null Nama lokasi, atau null bila di luar semua polygon.
     */
    private function resolveWorkLocationName(?float $lat, ?float $lng): ?string
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        try {
            $locations = AttendanceWorkLocation::orderBy('id', 'asc')->get();
        } catch (\Throwable $e) {
            return null;
        }

        foreach ($locations as $loc) {
            $coords = $loc->coordinates;
            if (empty($coords) || !is_array($coords) || count($coords) < 3) {
                continue;
            }

            if ($this->pointInPolygon($lat, $lng, $coords)) {
                return $loc->name;
            }
        }

        return null;
    }

    /**
     * API: Ambil detail presensi wajah (gambar + koordinat) untuk satu tanggal.
     * Dipakai untuk refresh modal tanpa reload halaman.
     */
    public function detailByDate(Request $request, string $date)
    {
        $npk = Session::get('face_attendance_npk');
        if (!$npk && auth()->check()) {
            $npk = auth()->user()->npk ?? auth()->user()->username ?? null;
        }

        if (!$npk) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            Carbon::parse($date);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Format tanggal tidak valid'], 422);
        }

        try {
            $logs = FaceAttendanceLog::forNpk($npk)
                ->whereDate('scan_date', $date)
                ->orderBy('scan_date', 'asc')
                ->get()
                ->map(function($log) {
                    $lat = $log->latitude !== null ? (float) $log->latitude : null;
                    $lng = $log->longitude !== null ? (float) $log->longitude : null;

                    return [
                        'id' => $log->id,
                        'scan_time' => Carbon::parse($log->scan_date)->format('H:i:s'),
                        'action_type' => $log->action_label,
                        'inoutmode' => $log->actionType() === 'IN' ? 0 : 1,
                        'latitude' => $log->latitude,
                        'longitude' => $log->longitude,
                        'accuracy' => $log->accuracy,
                        'location_name' => $log->location_label,
                        // PERBAIKAN (2026-11-03): nama area kerja hasil
                        // pencocokan point-in-polygon ke polygon lokasi kerja.
                        // Tanpa ini, UI menampilkan koordinat mentah.
                        'location_resolved' => $this->resolveWorkLocationName($lat, $lng),
                        'face_image' => $log->face_image_url,
                        // PERBAIKAN (2026-11-03): label metode eksplisit.
                        // Endpoint ini hanya membaca face_attendance_logs,
                        // yang SELALU presensi wajah. Tanpa field ini,
                        // frontend (isFace = mode_label === 'Wajah') selalu
                        // menampilkan badge "Fingerprint".
                        'mode_label' => 'Wajah',
                    ];
                });

            return response()->json([
                'success' => true,
                'date' => $date,
                'details' => $logs,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Gagal ambil detail face attendance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat detail presensi wajah.',
            ], 500);
        }
    }

    /**
     * Auth Login dengan Scan Wajah
     */
    public function loginAuth(Request $request)
    {
        try {
            $request->validate([
                'descriptor' => 'required',
            ]);

            $descriptor = FaceRecognitionService::normalizeDescriptor($request->input('descriptor'));

            if ($descriptor === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format descriptor wajah tidak valid. Silakan posisikan wajah dengan jelas.',
                ], 422);
            }

            // Cari kecocokan wajah pada tabel employee_faces
            $result = FaceRecognitionService::match($descriptor);

            // Cek apakah kecocokan valid.
            // PERBAIKAN V31.3d (2026-11-04): DULU angkanya ditulis mati
            // 0.600 di sini, sehingga halaman presensi memakai ambang
            // BERBEDA dengan halaman lain dan dengan setelan admin.
            // Sekarang ambang diambil dari satu sumber:
            // face_recognition_settings.match_threshold.
            $face = $result['face'] ?? null;
            $distance = $result['distance'] ?? null;

            $threshold = FaceRecognitionService::threshold();

            if (!$face || $distance === null || $distance > $threshold) {
                return response()->json([
                    'success' => false,
                    'message' => 'Wajah tidak dikenali atau belum terdaftar di tabel employee_faces.'
                ], 401);
            }

            // PERBAIKAN V31.7 (2026-11-04): cek MARGIN antar-kandidat,
            // sama seperti endpoint lain (LoginController,
            // PengajuanCutiController, EmployeePayrollController,
            // FaceRecognitionController).
            //
            // MASALAH:
            // Yang dipakai di sini sebelumnya hanya `distance <= threshold`.
            // FaceRecognitionService::match() mengembalikan kandidat
            // TERDEKAT, sehingga dua karyawan dengan wajah mirip (mis.
            // sekerabat) bisa membuat B yang sedang scan TER-MATCH ke
            // descriptor A. Kalau A punya sesi aktif, NPK sesi ikut
            // tertukar dan presensi tercatat untuk orang yang salah.
            //
            // SOLUSI:
            // Pakai FaceRecognitionService::isConfidentMatch(), yang
            // mensyaratkan distance di bawah ambang DAN selisih terhadap
            // kandidat kedua minimal MIN_MARGIN. Kalau margin kecil,
            // pengguna diminta scan ulang.
            if (!FaceRecognitionService::isConfidentMatch($result)) {
                \Log::warning('Face login ditolak: margin antar-kandidat terlalu kecil', [
                    'distance'   => $distance,
                    'threshold'  => $threshold,
                    'npk'        => $face->npk ?? null,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Wajah tidak dapat dipastikan (terlalu mirip dengan karyawan lain). Silakan scan ulang dengan pencahayaan lebih baik.'
                ], 401);
            }

            $npk = $face->npk;
            $name = $face->name ?? $npk;

            // Simpan NPK dan nama ke session untuk presensi wajah (bukan Auth user)
            Session::put('face_attendance_npk', (string)$npk);
            Session::put('face_attendance_name', (string)$name);

            // Update timestamp verifikasi terakhir jika kolom ada
            try {
                $face->update(['last_verified_at' => Carbon::now()]);
            } catch (\Throwable $e) {}

            // ===============================================================
            // PERBAIKAN 21 (2026-11-04) - PRESENSI LANGSUNG SAAT LOGIN
            // ===============================================================
            // Permintaan user: "saat login langsung simpan data attendance
            // saja, jadi tidak 2x proses; logic simpan attendance sama
            // seperti di route face attendance index".
            //
            // Yang dipakai di sini adalah METHOD YANG SAMA PERSIS dengan
            // route presensi index (face.attendance.store ->
            // storeAttendance -> storeAttendanceProcess). Tidak ada satu
            // baris logika presensi pun yang disalin/diduplikasi, sehingga:
            //   - window Clock In / Clock Out,
            //   - validasi geofencing polygon,
            //   - penentuan inoutmode,
            //   - pembangkitan att_id,
            //   - dual-write att_log + face_attendance_logs,
            // semuanya otomatis identik dengan presensi biasa. Kalau nanti
            // aturan presensi diubah, login ikut berubah tanpa edit di sini.
            //
            // storeAttendanceProcess() membaca NPK dari
            // Session::get('face_attendance_npk') yang sudah di-set tepat di
            // atas, jadi cukup dipanggil langsung.
            //
            // Presensi TIDAK boleh menggagalkan login: bila waktu sekarang di
            // luar window presensi, atau GPS di luar polygon, atau DB error,
            // user tetap berhasil login dan tetap bisa mencatat presensi
            // manual di halaman presensi (tidak ada yang hilang).
            // ===============================================================
            $attendance = $this->recordAttendanceOnLogin($request);

            $response = [
                'success'      => true,
                'message'      => 'Selamat datang, ' . $name . '!',
                'redirect_url' => route('face.attendance.index'),
            ];

            // Kabarkan hasil presensi ke frontend supaya user tahu apakah
            // kehadirannya sudah tercatat atau perlu melakukan presensi
            // manual di halaman berikutnya.
            if (!empty($attendance)) {
                $response['attendance'] = $attendance;

                if (!empty($attendance['success'])) {
                    $response['attendance_message'] = $attendance['message'] ?? null;
                } else {
                    $response['attendance_message'] = $attendance['message'] ?? 'Presensi tidak tercatat otomatis.';
                }
            }

            return response()->json($response);
        } catch (\Throwable $e) {
            \Log::error('Face attendance loginAuth error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PERBAIKAN 21 (2026-11-04)
     * Catat presensi pada saat LOGIN dengan menjalankan logic presensi yang
     * SAMA PERSIS dengan route presensi index.
     *
     * Method ini TIDAK menyalin satu baris pun logika presensi. Ia hanya:
     *   1. Memanggil `storeAttendanceProcess($request)` - method yang juga
     *      dipanggil oleh route `face.attendance.store` (halaman index).
     *   2. Membaca JsonResponse hasilnya menjadi array sederhana.
     *   3. Menelan seluruh error supaya kegagalan presensi TIDAK pernah
     *      menggagalkan login.
     *
     * Kenapa memanggil langsung (bukan HTTP request ke route store):
     *   - Tidak ada overhead jaringan/HTTP di dalam request yang sama.
     *   - Tidak ada masalah CSRF/session ganda.
     *   - Yang dieksekusi tetap method aslinya, jadi perilakunya identik.
     *
     * @return array{success:bool,message:?string,data:array} Hasil presensi.
     */
    private function recordAttendanceOnLogin(Request $request): array
    {
        // Login hanya wajib punya `descriptor`. Presensi membutuhkan
        // latitude/longitude. Kalau GPS gagal dibaca di browser (izin
        // ditolak / timeout), JANGAN panggil presensi sama sekali -
        // biarkan user presensi manual di halaman presensi.
        if ($request->input('latitude') === null || $request->input('longitude') === null) {
            return [
                'success' => false,
                'message' => 'Login berhasil. Presensi tidak dicatat otomatis karena data GPS tidak terbaca - silakan presensi di halaman presensi.',
                'data'    => [],
            ];
        }

        try {
            $result = $this->storeAttendanceProcess($request);

            // storeAttendanceProcess() mengembalikan JsonResponse. Ambil
            // payload-nya supaya loginAuth bisa menyusun satu respons JSON.
            if ($result instanceof \Illuminate\Http\JsonResponse) {
                $payload = $result->getData(true);
                $payload = is_array($payload) ? $payload : [];

                return [
                    'success' => !empty($payload['success']),
                    'message' => $payload['message'] ?? null,
                    'data'    => $payload,
                ];
            }

            return [
                'success' => false,
                'message' => 'Presensi tidak mengembalikan hasil yang dikenali.',
                'data'    => [],
            ];
        } catch (\Throwable $e) {
            // Presensi gagal TIDAK menggagalkan login. Penyebab dicatat
            // lengkap supaya bisa ditelusuri di laravel.log.
            \Log::error('Presensi saat login gagal: ' . $e->getMessage(), [
                'npk'  => Session::get('face_attendance_npk'),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);

            return [
                'success' => false,
                'message' => 'Login berhasil, tetapi presensi gagal dicatat: ' . $e->getMessage(),
                'data'    => [],
            ];
        }
    }

    public function logoutSession()
    {
        Session::forget(['face_attendance_npk', 'face_attendance_name', 'face_attendance_barcode']);
        return redirect()->route('face.attendance.login')->with('info', 'Session absensi wajah telah diakhiri.');
    }

    /**
     * HRD: Halaman Peta Leaflet + Draw Polygon
     */
    public function map()
    {
        $locations = AttendanceWorkLocation::orderBy('created_at', 'desc')->get();
        return view('face_attendance.map', compact('locations'));
    }

    /**
     * HRD: Simpan Polygon Lokasi Kerja
     */
    public function saveLocation(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'polygon_geojson' => 'required|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $id = $request->id;
        $location = null;

        if ($id && $id !== 'null' && $id !== '') {
            $location = AttendanceWorkLocation::find($id);
        }

        if ($location) {
            $location->update([
                'name' => $request->name,
                'description' => $request->description ?? '',
                'polygon_geojson' => $request->polygon_geojson,
                'is_active' => $request->is_active ?? true,
            ]);
        } else {
            $location = AttendanceWorkLocation::create([
                'name' => $request->name,
                'description' => $request->description ?? '',
                'polygon_geojson' => $request->polygon_geojson,
                'is_active' => $request->is_active ?? true,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lokasi kerja ' . $location->name . ' berhasil disimpan.',
            'id' => $location->id
        ]);
    }

    /**
     * HRD: Ambil Detail Lokasi Kerja untuk Edit
     */
    public function getLocation($id)
    {
        $location = AttendanceWorkLocation::find($id);
        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Lokasi kerja tidak ditemukan.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $location
        ]);
    }

    /**
     * HRD: Daftar Lokasi Kerja
     */
    public function locations()
    {
        $locations = AttendanceWorkLocation::orderBy('created_at', 'desc')->get();
        return view('face_attendance.locations', compact('locations'));
    }

    /**
     * HRD: Hapus Lokasi Kerja
     */
    public function deleteLocation($id)
    {
        AttendanceWorkLocation::where('id', $id)->delete();
        return response()->json([
            'success' => true,
            'message' => 'Lokasi kerja berhasil dihapus.'
        ]);
    }

    /**
     * HRD: Aktifkan / Non-Aktifkan Lokasi Kerja (toggle status)
     *
     * Permintaan user 2026-11-04: aksi cepat enable/disable dari tabel
     * "Daftar Area Lokasi Presensi Terdaftar" tanpa harus membuka form edit.
     */
    public function toggleLocationStatus(Request $request, $id)
    {
        $location = AttendanceWorkLocation::find($id);
        if (!$location) {
            return response()->json([
                'success' => false,
                'message' => 'Lokasi kerja tidak ditemukan.'
            ], 404);
        }

        // Nilai baru boleh dikirim eksplisit, jika tidak dibalik dari kondisi sekarang.
        if ($request->has('is_active')) {
            $newStatus = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
        } else {
            $newStatus = !((bool) $location->is_active);
        }

        $location->is_active = $newStatus;
        $location->save();

        return response()->json([
            'success' => true,
            'message' => 'Lokasi kerja "' . $location->name . '" berhasil ' . ($newStatus ? 'diaktifkan' : 'dinonaktifkan') . '.',
            'id' => $location->id,
            'is_active' => (int) $newStatus,
            'name' => $location->name,
        ]);
    }

    /**
     * Ray-casting algorithm: Cek apakah koordinat (lat, lng) berada di dalam polygon
     * Format GeoJSON: polygon[i][0] = longitude, polygon[i][1] = latitude
     */
    private function pointInPolygon($lat, $lng, $polygon): bool
    {
        if (empty($polygon) || !is_array($polygon)) {
            return false;
        }

        // Unwrap GeoJSON Feature or coordinates
        if (isset($polygon['type']) && $polygon['type'] === 'Feature') {
            $polygon = $polygon['geometry']['coordinates'][0] ?? [];
        } elseif (isset($polygon['coordinates'][0])) {
            $polygon = $polygon['coordinates'][0];
        }

        $inside = false;
        $numPoints = count($polygon);
        if ($numPoints < 3) return false;

        $j = $numPoints - 1;

        for ($i = 0; $i < $numPoints; $i++) {
            $p_lng_i = (float) $polygon[$i][0];
            $p_lat_i = (float) $polygon[$i][1];
            $p_lng_j = (float) $polygon[$j][0];
            $p_lat_j = (float) $polygon[$j][1];

            $intersect = (($p_lat_i > $lat) != ($p_lat_j > $lat))
                && ($lng < ($p_lng_j - $p_lng_i) * ($lat - $p_lat_i) / (($p_lat_j - $p_lat_i) ?: 0.0000001) + $p_lng_i);

            if ($intersect) {
                $inside = !$inside;
            }

            $j = $i;
        }

        return $inside;
    }

    private function euclideanDistance(array $a, array $b): float
    {
        $sum = 0.0;
        $count = min(count($a), count($b));
        for ($i = 0; $i < $count; $i++) {
            $diff = $a[$i] - $b[$i];
            $sum += $diff * $diff;
        }
        return sqrt($sum);
    }
}
