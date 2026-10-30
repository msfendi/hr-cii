<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Models\AttendanceWorkLocation;
use App\Models\EmployeeFace;
use App\Services\FaceRecognitionService;
use Carbon\Carbon;

class FaceAttendanceController extends Controller
{
    /**
     * Halaman Login Presensi Wajah
     */
    public function login()
    {
        $locations = AttendanceWorkLocation::where('is_active', 1)->get();
        return view('face_attendance.login', compact('locations'));
    }

    /**
     * Auth Login dengan Scan Wajah (alias untuk route login-auth)
     */
    public function faceLoginAuthenticate(Request $request)
    {
        return $this->loginAuth($request);
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

        // Hitung window absensi:
        // Clock In: [work_start - 15 menit] sampai [work_start]
        // Clock Out: [work_end] sampai [work_end + 15 menit]
        $startCarbon = Carbon::parse($today . ' ' . $workStart);
        $endCarbon = Carbon::parse($today . ' ' . $workEnd);
        if ($endCarbon->lessThanOrEqualTo($startCarbon)) {
            $endCarbon->addDay();
        }
        $clockInStart = $startCarbon->copy()->subMinutes(15);
        $clockInEnd = $startCarbon->copy();

        $clockOutStart = $endCarbon->copy();
        $clockOutEnd = $endCarbon->copy()->addMinutes(15);

        // Ambil log presensi hari ini
        $todayLogsRaw = DB::table('att_log')
            ->where('pin', $barcode)
            ->whereDate('scan_date', $today)
            ->orderBy('scan_date', 'asc')
            ->get();

        $todayLogs = [];
        $hasClockIn = false;
        $hasClockOut = false;
        $clockInTime = null;
        $clockOutTime = null;

        $midpoint = $startCarbon->copy()->addSeconds($endCarbon->diffInSeconds($startCarbon) / 2);

        foreach ($todayLogsRaw as $log) {
            $scanCarbon = Carbon::parse($log->scan_date);
            $type = $scanCarbon->lessThan($midpoint) ? 'in' : 'out';

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

        // Tentukan status presensi & aksi selanjutnya
        $now = Carbon::now();
        if (!$hasClockIn) {
            $nextAction = 'clock_in';
            $statusText = 'Belum Clock In Hari Ini';
        } elseif (!$hasClockOut) {
            $nextAction = 'clock_out';
            $statusText = 'Sudah Clock In (' . $clockInTime . '), Menunggu Clock Out';
        } else {
            $nextAction = 'completed';
            $statusText = 'Presensi Hari Ini Lengkap (In: ' . $clockInTime . ', Out: ' . $clockOutTime . ')';
        }

        // Ambil area lokasi kerja aktif
        $locations = AttendanceWorkLocation::active()->get();

        return view('face_attendance.index', compact(
            'biodata',
            'shift',
            'workStart',
            'workEnd',
            'clockInStart',
            'clockInEnd',
            'clockOutStart',
            'clockOutEnd',
            'hasClockIn',
            'hasClockOut',
            'clockInTime',
            'clockOutTime',
            'nextAction',
            'statusText',
            'locations',
            'todayLogs'
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
        $activeLocations = AttendanceWorkLocation::active()->get();
        $isInsideGeo = false;

        if ($activeLocations->count() > 0) {
            foreach ($activeLocations as $loc) {
                if ($this->pointInPolygon($lat, $lng, $loc->coordinates)) {
                    $isInsideGeo = true;
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

        $biodata = DB::table('BIODATA')->where('NPK', (string)$npk)->first();
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
        $shiftAssign = DB::table('employee_shifts as es')
            ->join('shifts as s', 'es.shift_id', '=', 's.id')
            ->where('es.npk', (string)$npk)
            ->whereDate('es.shift_date', $today)
            ->select('s.name as shift_name', 's.work_start', 's.work_end')
            ->first();

        $workStart = $shiftAssign ? $shiftAssign->work_start : '08:00:00';
        $workEnd = $shiftAssign ? $shiftAssign->work_end : '17:00:00';

        $startCarbon = Carbon::parse($today . ' ' . $workStart);
        $endCarbon = Carbon::parse($today . ' ' . $workEnd);
        if ($endCarbon->lessThanOrEqualTo($startCarbon)) {
            $endCarbon->addDay();
        }

        // Window:
        // Clock In: [work_start - 15m] s/d [work_start]
        // Clock Out: [work_end] s/d [work_end + 15m]
        $clockInStart = $startCarbon->copy()->subMinutes(15);
        $clockInEnd = $startCarbon->copy();

        $clockOutStart = $endCarbon->copy();
        $clockOutEnd = $endCarbon->copy()->addMinutes(15);

        // Ambil log hari ini untuk cek apakah sudah clock in
        $midpoint = $startCarbon->copy()->addSeconds($endCarbon->diffInSeconds($startCarbon) / 2);
        $existingLogs = DB::table('att_log')
            ->where('pin', $barcode)
            ->whereDate('scan_date', $today)
            ->get();

        $hasClockIn = false;
        $hasClockOut = false;
        foreach ($existingLogs as $el) {
            $elCarbon = Carbon::parse($el->scan_date);
            if ($elCarbon->lessThan($midpoint)) {
                $hasClockIn = true;
            } else {
                $hasClockOut = true;
            }
        }

        if (!$hasClockIn) {
            // Target: Clock In
            if ($now->lessThan($clockInStart)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Belum memasuki waktu Clock In. Window Clock In: ' . $clockInStart->format('H:i') . ' - ' . $clockInEnd->format('H:i') . ' WIB.'
                ], 422);
            }
            if ($now->greaterThan($clockInEnd)) {
                // Di luar window normal clock in
                return response()->json([
                    'success' => false,
                    'message' => 'Waktu Clock In telah berakhir (' . $clockInEnd->format('H:i') . ' WIB). Silakan hubungi HRD.'
                ], 422);
            }
            $inoutmode = 0;
            $actionName = 'Clock In (Masuk)';
        } elseif (!$hasClockOut) {
            // Target: Clock Out
            if ($now->lessThan($clockOutStart)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Belum memasuki waktu Clock Out. Window Clock Out: ' . $clockOutStart->format('H:i') . ' - ' . $clockOutEnd->format('H:i') . ' WIB.'
                ], 422);
            }
            if ($now->greaterThan($clockOutEnd)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Waktu Clock Out telah berakhir (' . $clockOutEnd->format('H:i') . ' WIB). Silakan hubungi HRD.'
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
        $inserted = DB::table('att_log')->insert([
            'sn' => 'FACE_GPS_WEB',
            'scan_date' => $now->format('Y-m-d H:i:s'),
            'pin' => $barcode,
            'verifymode' => 15, // 15 = Face Recognition
            'inoutmode' => $inoutmode,
            'reserved' => null,
            'work_code' => 0,
            'att_id' => null
        ]);

        if ($inserted) {
            return response()->json([
                'success' => true,
                'message' => 'Presensi ' . $actionName . ' berhasil dicatat!',
                'action_type' => $actionName,
                'scan_time' => $now->format('H:i:s')
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Gagal menyimpan log presensi ke database.'
        ], 500);
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
        $monthlyLogs = DB::table('att_log')
            ->where('pin', $barcode)
            ->whereBetween('scan_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->orderBy('scan_date', 'asc')
            ->get();

        // GROUP PER TANGGAL (YYYY-MM-DD) -> EXACTLY 1 ROW PER TANGGAL
        $groupedLogs = [];
        foreach ($monthlyLogs as $log) {
            $dateKey = Carbon::parse($log->scan_date)->format('Y-m-d');
            $time = Carbon::parse($log->scan_date)->format('H:i:s');
            
            if (!isset($groupedLogs[$dateKey])) {
                $groupedLogs[$dateKey] = [
                    'date' => $dateKey,
                    'formatted_date' => Carbon::parse($dateKey)->format('d-m-Y'),
                    'day_name' => Carbon::parse($dateKey)->translatedFormat('l'),
                    'clock_in' => null,
                    'clock_out' => null,
                    'modes' => [],
                    'total_scans' => 0
                ];
            }

            $groupedLogs[$dateKey]['total_scans']++;
            
            $modeName = ($log->verifymode == 15) ? 'Wajah' : 'Fingerprint';
            if (!in_array($modeName, $groupedLogs[$dateKey]['modes'])) {
                $groupedLogs[$dateKey]['modes'][] = $modeName;
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

        // Urutkan tanggal dari terbaru ke terlama
        krsort($groupedLogs);

        return view('face_attendance.rekap', compact('biodata', 'groupedLogs', 'month'));
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

            // Cek apakah kecocokan valid (threshold 0.600 untuk login presensi yang lebih fleksibel)
            $face = $result['face'] ?? null;
            $distance = $result['distance'] ?? null;

            if (!$face || $distance === null || $distance > 0.600) {
                return response()->json([
                    'success' => false,
                    'message' => 'Wajah tidak dikenali atau belum terdaftar di tabel employee_faces.'
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

            return response()->json([
                'success' => true,
                'message' => 'Selamat datang, ' . $name . '!',
                'redirect_url' => route('face.attendance.index')
            ]);
        } catch (\Throwable $e) {
            \Log::error('Face attendance loginAuth error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ], 500);
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
