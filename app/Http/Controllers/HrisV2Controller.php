<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureHrisV2Employee;
use App\Models\FaceRecognitionSetting;
use App\Models\HrisV2Menu;
use App\Models\HrisV2PublicCard;
use App\Services\EmployeeActiveStatusService;
use App\Services\FaceRecognitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * HRIS v2 — portal terpisah dari aplikasi lama.
 *
 * Tiga portal, terpisah jelas:
 *   1. /v2/login            -> portal USER (staf/admin) untuk login.
 *   2. /v2/karyawan/login   -> portal KARYAWAN (self-service): masukkan NPK,
 *                              lalu verifikasi wajah sesuai mode yang diset
 *                              admin (liveness | random challenge | none).
 *                              Satu kali login membuka SEMUA menu yang
 *                              diizinkan: slip gaji, presensi wajah, cuti.
 *   3. /v2/publik           -> portal PUBLIK: karier & pengumuman rekrutmen.
 *
 * Route "/" dan alur aplikasi lama TIDAK diubah. Semua route v2 baru.
 */
class HrisV2Controller extends Controller
{
    /** Scope tantangan acak untuk portal karyawan v2. */
    protected function challengeScope(Request $request): string
    {
        return 'hris-v2-' . $request->session()->getId();
    }

    // -----------------------------------------------------------------
    // Portal utama (/v2) — tiga pintu masuk
    // -----------------------------------------------------------------
    public function portal()
    {
        $mode = FaceRecognitionSetting::hrisV2VerificationMode();
        $sesiAktif = (bool) session(EnsureHrisV2Employee::SESSION_NPK);

        return view('hris_v2.portal', [
            'mode' => $mode,
            'modeLabel' => $this->modeLabel($mode),
            'sesiAktif' => $sesiAktif,
            'namaSesi' => session(EnsureHrisV2Employee::SESSION_NAME),
            'jumlahMenu' => count(HrisV2Menu::aktif()),
            'jumlahLowongan' => $this->jumlahLowongan(),
        ]);
    }

    // -----------------------------------------------------------------
    // 1. Portal USER (staf/admin) — memakai form login aplikasi lama
    // -----------------------------------------------------------------
    public function userLogin()
    {
        // Sudah login (belum logout) lalu menekan tombol Staff Login lagi di
        // kartu portal: arahkan ke /welcome, sama seperti hasil login staf,
        // bukan ke /home.
        if (auth()->check()) {
            return redirect()->route('welcome');
        }

        // Mode verifikasi wajah untuk login staf: liveness | random_challenge |
        // none. Disetel admin di /face-attendance/settings (kolom
        // face_recognition_settings.hris_v2_staff_face_mode).
        $mode = FaceRecognitionSetting::hrisV2StaffFaceMode();

        return view('hris_v2.user_login', [
            'faceEnabled' => FaceRecognitionService::isEnabled(),
            'mode'        => $mode,
            'modeLabel'   => $this->modeLabel($mode),
            'faceAuthUrl' => route('v2.login.face-auth'),
        ]);
    }

    /**
     * Login staf memakai Face ID dari halaman /v2/login.
     *
     * Diteruskan ke LoginController::faceAuth() supaya pencocokan descriptor,
     * pemeriksaan izin (can_login/disabled), dan Auth::login() memakai SATU
     * jalur yang sama dengan /login — tidak ada logika login kedua.
     */
    public function staffFaceAuth(Request $request)
    {
        // v2: login wajah wajib didahului NPK. Descriptor hanya dicocokkan
        // dengan baris employee_faces milik NPK tersebut (matchByNpk), supaya
        // hasilnya akurat dan tidak mungkin masuk sebagai karyawan lain.
        $npk = trim((string) $request->input('npk', ''));

        if ($npk === '') {
            return response()->json([
                'success' => false,
                'message' => 'Enter your NPK first, then open the camera.',
            ], 422);
        }

        return app(\App\Http\Controllers\LoginController::class)->prosesFaceAuth($request, 'en');
    }

