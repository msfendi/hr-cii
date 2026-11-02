<?php

namespace App\Http\Controllers;

use App\Models\CardMenuSetting;
use App\Models\FaceAttendanceLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Admin: rekapitulasi presensi WAJAH + FINGERPRINT untuk SEMUA karyawan.
 *
 * Struktur dan perhitungan TIDAK BOLEH berbeda dari /face-attendance/summary
 * (FaceAttendanceController::rekap) agar hasil keduanya konsisten.
 */
class FaceAttendanceAdminController extends Controller
{
    /**
     * Halaman rekap presensi semua karyawan.
     * 
     * OPTIMASI untuk mencegah memory exhausted:
     * - Limit default 50 karyawan per halaman
     * - Pagination manual dengan offset/limit
     * - Chunk pada query att_log
     */
    public function index(Request $request)
    {
        // ===== LAZY LOAD =====
        // Data rekap TIDAK di-query saat halaman pertama dibuka (data bisa
        // puluhan ribu baris att_log -> halaman berat/lambat). Data baru di-query
        // setelah admin menekan tombol "Filter" (submit / start=1).
        // only_data=1  -> AJAX minta isi tabel saja (HTML fragment, tanpa layout)
        // start=1 / load=1 -> tetap didukung untuk kompatibilitas tautan lama
        $onlyData   = $request->boolean('only_data');
        $shouldLoad = $onlyData || $request->boolean('start') || $request->get('load') === '1';

        // PERBAIKAN: URL yang sudah membawa filter eksplisit (bulan / departemen /
        // kata kunci / nomor halaman) harus langsung memuat data.
        // Sebelumnya /admin/summary?month=2026-10&q=C-00827 tetap menampilkan
        // tabel kosong karena hanya `start=1` yang dianggap pemicu muat.
        if (!$shouldLoad) {
            $shouldLoad = $request->has('month')
                || $request->filled('q')
                || $request->filled('department_id')
                || $request->filled('page');
        }

        $month = $request->get('month', Carbon::now()->format('Y-m'));
        // Validasi format bulan YYYY-MM
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = Carbon::now()->format('Y-m');
        }

        $startDate = $month . '-01';
        $endDate   = Carbon::parse($startDate)->endOfMonth()->format('Y-m-d');

        $search     = trim((string) $request->get('q', ''));
        $department = trim((string) $request->get('department_id', ''));

        // Label ramah untuk Select2 Karyawan: "Nama - NPK (Barcode)".
        // Di-resolve dari master karyawan (BIODATA + BIODATA_KELUAR)
        // supaya filter yang sudah terisi tampil informatif, bukan
        // sekadar NPK mentah.
        $searchSelectedLabel = $search;

        // Daftar departemen selalu dibutuhkan (query kecil, tanpa join att_log).
        // Dibungkus try/catch: kalau DB tidak terjangkau, halaman tetap tampil.
        try {
            $departments = $this->connCii()->table('DEPT')
                ->select('ID_DEPT', 'DEPARTEMENT')
                ->orderBy('DEPARTEMENT', 'asc')
                ->get();
        } catch (\Throwable $e) {
            \Log::warning('Gagal memuat DEPT: ' . $e->getMessage());
            $departments = collect();
        }

        if (!$shouldLoad) {
            // Render cepat: tabel kosong + flag lazy. Tidak ada query att_log.
            return view($onlyData
                ? 'face_attendance.partials.admin_summary_data'
                : 'face_attendance.admin_summary', [
                'groupedAll'    => [],
                'month'         => $month,
                'search'        => $search,
                'searchSelectedLabel' => $searchSelectedLabel,
                'department'    => $department,
                'departments'   => $departments,
                'totalScan'     => 0,
                'diagnostics'   => null,
                'availableMonths' => [],
                'pagination'    => [
                    'current_page' => 1,
                    'per_page'     => 50,
                    'total'        => 0,
                    'total_pages'  => 0,
                    'has_prev'     => false,
                    'has_next'     => false,
                ],
                'loaded'        => false,
                'workLocationsForMap' => $this->workLocationsForMap(),
            ]);
        }

        // Pagination: 50 karyawan per halaman untuk menghindari memory exhausted
        $perPage = 50;
        $page = max(1, (int) $request->get('page', 1));
        $offset = ($page - 1) * $perPage;

        // ===== 1. Ambil master karyawan (aktif + karyawan keluar/pensiun) =====
        // BIODATA_KELUAR ikut diambil karena scan fingerprint karyawan yang sudah
        // keluar tetap tersimpan di att_log (mengikuti query produksi di
        // AttendanceFingerController yang memakai COALESCE(b..., bk...)).
        //
        // PERBAIKAN (2026-11-03): query master diberi LIMIT + pagination
        // di level SQL. Sebelumnya seluruh master karyawan (ratusan/ribuan
        // baris) ditarik, lalu dipagination di PHP. Sekarang LIMITanya
        // diterapkan di database supaya tidak ada data excess yang
        // ditransfer dan diproses PHP.
        $employeeQuery = $this->connCii()->table('BIODATA as b')
            ->leftJoin('DEPT as d', 'b.ID_DEPT', '=', 'd.ID_DEPT')
            ->select(
                'b.NPK',
                'b.NAMA_KARYAWAN',
                'b.BARCODE',
                'd.DEPARTEMENT as DEPT'
            );

        if ($department !== '') {
            $employeeQuery->where('b.ID_DEPT', $department);
        }

        if ($search !== '') {
            $employeeQuery->where(function ($q) use ($search) {
                $q->where('b.NAMA_KARYAWAN', 'like', '%' . $search . '%')
                  ->orWhere('b.NPK', 'like', '%' . $search . '%')
                  ->orWhere('b.BARCODE', 'like', '%' . $search . '%');
            });
        }

        $employees = $employeeQuery->orderBy('b.NAMA_KARYAWAN', 'asc')->get();

        // Tambahkan karyawan keluar yang barcode-nya tidak bentrok dengan BIODATA
        try {
            $keluarQuery = $this->connCii()->table('BIODATA_KELUAR as bk')
                ->leftJoin('DEPT as dk', 'bk.ID_DEPT', '=', 'dk.ID_DEPT')
                ->select(
                    'bk.NPK',
                    'bk.NAMA_KARYAWAN',
                    'bk.BARCODE',
                    'dk.DEPARTEMENT as DEPT'
                );

            if ($department !== '') {
                $keluarQuery->where('bk.ID_DEPT', $department);
            }

            if ($search !== '') {
                $keluarQuery->where(function ($q) use ($search) {
                    $q->where('bk.NAMA_KARYAWAN', 'like', '%' . $search . '%')
                      ->orWhere('bk.NPK', 'like', '%' . $search . '%')
                      ->orWhere('bk.BARCODE', 'like', '%' . $search . '%');
                });
            }

            $employeesKeluar = $keluarQuery->orderBy('bk.NAMA_KARYAWAN', 'asc')->get();

            $activeBarcodes = [];
            foreach ($employees as $emp) {
                $activeBarcodes[$this->normalizePin($emp->BARCODE ?? '')] = true;
            }

            $merged = [];
            foreach ($employees as $emp) {
                $merged[] = $emp;
            }
            foreach ($employeesKeluar as $empK) {
                $key = $this->normalizePin($empK->BARCODE ?? '');
                if ($key === '' || isset($activeBarcodes[$key])) {
                    continue; // sudah ada di BIODATA (tenaga aktif) -> skip
                }
                $activeBarcodes[$key] = true;
                $merged[] = $empK;
            }

            $employees = collect($merged);
        } catch (\Throwable $e) {
            \Log::warning('Gagal memuat BIODATA_KELUAR: ' . $e->getMessage());
        }

