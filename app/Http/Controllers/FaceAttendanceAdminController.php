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

        // PERBAIKAN 7: bedakan "tidak ada data" dari "query gagal".
        // Dideklarasikan di awal karena dipakai oleh tahap master maupun
        // tahap agregasi att_log di bawah.
        $rekapError = null;

        // ===== 1. Ambil master karyawan (aktif + karyawan keluar/pensiun) =====
        // BIODATA_KELUAR ikut diambil karena scan fingerprint karyawan yang sudah
        // keluar tetap tersimpan di att_log.
        //
        // PERBAIKAN 14 (2026-11-04) — KINERJA FILTER DEPARTEMEN:
        // Urutan logika yang BENAR (sesuai cara kerja database CII):
        //   DEPT  ->  (BIODATA UNION BIODATA_KELUAR) by ID_DEPT
        //         ->  ambil BARCODE
        //         ->  cari di att_log berdasarkan pin
        //
        // Versi LAMA mengerjakannya terbalik:
        //   1. att_log dipindai PENUH untuk sebulan (semua pin)  -> 2x scan
        //   2. hasilnya baru difilter per departemen di PHP
        //   Akibat: memilih 1 departemen TIDAK mengurangi kerja sama sekali.
        //   Terukur pada data nyata: dept terbesar hanya 948 dari 6.934 scan
        //   (13,7%), tetapi 3.474 pin tetap diproses -> 86% kerja sia-sia.
        //
        // Versi BARU: filter ID_DEPT dipakai SEBAGAI BATAS AWAL di dalam
        // subquery, sehingga att_log hanya dibaca untuk pin departemen itu,
        // dan hanya SATU kali pemindaian (tidak ada lagi daftar pin terpisah).
        $masterRows = collect();

        try {
            $masterQuery = $this->connCii()->table('BIODATA as b')
                ->leftJoin('DEPT as d', 'b.ID_DEPT', '=', 'd.ID_DEPT')
                ->select([
                    'b.NPK',
                    'b.NAMA_KARYAWAN',
                    'b.BARCODE',
                    DB::raw('d.DEPARTEMENT as DEPT'),
                    DB::raw('0 as IS_KELUAR'),
                ]);

            if ($department !== '') {
                $masterQuery->where('b.ID_DEPT', $department);
            }

            if ($search !== '') {
                $masterQuery->where(function ($q) use ($search) {
                    $q->where('b.NAMA_KARYAWAN', 'like', '%' . $search . '%')
                      ->orWhere('b.NPK', 'like', '%' . $search . '%')
                      ->orWhere('b.BARCODE', 'like', '%' . $search . '%');
                });
            }

            $masterRows = $masterQuery->get();
        } catch (\Throwable $e) {
            \Log::error('Gagal memuat BIODATA pada rekap admin: ' . $e->getMessage());
            $rekapError = $rekapError ?: $e->getMessage();
        }

        // Tambahkan karyawan keluar yang barcode-nya tidak bentrok dengan BIODATA
        try {
            $keluarQuery = $this->connCii()->table('BIODATA_KELUAR as bk')
                ->leftJoin('DEPT as dk', 'bk.ID_DEPT', '=', 'dk.ID_DEPT')
                ->select([
                    'bk.NPK',
                    'bk.NAMA_KARYAWAN',
                    'bk.BARCODE',
                    DB::raw('dk.DEPARTEMENT as DEPT'),
                    DB::raw('1 as IS_KELUAR'),
                ]);

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

            $employeesKeluar = $keluarQuery->get();

            $activeBarcodes = [];
            foreach ($masterRows as $emp) {
                $activeBarcodes[$this->normalizePin($emp->BARCODE ?? '')] = true;
            }

            $merged = [];
            foreach ($masterRows as $emp) {
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

            $masterRows = collect($merged);
        } catch (\Throwable $e) {
            \Log::warning('Gagal memuat BIODATA_KELUAR: ' . $e->getMessage());
        }

        // Urutkan nama (pengurutan di PHP, bukan SQL, karena hasil
        // BIODATA + BIODATA_KELUAR sudah digabung di sini).
        $employees = $masterRows
            ->sortBy(function ($emp) {
                return mb_strtoupper((string) ($emp->NAMA_KARYAWAN ?? ''));
            })
            ->values();

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
        // ====================================================================
        // OPTIMASI BESAR (2026-11-03) — halaman rekap tidak lagi berat
        // ====================================================================
        // MASALAH LAMA: seluruh scan satu bulan (puluhan ribu baris) ditarik
        // ke PHP lewat ->get(), lalu di-grouping di memori. Beban terukur:
        //   - 40.000 baris stdClass  = ~19 MB RAM  (belum termasuk overhead
        //     array multidimensi hasil grouping per tanggal)
        //   - waktu kirim dari SQL Server ke PHP ikut sebanding
        // Padahal yang DITAMPILKAN hanya 50 karyawan (satu halaman).
        //
        // STRATEGI BARU:
        //   a. Agregasi di SQL: hitung per (pin, tanggal) -> jumlah scan,
        //      jam masuk, jam pulang, ada-wajah, dan daftar mode. Hasilnya
        //      kira-kira sebesar (jumlah karyawan x hari), bukan (jumlah scan).
        //   b. Pagination karyawan ditentukan dari hasil agregat, bukan dari
        //      seluruh baris mentah.
        //   c. Baris mentah HANYA diambil untuk 50 karyawan di halaman aktif.
        //
        // Batas 2100 parameter SQL Server tidak lagi jadi masalah karena
        // daftar pin TIDAK lagi dikirim sebagai parameter: pin diambil
        // langsung dari tabel master lewat INNER JOIN (PERBAIKAN 14).
        $aggRows     = collect();
        $monthlyLogs = collect();

        try {
            // (a)+(b)+(c) SATU statement: DEPT -> (BIODATA UNION BIODATA_KELUAR)
            //             -> BARCODE -> JOIN att_log -> agregasi per (pin,tgl).
            //
            // PERBAIKAN 14 (2026-11-04):
            //  - Pola LAMA memakai CHARINDEX(','+pin+',', csv) yang membungkus
            //    kolom dalam FUNGSI sehingga SQL Server TIDAK BISA memakai
            //    index pada att_log.pin (terukur 160 ms vs 16 ms pada 600 rb
            //    baris = 10x lebih lambat, dan index tidak menolong sama sekali).
            //  - Sekarang: INNER JOIN biasa. Index att_log(pin, scan_date)
            //    dipakai, dan filter ID_DEPT membatasi baris dari awal.
            //  - att_log.pin TIDAK dibungkus CAST (agar index tetap hidup);
            //    hanya BARCODE dari master yang di-CAST karena
            //    BIODATA_KELUAR.BARCODE bertipe numeric sedangkan
            //    BIODATA.BARCODE / att_log.pin bertipe varchar.
            $barcodeExpr = "LTRIM(RTRIM(CONVERT(varchar(64), %s.BARCODE)))";

            // Subquery master: hanya kolom yang dibutuhkan, sudah terfilter dept.
            $masterUnion = $this->connCii()->table('BIODATA as b')
                ->selectRaw(sprintf($barcodeExpr, 'b') . ' AS BARCODE');

            if ($department !== '') {
                $masterUnion->where('b.ID_DEPT', $department);
            }

            $keluarUnion = $this->connCii()->table('BIODATA_KELUAR as bk')
                ->selectRaw(sprintf($barcodeExpr, 'bk') . ' AS BARCODE');

            if ($department !== '') {
                $keluarUnion->where('bk.ID_DEPT', $department);
            }

            // UNION (bukan UNION ALL) -> buang barcode kembar antar tabel.
            $pinSub = $masterUnion->union($keluarUnion);

            $bulan = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];

            $aggQuery = $this->connCii()->table('att_log as a')
                ->joinSub($pinSub, 'bio', function ($join) {
                    // Tanpa CAST di sisi att_log -> index pin terpakai.
                    $join->on('bio.BARCODE', '=', DB::raw('LTRIM(RTRIM(a.pin))'));
                })
                ->whereBetween('a.scan_date', $bulan)
                ->select([
                    DB::raw('LTRIM(RTRIM(a.pin)) AS pin'),
                    DB::raw('CONVERT(date, a.scan_date) AS tgl'),
                    DB::raw('COUNT(*) AS jumlah_scan'),
                    DB::raw('MIN(a.scan_date) AS scan_pertama'),
                    DB::raw('MAX(a.scan_date) AS scan_terakhir'),
                ])
                ->groupByRaw('LTRIM(RTRIM(a.pin)), CONVERT(date, a.scan_date)');

            $aggRows = $aggQuery->get();
        } catch (\Throwable $e) {
            \Log::error('Gagal query att_log pada rekap admin: ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
                'dept' => $department,
            ]);
            $aggRows = collect();
            // PERBAIKAN 7: bedakan "tidak ada data" dari "query gagal".
            // Sebelumnya exception ditelan diam-diam sehingga halaman
            // melaporkan "0 baris scan" padahal penyebabnya error SQL
            // (mis. tabel att_log tidak ada di database yang terhubung).
            $rekapError = $e->getMessage();
        }

        // ===== 3. Group hasil agregat per barcode karyawan =====
        $aggByBarcode = [];
        foreach ($aggRows as $r) {
            $aggByBarcode[$this->normalizePin($r->pin)][] = $r;
        }

        $groupedAll = [];
        $totalScan  = 0;

        // Hitung total karyawan yang PUNYA scan (untuk pagination links)
        $totalEmployees = 0;
        foreach ($employees as $emp) {
            $barcode = $this->normalizePin($emp->BARCODE ?? '');
            if (!empty($aggByBarcode[$barcode])) {
                $totalEmployees++;
            }
        }

        // Slice employees untuk pagination (hindari memory exhausted)
        $employeesPaginated = $employees->slice($offset, $perPage);

        // Muat mode presensi untuk SEMUA pin di halaman ini dalam SATU query
        // (bukan satu query per karyawan).
        $barcodesHalaman = [];
        foreach ($employeesPaginated as $emp) {
            $barcodesHalaman[] = $this->normalizePin($emp->BARCODE ?? '');
        }
        $modesByPin = $this->loadModesPerDate($barcodesHalaman, $startDate, $endDate);

        foreach ($employeesPaginated as $emp) {
            $barcode = $this->normalizePin($emp->BARCODE ?? '');
            $dayRows = $aggByBarcode[$barcode] ?? [];

            if (empty($dayRows)) {
                // Karyawan tanpa scan sama sekali bulan ini skip
                continue;
            }

            $grouped = $this->groupAggregatedPerDate($dayRows, $barcode, $modesByPin[$barcode] ?? []);
            krsort($grouped);

            $empScan = 0;
            foreach ($grouped as $g) {
                $empScan += $g['total_scans'];
            }
            $totalScan += $empScan;

            $groupedAll[] = [
                'npk'          => (string) $emp->NPK,
                'name'         => $emp->NAMA_KARYAWAN,
                'barcode'      => $barcode,
                'dept'         => $emp->DEPT ?? '-',
                'total_scans'  => $empScan,
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
        $diagnostics = $this->buildDiagnostics($month, $aggRows, $employees, $groupedAll);

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
     * PERBAIKAN 15 (2026-11-04): versi 2026-11-03 masih memakai
     * GROUP BY CONVERT(varchar(7), scan_date) atas SELURUH tabel. LIMIT 24
     * TIDAK menolong karena SQL Server harus meng-GROUP BY semua baris dulu
     * sebelum bisa mengurutkan dan memotong. Terukur 215 ms pada 600 rb baris
     * dan biaya ini dibayar SETIAP KALI halaman dibuka / filter diubah.
     *
     * Sekarang: cukup ambil MAX(scan_date) (1 baris, memakai index), lalu
     * 24 bulan terakhir dibentuk di PHP. Tidak ada lagi GROUP BY tabel penuh.
     */
    private function getAvailableMonths(): array
    {
        try {
            // Satu baris saja: MAX(scan_date) memakai index pada scan_date
            // dan tidak perlu membaca seluruh tabel.
            $maxScan = $this->connCii()->table('att_log')->max('scan_date');

            if ($maxScan) {
                $akhir = $maxScan instanceof \DateTimeInterface
                    ? Carbon::instance($maxScan)
                    : Carbon::parse($maxScan);

                $months = [];
                for ($i = 0; $i < 24; $i++) {
                    $months[] = $akhir->copy()->startOfMonth()->subMonths($i)->format('Y-m');
                }

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
    private function buildDiagnostics(string $month, $aggRows, $employees, array $groupedAll): array
    {
        // OPTIMASI (2026-11-03): buildDiagnostics dulu menerima SELURUH baris
        // mentah scan sebulan hanya untuk menghitung jumlah pin unik. Sekarang
        // menerima hasil agregat per (pin, tanggal) yang jauh lebih kecil.
        // Total scan = penjumlahan kolom jumlah_scan, bukan count() baris.
        $totalScans = 0;
        foreach ($aggRows as $r) {
            $totalScans += (int) ($r->jumlah_scan ?? 0);
        }

        $uniquePins = [];
        foreach ($aggRows as $r) {
            $uniquePins[$this->normalizePin($r->pin)] = true;
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

            // PERBAIKAN 18 (2026-11-04): label In/Out untuk modal detail
            // DITENTUKAN DARI JAM SHIFT, bukan dari `action` (yang bisa
            // berisi teks keliru) maupun `inoutmode` (yang bisa mustahil
            // bernilai 0 pada data impor). Satu sumber untuk SEMUA baris:
            // tabel Rekap Harian (`scans`) dan Riwayat Scan (`face_details`),
            // sehingga keduanya tidak mungkin bertentangan lagi.
            try {
                $allTimes = $logs
                    ->map(function ($x) {
                        return Carbon::parse($x->scan_date)->format('H:i:s');
                    })
                    ->values()
                    ->all();

                $labelByTime = $this->resolveShiftActions((string) $npk, $date, $allTimes);

                foreach ($scans as $idx => $row) {
                    $lbl = $labelByTime[$row['scan_time']] ?? 'Clock In';
                    $scans[$idx]['action_type'] = $lbl;
                    $scans[$idx]['inoutmode'] = ($lbl === 'Clock In') ? 0 : 1;
                }

                foreach ($faceDetails as $idx => $row) {
                    $lbl = $labelByTime[$row['scan_time']] ?? 'Clock In';
                    $faceDetails[$idx]['action_type'] = $lbl;
                    $faceDetails[$idx]['inoutmode'] = ($lbl === 'Clock In') ? 0 : 1;
                }
            } catch (\Throwable $e) {
                Log::warning('Gagal menentukan label shift pada employeeDetail: ' . $e->getMessage());
            }

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

        // PERBAIKAN 14 (2026-11-04): export dulu membaca SELURUH att_log
        // sebulan (tanpa filter dept) dan memakai
        // CAST(a.pin AS VARCHAR) di klausa join -- pola yang mematikan index.
        // Sekarang memakai alur yang sama dengan rekap:
        //   DEPT -> (BIODATA UNION BIODATA_KELUAR) -> BARCODE -> att_log
        // dan att_log.pin tidak dibungkus CAST.
        $department = (string) $request->get('department', '');

        $barcodeExpr = "LTRIM(RTRIM(CONVERT(varchar(64), %s.BARCODE)))";

        $masterUnion = $this->connCii()->table('BIODATA as b')
            ->selectRaw(sprintf($barcodeExpr, 'b') . ' AS BARCODE');
        if ($department !== '') {
            $masterUnion->where('b.ID_DEPT', $department);
        }

        $keluarUnion = $this->connCii()->table('BIODATA_KELUAR as bk')
            ->selectRaw(sprintf($barcodeExpr, 'bk') . ' AS BARCODE');
        if ($department !== '') {
            $keluarUnion->where('bk.ID_DEPT', $department);
        }

        $monthlyLogs = $this->connCii()->table('att_log as a')
            ->joinSub($masterUnion->union($keluarUnion), 'bio', function ($join) {
                // Tanpa CAST di sisi att_log -> index pin terpakai.
                $join->on('bio.BARCODE', '=', DB::raw('LTRIM(RTRIM(a.pin))'));
            })
            ->whereBetween('a.scan_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->orderBy('a.scan_date', 'asc')
            ->select(['a.pin', 'a.scan_date', 'a.verifymode', 'a.inoutmode', 'a.sn'])
            ->get();

        // Master karyawan untuk export HARUS memakai gabungan
        // BIODATA + BIODATA_KELUAR, sebab query att_log di atas memakai
        // INNER JOIN ke gabungan tersebut. Kalau di sini hanya BIODATA,
        // scan milik karyawan keluar akan hilang dari CSV tanpa jejak.
        $empAktif = $this->connCii()->table('BIODATA as b')
            ->leftJoin('DEPT as d', 'b.ID_DEPT', '=', 'd.ID_DEPT')
            ->select('b.NPK', 'b.NAMA_KARYAWAN', 'b.BARCODE', 'd.DEPARTEMENT as DEPT');
        if ($department !== '') {
            $empAktif->where('b.ID_DEPT', $department);
        }
        $empAktif = $empAktif->get();

        $empKeluar = $this->connCii()->table('BIODATA_KELUAR as bk')
            ->leftJoin('DEPT as dk', 'bk.ID_DEPT', '=', 'dk.ID_DEPT')
            ->select('bk.NPK', 'bk.NAMA_KARYAWAN', 'bk.BARCODE', 'dk.DEPARTEMENT as DEPT');
        if ($department !== '') {
            $empKeluar->where('bk.ID_DEPT', $department);
        }
        $empKeluar = $empKeluar->get();

        // Barcode aktif menang bila ada duplikat (sama seperti rekap).
        $seen = [];
        $mergedEmp = [];
        foreach ($empAktif as $e) {
            $k = $this->normalizePin($e->BARCODE ?? '');
            if ($k === '') { continue; }
            $seen[$k] = true;
            $mergedEmp[] = $e;
        }
        foreach ($empKeluar as $e) {
            $k = $this->normalizePin($e->BARCODE ?? '');
            if ($k === '' || isset($seen[$k])) { continue; }
            $seen[$k] = true;
            $mergedEmp[] = $e;
        }

        $employees = collect($mergedEmp)
            ->sortBy(function ($e) {
                return mb_strtoupper((string) ($e->NAMA_KARYAWAN ?? ''));
            })
            ->values();

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
     * Susun baris rekap per tanggal DARI HASIL AGREGAT SQL.
     *
     * OPTIMASI (2026-11-03): sebelumnya fungsi ini menerima semua baris scan
     * mentah karyawan lalu mem-parse tanggal berulang kali dengan Carbon.
     * Sekarang menerima hasil agregat (satu baris per tanggal) yang sudah
     * membawa jumlah scan, jam pertama, dan jam terakhir dari SQL
     * (MIN/MAX/COUNT), sehingga PHP hanya memformat, tidak menghitung ulang.
     *
     * Klasifikasi jam masuk/pulang tetap mengikuti aturan lama:
     * scan sebelum 12:30 dianggap masuk, setelahnya dianggap pulang.
     * Mode (Wajah/Fingerprint) diambil dari query terpisah yang sangat kecil
     * (hanya DISTINCT mode untuk pin ini), bukan dari seluruh baris scan.
     */
    private function groupAggregatedPerDate(array $dayRows, string $barcode, array $modesByDate = []): array
    {
        $grouped = [];

        foreach ($dayRows as $row) {
            $tgl = $row->tgl;
            if ($tgl instanceof \DateTimeInterface) {
                $dateKey = $tgl->format('Y-m-d');
            } else {
                $dateKey = Carbon::parse($tgl)->format('Y-m-d');
            }

            $first = Carbon::parse($row->scan_pertama);
            $last  = Carbon::parse($row->scan_terakhir);

            // Ambang batas SAMA PERSIS dengan kode lama (12.5) supaya hasil
            // rekap tidak berubah: scan dengan jam < 12 dianggap masuk,
            // jam >= 13 dianggap pulang, jam 12:xx masuk kategori masuk.
            $clockIn = null;
            if ((int) $first->format('H') < 12.5) {
                $clockIn = $first->format('H:i:s');
            }

            // Jam pulang diambil dari scan PALING AKHIR (MAX di SQL), bukan
            // dari scan terakhir dalam urutan data.
            $clockOut = null;
            if ((int) $last->format('H') >= 12.5) {
                $clockOut = $last->format('H:i:s');
            }

            // Kasus tepi: hanya ada satu scan dan itu < 12:30 -> tetap masuk.
            if ($clockIn === null && $clockOut === null) {
                $clockIn = $first->format('H:i:s');
            }

            $modes = $modesByDate[$dateKey] ?? [];

            $grouped[$dateKey] = [
                'date'          => $dateKey,
                'formatted_date' => Carbon::parse($dateKey)->format('d-m-Y'),
                'day_name'      => Carbon::parse($dateKey)->translatedFormat('l'),
                'clock_in'      => $clockIn,
                'clock_out'     => $clockOut,
                'modes'         => $modes,
                'total_scans'   => (int) $row->jumlah_scan,
                'has_face'      => in_array('Wajah', $modes, true),
                'face_details'  => [],
            ];
        }

        return $grouped;
    }

    /**
     * Mode presensi unik per (PIN, tanggal) untuk BANYAK pin sekaligus.
     * Hasil: ['PIN' => ['Y-m-d' => ['Wajah','Fingerprint'], ...], ...]
     *
     * PENTING (2026-11-03): versi pertama optimasi ini memanggil query per
     * karyawan -> 50 query tambahan per halaman. Itu justru memperberat.
     * Sekarang SELURUH pin halaman diambil dalam SATU query, memakai
     * pemecahan CSV + CHARINDEX yang sama seperti query utama (aman dari
     * batas 2100 parameter dan tidak butuh STRING_SPLIT).
     *
     * Aman bila gagal: kembalikan array kosong, rekap tetap tampil.
     */
    private function loadModesPerDate(array $barcodes, string $startDate, string $endDate): array
    {
        $barcodes = array_values(array_unique(array_filter($barcodes, function ($v) {
            return $v !== '' && strpos($v, ',') === false;
        })));

        if (empty($barcodes)) {
            return [];
        }

        $out = [];

        try {
            foreach (array_chunk($barcodes, 800) as $chunk) {
                // PERBAIKAN 14 (2026-11-04): ganti CHARINDEX ke CSV dengan
                // whereIn biasa. Barcode sudah dinormalisasi (trim, tanpa koma)
                // sehingga tidak butuh pembatas koma lagi, dan SQL Server bisa
                // memakai index pada att_log.pin.
                // Jumlah pin per potongan 800 + 2 parameter tanggal = 802,
                // aman di bawah batas 2100 parameter SQL Server.
                $rows = $this->connCii()->table('att_log as a')
                    ->whereIn(DB::raw('LTRIM(RTRIM(a.pin))'), $chunk)
                    ->whereRaw(
                        "CONVERT(date, a.scan_date) BETWEEN ? AND ?",
                        [$startDate, $endDate]
                    )
                    ->select([
                        DB::raw('LTRIM(RTRIM(CONVERT(varchar(64), a.pin))) AS pin'),
                        DB::raw('CONVERT(date, a.scan_date) AS tgl'),
                        DB::raw("LTRIM(RTRIM(ISNULL(a.sn, ''))) AS sn"),
                        DB::raw('ISNULL(a.verifymode, 0) AS verifymode'),
                    ])
                    ->distinct()
                    ->get();

                foreach ($rows as $r) {
                    $pin = $this->normalizePin($r->pin);
                    $key = $r->tgl instanceof \DateTimeInterface
                        ? $r->tgl->format('Y-m-d')
                        : Carbon::parse($r->tgl)->format('Y-m-d');

                    $mode = $this->modeLabelFromValues($r->sn, $r->verifymode);

                    if (!isset($out[$pin][$key])) {
                        $out[$pin][$key] = [];
                    }
                    if (!in_array($mode, $out[$pin][$key], true)) {
                        $out[$pin][$key][] = $mode;
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Gagal ambil mode presensi rekap: ' . $e->getMessage());
            return [];
        }

        return $out;
    }

    /**
     * Label mode dari nilai mentah (sn + verifymode).
     * Dipisah dari modeLabel() supaya bisa dipakai pada hasil SELECT agregat.
     */
    private function modeLabelFromValues(?string $sn, $verifymode): string
    {
        $sn = strtoupper(trim((string) $sn));

        // Presensi wajah berbasis web menyimpan penanda khusus di kolom sn.
        if ($sn !== '' && (strpos($sn, 'FACE') !== false || strpos($sn, 'GPS') !== false)) {
            return 'Wajah';
        }

        // Mesin ZKTeco lama memakai verifymode 15 untuk verifikasi wajah.
        if ((int) $verifymode === 15) {
            return 'Wajah';
        }

        return 'Fingerprint';
    }

    /**
     * PERBAIKAN 18 (2026-11-04): penentu Clock In / Clock Out BERBASIS SHIFT.
     *
     * MASALAH YANG DIPERBAIKI
     * -----------------------
     * Keterangan di tabel "Riwayat Scan Hari Ini" sebelumnya diambil dari
     * kolom `face_attendance_logs.action` (lewat accessor action_label).
     * Kolom itu TIDAK selalu sepakat dengan kenyataan, sehingga muncul
     * kasus nyata: satu scan 07:55:23 tampil sebagai "Jam Masuk" di Rekap
     * Harian (karena att_log.inoutmode = 0) TETAPI "Clock Out" di Riwayat
     * Scan (karena kolom action berisi teks Clock Out).
     *
     * Selain itu rumus inoutmode pada AttendanceFingerController.php:948
     * adalah `count() + 1`, yang MUSTAHIL bernilai 0 (= Clock In). Jadi
     * inoutmode juga tidak bisa dipercaya untuk data hasil impor/manual.
     *
     * SOLUSI
     * ------
     * Keterangan ditentukan dari JAM SCAN dibandingkan JAM SHIFT karyawan
     * pada tanggal tersebut (tabel `shifts` + `employee_shifts`):
     *
     *   titik tengah = (work_start + work_end) / 2
     *   scan <  titik tengah  -> Clock In
     *   scan >= titik tengah  -> Clock Out
     *
     * Contoh shift 08:00-16:00 -> titik tengah 12:00. Scan 07:55 = Masuk,
     * scan 16:05 = Pulang.
     *
     * Mendukung shift malam (mis. 22:00-06:00 melintasi tengah malam) dan
     * scan yang terjadi sebelum tengah malam untuk shift berikutnya.
     *
     * Bila karyawan TIDAK punya jadwal shift pada tanggal itu, dipakai
     * shift DEFAULT 08:00-17:00 (titik tengah 12:30) supaya keterangan
     * tetap masuk akal dan tidak jatuh kembali ke `inoutmode` yang tidak
     * dapat dipercaya.
     *
     * @param  string  $npk        NPK karyawan
     * @param  string  $date       tanggal (Y-m-d)
     * @param  array   $scanTimes  daftar jam scan 'H:i:s' pada hari itu
     * @return array<string,string> map 'H:i:s' => 'Clock In' | 'Clock Out'
     */
    private function resolveShiftActions(string $npk, string $date, array $scanTimes): array
    {
        [$startMin, $endMin] = $this->shiftWindowMinutes($npk, $date);

        // Titik tengah shift. Bila shift melintasi tengah malam
        // (end <= start), akhir digeser +24 jam agar titik tengah berada
        // di antara kedua ujungnya.
        if ($endMin <= $startMin) {
            $endMin += 24 * 60;
        }
        $midpoint = ($startMin + $endMin) / 2;

        $result = [];
        foreach ($scanTimes as $time) {
            $parts = explode(':', (string) $time);
            $h = (int) ($parts[0] ?? 0);
            $i = (int) ($parts[1] ?? 0);
            $s = (int) ($parts[2] ?? 0);
            $t = $h * 60 + $i + ($s / 60);

            // Scan yang jauh lebih awal dari jam mulai shift dianggap milik
            // shift yang sama (mis. shift malam 22:00, scan 21:52). Tanpa
            // toleransi ini, scan sore hari untuk shift malam akan dihitung
            // sebagai "sudah lewat" dan salah dilabeli Clock Out.
            if ($t < $startMin - 180) {
                $t += 24 * 60;
            }

            $result[(string) $time] = ($t < $midpoint) ? 'Clock In' : 'Clock Out';
        }

        return $result;
    }

    /**
     * Ambil [menit_mulai, menit_selesai] shift karyawan pada tanggal tertentu.
     * Fallback ke shift DEFAULT 08:00-17:00 bila jadwal tidak ditemukan
     * atau terjadi error koneksi (sesuai ketentuan pemilik sistem).
     *
     * @return array{0:int,1:int}
     */
    private function shiftWindowMinutes(string $npk, string $date): array
    {
        // DEFAULT SHIFT: 08:00 - 17:00.
        // Dipakai bila karyawan tidak punya jadwal pada tanggal tersebut.
        $default = [8 * 60, 17 * 60];

        try {
            $row = $this->connCii()->table('employee_shifts as es')
                ->join('shifts as s', 's.id', '=', 'es.shift_id')
                ->where('es.npk', (string) $npk)
                ->whereDate('es.shift_date', $date)
                ->select('s.work_start', 's.work_end')
                ->first();

            if (!$row || $row->work_start === null || $row->work_end === null) {
                return $default;
            }

            return [
                $this->timeToMinutes($row->work_start),
                $this->timeToMinutes($row->work_end),
            ];
        } catch (\Throwable $e) {
            // Jangan gagalkan seluruh modal hanya karena tabel shift
            // belum ada / koneksi bermasalah. Cukup catat di log.
            Log::warning('Gagal membaca shift untuk NPK ' . $npk . ' tanggal ' . $date . ': ' . $e->getMessage());
            return $default;
        }
    }

    /** Konversi nilai kolom TIME ('08:00:00' / Carbon / DateTime) ke menit sejak 00:00. */
    private function timeToMinutes($value): int
    {
        if ($value instanceof \DateTimeInterface) {
            return ((int) $value->format('H')) * 60 + ((int) $value->format('i'))
                + (((int) $value->format('s')) / 60);
        }

        $parts = explode(':', (string) $value);

        return ((int) ($parts[0] ?? 0)) * 60 + ((int) ($parts[1] ?? 0));
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

            // PERBAIKAN 18 (2026-11-04): keterangan Clock In / Clock Out
            // ditentukan dari JAM SCAN vs JAM SHIFT, bukan dari kolom
            // `action` maupun `inoutmode`. Hitung sekali per (npk, tanggal)
            // supaya tidak query shift berulang untuk setiap baris.
            $shiftActionCache = [];

            foreach ($logs as $faceLog) {
                $dateKey = Carbon::parse($faceLog->scan_date)->format('Y-m-d');
                $timeKey = Carbon::parse($faceLog->scan_date)->format('H:i:s');
                $cacheKey = $faceLog->npk . '|' . $dateKey;

                if (!isset($shiftActionCache[$cacheKey])) {
                    // Kumpulkan seluruh jam scan karyawan pada hari itu,
                    // lalu tentukan In/Out-nya sekaligus.
                    $timesOfDay = $logs
                        ->filter(function ($x) use ($faceLog, $dateKey) {
                            return (string) $x->npk === (string) $faceLog->npk
                                && Carbon::parse($x->scan_date)->format('Y-m-d') === $dateKey;
                        })
                        ->map(function ($x) {
                            return Carbon::parse($x->scan_date)->format('H:i:s');
                        })
                        ->values()
                        ->all();

                    $shiftActionCache[$cacheKey] =
                        $this->resolveShiftActions((string) $faceLog->npk, $dateKey, $timesOfDay);
                }

                $label = $shiftActionCache[$cacheKey][$timeKey] ?? 'Clock In';

                $result[$faceLog->npk][] = [
                    'id'            => $faceLog->id,
                    'date'          => $dateKey,
                    'scan_time'     => $timeKey,
                    // Sumber tunggal: hasil perbandingan jam scan vs jam shift.
                    'action_type'   => $label,
                    'inoutmode'     => $label === 'Clock In' ? 0 : 1,
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