    // -----------------------------------------------------------------
    // 2. Portal KARYAWAN — satu login, semua menu
    // -----------------------------------------------------------------
    public function employeeLogin(Request $request)
    {
        if ($request->session()->get(EnsureHrisV2Employee::SESSION_NPK)) {
            return redirect()->route('v2.employee.home');
        }

        $mode = FaceRecognitionSetting::hrisV2VerificationMode();
        $npk = (string) $request->session()->get('hris_v2_pending_npk', '');

        return view('hris_v2.employee_login', [
            'mode' => $mode,
            'modeLabel' => $this->modeLabel($mode),
            'npk' => $npk,
            'npkTerkonfirmasi' => (bool) $request->session()->get('hris_v2_npk_confirmed'),
            'namaPending' => (string) $request->session()->get('hris_v2_pending_nama', ''),
            'threshold' => FaceRecognitionService::threshold(),
            'challenge' => $mode === FaceRecognitionSetting::HRIS_V2_MODE_RANDOM
                ? FaceRecognitionService::issueRandomChallenge($this->challengeScope($request))
                : null,
        ]);
    }

    /**
     * Langkah 1: validasi NPK. Karyawan wajib memasukkan NPK DULU sebelum
     * scan wajah (sesuai permintaan). Kalau mode = random challenge, di sini
     * juga diterbitkan tantangan gerakan.
     */
    public function employeeCheckNpk(Request $request)
    {
        $npk = trim((string) $request->input('npk', ''));
        $mode = FaceRecognitionSetting::hrisV2VerificationMode();

        $cek = $this->validateNpk($npk, $mode);

        if (! $cek['ok']) {
            return response()->json([
                'success' => false,
                'message' => $cek['message'],
            ], $cek['status'] >= 400 && $cek['status'] < 600 ? $cek['status'] : 422);
        }

        $request->session()->put('hris_v2_pending_npk', $cek['npk']);
        $request->session()->put('hris_v2_pending_nama', $cek['name']);
        $request->session()->put('hris_v2_npk_confirmed', true);

        $payload = [
            'success' => true,
            'npk' => $cek['npk'],
            'name' => $cek['name'],
            'mode' => $mode,
            'has_face' => $cek['has_face'],
            'message' => $cek['message'],
        ];

        if ($mode === FaceRecognitionSetting::HRIS_V2_MODE_RANDOM) {
            $payload['challenge'] = FaceRecognitionService::issueRandomChallenge($this->challengeScope($request));
        }

        return response()->json($payload);
    }

    /**
     * Terbitkan tantangan baru (mode random challenge) — mis. saat masa
     * berlaku tantangan habis sebelum karyawan selesai.
     */
    public function employeeChallenge(Request $request)
    {
        if (FaceRecognitionSetting::hrisV2VerificationMode() !== FaceRecognitionSetting::HRIS_V2_MODE_RANDOM) {
            return response()->json(['success' => false, 'message' => 'Random challenge mode is not active.'], 422);
        }

        return response()->json([
            'success' => true,
            'challenge' => FaceRecognitionService::issueRandomChallenge($this->challengeScope($request)),
        ]);
    }

    /**
     * Langkah 2: verifikasi wajah -> buat satu sesi karyawan.
     *
     * Mode:
     *   none               -> scan wajah saja: tanpa verifikasi gerakan (liveness)
     *                         dan tanpa tantangan acak.
     *   liveness           -> wajah dipindai + verifikasi gerakan (kedip/menoleh).
     *   random_challenge   -> wajah dipindai + tantangan gerakan acak.
     *
     * Di ketiga mode, wajah TETAP dipindai dan dicocokkan dengan NPK. Bedanya
     * hanya pada gerbang liveness/tantangan di sisi klien, bukan pada ada
     * tidaknya pemindaian wajah.
     */
    public function employeeVerify(Request $request)
    {
        $npk = trim((string) ($request->input('npk') ?: $request->session()->get('hris_v2_pending_npk', '')));
        $mode = FaceRecognitionSetting::hrisV2VerificationMode();

        $cek = $this->validateNpk($npk, $mode);

        if (! $cek['ok']) {
            return response()->json(['success' => false, 'message' => $cek['message']], $cek['status'] >= 400 && $cek['status'] < 600 ? $cek['status'] : 422);
        }

        // Scan wajah wajib di SEMUA mode, termasuk mode 'none'. Mode 'none'
        // hanya melepas liveness + tantangan gerakan, bukan melepas wajah.
        $descriptor = FaceRecognitionService::normalizeDescriptor($request->input('descriptor'));

        if ($descriptor === null) {
            return response()->json([
                'success' => false,
                'message' => 'Face not detected. Position your face in the camera and try again.',
            ], 422);
        }

        if ($mode === FaceRecognitionSetting::HRIS_V2_MODE_RANDOM) {
            $challenge = FaceRecognitionService::getRandomChallenge($this->challengeScope($request));

            if (! $challenge) {
                return response()->json([
                    'success' => false,
                    'message' => 'The motion challenge has expired. Please request a new challenge.',
                    'need_challenge' => true,
                ], 422);
            }
        }

        $hasil = FaceRecognitionService::matchByNpk($descriptor, $cek['npk']);

        if (! FaceRecognitionService::isConfidentMatch($hasil)) {
            return response()->json([
                'success' => false,
                'message' => 'Face does not match the data for NPK ' . $cek['npk'] . '. Please try again.',
                'distance' => $hasil['distance'],
            ], 422);
        }

        if ($mode === FaceRecognitionSetting::HRIS_V2_MODE_RANDOM) {
            // Tantangan sekali pakai: hapus setelah verifikasi berhasil.
            \Illuminate\Support\Facades\Cache::forget('face_random_challenge:' . $this->challengeScope($request));
        }

        $this->mulaiSesiKaryawan($request, $cek['npk'], $cek['name'], $mode);

        return response()->json([
            'success' => true,
            'message' => 'Login successful. Opening the employee portal...',
            'npk' => $cek['npk'],
            'name' => $cek['name'],
            'redirect_url' => route('v2.employee.home'),
        ]);
    }