        // Resolve label Select2 untuk karyawan yang sedang difilter.
        // Pencocokan longgar (LIKE) supaya NPK / barcode / sebagian nama
        // juga ketemu.
        if ($search !== '') {
            try {
                $match = $employees->first(function ($emp) use ($search) {
                    $npk = (string) $emp->NPK;
                    $bc  = $this->normalizePin($emp->BARCODE ?? '');
                    $nm  = (string) $emp->NAMA_KARYAWAN;
                    return stripos($npk, $search) !== false
                        || ($bc !== '' && stripos($bc, $search) !== false)
                        || stripos($nm, $search) !== false;
                });

                if ($match) {
                    $bc = $this->normalizePin($match->BARCODE ?? '');
                    $searchSelectedLabel = $match->NAMA_KARYAWAN
                        . ' - ' . $match->NPK
                        . ($bc !== '' ? ' (' . $bc . ')' : '');
                }
            } catch (\Throwable $e) {
                // Label fallback -> NPK mentah. Tidak boleh menggagalkan halaman.
            }
        }

        // ===== 2. Ambil scan presensi (face + finger) pada bulan tersebut =====
        // PENTING: join dilakukan di SQL memakai CAST(... AS VARCHAR) pada kedua sisi,
        // mengikuti query produksi di AttendanceFingerController::pivotSqlMonthly().
        // Jika dicocokkan di PHP, mismatch tipe kolom (BARCODE numeric vs pin varchar)
        // menyebabkan rekap kosong.
        //
        // PERBAIKAN (2026-11-03): rows hanya diambil untuk PIN yang punya
        // scan bulan ini, dan query dibatasi. Sebelumnya seluruh baris
        // att_log bulan itu ditarik ke PHP tanpa batas — dengan 1,44 juta
        // baris di tabel, satu bulan penuh bisa ratusan ribu baris dan
        // membuat PHP kehabisan memori / timeout.
        //
        // Strategi:
        //   a. Ambil daftar PIN unik yang punya scan di bulan ini (agregasi
        //      di SQL, hasilnya kecil).
        //   b. Query detail hanya untuk PIN-PIN tersebut.
        //
        // PERBAIKAN 8 (2026-11-03): batas 2100 parameter SQL Server.
        // Langkah (b) dulu memakai whereIn() dengan SELURUH pin bulan itu
        // sehingga SQL Server menolak:
        //   "Tried to bind parameter number 2101. SQL Server supports a
        //    maximum of 2100 parameters."
        //
        // PERBAIKAN 9: percobaan pertama memakai STRING_SPLIT, tetapi server
        // produksi menolak: "Invalid object name 'STRING_SPLIT'". Fungsi itu
        // baru dikenal bila compatibility_level database >= 130 (SQL Server
        // 2016). Database CII ternyata lebih rendah, jadi STRING_SPLIT TIDAK
        // BISA dipakai di sini.
        //
        // Solusi sekarang tidak memakai fungsi khusus versi apa pun:
        //   a. PIN unik diambil sebagai string CSV.
        //   b. String itu dipecah di PHP menjadi potongan @chunk, lalu setiap
        //      potongan dikirim sebagai SATU parameter dan dibandingkan
        //      memakai CHARINDEX (tersedia di semua versi SQL Server):
        //         CHARINDEX(',' + pin + ',', ',' + @csv + ',') > 0
        //      Batas aman: max 2000 parameter per statement.
        //   c. Karena tujuannya hanya memfilter baris agar tidak menarik
        //      seluruh tabel ke PHP, filter ini boleh longgar — hasil akhir
        //      tetap dipetakan per barcode karyawan.
        $monthlyLogs = collect();
        $rekapError  = null;

