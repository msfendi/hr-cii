<?php

namespace App\Http\Controllers;

use App\Models\Canteen;
use App\Models\CanteenBreakWindow;
use App\Models\CanteenSetting;
use App\Models\AttendanceWorkLocation;
use App\Models\EmployeeFace;
use App\Models\FaceRecognitionSetting;
use App\Services\FaceRecognitionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CanteenTransactionController extends Controller
{
    /**
     * Halaman utama transaksi kantin (public, tidak butuh auth — seperti face-attendance/login).
     * Flow: pilih kantin (canteen_id 1/2) -> input NPK -> scan wajah -> receipt invoice-style.
     *
     * PERBAIKAN V35 HOTFIX (2026-11-09) — index() TIDAK BOLEH 500.
     *
     * Kenapa masih 500 padahal model sudah aman (V34)?
     *   Di V34 semua MODEL sudah dilindungi try/catch. Tapi index()
     *   sendiri masih punya 3 query LANGSUNG ke database tanpa proteksi:
     *
     *     $canteens  = Canteen::where('is_active', true)->...->get();    <-- unprotected
     *     $windows   = CanteenBreakWindow::query()->orderBy('id')->get(); <-- unprotected
     *     $locations = AttendanceWorkLocation::where('is_active', true)->get(); <-- unprotected
     *
     *   Kalau salah satu query itu timeout (SQL Server remote HYT00),
     *   halaman mati 500 SEBELUM view pernah dirender.
     *
     *   V35: ketiga query dibungkus try/catch dengan fallback kosong,
     *   jadi halaman SELALU bisa dirender (minimally tapi tidak error).
     *   Error asli tetap dicatat di log supaya bisa ditelusuri.
     */
    public function index()
    {
        // PERBAIKAN V35: query aman dengan fallback koleksi kosong.
        $canteens  = $this->safeQuery(fn () => Canteen::where('is_active', true)->orderBy('canteen_id')->get(), 'canteen');
        $windows   = $this->safeQuery(fn () => CanteenBreakWindow::query()->orderBy('id')->get(), 'break_masters');
        $locations = $this->safeQuery(fn () => AttendanceWorkLocation::where('is_active', true)->orderBy('name')->get(), 'attendance_work_locations');

        // CanteenSetting::current() sudah aman (V34) tapi tetap bungkus
        // supaya controller tidak pernah Rethrow dari mana pun.
        try {
            $setting = CanteenSetting::current();
        } catch (\Throwable $e) {
            Log::warning('Canteen index: CanteenSetting::current() gagal: ' . $e->getMessage());
            $setting = new CanteenSetting();
            $setting->require_geomap = false;
            $setting->allowed_work_location_ids = [];
        }

        // PERBAIKAN V32 (2026-11-09): mode canteen = liveness ATAU random
        // challenge (bukan keduanya). Mode disimpan di
        // face_recognition_settings.canteen_verification_mode dan
        // disetting dari /face-attendance/settings.
        //
        // CanteenSetting (canteen_settings) TIDAK lagi dipakai untuk
        // liveness/random canteen. Nanti kolomnya dihapus (lihat
        // migration). Geomap tetap di canteen_settings.
        $canteenVerificationMode = FaceRecognitionSetting::canteenVerificationMode();
        $livenessEnabled = $canteenVerificationMode === FaceRecognitionSetting::CANTEEN_MODE_LIVENESS;
        $npkRequired = FaceRecognitionSetting::isNpkRequiredEnabled();
        $randomChallengeEnabled = $canteenVerificationMode === FaceRecognitionSetting::CANTEEN_MODE_RANDOM;
        $randomChallenge = null;
        if ($randomChallengeEnabled) {
            try {
                $randomChallenge = FaceRecognitionService::issueRandomChallenge('canteen-' . session()->getId());
            } catch (\Throwable $e) {
                Log::warning('Canteen index: issueRandomChallenge gagal: ' . $e->getMessage());
            }
        }

        $currentWindow = CanteenBreakWindow::currentWindow();
        $now = now()->format('H:i d M Y');
        $insideWindow = $currentWindow !== null;

        // PERBAIKAN V32 (2026-11-09): di luar jam istirahat, daftar kantin
        // TIDAK boleh dirender. Permintaan user: "jika diluar window jam
        // istirahat maka daftar kantin tidak muncul langsung munculkan
        // sweet alert diluar jam istirahat".
        //
        // Daftar kantin sengaja DIKOSONGKAN (bukan cuma disembunyikan
        // dengan CSS) supaya tidak ada di DOM sama sekali — user tidak
        // bisa memunculkannya lewat devtools, dan tidak ada tombol yang
        // bisa diklik untuk melanjutkan ke langkah NPK.
        $canteensForView = $insideWindow ? $canteens : collect();

        return view('canteen_transaction.index', compact(
            'canteens', 'canteensForView', 'windows', 'locations', 'livenessEnabled', 'npkRequired',
            'randomChallengeEnabled', 'randomChallenge', 'canteenVerificationMode', 'currentWindow', 'insideWindow', 'now',
            'setting'
        ));
    }

    /**
     * PERBAIKAN V35 HOTFIX (2026-11-09) — query aman dengan fallback.
     *
     * Kalau query gagal (timeout, tabel tidak ada, permission denied),
     * kembalikan koleksi KOSONG supaya halaman tetap render. Error
     * dicatat di log (bukan swallowing diam-diam).
     *
     * @param  callable  $query
     * @param  string    $label  nama sumber data untuk pesan log
     * @return \Illuminate\Support\Collection
     */
    private function safeQuery(callable $query, string $label)
    {
        try {
            $result = $query();
            return $result instanceof \Illuminate\Support\Collection ? $result : collect($result);
        } catch (\Throwable $e) {
            Log::warning('Canteen index: gagal memuat ' . $label . ': ' . $e->getMessage(), [
                'exception' => get_class($e),
            ]);
            return collect();
        }
    }

    /**
     * Validasi NPK sebelum kamera dibuka (mirror FaceAttendanceController::validateNpk).
     */
    public function validateNpk(Request $request)
    {
        $npk = trim((string) $request->input('npk', ''));
        if ($npk === '') {
            return response()->json(['success' => false, 'message' => 'NPK wajib diisi.'], 422);
        }

        $check = FaceRecognitionService::validateNpkForFace($npk);

        // ==============================================================
        // KONTRAK RESPON diseragamkan dengan /face-attendance/login
        // (FaceAttendanceController::validateNpk):
        //   success           -> true/false  (WAJIB, bukan "ok")
        //   inactive_employee -> true bila PKWT.TKK terisi
        //   has_face          -> true bila descriptor wajah terdaftar
        //   npk / name        -> data karyawan
        //   message           -> pesan Bahasa Indonesia
        //
        // SEBELUM PERBAIKAN INI:
        //   Controller mengembalikan key "ok" + "inactive", sedangkan
        //   frontend /canteen-transaction membaca "success" +
        //   "inactive_employee" (copy-paste dari /login). Akibatnya
        //   `data.success` selalu undefined -> `!data.success` selalu
        //   TRUE -> SweetAlert merah "NPK Tidak Valid" selalu muncul,
        //   padahal NPK-nya VALID. Lalu cabang success tetap jalan
        //   (karena dicek lewat `data.ok`?) sehingga muncul SweetAlert
        //   kedua yang menimpa teks -> judul merah + teks hijau
        //   ("NPK Tidak Valid" / "NPK valid. Silakan posisikan...").
        //   Kamera & minimap pun tidak pernah dijalankan karena alur
        //   dianggap gagal.
        // ==============================================================
        $isInactive = (bool) ($check['inactive'] ?? false);

        $payload = [
            'success'           => (bool) ($check['ok'] ?? false),
            'ok'                => (bool) ($check['ok'] ?? false),
            'inactive_employee' => $isInactive,
            'inactive'          => $isInactive,
            'has_face'          => (bool) ($check['has_face'] ?? false),
            'npk'               => $check['npk'] ?? $npk,
            'name'              => $check['name'] ?? '',
            'message'           => $check['message'] ?? '',
        ];

        return response()->json($payload, $check['status'] ?? 200);
    }

    /**
     * Proses scan wajah + simpan ke database canteen (canteen / canteen_twos).
     * Di-override dari FaceAttendanceController::loginAuth: menambah cek window + geomap opsional.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'descriptor' => 'required',
            ]);

            $descriptor = FaceRecognitionService::normalizeDescriptor($request->input('descriptor'));
            if ($descriptor === null) {
                return response()->json(['success' => false, 'message' => 'Format descriptor wajah tidak valid. Silakan posisikan wajah dengan jelas.'], 422);
            }

            $npkInput = trim((string) $request->input('npk', ''));
            $canteenId = (int) $request->input('canteen_id', 0);

            if (!in_array($canteenId, [1, 2], true)) {
                return response()->json(['success' => false, 'message' => 'Pilih Kantin 1 atau Kantin 2 terlebih dahulu.'], 422);
            }

            $canteen = Canteen::where('canteen_id', $canteenId)->where('is_active', true)->first();
            if (!$canteen) {
                return response()->json(['success' => false, 'message' => "Kantin {$canteenId} belum aktif. Hubungi admin."], 422);
            }

            // Window check (boleh dilonggarkan bila admin menonaktifkan via setting window? untuk sekarang wajib di dalam salah satu window aktif)
            $window = CanteenBreakWindow::currentWindow();
            if (!$window) {
                // Fallback: bila tidak ada window cocok saat ini, tolak dengan pesan jelas (bukan diam-diam lolos).
                return response()->json([
                    'success' => false,
                    'message' => 'Di luar jam istirahat / window kantin. Transaksi kantin hanya bisa dilakukan pada window yang dikonfigurasi admin.',
                    'outside_window' => true,
                ], 422);
            }

            $npkRequired = FaceRecognitionSetting::isNpkRequiredEnabled();
            if ($npkRequired && $npkInput === '') {
                return response()->json(['success' => false, 'message' => 'NPK wajib diisi terlebih dahulu sebelum scan wajah.'], 422);
            }

            if ($npkInput !== '') {
                $result = FaceRecognitionService::matchByNpk($descriptor, $npkInput);
            } else {
                $result = FaceRecognitionService::match($descriptor);
            }

            $face = $result['face'] ?? null;
            $distance = $result['distance'] ?? null;
            $threshold = FaceRecognitionService::threshold();

            if (!$face || $distance === null || $distance > $threshold) {
                return response()->json([
                    'success' => false,
                    'message' => $npkInput !== ''
                        ? 'Wajah tidak cocok dengan NPK ' . $npkInput . '. Silakan posisikan wajah lebih jelas.'
                        : 'Wajah tidak dikenali atau belum terdaftar di tabel employee_faces.',
                ], 401);
            }

            if (!FaceRecognitionService::isConfidentMatch($result)) {
                Log::warning('Canteen scan ditolak: margin terlalu kecil', ['distance' => $distance, 'threshold' => $threshold, 'npk' => $face->npk ?? null]);
                return response()->json([
                    'success' => false,
                    'message' => $npkInput !== ''
                        ? 'Wajah tidak cocok dengan NPK ' . $npkInput . '. Silakan posisikan wajah lebih jelas dan coba lagi.'
                        : 'Wajah tidak dapat dipastikan (terlalu mirip dengan karyawan lain). Silakan scan ulang dengan pencahayaan lebih baik.',
                ], 401);
            }

            $npk = (string) ($face->npk ?? $npkInput);
            $name = (string) ($face->name ?? $npk);

            $activeStatus = \App\Services\EmployeeActiveStatusService::check($npk);
            if ($activeStatus['found'] && !$activeStatus['active']) {
                return response()->json([
                    'success' => false,
                    'inactive_employee' => true,
                    'message' => \App\Services\EmployeeActiveStatusService::inactiveMessage($activeStatus['nama'], $activeStatus['tkk']),
                ], 403);
            }

            // Ambil data karyawan dari BIODATA (CII) untuk nama & dept.
            $biodata = null;
            try {
                $biodata = DB::connection('cii')->table('BIODATA')->where('NPK', $npk)->first();
            } catch (\Throwable $e) {
                Log::warning('Canteen: gagal baca BIODATA: ' . $e->getMessage());
            }
            if ($biodata && !empty($biodata->NAMA_KARYAWAN)) {
                $name = $biodata->NAMA_KARYAWAN;
            }
            $deptId = $biodata->ID_DEPT ?? $biodata->BAG ?? null;

            // PERBAIKAN V32 (2026-11-09): tampilkan NAMA DEPARTEMENT dari
            // DEPT.DEPARTEMENT (CII), bukan kode ID_DEPT yang berupa
            // angka ("2095"). Kalau lookup gagal / ID_DEPT null, tetap
            // pakai kode aslinya supaya tidak ada data yang hilang.
            $dept = $this->resolveDepartmentName($deptId, $deptId);

            // Geolocation + geomap opsional.
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $locationName = $request->input('location_name');
            $insideGeomap = null;

            $setting = CanteenSetting::current();
            $requireGeomap = (bool) ($setting->require_geomap ?? false);
            if ($requireGeomap) {
                if ($latitude === null || $longitude === null || $latitude === '' || $longitude === '') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Lokasi GPS wajib diizinkan untuk transaksi kantin. Aktifkan lokasi di browser lalu coba lagi.',
                        'geomap_failed' => true,
                    ], 422);
                }
                $check = $this->checkGeomap((float) $latitude, (float) $longitude);
                $insideGeomap = $check['inside'];
                $locationName = $locationName ?: $check['location_name'];
                if (!$insideGeomap) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Posisi Anda di luar area geomap kantin yang diizinkan. Pindah ke area ' . ($check['allowed_names'] ?? 'kantin') . ' lalu coba lagi.',
                        'geomap_failed' => true,
                    ], 422);
                }
            } elseif ($latitude !== null && $longitude !== null && $latitude !== '' && $longitude !== '') {
                $check = $this->checkGeomap((float) $latitude, (float) $longitude);
                $insideGeomap = $check['inside'];
                $locationName = $locationName ?: $check['location_name'];
            }

            // ==============================================================
            // PERBAIKAN V32 (2026-11-09) — PROTEKSI DOUBLE-POST
            // ==============================================================
            // Permintaan user: "beri proteksi agar saat post setelah scan
            // wajah tidak bisa double. Jadi cek dulu di table canteen atau
            // canteen_twos apakah ada npk tersebut di window istirahat.
            // Jika ada maka data tidak dipost."
            //
            // PENTING: cek MELINTAS kedua tabel (canteen = kantin 1,
            // canteen_twos = kantin 2). Jadi kalau user scan di Kantin 1
            // lalu scan lagi di Kantin 2 pada hari yang sama, yang
            // kedua DITOLAK.
            //
            // PERBAIKAN V44 (2026-11-10) — CEK BERDASARKAN TANGGAL SAJA.
            // Sebelumnya syarat duplikat = NPK sama + tanggal sama +
            // window_name sama. Akibatnya user bisa scan berkali-kali
            // dalam satu hari selama window berbeda (mis. Sarapan lalu
            // Makan Siang), padahal aturannya: 1x scan per hari.
            // Sekarang: NPK sama + tanggal sama (di salah satu dari dua
            // tabel) = duplikat, tidak peduli window-nya apa.
            $today = now()->format('Y-m-d');
            try {
                $dup = $this->findDuplicateAnyCanteen($npk, $today);
            } catch (\RuntimeException $e) {
                // Cek duplikat tidak bisa dijalankan (DB kantin error).
                // Fail-closed: tolak transaksi, jangan lolos diam-diam.
                //
                // PERBAIKAN V40 (2026-11-10): sertakan KODE error + pesan
                // yang JAUH lebih spesifik untuk kasus "tabel kantin belum
                // ada". Pesan lama ("coba lagi beberapa saat lagi") membuat
                // user mengira masalahnya sementara, padahal penyebabnya
                // (tabel tidak pernah dibuat) TIDAK akan hilang dengan
                // sendirinya — harus diperbaiki admin.
                $reason = $e->getMessage() === 'cek-duplikat-gagal'
                    ? 'schema_or_db'
                    : 'unknown';

                $message = $reason === 'schema_or_db'
                    ? 'Sistem tidak dapat memeriksa transaksi sebelumnya. '
                      . 'Database kantin belum siap. Hubungi admin untuk menjalankan '
                      . 'perbaikan database kantin, lalu coba lagi.'
                    : 'Sistem tidak dapat memeriksa transaksi sebelumnya. '
                      . 'Silakan coba lagi beberapa saat lagi.';

                return response()->json([
                    'success' => false,
                    'duplicate_check_failed' => true,
                    'reason' => $reason,
                    'message' => $message,
                ], 503);
            }

            if ($dup) {
                $dupCanteenNo = (string) ($dup->canteen_no ?: '?');
                $dupCanteenLabel = $dupCanteenNo === '2' ? 'Kantin 2' : 'Kantin ' . $dupCanteenNo;
                $dupTime = '';
                try {
                    $dupTime = $dup->created_at ? \Carbon\Carbon::parse($dup->created_at)->format('H:i') : '';
                } catch (\Throwable $e) {
                    $dupTime = (string) $dup->created_at;
                }

                return response()->json([
                    'success' => false,
                    'duplicate' => true,
                    'duplicate_canteen' => $dupCanteenNo,
                    // PERBAIKAN V44 (2026-11-10): cek duplikat sekarang
                    // berdasarkan TANGGAL saja, jadi window tidak lagi
                    // dilaporkan sebagai penyebab (bisa beda window tapi
                    // tetap duplikat karena satu hari).
                    'duplicate_window' => (string) ($dup->window_name ?: ''),
                    'duplicate_time' => $dupTime,
                    'message' => "Anda sudah scan di {$dupCanteenLabel} hari ini"
                        . ($dupTime !== '' ? " (pukul {$dupTime})" : '')
                        . ". Transaksi tidak diduplikasi. Tunjukkan bukti scan sebelumnya ke pihak kantin.",
                ], 409);
            }

            // Foto wajah hasil capture (base64 JPEG). Dikirim dari browser
            // sebagai data URI; disimpan apa adanya supaya bisa ditampilkan
            // lagi di "Bukti Scan Kantin" (termasuk saat user membuka
            // bukti scan sebelumnya).
            $photoFace = $this->sanitizePhotoFace($request->input('photo_face'));

            // PERBAIKAN V41 (2026-11-10): id_ticket (identitas unik transaksi).
            // Dibangkitkan SEBELUM simpan supaya nilai yang tersimpan di DB
            // sama persis dengan yang tampil di bukti scan.
            $idTicket = $this->buildTicketId($npk);

            // Simpan ke database 'canteen' — tabel tergantung canteen_id.
            $record = $this->saveToCanteenDb($canteenId, [
                'npk' => $npk,
                'name' => $name,
                'dept' => $dept,
                'canteen_no' => (string) $canteenId,
                'id_ticket' => $idTicket,
                'date' => $today,
                'window_name' => $window->name,
                'location_name' => $locationName,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'photo_face' => $photoFace,
            ]);

            // Receipt/invoice: dikembalikan sebagai JSON, dirender di layar seperti bukti bayar QRIS.
            $invoice = [
                'no' => 'CTN-' . $canteenId . '-' . now()->format('Ymd-His') . '-' . strtoupper(substr(md5($npk . now()->timestamp), 0, 6)),
                // PERBAIKAN V41 (2026-11-10): id_ticket ditampilkan di Bukti Scan
                // supaya user / kasir punya identitas unik transaksi (bukan
                // cuma "No. Bukti" yang formatnya berurutan).
                'id_ticket' => $idTicket,
                'canteen_id' => $canteenId,
                'canteen_name' => $canteen->name,
                'canteen_location' => $canteen->location,
                'contact_person' => $canteen->contact_person ?: $canteen->pic_name,
                'contact_phone' => $canteen->contact_phone ?: $canteen->pic_phone,
                'npk' => $npk,
                'name' => $name,
                'dept' => $dept,
                'window_name' => $window->name,
                'date' => $today,
                'time' => now()->format('H:i:s'),
                'location_name' => $locationName,
                'inside_geomap' => $insideGeomap,
                'latitude' => $latitude !== null ? (float) $latitude : null,
                'longitude' => $longitude !== null ? (float) $longitude : null,
                // PERBAIKAN V32 (2026-11-09): foto wajah hasil capture,
                // supaya bisa ditampilkan di Bukti Scan Kantin.
                // PERBAIKAN V44 HOTFIX: pastikan berformat data:image/...;base64,...
                // agar browser tidak meminta relative path (memicu HTTP 414).
                'photo_face' => self::formatPhotoFaceForResponse($photoFace),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Scan wajah berhasil. Tunjukkan bukti ini ke pihak kantin.',
                'invoice' => $invoice,
                'record_id' => $record->id ?? null,
            ]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json(['success' => false, 'message' => $ve->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Canteen store error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.'], 500);
        }
    }

    /**
     * PERBAIKAN V32 (2026-11-09) — BUKTI SCAN TERAKHIR untuk satu NPK.
     *
     * Dipakai tombol "Lihat Bukti Scan" pada SweetAlert duplikat, supaya
     * user bisa menunjukkan bukti scan sebelumnya ke pihak kantin tanpa
     * perlu scan ulang (scan ulang sudah ditolak memang).
     *
     * Query memakai parameter binding (Eloquent), jadi NPK dari request
     * TIDAK bisa disisipkan ke SQL.
     */
    public function latestReceipt(Request $request)
    {
        $npk = trim((string) $request->query('npk', ''));
        if ($npk === '') {
            return response()->json(['success' => false, 'message' => 'NPK wajib diisi.'], 422);
        }

        // Validasi format NPK: huruf/angka/minus saja. Maksimal 20 karakter.
        if (!preg_match('/^[A-Za-z0-9\-]{1,20}$/', $npk)) {
            return response()->json(['success' => false, 'message' => 'Format NPK tidak valid.'], 422);
        }

        $date = now()->format('Y-m-d');

        $rows = [];
        $errors = [];
        foreach ([
            1 => \App\Models\CanteenReport::class,
            2 => \App\Models\CanteenTwoReport::class,
        ] as $canteenNo => $modelClass) {
            try {
                $row = $modelClass::query()
                    ->where('npk', $npk)
                    ->where('date', $date)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->first();
                if ($row) {
                    $rows[] = [
                        'canteen_id' => $canteenNo,
                        'canteen_no' => (string) ($row->canteen_no ?: $canteenNo),
                        // PERBAIKAN V41 (2026-11-10): id_ticket ikut dikembalikan
                        // supaya bukti scan sebelumnya juga menampilkannya.
                        'id_ticket' => $row->id_ticket,
                        'npk' => $row->npk,
                        'name' => $row->name,
                        'dept' => $row->dept,
                        'date' => $row->date ? (string) $row->date : null,
                        'window_name' => $row->window_name,
                        'location_name' => $row->location_name,
                        'latitude' => $row->latitude !== null ? (float) $row->latitude : null,
                        'longitude' => $row->longitude !== null ? (float) $row->longitude : null,
                        'photo_face' => self::formatPhotoFaceForResponse($row->photo_face),
                        'time' => $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('H:i:s') : null,
                        'created_at' => $row->created_at ? (string) $row->created_at : null,
                    ];
                }
            } catch (\Throwable $e) {
                $errors[] = "kantin{$canteenNo}: " . $e->getMessage();
            }
        }

        if (empty($rows) && !empty($errors)) {
            Log::warning('Canteen latestReceipt gagal: ' . implode(' | ', $errors));
            return response()->json(['success' => false, 'message' => 'Gagal memuat bukti scan.'], 503);
        }

        // Urutkan dari yang terbaru.
        usort($rows, function ($a, $b) {
            return strcmp((string) $b['created_at'], (string) $a['created_at']);
        });

        return response()->json([
            'success' => true,
            'receipts' => $rows,
            'count' => count($rows),
        ]);
    }

    // -----------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------

    private function saveToCanteenDb(int $canteenId, array $attrs)
    {
        $modelClass = $canteenId === 2 ? \App\Models\CanteenTwoReport::class : \App\Models\CanteenReport::class;
        $model = new $modelClass();
        $model->fill($attrs);
        $model->save();
        return $model;
    }

    /**
     * PERBAIKAN V44 (2026-11-10) — parameter $windowName DIHAPUS.
     * Cek duplikat sekarang berdasarkan NPK + TANGGAL saja di kedua
     * tabel (canteen & canteen_twos). Lihat findDuplicateAnyCanteen()
     * untuk penjelasan lengkap.
     */
    private function findDuplicate(int $canteenId, string $npk, string $date)
    {
        $modelClass = $canteenId === 2 ? \App\Models\CanteenTwoReport::class : \App\Models\CanteenReport::class;
        try {
            return $modelClass::query()
                ->where('npk', $npk)
                ->where('date', $date)
                ->orderByDesc('created_at')
                ->first();
        } catch (\Throwable $e) {
            Log::warning('Canteen duplicate check gagal: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * PERBAIKAN V32 (2026-11-09) — CEK DUPLIKAT MELINTAS KEDUA KANTIN.
     *
     * Permintaan user: "cek dulu di table canteen atau canteen_twos apakah
     * ada npk tersebut di window istirahat. Jika ada maka data tidak
     * dipost."
     *
     * Jadi kita cek di KEDUA tabel:
     *   canteen      -> Kantin 1
     *   canteen_twos -> Kantin 2
     *
     * PERBAIKAN V44 (2026-11-10) — CEK BERDASARKAN TANGGAL SAJA.
     * Parameter $windowName dihapus. Aturan baru (permintaan user):
     * kalau NPK sudah pernah scan PADA TANGGAL TERSEBUT di SALAH SATU
     * dari kedua tabel (apa pun window-nya), maka scan berikutnya
     * DITOLAK dan cukup tampilkan history + bukti scan. Sebelumnya
     * syarat window_name sama membuat scan kedua di window berbeda
     * (mis. sarapan vs makan siang) LOLOS sebagai "bukan duplikat".
     *
     * PENTING (fail-safe): kalau salah satu tabel tidak bisa dibaca (mis.
     * koneksi DB kantin putus), TETAP harus menolak — jangan lolos
     * diam-diam. Kalau tidak, proteksi double-post bisa dilewati setiap
     * kali DB kantin error.
     *
     * PERBAIKAN V40 (2026-11-10) — AUTO-HEAL SCHEMA:
     * Gejala produksi: "Sistem tidak dapat memeriksa transaksi sebelumnya."
     * (HTTP 503 duplicate_check_failed). Penyebabnya: tabel 'canteen' /
     * 'canteen_twos' (dan/atau kolomnya) BELUM ADA di database 'canteen'.
     *
     * Kenapa bisa terjadi: migrasi 2026_11_09_130000 membuat kedua tabel
     * di dalam blok try yang HANYA Log::warning() tanpa throw. Jadi kalau
     * koneksi DB 'canteen' sedang tidak terjangkau saat migrasi berjalan,
     * Laravel tetap menandai migrasi sebagai SUCCEES — padahal tabelnya
     * tidak pernah dibuat. Akibatnya SETIAP scan berikutnya gagal 503.
     *
     * Perbaikan: kalau error-nya "object/table/column tidak ada" (bukan
     * timeout / koneksi mati), coba buat tabel/kolom yang kurang SATU
     * kali, lalu ulangi query. Kalau tetap gagal, tetap fail-closed 503
     * — proteksi double-post tidak boleh dilonggarkan.
     *
     * @return object|null baris duplikat, atau null kalau tidak ada
     */
    private function findDuplicateAnyCanteen(string $npk, string $date)
    {
        $models = [
            1 => \App\Models\CanteenReport::class,
            2 => \App\Models\CanteenTwoReport::class,
        ];

        $errors = [];
        foreach ($models as $canteenNo => $modelClass) {
            try {
                $row = $modelClass::query()
                    ->where('npk', $npk)
                    ->where('date', $date)
                    ->orderByDesc('created_at')
                    ->first();

                if ($row) {
                    // Pastikan canteen_no terisi supaya pesan error menyebut
                    // kantin yang benar, walau baris lamanya belum punya.
                    if (empty($row->canteen_no)) {
                        $row->canteen_no = (string) $canteenNo;
                    }
                    return $row;
                }
            } catch (\Throwable $e) {
                // PERBAIKAN V40: kalau masalahnya "tabel/kolom belum ada",
                // coba buat otomatis SEKALI lalu ulangi query. Error lain
                // (timeout, koneksi mati, hak akses) TIDAK dicoba di-heal.
                if ($this->isMissingSchemaError($e)) {
                    if ($this->healCanteenSchema($canteenNo)) {
                        try {
                            $row = $modelClass::query()
                                ->where('npk', $npk)
                                ->where('date', $date)
                                ->orderByDesc('created_at')
                                ->first();

                            if ($row) {
                                // Pastikan canteen_no terisi supaya pesan error
                                // menyebut kantin yang benar, walau baris lamanya
                                // belum punya.
                                if (empty($row->canteen_no)) {
                                    $row->canteen_no = (string) $canteenNo;
                                }
                                return $row;
                            }

                            // Heal berhasil & query ulang jalan (tidak ada
                            // duplikat). Error ASLI sudah tidak relevan, jadi
                            // jangan disimpan sebagai kegagalan.
                            //
                            // PENTING: entri error hanya ditambahkan kalau
                            // heal GAGAL atau retry tetap gagal. Kalau error
                            // asli dibiarkan di $errors, blok akhir akan
                            // melempar 'cek-duplikat-gagal' walaupun heal
                            // sudah berhasil — persis bug yang terjadi.
                            continue;
                        } catch (\Throwable $e2) {
                            // Heal jalan tapi query ulang tetap gagal ->
                            // simpan BOTH error supaya jelas apa masalahnya.
                            $errors[] = "kantin{$canteenNo}: " . $e->getMessage();
                            $errors[] = "kantin{$canteenNo} (setelah heal): " . $e2->getMessage();
                        }
                    } else {
                        // Heal gagal -> error asli tetap relevan.
                        $errors[] = "kantin{$canteenNo}: " . $e->getMessage()
                            . ' (auto-heal gagal)';
                    }
                } else {
                    // Error bukan skema (timeout / koneksi mati / hak akses)
                    // -> tidak ada yang bisa di-heal, error tetap dicatat.
                    $errors[] = "kantin{$canteenNo}: " . $e->getMessage();
                }
            }
        }

        if (!empty($errors)) {
            // Gagal cek = tidak boleh lolos (fail-closed).
            Log::warning('Canteen duplicate check lintas kantin GAGAL: ' . implode(' | ', $errors));
            throw new \RuntimeException('cek-duplikat-gagal');
        }

        return null;
    }

    /**
     * PERBAIKAN V40 — apakah exception ini akibat "tabel/kolom belum ada"?
     *
     * Hanya error skema yang boleh di-heal otomatis. Timeout, koneksi
     * putus, atau error lain TIDAK boleh — attempt membuat tabel saat
     * koneksi putus justru menambah beban ke DB yang sedang sakit.
     *
     * @param \Throwable $e
     * @return bool
     */
    private function isMissingSchemaError(\Throwable $e): bool
    {
        $msg = $e->getMessage();

        // SQL Server: "Invalid object name 'canteen'." / "Invalid column name 'window_name'."
        if (stripos($msg, 'Invalid object name') !== false) {
            return true;
        }
        if (stripos($msg, 'Invalid column name') !== false) {
            return true;
        }
        // SQLite (test): "no such table" / "no such column"
        if (stripos($msg, 'no such table') !== false) {
            return true;
        }
        if (stripos($msg, 'no such column') !== false) {
            return true;
        }

        return false;
    }

    /**
     * PERBAIKAN V40 — buat tabel 'canteen' / 'canteen_twos' beserta kolom
     * yang hilang, bila memang belum ada.
     *
     * CATATAN: schema builder diambil dari koneksi 'canteen' yang sama
     * dengan yang dipakai model CanteenReport / CanteenTwoReport
     * (protected $connection = 'canteen'). Jadi objek yang disentuh
     * auto-heal ini PERSIS database yang dibaca query cek-duplikat.
     *
     * Idempotent: kalau tabel sudah ada, kolom yang ada tidak diubah; hanya
     * kolom yang benar-benar hilang yang ditambahkan.
     *
     * @param int $canteenNo 1 = canteen, 2 = canteen_twos
     * @return bool true bila heal berhasil (tabel siap dibaca)
     */
    private function healCanteenSchema(int $canteenNo): bool
    {
        $table = $canteenNo === 2 ? 'canteen_twos' : 'canteen';

        $columns = [
            'npk'           => 'string',
            'name'          => 'string',
            'dept'          => 'string',
            'canteen_no'    => 'string',
            'date'          => 'date',
            'window_name'   => 'string',
            'location_name' => 'string',
            'latitude'      => 'decimal',
            'longitude'     => 'decimal',
            'photo_face'    => 'string',
            'created_at'    => 'datetime',
            'updated_at'    => 'datetime',
        ];

        try {
            // Pakai schema builder milik koneksi 'canteen' yang aktif.
            $schema = Schema::connection('canteen');

            if (!$schema->hasTable($table)) {
                $schema->create($table, function ($t) {
                    $t->id();
                    $t->string('npk')->index();
                    $t->string('name')->nullable();
                    $t->string('dept')->nullable();
                    $t->string('canteen_no')->nullable();
                    $t->date('date')->index();
                    $t->string('window_name')->nullable();
                    $t->string('location_name')->nullable();
                    $t->decimal('latitude', 10, 7)->nullable();
                    $t->decimal('longitude', 11, 7)->nullable();
                    $t->string('photo_face')->nullable();
                    $t->timestamps();
                });

                Log::info('Canteen V40 auto-heal: tabel ' . $table . ' dibuat di database canteen.');
                return true;
            }

            // Tabel ada -> pastikan semua kolom yang dibutuhkan tersedia.
            $added = [];
            foreach ($columns as $col => $type) {
                if ($schema->hasColumn($table, $col)) {
                    continue;
                }
                $schema->table($table, function ($t) use ($col, $type) {
                    $t->addColumn($col, $type)->nullable();
                });
                $added[] = $col;
            }

            if (!empty($added)) {
                Log::info('Canteen V40 auto-heal: kolom ditambahkan di ' . $table . ': '
                    . implode(', ', $added));
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Canteen V40 auto-heal gagal untuk ' . $table . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * PERBAIKAN V32 (2026-11-09) — NAMA DEPARTEMENT dari DEPT.DEPARTEMENT.
     *
     * CATATAN: reproducible di server. Bukti Scan menampilkan kode angka
     * ("2095") karena DEPT diambil dari BIODATA.ID_DEPT. Sekarang lookup
     * ke tabel DEPT di koneksi 'cii' (SQL Server CII) untuk mengambil
     * DEPARTEMENT.
     *
     * Fallback: kalau ID_DEPT null / tidak ditemukan / DB error, pakai
     * kode aslinya supaya tidak ada data yang hilang.
     */
    private function resolveDepartmentName($deptId, $fallback = null)
    {
        if ($deptId === null || $deptId === '') {
            return $fallback;
        }

        try {
            $row = DB::connection('cii')->table('DEPT')
                ->select('ID_DEPT', 'DEPARTEMENT')
                ->where('ID_DEPT', $deptId)
                ->first();

            if ($row && !empty($row->DEPARTEMENT)) {
                return trim((string) $row->DEPARTEMENT);
            }
        } catch (\Throwable $e) {
            Log::warning('Canteen: lookup DEPT.DEPARTEMENT gagal untuk ID_DEPT=' . $deptId . ': ' . $e->getMessage());
        }

        return $fallback !== null ? $fallback : $deptId;
    }

    /**
     * PERBAIKAN V32 (2026-11-09) — SANITASI FOTO WAJAH (base64 data URI).
     *
     * Yang diterima HANYA:
     *   - data:image/jpeg;base64,<payload>
     *   - data:image/png;base64,<payload>
     *
     * Payload dibatasi (MAX 400 KB string) supaya tidak ada payload raksasa
     * yang membanjiri database. Semua karakter selain base64 yang valid
     * dibuang, jadi tidak ada payload/skrip yang bisa diselundupkan.
     *
     * Hasil disimpan TANPA prefix data URI (cuma payload base64) supaya
     * kolom TEXT di SQL Server tetap ringkas.
     */
    private function sanitizePhotoFace($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // Terima juga tanpa prefix data URI.
        if (preg_match('#^data:image/(jpeg|jpg|png);base64,([A-Za-z0-9+/=]+)$#', $value, $m)) {
            $payload = $m[2];
        } elseif (preg_match('/^[A-Za-z0-9+/=]+$/', $value)) {
            $payload = $value;
        } else {
            Log::warning('Canteen: photo_face ditolak karena format bukan data URI base64 yang valid.');
            return null;
        }

        // Batas ukuran: 400 KB base64 ~= 300 KB gambar.
        if (strlen($payload) > 400 * 1024) {
            Log::warning('Canteen: photo_face ditolak karena melebihi batas ukuran.');
            return null;
        }

        // Validasi base64 agar tidak ada byte ilegal.
        $decoded = base64_decode($payload, true);
        if ($decoded === false || $decoded === '') {
            Log::warning('Canteen: photo_face ditolak karena base64 tidak valid.');
            return null;
        }

        // Pastikan benar-benar gambar (magic bytes JPEG / PNG).
        $isJpeg = strncmp($decoded, "\xFF\xD8\xFF", 3) === 0;
        $isPng  = strncmp($decoded, "\x89PNG\r\n\x1A\n", 8) === 0;
        if (!$isJpeg && !$isPng) {
            Log::warning('Canteen: photo_face ditolak karena bukan JPEG/PNG yang valid.');
            return null;
        }

        return $payload;
    }

    /**
     * Pastikan foto wajah memiliki prefix data URI (data:image/jpeg;base64,...)
     * agar browser tidak memperlakukannya sebagai relative URL path yang memicu
     * HTTP 414 (Request-URI Too Long).
     */
    public static function formatPhotoFaceForResponse(?string $photo): ?string
    {
        if (!$photo) {
            return null;
        }
        $photo = trim($photo);
        if ($photo === '') {
            return null;
        }
        if (str_starts_with($photo, 'data:image/')) {
            return $photo;
        }
        return 'data:image/jpeg;base64,' . $photo;
    }

    /**
     * PERBAIKAN V41 (2026-11-10) — BANGKITKAN `id_ticket` TRANSAKSI KANTIN.
     *
     * `id_ticket` adalah identitas unik untuk satu transaksi scan kantin.
     * Formatnya PERSIS meniru `att_id` di modul Face Attendance supaya
     * keduanya konsisten dan mudah dibaca:
     *
     *     <unix_detik>_CTN_<6 hex acak>
     *     Contoh: 1762060800_CTN_7F3A2B
     *
     * Sifat:
     *   - Mengandung unix timestamp -> urut leksikografis = urut waktu.
     *   - Suffix acak 6 hex -> tetap unik walau ada 2 transaksi pada
     *     detik yang sama.
     *   - Panjang <= 50 char -> aman untuk NVARCHAR(50).
     *
     * Dibangkitkan di server, BUKAN di browser, supaya user tidak bisa
     * menebak atau memalsukan tiket.
     *
     * @param string $npk  NPK karyawan (dipakai sebagai bahan hash supaya
     *                     transaksi berbeda orang pada detik sama tetap beda).
     */
    private function buildTicketId(string $npk): string
    {
        $unix = now()->getTimestamp();
        $suffix = strtoupper(substr(hash('sha256', $unix . '|' . $npk . '|' . uniqid('', true)), 0, 6));

        return $unix . '_CTN_' . $suffix;
    }

    private function checkGeomap(float $lat, float $lng): array
    {
        $allowedIds = CanteenSetting::allowedLocationIds();
        if (empty($allowedIds)) {
            // Jika admin belum memilih geomap, tidak ada batasan (fail-open untuk geomap).
            // Bila require_geomap=true tapi daftar kosong, dianggap gagal agar tidak lolos diam-diam.
            return ['inside' => false, 'location_name' => null, 'allowed_names' => 'area kantin (belum dikonfigurasi admin)'];
        }

        $locations = AttendanceWorkLocation::whereIn('id', $allowedIds)->where('is_active', true)->get();
        foreach ($locations as $loc) {
            if ($this->pointInPolygon($lat, $lng, $loc->coordinates)) {
                return ['inside' => true, 'location_name' => $loc->name, 'allowed_names' => $loc->name];
            }
        }

        $names = $locations->pluck('name')->implode(', ');
        return ['inside' => false, 'location_name' => null, 'allowed_names' => $names ?: 'area kantin'];
    }

    private function pointInPolygon($lat, $lng, $polygon): bool
    {
        if (empty($polygon) || !is_array($polygon)) {
            return false;
        }

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

    // -----------------------------------------------------------------
    // Admin: master kantin + window + setting
    // -----------------------------------------------------------------

    public function adminIndex()
    {
        $canteens  = $this->safeQuery(fn () => Canteen::orderBy('canteen_id')->get(), 'canteen');
        $windows   = $this->safeQuery(fn () => CanteenBreakWindow::query()->orderBy('id')->get(), 'break_masters');
        $locations = $this->safeQuery(fn () => AttendanceWorkLocation::where('is_active', true)->orderBy('name')->get(), 'attendance_work_locations');
        try {
            $setting = CanteenSetting::current();
        } catch (\Throwable $e) {
            Log::warning('Canteen adminIndex: CanteenSetting::current() gagal: ' . $e->getMessage());
            $setting = new CanteenSetting();
        }
        // PERBAIKAN V32 (2026-11-09): mode canteen dibaca dari
        // face_recognition_settings (sumber tunggal). Admin kantin hanya
        // menampilkan ringkasan mode; pengaturan mode dilakukan dari
        // /face-attendance/settings.
        $canteenVerificationMode = FaceRecognitionSetting::canteenVerificationMode();

        return view('canteen_transaction.admin', compact('canteens', 'windows', 'setting', 'locations', 'canteenVerificationMode'));
    }

    public function adminStoreWindow(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'time_start' => 'required|date_format:H:i',
            'time_end' => 'required|date_format:H:i|after:time_start',
        ]);

        // Break window diambil dari tabel break_masters (existing), bukan tabel canteen_break_windows.
        \App\Models\BreakMaster::create([
            'sesi' => $request->input('name'),
            'time_start' => $request->input('time_start'),
            'time_end' => $request->input('time_end'),
        ]);

        return back()->with('success', 'Window istirahat ditambahkan ke break_masters.');
    }

    public function adminUpdateWindow(Request $request, $id)
    {
        $w = \App\Models\BreakMaster::findOrFail($id);
        $request->validate([
            'name' => 'required|string|max:100',
            'time_start' => 'required|date_format:H:i',
            'time_end' => 'required|date_format:H:i|after:time_start',
        ]);

        $w->update([
            'sesi' => $request->input('name'),
            'time_start' => $request->input('time_start'),
            'time_end' => $request->input('time_end'),
        ]);

        return back()->with('success', 'Window istirahat diperbarui di break_masters.');
    }

    public function adminDeleteWindow($id)
    {
        \App\Models\BreakMaster::findOrFail($id)->delete();
        return back()->with('success', 'Window istirahat dihapus dari break_masters.');
    }

    public function adminUpdateSetting(Request $request)
    {
        $setting = CanteenSetting::current();
        $allowedIds = $request->input('allowed_work_location_ids', []);
        if (!is_array($allowedIds)) $allowedIds = [$allowedIds];

        // PERBAIKAN V32 (2026-11-09): mode canteen (liveness ATAU random
        // challenge) TIDAK lagi disimpan di canteen_settings. Kolom
        // liveness_canteen_enabled / random_challenge_canteen_enabled di
        // canteen_settings sudah deprecated dan akan dihapus lewat
        // migration. Mode dikelola dari /face-attendance/settings
        // (face_recognition_settings.canteen_verification_mode).
        //
        // Method ini sekarang hanya menyimpan fitur aktif + geomap.
        $setting->update([
            'is_enabled' => $request->boolean('is_enabled', true),
            'require_geomap' => $request->boolean('require_geomap', false),
            'allowed_work_location_ids' => array_values(array_filter(array_map('intval', $allowedIds))),
        ]);

        return back()->with('success', 'Pengaturan transaksi kantin disimpan.');
    }
}