    /** Dashboard karyawan: kartu menu yang diizinkan admin. */
    public function employeeHome(Request $request)
    {
        $npk = (string) $request->session()->get(EnsureHrisV2Employee::SESSION_NPK);

        $menus = [];
        foreach (HrisV2Menu::aktif() as $menu) {
            $routeName = is_array($menu) ? ($menu['route_name'] ?? '') : ($menu->route_name ?? '');

            $menus[] = [
                'key' => is_array($menu) ? ($menu['key'] ?? '') : ($menu->key ?? ''),
                'label' => is_array($menu) ? ($menu['label'] ?? '') : ($menu->label ?? ''),
                'description' => is_array($menu) ? ($menu['description'] ?? '') : ($menu->description ?? ''),
                'icon' => is_array($menu) ? ($menu['icon'] ?? 'fas fa-circle') : ($menu->icon ?? 'fas fa-circle'),
                'color' => is_array($menu) ? ($menu['color'] ?? '#1d3f86') : ($menu->color ?? '#1d3f86'),
                'url' => ($routeName && Route::has($routeName)) ? route($routeName) : null,
            ];
        }

        return view('hris_v2.employee_home', [
            'npk' => $npk,
            'nama' => (string) $request->session()->get(EnsureHrisV2Employee::SESSION_NAME, $npk),
            'menus' => $menus,
            'profil' => $this->profilKaryawan($npk),
            'loginAt' => (int) $request->session()->get(EnsureHrisV2Employee::SESSION_AT, 0),
        ]);
    }

    /** Slip gaji karyawan yang sedang login (hanya miliknya sendiri). */
    public function employeePayslip(Request $request)
    {
        $npk = (string) $request->session()->get(EnsureHrisV2Employee::SESSION_NPK);

        return view('hris_v2.employee_payslip', [
            'npk' => $npk,
            'nama' => (string) $request->session()->get(EnsureHrisV2Employee::SESSION_NAME, $npk),
            'slips' => $this->daftarSlip($npk),
        ]);
    }

    /** Halaman biodata lengkap karyawan yang sedang login (read-only). */
    public function employeeBiodata(Request $request)
    {
        $npk = (string) $request->session()->get(EnsureHrisV2Employee::SESSION_NPK);

        return view('hris_v2.employee_biodata', [
            'npk' => $npk,
            'nama' => (string) $request->session()->get(EnsureHrisV2Employee::SESSION_NAME, $npk),
            'bio' => $this->biodataKaryawan($npk),
        ]);
    }

    public function employeeLogout(Request $request)
    {
        EnsureHrisV2Employee::logout($request);
        $request->session()->forget(['hris_v2_pending_npk', 'hris_v2_pending_nama', 'hris_v2_npk_confirmed']);

        // Instruksi: setiap logout diarahkan ke route '/' (portal publik), bukan ke /home.
        return redirect('/')->with('success', 'You have signed out of the Employee Portal.');
    }

    // -----------------------------------------------------------------
    // 3. Portal PUBLIK — karier & pengumuman rekrutmen
    // -----------------------------------------------------------------
    public function publicHome()
    {
        return view('hris_v2.public_home', [
            'lowongan' => $this->lowonganTerbuka(),
            'jumlahLowongan' => $this->jumlahLowongan(),
            // Card portal publik yang aktif. Show/hide diatur admin di
            // /pengaturan/card-menu-hris (scope: publik).
            'publicCards' => HrisV2PublicCard::aktif(),
        ]);
    }