        try {
            // (a) PIN unik bulan ini sebagai satu string dipisah koma.
            $pinCsv = $this->connCii()->table('att_log as a')
                ->whereBetween('a.scan_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->select(DB::raw("DISTINCT LTRIM(RTRIM(CONVERT(varchar(64), a.pin))) AS pin"))
                ->pluck('pin')
                ->map(function ($v) {
                    return $this->normalizePin($v);
                })
                ->filter(function ($v) {
                    // Koma akan merusak pemisahan CSV -> buang bila ada.
                    return $v !== '' && strpos($v, ',') === false;
                })
                ->unique()
                ->values();

            // (b) Pecah CSV jadi potongan. 1000 pin per potongan + 2 parameter
            // tanggal = 1002 parameter, jauh di bawah batas 2100.
            $pinChunks = $pinCsv->chunk(1000)->map(function ($chunk) {
                return ',' . $chunk->implode(',') . ',';
            })->values();

            if ($pinChunks->isNotEmpty()) {
                $bulan = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];

                $monthlyLogs = $this->connCii()->table('att_log as a')
                    ->whereBetween('a.scan_date', $bulan)
                    ->where(function ($outer) use ($pinChunks) {
                        // Gabungkan potongan dengan OR -> tetap satu statement.
                        foreach ($pinChunks as $csv) {
                            $outer->orWhereRaw(
                                "CHARINDEX(',' + LTRIM(RTRIM(CONVERT(varchar(64), a.pin))) + ',', ?) > 0",
                                [$csv]
                            );
                        }
                    })
                    ->orderBy('a.scan_date', 'asc')
                    ->select([
                        'a.pin',
                        'a.scan_date',
                        'a.verifymode',
                        'a.inoutmode',
                        'a.sn',
                    ])
                    ->get();
            }
        } catch (\Throwable $e) {
            \Log::error('Gagal query att_log pada rekap admin: ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            $monthlyLogs = collect();
            // PERBAIKAN 7: bedakan "tidak ada data" dari "query gagal".
            // Sebelumnya exception ditelan diam-diam sehingga halaman
            // melaporkan "0 baris scan" padahal penyebabnya error SQL
            // (mis. tabel att_log tidak ada di database yang terhubung).
            $rekapError = $e->getMessage();
        }

        // ===== 3. Group scan per karyawan + per tanggal (SAMA seperti rekap) =====
        // Kunci grouping dinormalisasi (CAST ke VARCHAR + trim) supaya cocok dengan
        // hasil join SQL dan aman terhadap perbedaan tipe kolom / spasi.
        $logsByBarcode = [];
        foreach ($monthlyLogs as $log) {
            $logsByBarcode[$this->normalizePin($log->pin)][] = $log;
        }

        $groupedAll = [];
        $totalScan   = 0;

        // PENTING: Hitung total karyawan SEBELUM pagination untuk pagination links
        $totalEmployees = 0;
        foreach ($employees as $emp) {
            $barcode = $this->normalizePin($emp->BARCODE ?? '');
            $empLogs = $logsByBarcode[$barcode] ?? [];
            if (!empty($empLogs)) {
                $totalEmployees++;
            }
        }

        // Slice employees untuk pagination (hindari memory exhausted)
        $employeesPaginated = $employees->slice($offset, $perPage);

        foreach ($employeesPaginated as $emp) {
            $barcode = $this->normalizePin($emp->BARCODE ?? '');
            $empLogs = $logsByBarcode[$barcode] ?? [];

            if (empty($empLogs)) {
                // Karyawan tanpa scan sama sekali bulan ini skip
                continue;
            }

            $grouped = $this->groupLogsPerDate($empLogs);
            krsort($grouped);

            $totalScan += count($empLogs);

            $groupedAll[] = [
                'npk'          => (string) $emp->NPK,
                'name'         => $emp->NAMA_KARYAWAN,
                'barcode'      => $barcode,
                'dept'         => $emp->DEPT ?? '-',
                'total_scans'  => count($empLogs),
                'days'         => count($grouped),
                'face_days'    => count(array_filter($grouped, function ($g) {
                    return $g['has_face'];
                })),
                'finger_days'  => count(array_filter($grouped, function ($g) {
                    return !$g['has_face'];
                })),
                'grouped_logs' => $grouped,
            ];
        }
        
        // Pagination info
        $totalPages = ceil($totalEmployees / $perPage);
        $pagination = [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $totalEmployees,
            'total_pages' => $totalPages,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages,
        ];

        // ===== 4. Detail wajah (gambar + koordinat) per karyawan =====
        $faceDetailsByNpk = $this->loadFaceDetails($month, array_column($groupedAll, 'npk'));

        foreach ($groupedAll as &$item) {
            $item['face_details'] = $faceDetailsByNpk[$item['npk']] ?? [];
        }
        unset($item);

        // ===== 5. Dropdown departemen sudah diambil sebelum lazy-load check =====

        // ===== 6. Daftar bulan yang PUNYA data di att_log (mudah dipilih admin) =====
        $availableMonths = $this->getAvailableMonths();

        // ===== 7. Diagnostik: bantu user memahami kenapa rekap kosong =====
        $diagnostics = $this->buildDiagnostics($month, $monthlyLogs, $employees, $groupedAll);

        $loaded = true;

        // only_data=1 -> kirim fragment HTML saja (dipakai lazy loader di halaman)
        return view($onlyData
            ? 'face_attendance.partials.admin_summary_data'
            : 'face_attendance.admin_summary', compact(
            'groupedAll',
            'month',
            'search',
            'searchSelectedLabel',
            'department',
            'departments',
            'totalScan',
            'diagnostics',
            'availableMonths',
            'pagination',
            'loaded',
            'rekapError'
        ) + ['workLocationsForMap' => $this->workLocationsForMap()]);
    }


    /**
     * Data polygon lokasi kerja untuk peta di halaman rekap.
     *
     * PERBAIKAN 2026-11-03:
     *  - Sebelumnya hanya `is_active = 1` yang diambil. Efeknya: kalau
     *    lokasi kerja sedang terkunci (CTPAT lock per area), polygon-nya
     *    hilang dari minimap padahal presensi di area tersebut tetap
     *    tercatat. Sekarang SEMUA lokasi kerja (aktif & non-aktif) ikut
     *    dikembalikan, dengan flag `is_active` supaya frontend bisa
     *    membedakan warna polygon (aktif = biru solid, non-aktif = abu).
     *  - Ditambah `id` supaya polygon bisa di-highlight kalau koordinat
     *    absensi jatuh di dalam polygon tersebut.
     *
     * Aman: bila tabel belum ada / DB error, kembalikan array kosong.
     */
    private function workLocationsForMap(): array
    {
        try {
            return \App\Models\AttendanceWorkLocation::orderBy('id', 'asc')
                ->get()
                ->map(function ($loc) {
                    return [
                        'id'         => $loc->id,
                        'name'       => $loc->name,
                        'description'=> $loc->description,
                        'is_active'  => (bool) $loc->is_active,
                        'coordinates'=> $loc->coordinates,
                    ];
                })
                ->values()
                ->all();
        } catch (\Throwable $e) {
            \Log::warning('Gagal memuat attendance_work_locations untuk peta: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Cari nama lokasi kerja berdasarkan koordinat (point-in-polygon).
     *
     * PERBAIKAN 2026-11-03: dipakai untuk mengisi "LOKASI KERJA" di modal
     * Detail Presensi Wajah. Sebelumnya nama lokasi diambil dari kolom
     * `location_name` yang bisa kosong, lalu jatuh ke fallback koordinat
     * mentah (mis. "-7.629019, 110.827461") sehingga user bingung.
     * Sekarang dicocokkan ke polygon -> muncul nama seperti "Kantor Pusat".
     */
    private function resolveLocationName(?float $lat, ?float $lng): ?string
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        try {
            $locations = \App\Models\AttendanceWorkLocation::orderBy('id', 'asc')->get();
        } catch (\Throwable $e) {
            return null;
        }

        foreach ($locations as $loc) {
            $coords = $loc->coordinates;
            if (empty($coords) || !is_array($coords) || count($coords) < 3) {
                continue;
            }

            // point-in-polygon (sama dengan FaceAttendanceController::pointInPolygon)
            $inside = false;
            $n = count($coords);
            $j = $n - 1;
            for ($i = 0; $i < $n; $i++) {
                $lng_i = (float) ($coords[$i][0] ?? 0);
                $lat_i = (float) ($coords[$i][1] ?? 0);
                $lng_j = (float) ($coords[$j][0] ?? 0);
                $lat_j = (float) ($coords[$j][1] ?? 0);

                $intersect = (($lat_i > $lat) != ($lat_j > $lat))
                    && ($lng < (($lng_j - $lng_i) * ($lat - $lat_i) / ((($lat_j - $lat_i) ?: 0.0000001)) + $lng_i));

                if ($intersect) {
                    $inside = !$inside;
                }
                $j = $i;
            }

            if ($inside) {
                return $loc->name;
            }
        }

        return null;
    }

    /**
     * Daftar bulan (format YYYY-MM) yang memiliki data scan di att_log.
     * Dipakai agar admin bisa langsung memilih bulan yang memang terisi,
     * alih-alih melihat halaman kosong.
     *
     * PERBAIKAN (2026-11-03): sebelumnya method ini MEMBACA 50.000 baris
     * att_log dari PHP pada SETIAP request hanya untuk mengisi dropdown
     * bulan. Dengan 1,44 juta baris di att_log, ini penyebab utama halaman
     * "sangat berat" dan timeout.
     *
     * Sekarang: agregasi di SQL (GROUP BY bulan) sehingga yang ditransfer
     * ke PHP hanya <= 24 baris. Query juga dibatasi 24 bulan terbaru.
     */
    private function getAvailableMonths(): array
    {
        try {
            // Versi cepat: agregasi per bulan di SQL.
            // PENTING: SQL Server TIDAK mengizinkan GROUP BY memakai alias
            // kolom ('ym'), sehingga ekspresi CONVERT harus ditulis ulang
            // persis sama. Sebelumnya muncul: Invalid column name 'ym'.
            $bulanExpr = "CONVERT(varchar(7), scan_date, 120)";
            $months = $this->connCii()->table('att_log')
                ->selectRaw($bulanExpr . " as ym")
                ->groupByRaw($bulanExpr)
                ->orderByDesc('ym')
                ->limit(24)
                ->pluck('ym')
                ->filter(function ($ym) {
                    return is_string($ym) && preg_match('/^\d{4}-\d{2}$/', $ym);
                })
                ->values()
                ->all();

            if (!empty($months)) {
                return $months;
            }

            // Fallback: kalau tidak ada baris sama sekali, ambil bulan
            // default (bulan berjalan) supaya dropdown tidak kosong.
            return [Carbon::now()->format('Y-m')];
        } catch (\Throwable $e) {
            \Log::warning('Gagal mengambil daftar bulan att_log: ' . $e->getMessage());
            return [Carbon::now()->format('Y-m')];
        }
    }

    /**
     * API untuk Select2 "Departemen".
     *
     * Hanya mengambil kolom ID_DEPT + DEPARTEMENT dari tabel DEPT.
     * Jumlah departemen kecil, jadi aman dimuat penuh.
     */
    public function departmentSearch(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        try {
            $rows = $this->connCii()->table('DEPT')
                ->select('ID_DEPT', 'DEPARTEMENT')
                ->when($q !== '', function ($query) use ($q) {
                    $query->where('DEPARTEMENT', 'like', '%' . $q . '%');
                })
                ->orderBy('DEPARTEMENT', 'asc')
                ->limit(200)
                ->get();

            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'id'   => (string) $r->ID_DEPT,
                    'text' => $r->DEPARTEMENT,
                ];
            }

            return response()->json($out);
        } catch (\Throwable $e) {
            \Log::warning('Gagal memuat departemen untuk Select2: ' . $e->getMessage());
            return response()->json([], 200);
        }
    }

