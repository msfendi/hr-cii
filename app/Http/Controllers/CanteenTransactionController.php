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

class CanteenTransactionController extends Controller
{
    /**
     * Halaman utama transaksi kantin (public, tidak butuh auth — seperti face-attendance/login).
     * Flow: pilih kantin (canteen_id 1/2) -> input NPK -> scan wajah -> receipt invoice-style.
     */
    public function index()
    {
        $setting = CanteenSetting::current();
        $canteens = Canteen::where('is_active', true)->orderBy('canteen_id')->get();
        $windows = CanteenBreakWindow::active()->orderBy('sort_order')->get();
        $locations = AttendanceWorkLocation::where('is_active', true)->orderBy('name')->get();

        $livenessEnabled = CanteenSetting::isLivenessEnabled();
        $npkRequired = FaceRecognitionSetting::isNpkRequiredEnabled();
        $randomChallengeEnabled = (bool) ($setting->random_challenge_canteen_enabled ?? false);
        $randomChallenge = $randomChallengeEnabled
            ? FaceRecognitionService::issueRandomChallenge('canteen-' . session()->getId())
            : null;

        $currentWindow = CanteenBreakWindow::currentWindow();
        $now = now()->format('H:i d M Y');
        $insideWindow = $currentWindow !== null;

        return view('canteen_transaction.index', compact(
            'canteens', 'windows', 'locations', 'livenessEnabled', 'npkRequired',
            'randomChallengeEnabled', 'randomChallenge', 'currentWindow', 'insideWindow', 'now',
            'setting'
        ));
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
        return response()->json($check, $check['status'] ?? 200);
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
            $dept = $biodata->ID_DEPT ?? $biodata->BAG ?? null;

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

            // Idempotency: cegah double scan di window yang sama (5 menit terakhir)
            $today = now()->format('Y-m-d');
            $dup = $this->findDuplicate($canteenId, $npk, $today, $window->name);
            if ($dup) {
                return response()->json([
                    'success' => false,
                    'duplicate' => true,
                    'message' => "Anda sudah scan di {$window->name} hari ini (" . ($dup->created_at ?? '') . "). Jika perlu scan lagi, hubungi admin.",
                ], 409);
            }

            // Simpan ke database 'canteen' — tabel tergantung canteen_id.
            $record = $this->saveToCanteenDb($canteenId, [
                'npk' => $npk,
                'name' => $name,
                'dept' => $dept,
                'canteen_no' => (string) $canteenId,
                'date' => $today,
                'window_name' => $window->name,
                'location_name' => $locationName,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);

            // Receipt/invoice: dikembalikan sebagai JSON, dirender di layar seperti bukti bayar QRIS.
            $invoice = [
                'no' => 'CTN-' . $canteenId . '-' . now()->format('Ymd-His') . '-' . strtoupper(substr(md5($npk . now()->timestamp), 0, 6)),
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

    private function findDuplicate(int $canteenId, string $npk, string $date, string $windowName)
    {
        $modelClass = $canteenId === 2 ? \App\Models\CanteenTwoReport::class : \App\Models\CanteenReport::class;
        try {
            return $modelClass::query()
                ->where('npk', $npk)
                ->where('date', $date)
                ->where('window_name', $windowName)
                ->orderByDesc('created_at')
                ->first();
        } catch (\Throwable $e) {
            Log::warning('Canteen duplicate check gagal: ' . $e->getMessage());
            return null;
        }
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
        $canteens = Canteen::orderBy('canteen_id')->get();
        $windows = CanteenBreakWindow::orderBy('sort_order')->get();
        $setting = CanteenSetting::current();
        $locations = AttendanceWorkLocation::where('is_active', true)->orderBy('name')->get();

        return view('canteen_transaction.admin', compact('canteens', 'windows', 'setting', 'locations'));
    }

    public function adminStoreWindow(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'time_start' => 'required|date_format:H:i',
            'time_end' => 'required|date_format:H:i|after:time_start',
        ]);

        // Aturan Fitur 1: total window istirahat maksimal 3.
        if (CanteenBreakWindow::query()->count() >= 3) {
            return back()->withErrors(['name' => 'Sudah 3 window — hapus salah satu sebelum menambah baru.'])
                ->withInput();
        }

        CanteenBreakWindow::create([
            'name' => $request->input('name'),
            'time_start' => $request->input('time_start'),
            'time_end' => $request->input('time_end'),
            'sort_order' => $request->input('sort_order', 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Window istirahat ditambahkan.');
    }

    public function adminUpdateWindow(Request $request, $id)
    {
        $w = CanteenBreakWindow::findOrFail($id);
        $request->validate([
            'name' => 'required|string|max:100',
            'time_start' => 'required|date_format:H:i',
            'time_end' => 'required|date_format:H:i|after:time_start',
        ]);

        $w->update([
            'name' => $request->input('name'),
            'time_start' => $request->input('time_start'),
            'time_end' => $request->input('time_end'),
            'sort_order' => $request->input('sort_order', $w->sort_order),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Window istirahat diperbarui.');
    }

    public function adminDeleteWindow($id)
    {
        CanteenBreakWindow::findOrFail($id)->delete();
        return back()->with('success', 'Window istirahat dihapus.');
    }

    public function adminUpdateSetting(Request $request)
    {
        $setting = CanteenSetting::current();
        $allowedIds = $request->input('allowed_work_location_ids', []);
        if (!is_array($allowedIds)) $allowedIds = [$allowedIds];

        $setting->update([
            'is_enabled' => $request->boolean('is_enabled', true),
            'liveness_canteen_enabled' => $request->boolean('liveness_canteen_enabled', false),
            'is_random_challenge_enabled' => $request->boolean('is_random_challenge_enabled', false),
            'random_challenge_canteen_enabled' => $request->boolean('random_challenge_canteen_enabled', false),
            'require_geomap' => $request->boolean('require_geomap', false),
            'allowed_work_location_ids' => array_values(array_filter(array_map('intval', $allowedIds))),
        ]);

        return back()->with('success', 'Pengaturan transaksi kantin disimpan.');
    }
}