    public function publicCareer()
    {
        return view('hris_v2.public_career', [
            'lowongan' => $this->lowonganTerbuka(),
        ]);
    }

    public function publicAnnouncement()
    {
        return view('hris_v2.public_announcement', [
            'lowongan' => $this->lowonganTerbuka(),
        ]);
    }

    // -----------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------

    protected function modeLabel(string $mode): string
    {
        switch ($mode) {
            case FaceRecognitionSetting::HRIS_V2_MODE_RANDOM:
                return 'Motion Challenge (Random Challenge)';
            case FaceRecognitionSetting::HRIS_V2_MODE_NONE:
                return 'No Motion Verification';
            default:
                return 'Liveness (Live Face)';
        }
    }

    /**
     * Validasi NPK untuk portal karyawan.
     *
     * Wajah terdaftar WAJIB ada di semua mode: mode 'none' (Tanpa Verifikasi
     * Gerakan) tetap memindai wajah, jadi karyawan tanpa data wajah tidak
     * dapat login lewat portal ini.
     */
    protected function validateNpk(string $npk, string $mode): array
    {
        $npk = trim($npk);

        if ($npk === '') {
            return ['ok' => false, 'status' => 422, 'npk' => '', 'name' => '', 'has_face' => false, 'message' => 'NPK is required.'];
        }

        try {
            $biodata = DB::table('BIODATA')->where('NPK', $npk)->select('NPK', 'NAMA_KARYAWAN')->first();
        } catch (\Throwable $e) {
            Log::warning('HrisV2 validateNpk gagal baca BIODATA: ' . $e->getMessage());

            return ['ok' => false, 'status' => 500, 'npk' => $npk, 'name' => '', 'has_face' => false, 'message' => 'Failed to load employee data. Please try again.'];
        }

        if (! $biodata) {
            return ['ok' => false, 'status' => 404, 'npk' => $npk, 'name' => '', 'has_face' => false, 'message' => 'NPK ' . $npk . ' was not found.'];
        }

        $name = (string) ($biodata->NAMA_KARYAWAN ?? $npk);

        try {
            $status = EmployeeActiveStatusService::check($npk);
            if (($status['found'] ?? false) && ! ($status['active'] ?? false)) {
                // Pesan dari service dipakai juga oleh v1 (berbahasa Indonesia).
                // Untuk portal v2 disusun ulang dalam bahasa Inggris tanpa
                // mengubah isi service-nya.
                $pesanNonaktif = 'You are not an active employee';
                if (! empty($status['nama'])) {
                    $pesanNonaktif .= ' (' . $status['nama'] . ')';
                }
                $pesanNonaktif .= '. Your employment record shows you have left the company';
                if (! empty($status['tkk'])) {
                    $pesanNonaktif .= ' on ' . $this->formatTanggalPanjang($status['tkk']);
                }
                $pesanNonaktif .= '. Please contact HRD for more information.';

                return ['ok' => false, 'status' => 403, 'npk' => $npk, 'name' => $name, 'has_face' => false, 'message' => $pesanNonaktif];
            }
        } catch (\Throwable $e) {
            Log::warning('HrisV2 validateNpk gagal cek status aktif: ' . $e->getMessage());
        }

        $hasFace = false;
        try {
            $hasFace = \App\Models\EmployeeFace::query()
                ->where('npk', $npk)
                ->where('is_active', true)
                ->whereNotNull('descriptor')
                ->exists();
        } catch (\Throwable $e) {
            Log::warning('HrisV2 validateNpk gagal cek wajah: ' . $e->getMessage());
        }

        if (! $hasFace) {
            return [
                'ok' => false,
                'status' => 422,
                'npk' => $npk,
                'name' => $name,
                'has_face' => false,
                'message' => 'No face is registered for NPK ' . $npk . '. Please contact admin to register a face.',
            ];
        }

        $pesan = $mode === FaceRecognitionSetting::HRIS_V2_MODE_NONE
            ? 'NPK is valid. Please scan your face (no motion check).'
            : 'NPK is valid. Please place your face inside the camera frame.';

        return ['ok' => true, 'status' => 200, 'npk' => $npk, 'name' => $name, 'has_face' => $hasFace, 'message' => $pesan];
    }