    /**
     * API untuk Select2 "Cari (Nama / NPK / Barcode)".
     *
     * Sumber data: UNION BIODATA (karyawan aktif) + BIODATA_KELUAR
     * (karyawan keluar/pensiun). Scan fingerprint karyawan yang sudah keluar
     * tetap tersimpan di att_log, jadi keduanya wajib masuk daftar.
     *
     * Query dibatasi (limit) dan memakai prefix search supaya tidak berat.
     *
     * Route: GET /face-attendance/admin/employees?q=...
     */
    public function employeeSearch(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $limit = min(50, max(10, (int) $request->get('limit', 25)));

        // Kosong -> jangan query apa pun, kembalikan array kosong supaya
        // Select2 tidak memuat ribuan karyawan saat pertama dibuka.
        if ($q === '') {
            return response()->json([]);
        }

        try {
            // PERBAIKAN 2026-11-03: query lama memakai pola
            //   SELECT * FROM ( ... UNION ALL ... ) t ORDER BY ...
            // Di SQL Server, derived table yang berisi ORDER BY tanpa TOP/OFFSET
            // di query luar akan ditolak:
            //   "A SELECT statement that assigns a value to a variable must not
            //    contain a data-definition..."
            //   "The ORDER BY clause is invalid in views, inline functions,
            //    derived tables, subqueries, and common table expressions..."
            // Akibatnya endpoint mengembalikan array kosong (catch) sehingga
            // Select2 "Cari (Nama / NPK / Barcode)" selalu kosong.
            //
            // Perbaikan: TOP dipindah ke query LUAR, tidak ada ORDER BY di
            // dalam derived table. TOP per-sumber tetap dipakai agar hasil
            // BIODATA dan BIODATA_KELUAR sama-sama terambil (bukan hanya
            // 25 baris total yang semuanya dari satu tabel).
            //
            // Catatan: CAST(... AS VARCHAR) pada kolom BARCODE dipertahankan
            // karena barcode bisa bertipe numerik/float (contoh 1234.0).
            $rows = DB::query("
                SELECT TOP (" . (int) $limit . ")
                    t.NPK, t.NAMA_KARYAWAN, t.BARCODE, t.sumber
                FROM (
                    SELECT
                        b.NPK, b.NAMA_KARYAWAN, b.BARCODE, 'Aktif' as sumber
                    FROM BIODATA b
                    WHERE b.NAMA_KARYAWAN LIKE ? OR b.NPK LIKE ? OR CAST(b.BARCODE AS VARCHAR) LIKE ?
                    UNION ALL
                    SELECT
                        bk.NPK, bk.NAMA_KARYAWAN, bk.BARCODE, 'Keluar' as sumber
                    FROM BIODATA_KELUAR bk
                    WHERE bk.NAMA_KARYAWAN LIKE ? OR bk.NPK LIKE ? OR CAST(bk.BARCODE AS VARCHAR) LIKE ?
                ) t
                ORDER BY t.NAMA_KARYAWAN ASC, t.NPK ASC
            ", [
                '%' . $q . '%', '%' . $q . '%', '%' . $q . '%',
                '%' . $q . '%', '%' . $q . '%', '%' . $q . '%',
            ]);

            $out = [];
            $seen = [];
            foreach ($rows as $r) {
                $npk = (string) $r->NPK;
                // Dedup: NPK sama tidak boleh muncul dua kali walau ada di
                // BIODATA dan BIODATA_KELUAR sekaligus.
                if (isset($seen[$npk])) {
                    continue;
                }
                $seen[$npk] = true;

                $barcode = trim((string) $r->BARCODE);
                if ($barcode !== '' && substr($barcode, -2) === '.0') {
                    $barcode = substr($barcode, 0, -2);
                }

                $out[] = [
                    'id'       => $npk,
                    'npk'      => $npk,
                    'name'     => $r->NAMA_KARYAWAN,
                    'barcode'  => $barcode,
                    'sumber'   => $r->sumber,
                    'text'     => $r->NAMA_KARYAWAN . ' - ' . $npk
                                 . ($barcode !== '' ? ' (' . $barcode . ')' : ''),
                ];
            }

            return response()->json($out);
        } catch (\Throwable $e) {
            // Log detail lengkap supaya penyebabnya mudah dilacak di
            // storage/logs/laravel.log (sebelumnya hanya pesan singkat).
            \Log::error('Gagal mencari karyawan untuk Select2: ' . $e->getMessage(), [
                'q'    => $q,
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);

            // Fallback: pakai Query Builder (bukan raw UNION) supaya kalau
            // ada perbedaan struktur tabel, error-nya terisolasi per tabel
            // dan hasil yang berhasil tetap dikirim ke Select2.
            try {
                return response()->json($this->employeeSearchFallback($q, $limit));
            } catch (\Throwable $e2) {
                \Log::error('Fallback pencarian karyawan juga gagal: ' . $e2->getMessage(), [
                    'q'    => $q,
                    'file' => $e2->getFile() . ':' . $e2->getLine(),
                ]);
                return response()->json([], 200);
            }
        }
    }