    /** Satu login -> sesi v2 + sesi subsistem lama (presensi & cuti). */
    protected function mulaiSesiKaryawan(Request $request, string $npk, string $nama, string $mode): void
    {
        $request->session()->forget(['hris_v2_pending_npk', 'hris_v2_pending_nama', 'hris_v2_npk_confirmed']);

        // Regenerate dulu (cegah session fixation), baru isi sesi karyawan.
        $request->session()->regenerate();

        $request->session()->put([
            EnsureHrisV2Employee::SESSION_NPK => $npk,
            EnsureHrisV2Employee::SESSION_NAME => $nama,
            EnsureHrisV2Employee::SESSION_AT => time(),
            EnsureHrisV2Employee::SESSION_MAX => \App\Models\HrisV2Setting::sessionMinutes(),
            // Bridge ke subsistem lama supaya tidak perlu login dua kali.
            'face_attendance_npk' => $npk,
            'cuti_employee_npk' => $npk,
        ]);

        Log::info('HRIS v2: karyawan login', ['npk' => $npk, 'mode' => $mode]);
    }

    /**
     * Biodata lengkap karyawan (read-only) untuk menu "Data Karyawan".
     *
     * Sumber nyata: PKWT + BIODATA + DEPT (koneksi cii), employees_contract
     * untuk riwayat kontrak, dan pelamar_details untuk data keluarga,
     * pendidikan, pengalaman kerja, serta kontak darurat.
     */
    protected function biodataKaryawan(string $npk): array
    {
        $data = [
            'ada' => false,
            'npk' => $npk,
            'nama' => $npk,
            'jabatan' => '-',
            'departemen' => '-',
            'statusKaryawan' => '-',
            'statusAktif' => null,
            'masaKerja' => '-',
            'tanggalMasuk' => null,
            'nomorTelepon' => '-',
            'kontrakAkhir' => null,
            'foto' => null,
            'pribadi' => [],
            'kepegawaian' => [],
            'kontakDarurat' => [],
            'anak' => [],
            'ayah' => [],
            'ibu' => [],
            'saudara' => [],
            'pendidikan' => [],
            'pengalaman' => [],
            'kontrak' => [],
        ];

        try {
            $row = DB::table('PKWT as p')
                ->leftJoin('BIODATA as b', 'b.NPK', '=', 'p.NPK')
                ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'b.ID_DEPT')
                ->where('p.NPK', $npk)
                ->select(
                    'p.*',
                    'b.ID_DEPT',
                    'b.IS_STAFF',
                    'b.BAG',
                    'b.SECTION',
                    'b.STATUS as STATUS_BIODATA',
                    'd.DEPARTEMENT'
                )
                ->first();
        } catch (\Throwable $e) {
            Log::warning('HrisV2 biodataKaryawan: gagal baca PKWT — ' . $e->getMessage());

            return $data;
        }

        if (! $row) {
            return $data;
        }

        $data['ada'] = true;
        $data['nama'] = (string) ($row->NAMA ?? $npk);
        $data['jabatan'] = (string) ($row->BAGIAN ?: '-');
        $data['departemen'] = (string) ($row->DEPARTEMENT ?: '-');
        $data['statusKaryawan'] = ((int) ($row->IS_STAFF ?? 0) === 1) ? 'Permanent Employee' : 'Contract Employee';
        $data['statusAktif'] = (string) ($row->STATUS_BIODATA ?: (empty($row->TKK) ? 'AKTIF' : 'NON-AKTIF'));
        $data['nomorTelepon'] = (string) ($row->HP ?: '-');

        $data['masaKerja'] = $this->masaKerja($row->TMK ?? null);
        $data['tanggalMasuk'] = $this->formatTanggal($row->TMK ?? null);

        $data['pribadi'] = [
            ['label' => 'Full Name', 'value' => $row->NAMA ?? null, 'icon' => 'fa-user'],
            ['label' => 'National ID (KTP)', 'value' => $row->KTP ?? null, 'icon' => 'fa-id-card'],
            ['label' => 'Family Card No.', 'value' => $row->NO_KK ?? null, 'icon' => 'fa-home'],
            ['label' => 'Gender', 'value' => $row->JK === 'L' ? 'Male' : ($row->JK === 'P' ? 'Female' : null), 'icon' => 'fa-venus-mars'],
            ['label' => 'Place, Date of Birth', 'value' => trim(($row->TMPTLAHIR ?: '') . ($row->TGLLAHIR ? ', ' . $this->formatTanggal($row->TGLLAHIR) : ''), ', '), 'icon' => 'fa-birthday-cake'],
            ['label' => 'Age', 'value' => $row->USIA ? $row->USIA . ' years' : null, 'icon' => 'fa-calendar-alt'],
            ['label' => 'Religion', 'value' => $row->AGAMA ?? null, 'icon' => 'fa-praying-hands'],
            ['label' => 'Marital Status', 'value' => $this->statusPernikahan($row->STATUS ?? null), 'icon' => 'fa-heart'],
            ['label' => 'Dependents', 'value' => $row->TANGGUNGAN !== null ? $row->TANGGUNGAN . ' persons' : null, 'icon' => 'fa-users'],
            ['label' => 'Phone / WhatsApp', 'value' => $row->HP ?? null, 'icon' => 'fa-phone'],
            ['label' => 'ID Card Address', 'value' => $row->ALAMAT ?? null, 'icon' => 'fa-map-marker-alt'],
            ['label' => 'City / Regency', 'value' => $row->KABUPATEN ?? null, 'icon' => 'fa-city'],
            ['label' => 'Latest Education', 'value' => $row->PDDK ?? null, 'icon' => 'fa-graduation-cap'],
            ['label' => 'Major', 'value' => $row->JURUSAN ?? null, 'icon' => 'fa-book'],
        ];

        $data['kepegawaian'] = [
            ['label' => 'NPK', 'value' => $row->NPK ?? null, 'icon' => 'fa-fingerprint'],
            ['label' => 'Department', 'value' => $row->DEPARTEMENT ?? null, 'icon' => 'fa-sitemap'],
            ['label' => 'Join Date (TMK)', 'value' => $this->formatTanggal($row->TMK ?? null), 'icon' => 'fa-calendar-check'],
            ['label' => 'Exit Date (TKK)', 'value' => $row->TKK ? $this->formatTanggal($row->TKK) : null, 'icon' => 'fa-calendar-times'],
            ['label' => 'Years of Service', 'value' => $data['masaKerja'], 'icon' => 'fa-clock'],
            ['label' => 'Bank Account No.', 'value' => $row->NOREK ?? null, 'icon' => 'fa-university'],
        ];

        // Riwayat kontrak
        try {
            $kontrak = DB::table('employees_contract')
                ->where('npk', $npk)
                ->orderByDesc('contract_ke')
                ->get();

            $data['kontrak'] = $kontrak->map(fn ($c) => [
                'ke' => $c->contract_ke,
                'mulai' => $this->formatTanggalPanjang($c->start_date ?? null),
                'selesai' => $this->formatTanggalPanjang($c->end_date ?? null),
                'durasi' => $c->month_duration ? $c->month_duration . ' mo' : '-',
                'status' => (string) ($c->status_contract ?? '-'),
            ])->all();

            $aktif = $kontrak->firstWhere('status_contract', 'AKTIF') ?? $kontrak->first();
            $data['kontrakAkhir'] = $aktif ? $this->formatTanggalPanjang($aktif->end_date ?? null) : null;
        } catch (\Throwable $e) {
            Log::warning('HrisV2 biodataKaryawan: gagal baca employees_contract — ' . $e->getMessage());
        }