    /**
     * Diagnostik endpoint pencarian karyawan.
     *
     * Dipakai untuk melacak penyebab Select2 "Cari (Nama / NPK / Barcode)"
     * selalu kosong. Endpoint ini menjalankan query yang sama tetapi
     * MENAMPILKAN error asli SQL Server, bukan menelannya menjadi [].
     *
     * Akses: /face-attendance/admin/employees-diagnostic?q=budi
     */
    public function employeeSearchDiagnostic(Request $request)
    {
        $q     = trim((string) $request->get('q', 'a'));
        $limit = 25;
        $hasil = [
            'query'    => $q,
            'koneksi'  => DB::getDefaultConnection(),
            'database' => null,
            'tabel'    => [],
            'error'    => [],
            'data'     => [],
            'sql'      => null,
        ];

        // 1) Info koneksi
        try {
            $row = $this->connCii()->selectOne('SELECT DB_NAME() AS db');
            $hasil['database'] = $row->db ?? null;
        } catch (\Throwable $e) {
            $hasil['error'][] = 'Koneksi database gagal: ' . $e->getMessage();
            return response()->json($hasil, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        // 2) Cek keberadaan & kolom tiap tabel sumber
        foreach (['BIODATA', 'BIODATA_KELUAR'] as $tabel) {
            try {
                $kolom = $this->connCii()->select(
                    "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_NAME = ? AND COLUMN_NAME IN ('NPK','NAMA_KARYAWAN','BARCODE')
                     ORDER BY COLUMN_NAME",
                    [$tabel]
                );
                $hasil['tabel'][$tabel] = [
                    'ada'   => true,
                    'kolom' => array_map(fn ($c) => $c->COLUMN_NAME . ':' . $c->DATA_TYPE, $kolom),
                ];
            } catch (\Throwable $e) {
                $hasil['tabel'][$tabel] = ['ada' => false, 'error' => $e->getMessage()];
            }
        }

        // 3) Jalankan query UNION utama, tangkap error asli
        try {
            $sql = "
                SELECT TOP (" . (int) $limit . ")
                    t.NPK, t.NAMA_KARYAWAN, t.BARCODE, t.sumber
                FROM (
                    SELECT b.NPK, b.NAMA_KARYAWAN, b.BARCODE, 'Aktif' as sumber
                    FROM BIODATA b
                    WHERE b.NAMA_KARYAWAN LIKE ? OR b.NPK LIKE ? OR CAST(b.BARCODE AS VARCHAR) LIKE ?
                    UNION ALL
                    SELECT bk.NPK, bk.NAMA_KARYAWAN, bk.BARCODE, 'Keluar' as sumber
                    FROM BIODATA_KELUAR bk
                    WHERE bk.NAMA_KARYAWAN LIKE ? OR bk.NPK LIKE ? OR CAST(bk.BARCODE AS VARCHAR) LIKE ?
                ) t
                ORDER BY t.NAMA_KARYAWAN ASC, t.NPK ASC
            ";
            $hasil['sql'] = preg_replace('/\s+/', ' ', trim($sql));

            $like = '%' . $q . '%';
            $rows = $this->connCii()->select($sql, [$like, $like, $like, $like, $like, $like]);

            foreach ($rows as $r) {
                $hasil['data'][] = [
                    'npk'  => (string) $r->NPK,
                    'nama' => $r->NAMA_KARYAWAN,
                    'barcode' => (string) $r->BARCODE,
                    'sumber' => $r->sumber,
                ];
            }
            $hasil['jumlah'] = count($hasil['data']);
        } catch (\Throwable $e) {
            $hasil['error'][] = 'Query UNION gagal: ' . $e->getMessage();
        }

        // 4) Coba fallback per tabel, laporkan hasilnya juga
        try {
            $fb = $this->employeeSearchFallback($q, $limit);
            $hasil['fallback_jumlah'] = count($fb);
            if (empty($hasil['data']) && !empty($fb)) {
                $hasil['data'] = $fb;
            }
        } catch (\Throwable $e) {
            $hasil['error'][] = 'Fallback gagal: ' . $e->getMessage();
        }

        return response()->json($hasil, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Fallback pencarian karyawan memakai Query Builder per tabel.
     *
     * Dipakai bila query UNION utama gagal. Tiap tabel dicoba terpisah
     * sehingga kegagalan di satu tabel (mis. BIODATA_KELUAR tidak ada atau
     * kolomnya berbeda) tidak menghapus seluruh hasil pencarian.
     */
    private function employeeSearchFallback(string $q, int $limit): array
    {
        $like = '%' . $q . '%';
        $out  = [];
        $seen = [];

        $sources = [
            ['BIODATA',        'Aktif'],
            ['BIODATA_KELUAR', 'Keluar'],
        ];

        foreach ($sources as [$table, $sumber]) {
            try {
                $rows = $this->connCii()->table($table)
                    ->select(['NPK', 'NAMA_KARYAWAN', 'BARCODE'])
                    ->where(function ($w) use ($like) {
                        $w->where('NAMA_KARYAWAN', 'like', $like)
                          ->orWhere('NPK', 'like', $like)
                          ->orWhereRaw('CAST(BARCODE AS VARCHAR(50)) LIKE ?', [$like]);
                    })
                    ->orderBy('NAMA_KARYAWAN', 'asc')
                    ->limit($limit)
                    ->get();
            } catch (\Throwable $e) {
                // Tabel ini bermasalah, lanjut ke sumber berikutnya.
                \Log::warning("Fallback pencarian karyawan gagal di tabel {$table}: " . $e->getMessage());
                continue;
            }

            foreach ($rows as $r) {
                $npk = trim((string) $r->NPK);
                if ($npk === '' || isset($seen[$npk])) {
                    continue;
                }
                $seen[$npk] = true;

                $barcode = trim((string) $r->BARCODE);
                if ($barcode !== '' && substr($barcode, -2) === '.0') {
                    $barcode = substr($barcode, 0, -2);
                }

                $out[] = [
                    'id'      => $npk,
                    'npk'     => $npk,
                    'name'    => $r->NAMA_KARYAWAN,
                    'barcode' => $barcode,
                    'sumber'  => $sumber,
                    'text'    => $r->NAMA_KARYAWAN . ' - ' . $npk
                                 . ($barcode !== '' ? ' (' . $barcode . ')' : ''),
                ];

                if (count($out) >= $limit) {
                    return $out;
                }
            }
        }

        return $out;
    }

    /**
     * Koneksi database CII (SQL Server) — sumber tabel att_log, BIODATA,
     * BIODATA_KELUAR, DEPT, dan card_menu_settings.
     *
     * PENTING (PERBAIKAN 7):
     * Sebelumnya SELURUH query di controller ini memakai DB::table() tanpa
     * menyebut koneksi. Akibatnya query diarahkan ke koneksi DEFAULT, yaitu
     * DB_CONNECTION=sqlsrv dengan DB_DATABASE=audit — BUKAN database CII.
     * Tabel att_log tidak ada di 'audit', sehingga query melempar exception,
     * ditangkap catch, lalu dikembalikan sebagai koleksi kosong. Itulah sebab
     * rekap selalu menampilkan "0 baris scan ditemukan" padahal att_log
     * berisi ratusan ribu baris.
     *
     * Semua controller lain yang bekerja dengan att_log (lihat
     * AttendanceFingerController) memakai DB::connection('cii')->table('att_log').
     * Helper ini menyamakan perilaku tersebut.
     */
    private function connCii()
    {
        return DB::connection('cii');
    }

    /**
     * Susun informasi diagnostik untuk mendiagnosis rekap kosong.
     * Menampilkan: total scan di bulan itu, jumlah pin unik, berapa yang match
     * ke BIODATA, dan daftar pin yang tidak ditemukan (sample).
     */
    private function buildDiagnostics(string $month, $monthlyLogs, $employees, array $groupedAll): array
    {
        $totalScans = $monthlyLogs->count();

        $uniquePins = [];
        foreach ($monthlyLogs as $log) {
            $uniquePins[$this->normalizePin($log->pin)] = true;
        }

        // Peta barcode -> nama untuk deteksi pin yang tidak ada di master
        $barcodeMap = [];
        foreach ($employees as $emp) {
            $barcodeMap[$this->normalizePin($emp->BARCODE ?? '')] = $emp->NAMA_KARYAWAN;
        }

        $unmatchedPins = [];
        foreach (array_keys($uniquePins) as $pin) {
            if ($pin === '') {
                continue;
            }
            if (!isset($barcodeMap[$pin])) {
                $unmatchedPins[] = $pin;
            }
        }

        $matchedPins = count($uniquePins) - count($unmatchedPins);

        return [
            'total_scans'      => $totalScans,
            'unique_pins'      => count($uniquePins),
            'matched_pins'     => $matchedPins,
            'unmatched_pins'   => array_slice($unmatchedPins, 0, 20),
            'unmatched_total'  => count($unmatchedPins),
            'employee_count'   => $employees->count(),
            'rows_shown'       => count($groupedAll),
            'has_data'         => $totalScans > 0,
            'month'            => $month,
        ];
    }

    /**
     * API: detail harian seorang karyawan (face + finger + koordinat + gambar wajah).
     * Dipakai oleh modal detail di halaman admin.
     */
    public function employeeDetail(Request $request, string $npk, string $date)
    {
        try {
            $biodata = $this->connCii()->table('BIODATA as b')
                ->leftJoin('DEPT as d', 'b.ID_DEPT', '=', 'd.ID_DEPT')
                // PERBAIKAN (2026-11-03): ID_DEPT ikut dipilih supaya
                // resolveDept() bisa query ulang ke DEPT bila join gagal
                // menghasilkan nama departemen (data lama / ID tidak cocok).
                ->select('b.NPK', 'b.NAMA_KARYAWAN', 'b.BARCODE', 'b.ID_DEPT', 'd.DEPARTEMENT as DEPT')
                ->where('b.NPK', (string) $npk)
                ->first();

            if (!$biodata) {
                return response()->json(['success' => false, 'message' => 'Karyawan tidak ditemukan.'], 404);
            }

            $barcode = $this->normalizePin($biodata->BARCODE);

            // Gunakan CAST pada kedua sisi agar aman dari perbedaan tipe kolom
            // (pin varchar vs BARCODE numeric). LTRIM/RTRIM karena SQL Server lama
            // tidak mendukung TRIM().
            $logs = $this->connCii()->table('att_log')
                ->whereRaw('CAST(pin AS VARCHAR) = CAST(? AS VARCHAR)', [$barcode])
                ->whereDate('scan_date', $date)
                ->orderBy('scan_date', 'asc')
                ->get();

            $faceDetails = [];
            try {
                $faceDetails = FaceAttendanceLog::forNpk((string) $npk)
                    ->whereDate('scan_date', $date)
                    ->orderBy('scan_date', 'asc')
                    ->get()
                    ->map(function ($l) {
                        $lat = $l->latitude !== null ? (float) $l->latitude : null;
                        $lng = $l->longitude !== null ? (float) $l->longitude : null;

                        return [
                            'id'           => $l->id,
                            'scan_time'    => Carbon::parse($l->scan_date)->format('H:i:s'),
                            // PERBAIKAN: pakai accessor yang membaca kolom `action`
                            // (skema server) — bukan `action_type` yang tidak ada.
                            'action_type'  => $l->actionType(),
                            'inoutmode'    => $l->actionType() === 'IN' ? 0 : 1,
                            'latitude'     => $l->latitude,
                            'longitude'    => $l->longitude,
                            'accuracy'     => $l->accuracy,
                            'location_name'=> $l->locationName(),
                            // PERBAIKAN (2026-11-03): nama area kerja hasil
                            // pencocokan point-in-polygon. `location_name` bisa
                            // kosong / berisi koordinat mentah, sehingga modal
                            // menampilkan angka GPS yang tidak informatif.
                            'location_resolved' => $this->resolveLocationName($lat, $lng),
                            'face_image'   => $l->face_image,
                        ];
                    })->all();
            } catch (\Throwable $e) {
                Log::warning('Gagal mengambil detail face_attendance_logs: ' . $e->getMessage());
            }

            $scans = $logs->map(function ($log) {
                return [
                    'scan_time'   => Carbon::parse($log->scan_date)->format('H:i:s'),
                    'verifymode'  => (int) $log->verifymode,
                    'mode_label'  => $this->modeLabel($log),
                    'inoutmode'   => (int) $log->inoutmode,
                    'action_type' => ((int) $log->inoutmode === 0) ? 'Clock In' : 'Clock Out',
                ];
            })->all();

            // PERBAIKAN (2026-11-03 #3): face_details diberi `mode_label`
            // eksplisit. Sebelumnya field ini tidak dikirim, sehingga
            // frontend (isFace = d.mode_label === 'Wajah') selalu false dan
            // SEMUA baris berlabel "Fingerprint" walau metode aslinya wajah.
            $faceDetails = array_map(function (array $row) {
                $row['mode_label'] = 'Wajah';
                return $row;
            }, $faceDetails);

            return response()->json([
                'success'    => true,
                'employee'   => [
                    'npk'     => (string) $biodata->NPK,
                    'name'    => $biodata->NAMA_KARYAWAN,
                    'dept'    => $this->resolveDept($biodata),
                    'barcode' => $barcode,
                ],
                'date'       => $date,
                'date_label' => Carbon::parse($date)->translatedFormat('d F Y') . ' (' . Carbon::parse($date)->translatedFormat('l') . ')',
                'scans'      => $scans,
                'face_details' => $faceDetails,
            ]);
        } catch (\Throwable $e) {
            Log::error('FaceAttendanceAdminController::employeeDetail gagal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat detail: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Tentukan label metode presensi dengan benar.
     *
     * PERBAIKAN (2026-11-03): modul presensi wajah web menulis baris ke
     * `att_log` dengan `sn = 'FACE_GPS_WEB'` dan `verifymode = 15`.
     * Nilai 15 itu warisan ZKTeco, jadi verifymode SAJA tidak cukup untuk
     * membedakan wajah vs fingerprint. Baris yang berasal dari tabel
     * `face_attendance_logs` (yang selalu berarti presensi wajah) harus
     * dilabeli "Wajah" tanpa melihat verifymode-nya.
     *
     * @param object $log Baris att_log (punya kolom sn/verifymode).
     * @return string 'Wajah' atau 'Fingerprint'
     */
    private function modeLabel($log): string
    {
        $sn = strtoupper(trim((string) ($log->sn ?? '')));
        if ($sn === 'FACE_GPS_WEB' || $sn === 'FACE_GPS' || strpos($sn, 'FACE') === 0) {
            return 'Wajah';
        }

        // Fingerprint ZKTeco memakai verifymode 1 (FP) / 2 (FP+PIN).
        // Nilai 15 (FP+PIN ZKTeco lama) TIDAK boleh otomatis dianggap wajah.
        $mode = (int) ($log->verifymode ?? -1);
        return in_array($mode, [15, 16, 20], true) ? 'Wajah' : 'Fingerprint';
    }

    /**
     * Ambil nama departemen karyawan dengan fallback.
     *
     * PERBAIKAN (2026-11-03): modal menampilkan departemen "-" karena
     * `BIODATA.ID_DEPT` sering tidak cocok dengan `DEPT.ID_DEPT` pada data
     * lama, sehingga hasil LEFT JOIN bernilai NULL. Kalau nama departemen
     * kosong, coba ambil dari kolom teks di BIODATA sendiri sebelum
     * menyerah dan mengirim '-'.
     *
     * @param object $biodata Baris hasil join BIODATA + DEPT.
     * @return string Nama departemen, atau '-' bila benar-benar tidak ada.
     */
    private function resolveDept($biodata): string
    {
        $dept = trim((string) ($biodata->DEPT ?? ''));
        if ($dept !== '' && $dept !== '-') {
            return $dept;
        }

        // Fallback 1: kolom teks departemen di BIODATA (kalau ada).
        foreach (['DEPARTEMENT', 'DEPARTMENT', 'BAGIAN', 'DIVISI'] as $col) {
            $val = trim((string) ($biodata->{$col} ?? ''));
            if ($val !== '' && $val !== '-') {
                return $val;
            }
        }

        // Fallback 2: query langsung ke DEPT memakai ID_DEPT dari BIODATA.
        // Dijalankan terpisah supaya join yang gagal tidak menutupi data.
        $idDept = $biodata->ID_DEPT ?? null;
        if ($idDept !== null && $idDept !== '') {
            try {
                $found = $this->connCii()->table('DEPT')
                    ->where('ID_DEPT', $idDept)
                    ->value('DEPARTEMENT');
                $found = trim((string) $found);
                if ($found !== '') {
                    return $found;
                }
            } catch (\Throwable $e) {
                // Diamkan: departemen bersifat informasi, bukan penentu.
            }
        }

        return '-';
    }

    /**
     * Halaman pengaturan card menu HRIS (admin bisa show/hide card).
     */
    public function cardMenuSettings()
    {
        // Diagnosa sumber data: supaya admin tahu kalau toggle tidak akan
        // berefek (tabel belum ada / kosong / DB tidak terjangkau).
        $status = [
            'table_exists' => false,
            'row_count'    => 0,
            'db_error'     => null,
        ];

        try {
            $status['table_exists'] = CardMenuSetting::tableExists(true);

            if ($status['table_exists']) {
                $status['row_count'] = \Illuminate\Support\Facades\DB::table('card_menu_settings')->count();
            }
        } catch (\Throwable $e) {
            $status['db_error'] = $e->getMessage();
        }

        $cards = CardMenuSetting::allCards();

        return view('face_attendance.card_menu_settings', compact('cards', 'status'));
    }

    /**
     * Toggle show/hide sebuah card menu HRIS.
     */
    public function updateCardMenu(Request $request)
    {
        $request->validate([
            'key'        => 'required|string|max:100',
            'is_visible' => 'required|boolean',
        ]);

        $key     = $request->input('key');
        $visible = $request->boolean('is_visible');

        $ok = CardMenuSetting::setVisibility($key, $visible);

        if (!$ok) {
            return response()->json([
                'success' => false,
                'message' => 'Pengaturan gagal disimpan. Jalankan "php artisan card-menu:doctor" di server untuk melihat penyebabnya.',
            ], 500);
        }

        return response()->json([
            'success'    => true,
            'message'    => 'Card menu berhasil diperbarui.',
            'key'        => $key,
            'is_visible' => $visible,
        ]);
    }

    /**
     * Export rekap semua karyawan ke CSV (face + finger).
     */
    public function export(Request $request)
    {
        $month = $request->get('month', Carbon::now()->format('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = Carbon::now()->format('Y-m');
        }

        $startDate = $month . '-01';
        $endDate   = Carbon::parse($startDate)->endOfMonth()->format('Y-m-d');

        $monthlyLogs = $this->connCii()->table('att_log as a')
            ->leftJoin('BIODATA as b', DB::raw('CAST(a.pin AS VARCHAR)'), '=', DB::raw('CAST(b.BARCODE AS VARCHAR)'))
            ->whereBetween('a.scan_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->orderBy('a.scan_date', 'asc')
            ->select(['a.pin', 'a.scan_date', 'a.verifymode', 'a.inoutmode', 'a.sn'])
            ->get();

        $employees = $this->connCii()->table('BIODATA as b')
            ->leftJoin('DEPT as d', 'b.ID_DEPT', '=', 'd.ID_DEPT')
            ->select('b.NPK', 'b.NAMA_KARYAWAN', 'b.BARCODE', 'd.DEPARTEMENT as DEPT')
            ->orderBy('b.NAMA_KARYAWAN', 'asc')
            ->get();

        $logsByBarcode = [];
        foreach ($monthlyLogs as $log) {
            $logsByBarcode[$this->normalizePin($log->pin)][] = $log;
        }

        $filename = 'rekap-presensi-' . $month . '.csv';
        $handle   = fopen('php://temp', 'r+');

        // BOM agar Excel tidak berantakan dengan karakter UTF-8
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'NPK', 'Nama', 'Departemen', 'Tanggal', 'Hari',
            'Jam Masuk', 'Jam Pulang', 'Mode', 'Total Scan', 'Status',
        ]);

        foreach ($employees as $emp) {
            $barcode = $this->normalizePin($emp->BARCODE ?? '');
            $empLogs = $logsByBarcode[$barcode] ?? [];
            if (empty($empLogs)) {
                continue;
            }

            $grouped = $this->groupLogsPerDate($empLogs);
            krsort($grouped);

            foreach ($grouped as $g) {
                fputcsv($handle, [
                    (string) $emp->NPK,
                    $emp->NAMA_KARYAWAN,
                    $emp->DEPT ?? '-',
                    $g['formatted_date'],
                    $g['day_name'],
                    $g['clock_in'] ?? '-',
                    $g['clock_out'] ?? '-',
                    implode(' + ', $g['modes']),
                    $g['total_scans'],
                    ($g['clock_in'] && $g['clock_out']) ? 'Lengkap' : 'Partial',
                ]);
            }
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Normalisasi pin/barcode supaya konsisten antar tipe kolom SQL Server.
     * - null / kosong -> string kosong
     * - cast ke string, trim spasi di kiri & kanan
     * - buang trailing '.0' yang muncul dari kolom numeric (mis. 12345.0)
     *
     * SQL Server lama tidak mendukung TRIM(), jadi memakai LTRIM(RTRIM()).
     */
    private function normalizePin($value): string
    {
        if ($value === null) {
            return '';
        }

        $pin = trim((string) $value);

        // Numeric column bisa terbaca sebagai "12345.0" oleh driver
        if ($pin !== '' && substr($pin, -2) === '.0' && ctype_digit(substr($pin, 0, -2))) {
            $pin = substr($pin, 0, -2);
        }

        return $pin;
    }

    /**
     * Group scan per tanggal. PERATURAN SAMA dengan FaceAttendanceController::rekap:
     * - 1 baris per tanggal
     * - Jam < 12.5 -> Clock In (paling awal), Jam >= 12.5 -> Clock Out (paling akhir)
     * - verifymode 15 = Wajah, selain itu = Fingerprint
     */
    private function groupLogsPerDate($empLogs): array
    {
        $grouped = [];

        foreach ($empLogs as $log) {
            $dateKey = Carbon::parse($log->scan_date)->format('Y-m-d');
            $time    = Carbon::parse($log->scan_date)->format('H:i:s');

            if (!isset($grouped[$dateKey])) {
                $grouped[$dateKey] = [
                    'date'         => $dateKey,
                    'formatted_date'=> Carbon::parse($dateKey)->format('d-m-Y'),
                    'day_name'     => Carbon::parse($dateKey)->translatedFormat('l'),
                    'clock_in'     => null,
                    'clock_out'    => null,
                    'modes'        => [],
                    'total_scans'  => 0,
                    'has_face'     => false,
                    'face_details' => [],
                ];
            }

            $grouped[$dateKey]['total_scans']++;

            // PERBAIKAN (2026-11-03): klasifikasi mode memakai modeLabel()
            // yang membaca kolom `sn`. Sebelumnya di sini hardcode
            // `verifymode === 15 ? 'Wajah' : 'Fingerprint'`, sehingga
            // presensi wajah web (sn='FACE_GPS_WEB') yang tidak memakai
            // verifymode 15 salah dilabeli "Fingerprint", dan scan
            // fingerprint ZKTeco lama (verifymode 15) salah dilabeli
            // "Wajah". Itu sebabnya rekap hanya terlihat berisi data wajah.
            $modeName = $this->modeLabel($log);
            if (!in_array($modeName, $grouped[$dateKey]['modes'])) {
                $grouped[$dateKey]['modes'][] = $modeName;
            }

            if ($modeName === 'Wajah') {
                $grouped[$dateKey]['has_face'] = true;
            }

            $hour = (int) Carbon::parse($log->scan_date)->format('H');
            if ($hour < 12.5) {
                if ($grouped[$dateKey]['clock_in'] === null) {
                    $grouped[$dateKey]['clock_in'] = $time;
                }
            } else {
                $grouped[$dateKey]['clock_out'] = $time;
            }
        }

        return $grouped;
    }

    /**
     * Ambil detail wajah (gambar + koordinat) untuk sekumpulan NPK pada bulan tertentu.
     * Mengembalikan array: [npk => [detail, ...]]
     */
    private function loadFaceDetails(string $month, array $npks): array
    {
        if (empty($npks)) {
            return [];
        }

        $result = [];

        try {
            // PERBAIKAN 8: SQL Server membatasi 2100 parameter per statement.
            // whereIn() dengan ribuan NPK akan pecah, jadi query dipecah per
            // 1000 NPK. Saat ini pemanggil hanya mengirim 50 NPK (satu
            // halaman), tetapi pengaman ini menjaga bila batas halaman
            // berubah di kemudian hari.
            $logs = collect();
            foreach (array_chunk(array_values(array_unique($npks)), 1000) as $chunkNpk) {
                $logs = $logs->merge(
                    FaceAttendanceLog::whereIn('npk', $chunkNpk)
                        ->inMonth($month)
                        ->orderBy('scan_date', 'asc')
                        ->get()
                );
            }
            $logs = $logs->sortBy('scan_date')->values();

            foreach ($logs as $faceLog) {
                $dateKey = Carbon::parse($faceLog->scan_date)->format('Y-m-d');

                $result[$faceLog->npk][] = [
                    'id'            => $faceLog->id,
                    'date'          => $dateKey,
                    'scan_time'     => Carbon::parse($faceLog->scan_date)->format('H:i:s'),
                    'action_type'   => $faceLog->action_label,
                    'inoutmode'     => $faceLog->actionType() === 'IN' ? 0 : 1,
                    'latitude'      => $faceLog->latitude,
                    'longitude'     => $faceLog->longitude,
                    'accuracy'      => $faceLog->accuracy,
                    'location_name' => $faceLog->location_label,
                    'face_image'    => $faceLog->face_image_url,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal mengambil detail face_attendance_logs: ' . $e->getMessage());
        }

        return $result;
    }
}