        // Detail pelamar: keluarga, pendidikan, pengalaman, kontak darurat
        try {
            $detail = DB::table('pelamar_details as pd')
                ->join('PELAMAR as pl', 'pl.ID', '=', 'pd.id_pelamar')
                ->where('pl.NPK', $npk)
                ->select('pd.*')
                ->first();

            if (! $detail && ! empty($row->KTP)) {
                $detail = DB::table('pelamar_details as pd')
                    ->join('PELAMAR as pl', 'pl.ID', '=', 'pd.id_pelamar')
                    ->where('pl.NIK', trim((string) $row->KTP))
                    ->select('pd.*')
                    ->first();
            }

            if ($detail) {
                $data['kontakDarurat'] = [
                    'nama' => (string) ($detail->nama_ktk_darurat ?? ''),
                    'hubungan' => (string) ($detail->hubungan ?? ''),
                    'telepon' => (string) ($detail->no_telp_darurat ?? ''),
                    'domisili' => trim((string) ($detail->alamat_skrg ?? '') . ($detail->kabupaten_kota_skrg ? ', ' . $detail->kabupaten_kota_skrg : ''), ', '),
                ];
                $data['anak'] = $this->safeJson($detail->data_anak ?? null);
                $data['pendidikan'] = $this->safeJson($detail->riwayat_pendidikan ?? null);
                $data['pengalaman'] = $this->safeJson($detail->pengalaman_kerja ?? null);
                $data['ayah'] = $this->safeJson($detail->data_ayah ?? null);
                $data['ibu'] = $this->safeJson($detail->data_ibu ?? null);
                $data['saudara'] = $this->safeJson($detail->saudara_kandung ?? null);

                if (empty($data['ibu']['nama']) && ! empty($row->IBU)) {
                    $data['ibu']['nama'] = (string) $row->IBU;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('HrisV2 biodataKaryawan: gagal baca pelamar_details — ' . $e->getMessage());
        }

        // Foto profil (opsional — kalau tidak ada, halaman memakai inisial nama).
        try {
            $relatif = 'img/profile/' . ($row->BAGIAN ?? '') . '/' . $npk . '_' . ($row->NAMA ?? '') . '.jpg';
            if (Storage::disk('public')->exists($relatif)) {
                $data['foto'] = asset('storage/' . $relatif);
            }
        } catch (\Throwable $e) {
            Log::warning('HrisV2 biodataKaryawan: gagal cek foto — ' . $e->getMessage());
        }

        return $data;
    }

    /** Masa kerja dalam format "1 yr 5 mo" dari tanggal masuk. */
    protected function masaKerja($tmk): string
    {
        if (empty($tmk)) {
            return '-';
        }

        try {
            $masuk = \Carbon\Carbon::parse($tmk);
        } catch (\Throwable $e) {
            return '-';
        }

        $diff = $masuk->diff(now());
        $bagian = [];
        if ($diff->y > 0) {
            $bagian[] = $diff->y . ' yr';
        }
        if ($diff->m > 0) {
            $bagian[] = $diff->m . ' mo';
        }
        if ($diff->d > 0 && $diff->y === 0) {
            $bagian[] = $diff->d . ' day';
        }

        return $bagian === [] ? '< 1 month' : implode(' ', $bagian);
    }

    /**
     * Tanggal SQL Server/serial -> "1 January 2026" (null bila kosong).
     *
     * Sejak revisi Portal v2, SEMUA tanggal di portal memakai format panjang
     * ini (bukan lagi d/m/Y). Nama bulan ditulis manual (English) supaya tidak
     * bergantung pada locale Carbon.
     */
    protected function formatTanggal($nilai): ?string
    {
        return $this->formatTanggalPanjang($nilai);
    }

    /**
     * Status pernikahan dari DB (Indonesia) -> Inggris untuk portal v2.
     * Nilai yang tidak dikenali dikembalikan apa adanya supaya data tidak hilang.
     */
    protected function statusPernikahan($nilai): ?string
    {
        $teks = trim((string) $nilai);

        if ($teks === '') {
            return null;
        }

        $peta = [
            'KAWIN' => 'Married',
            'KAWIN BELUM TERCATAT' => 'Married (unregistered)',
            'BELUM KAWIN' => 'Single',
            'BELUM MENIKAH' => 'Single',
            'TIDAK KAWIN' => 'Single',
            'CERAI' => 'Divorced',
            'CERAI HIDUP' => 'Divorced',
            'CERAI MATI' => 'Widowed',
            'JANDA' => 'Widow',
            'DUDA' => 'Widower',
        ];

        return $peta[mb_strtoupper($teks)] ?? $teks;
    }

    /**
     * Tanggal SQL Server/serial -> "1 January 2026" (dipakai di Riwayat Kontrak).
     * Nama bulan ditulis manual (English) supaya tidak bergantung pada locale Carbon.
     */
    protected function formatTanggalPanjang($nilai): ?string
    {
        if (empty($nilai)) {
            return null;
        }

        try {
            $tanggal = \Carbon\Carbon::parse($nilai);
        } catch (\Throwable $e) {
            return null;
        }

        $bulan = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];

        $namaBulan = $bulan[(int) $tanggal->month] ?? '';

        return trim($tanggal->day . ' ' . $namaBulan . ' ' . $tanggal->year);
    }

    /**
     * Tanggal ringkas untuk kartu lowongan v2: 13 Nov 2026.
     * Nama bulan ditulis manual agar tidak bergantung pada locale aplikasi.
     */
    protected function formatTanggalPendek($nilai): string
    {
        if (empty($nilai)) {
            return '-';
        }

        try {
            $tanggal = \Carbon\Carbon::parse($nilai);
        } catch (\Throwable $e) {
            return '-';
        }

        $bulan = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];

        $namaBulan = $bulan[(int) $tanggal->month] ?? '';

        return trim($tanggal->day . ' ' . $namaBulan . ' ' . $tanggal->year);
    }

    /** Decode kolom JSON pelamar_details yang kadang berupa string berslash. */
    protected function safeJson($raw): array
    {
        if (empty($raw)) {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        if (is_object($raw)) {
            return (array) $raw;
        }
        if (is_string($raw)) {
            $bersih = trim($raw);
            $satu = json_decode($bersih, true);
            if (is_array($satu)) {
                return $satu;
            }
            $dua = json_decode(stripslashes($bersih), true);
            if (is_array($dua)) {
                return $dua;
            }
        }

        return [];
    }

    protected function profilKaryawan(string $npk): array
    {
        $profil = ['npk' => $npk, 'nama' => $npk, 'dept' => '-', 'bagian' => '-'];

        try {
            $row = DB::table('BIODATA as b')
                ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'b.ID_DEPT')
                ->where('b.NPK', $npk)
                ->select('b.NPK', 'b.NAMA_KARYAWAN', 'b.BAG', 'd.DEPARTEMENT')
                ->first();

            if ($row) {
                $profil['nama'] = (string) ($row->NAMA_KARYAWAN ?? $npk);
                $profil['dept'] = (string) ($row->DEPARTEMENT ?? '-');
                $profil['bagian'] = (string) ($row->BAG ?? '-');
            }
        } catch (\Throwable $e) {
            Log::warning('HrisV2 profilKaryawan gagal: ' . $e->getMessage());
        }

        return $profil;
    }

    /** Daftar slip gaji milik NPK dari periode payroll yang sudah ditutup. */
    protected function daftarSlip(string $npk): array
    {
        $hasil = [];

        try {
            $rows = DB::table('payroll_run_details as prd')
                ->join('payroll_runs as pr', 'pr.id', '=', 'prd.run_id')
                ->join('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
                ->where('prd.employee_npk', $npk)
                ->where('pp.is_closed', 1)
                ->orderByDesc('pp.end_date')
                ->select('pr.id as run_id', 'pp.name as periode', 'pp.start_date', 'pp.end_date', 'pr.processed_at')
                ->get();
        } catch (\Throwable $e) {
            Log::warning('HrisV2 daftarSlip gagal: ' . $e->getMessage());

            return ['tersedia' => false, 'catatan' => 'Payslip data could not be read: ' . $e->getMessage(), 'items' => []];
        }

        foreach ($rows as $r) {
            $hasil[] = [
                'run_id' => $r->run_id,
                'periode' => (string) ($r->periode ?? '-'),
                'rentang' => ($r->start_date ?? '-') . ' s/d ' . ($r->end_date ?? '-'),
                'url' => url('/employee-payroll/show/' . $r->run_id . '/' . rawurlencode($npk)),
            ];
        }

        return ['tersedia' => true, 'catatan' => null, 'items' => $hasil];
    }

    protected function lowonganTerbuka(): array
    {
        try {
            $departemen = DB::table('DEPT')->pluck('DEPARTEMENT', 'ID_DEPT');

            return \App\Models\JobVacancy::query()
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(function ($v) use ($departemen) {
                    $status = (string) ($v->computed_status_label ?? ($v->status ?? ''));
                    // Label status dari model masih berbahasa Indonesia (dipakai juga
                    // oleh halaman v1). Untuk halaman v2 diterjemahkan di sini agar
                    // tidak mengubah perilaku v1.
                    $statusInggris = [
                        'Dibuka' => 'Open',
                        'Akan Dibuka' => 'Opening Soon',
                        'Ditutup' => 'Closed',
                    ];
                    $status = $statusInggris[$status] ?? $status;

                    return [
                        'position' => $v->position,
                        'department' => $departemen->get($v->department_id, 'All Departments'),
                        'total_needed' => $v->total_needed,
                        'status_label' => $status,
                        'open' => $v->open_date ? $this->formatTanggalPendek($v->open_date) : '-',
                        'close' => $v->close_date ? $this->formatTanggalPendek($v->close_date) : '-',
                    ];
                })
                ->all();
        } catch (\Throwable $e) {
            Log::warning('HrisV2 lowonganTerbuka gagal: ' . $e->getMessage());

            return [];
        }
    }

    protected function jumlahLowongan(): int
    {
        try {
            return (int) \App\Models\JobVacancy::query()
                ->where(function ($q) {
                    $q->whereNull('close_date')->orWhere('close_date', '>=', now()->toDateString());
                })
                ->count();
        } catch (\Throwable $e) {
            Log::warning('HrisV2 jumlahLowongan gagal: ' . $e->getMessage());

            return 0;
        }
    }
}
