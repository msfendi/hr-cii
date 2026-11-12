<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        return view('hris');
    }

    public function home()
    {
        return view('home');
    }

    public function welcome()
    {
        return view('loginwelcome');
    }

    public function getPKWTChart(Request $request)
    {
        // ambil dari table Rekap
        $query = DB::connection('cii')->table('Rekap')
            ->select('PKWT', 'BULAN', 'TAHUN')
            ->orderBy('ID', 'desc');

        $selectedMonth = $request->input('month');
        if ($selectedMonth) {
            $year = (int) substr($selectedMonth, 0, 4);
            $month = (int) substr($selectedMonth, 5, 2);

            $query->where(function ($q) use ($year, $month) {
                $q->where('TAHUN', '<', $year)
                    ->orWhere(function ($q2) use ($year, $month) {
                        $q2->where('TAHUN', '=', $year)
                            ->where('BULAN', '<=', $month);
                    });
            });
        }

        $rekap = $query->limit(12)
            ->get()
            ->sortBy([['TAHUN', 'asc'], ['BULAN', 'asc']])
            ->values();

        $labels = [];
        $pkwtData = [];
        $joinedData = [];
        $leftData = [];

        foreach ($rekap as $item) {
            $labels[] = Carbon::createFromDate($item->TAHUN, $item->BULAN, 1)->translatedFormat('M Y');
            $pkwtData[] = $item->PKWT;

            $joinedCount = DB::connection('cii')->table('PKWT')
                ->whereYear('TMK', $item->TAHUN)
                ->whereMonth('TMK', $item->BULAN)
                ->count();

            $leftCount = DB::connection('cii')->table('PKWT')
                ->whereYear('TKK', $item->TAHUN)
                ->whereMonth('TKK', $item->BULAN)
                ->count();

            $joinedData[] = $joinedCount;
            $leftData[] = $leftCount;
        }

        return response()->json([
            'labels' => $labels,
            'data' => $pkwtData,
            'joined' => $joinedData,
            'left' => $leftData,
        ]);
    }



    public function getRecapCount()
    {
        $pkwtCount = DB::connection('cii')->table('BIODATA')
            ->count();

        $deptNonSewingCount = DB::connection('cii')->table('DEPT')
            ->select('IS_SEWING')
            ->where('IS_SEWING', '1')
            ->count();

        $deptSewingCount = DB::connection('cii')
            ->table('DEPT')
            ->where('IS_SEWING', '0')
            ->where('DEPARTEMENT', 'LIKE', '%LINE%')
            ->whereNotIn('DEPARTEMENT', [
                'LINE BARU',
                'LINE TRAINING',
            ])
            ->count();

        $sewingEmployeesCount = DB::connection('cii')
            ->table('BIODATA')
            ->join('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->where('DEPT.IS_SEWING', '0')
            ->where('DEPT.DEPARTEMENT', 'LIKE', '%LINE%')
            ->count();

        $nonsewingEmployeesCount = DB::connection('cii')->table('BIODATA')->join('DEPT', 'BIODATA.ID_DEPT', 'DEPT.ID_DEPT')
            ->where('DEPT.IS_SEWING', '1')
            ->count();

        $maleEmployeeCount = DB::connection('cii')->table('BIODATA')
            ->where('JENIS_KEL', 'L')
            ->count();

        $femaleEmployeeCount = DB::connection('cii')->table('BIODATA')
            ->where('JENIS_KEL', 'P')
            ->count();

        $recentlyHired = DB::connection('cii')->table('PKWT')
            ->select('NPK', 'NAMA', 'TMK', 'BAGIAN', 'TKK')
            ->where('TMK', '>=', Carbon::now()->subMonths(1))
            ->orderBy('TMK', 'desc')
            ->get();

        // Count employees per department (Top 5)
        $mostEmployee = DB::connection('cii')
            ->table('BIODATA')
            ->join('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->select('DEPT.DEPARTEMENT as department_name', DB::raw('COUNT(BIODATA.NPK) as employee_count'))
            ->groupBy('DEPT.DEPARTEMENT', 'DEPT.ID_DEPT')
            ->orderBy('employee_count', 'desc')
            ->limit(5)
            ->get();

        // Count male and female per department
        $genderPerDept = DB::connection('cii')
            ->table('BIODATA')
            ->join('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->select(
                'DEPT.DEPARTEMENT as department_name',
                DB::raw("SUM(CASE WHEN BIODATA.JENIS_KEL = 'L' THEN 1 ELSE 0 END) as male_count"),
                DB::raw("SUM(CASE WHEN BIODATA.JENIS_KEL = 'P' THEN 1 ELSE 0 END) as female_count")
            )
            ->groupBy('DEPT.DEPARTEMENT', 'DEPT.ID_DEPT')
            ->orderBy('DEPT.DEPARTEMENT', 'asc')
            ->get();

        return response()->json([
            'totalpkwt' => $pkwtCount,
            'deptnonsewing' => $deptNonSewingCount,
            'deptsewing' => $deptSewingCount,
            'sewingemployees' => $sewingEmployeesCount,
            'nonsewingemployees' => $nonsewingEmployeesCount,
            'maleemployees' => $maleEmployeeCount,
            'femaleemployees' => $femaleEmployeeCount,
            'recentlyhired' => $recentlyHired,
            'mostemployee' => $mostEmployee,
            'genderperdept' => $genderPerDept,
        ]);
    }

    /**
     * Analisis turn over karyawan per departemen per bulan.
     * Karyawan MASUK dihitung dari PKWT.TMK, karyawan KELUAR dari PKWT.TKK.
     * Departemen diambil dari kolom PKWT.BAGIAN (satu baris PKWT = satu NPK).
     */
    public function getTurnover(Request $request)
    {
        $bulanInput = $request->input('month');
        try {
            $awal = $bulanInput ? Carbon::createFromFormat('Y-m', substr($bulanInput, 0, 7))->startOfMonth()
                : Carbon::now()->startOfMonth();
        } catch (\Exception $e) {
            $awal = Carbon::now()->startOfMonth();
        }
        $awalStr = $awal->toDateString();
        $akhirStr = $awal->copy()->endOfMonth()->toDateString();

        $dept = trim((string) $request->input('dept', 'ALL'));
        $semuaDept = ($dept === '' || strtoupper($dept) === 'ALL');
        $tanpaDept = ($dept === '__NODEPT__');

        // Mode pengelompokan line sewing: pisah (per line) | global (semua line menjadi SEWING)
        // | factory (Line 1-42 = FACTORY 2, Line 43-84 = FACTORY 1). Leader line TIDAK ikut digabung.
        $sewing = strtolower(trim((string) $request->input('sewing', 'pisah')));
        if (! in_array($sewing, ['pisah', 'global', 'factory'], true)) {
            $sewing = 'pisah';
        }

        $conn = DB::connection('cii');

        // Label departemen: baris tanpa BAGIAN tetap tampil agar total tetap utuh.
        // Bila mode sewing aktif, nama LINE dipetakan ke label kelompok.
        $labelDept = function ($bagian) use ($sewing) {
            $bagian = trim((string) $bagian);
            if ($bagian === '') {
                return '(Tanpa bagian)';
            }
            if ($sewing !== 'pisah' && preg_match('/^LINE\s*0*(\d+)$/i', $bagian, $m)) {
                $no = (int) $m[1];
                if ($sewing === 'global') {
                    return 'SEWING';
                }
                if ($no >= 1 && $no <= 42) {
                    return 'FACTORY 2';
                }
                if ($no >= 43 && $no <= 84) {
                    return 'FACTORY 1';
                }
            }
            return $bagian;
        };

        // Daftar departemen asli (dari data PKWT supaya selalu cocok).
        $daftarDeptRaw = $conn->table('PKWT')
            ->select('BAGIAN')
            ->whereNotNull('BAGIAN')
            ->where('BAGIAN', '<>', '')
            ->distinct()
            ->orderBy('BAGIAN')
            ->pluck('BAGIAN')
            ->map(function ($v) {
                return trim((string) $v);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Peta label kelompok -> daftar BAGIAN asli (dipakai filter whereIn saat mode sewing aktif).
        $anggotaGrup = [];
        foreach ($daftarDeptRaw as $b) {
            $anggotaGrup[$labelDept($b)][] = $b;
        }

        // Daftar departemen untuk pilihan filter: sudah dikelompokkan sesuai mode sewing.
        $daftarDept = collect($daftarDeptRaw)
            ->map(function ($v) use ($labelDept) {
                return $labelDept($v);
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        // Filter departemen yang dipakai berulang.
        $pakaiDept = function ($q) use ($semuaDept, $tanpaDept, $dept, $anggotaGrup) {
            if ($semuaDept) {
                return $q;
            }
            if ($tanpaDept) {
                return $q->where(function ($qq) {
                    $qq->whereNull('BAGIAN')->orWhere('BAGIAN', '=', '');
                });
            }
            if (isset($anggotaGrup[$dept])) {
                return $q->whereIn('BAGIAN', $anggotaGrup[$dept]);
            }
            return $q->where('BAGIAN', '=', $dept);
        };
        $base = function () use ($conn, $pakaiDept) {
            return $pakaiDept($conn->table('PKWT'));
        };

        // --- agregat bulan terpilih ---
        $masukRows = $pakaiDept($conn->table('PKWT'))
            ->select('BAGIAN', DB::raw('COUNT(*) as jml'))
            ->whereBetween('TMK', [$awalStr, $akhirStr])
            ->groupBy('BAGIAN')
            ->get();

        $keluarRows = $pakaiDept($conn->table('PKWT'))
            ->select('BAGIAN', DB::raw('COUNT(*) as jml'))
            ->whereBetween('TKK', [$awalStr, $akhirStr])
            ->groupBy('BAGIAN')
            ->get();

        // Karyawan aktif di awal bulan (masuk sebelum awal bulan, belum keluar).
        $awalRows = $pakaiDept($conn->table('PKWT'))
            ->select('BAGIAN', DB::raw('COUNT(*) as jml'))
            ->where('TMK', '<', $awalStr)
            ->where(function ($q) use ($awalStr) {
                $q->whereNull('TKK')->orWhere('TKK', '>=', $awalStr);
            })
            ->groupBy('BAGIAN')
            ->get();

        $kumpul = [];
        foreach ([['masuk', $masukRows], ['keluar', $keluarRows], ['awal', $awalRows]] as $pasang) {
            list($kunci, $rows) = $pasang;
            foreach ($rows as $r) {
                $nama = $labelDept($r->BAGIAN);
                if (!isset($kumpul[$nama])) {
                    $kumpul[$nama] = ['departemen' => $nama, 'awal' => 0, 'masuk' => 0, 'keluar' => 0];
                }
                $kumpul[$nama][$kunci] += (int) $r->jml;
            }
        }

        $perDepartemen = [];
        $totalAwal = 0;
        $totalMasuk = 0;
        $totalKeluar = 0;
        foreach ($kumpul as $baris) {
            $baris['net'] = $baris['masuk'] - $baris['keluar'];
            $baris['rate'] = $baris['awal'] > 0 ? round($baris['keluar'] / $baris['awal'] * 100, 2) : 0.0;
            $totalAwal += $baris['awal'];
            $totalMasuk += $baris['masuk'];
            $totalKeluar += $baris['keluar'];
            $perDepartemen[] = $baris;
        }
        usort($perDepartemen, function ($a, $b) {
            $ka = $a['masuk'] + $a['keluar'];
            $kb = $b['masuk'] + $b['keluar'];
            if ($ka === $kb) {
                return strcmp($a['departemen'], $b['departemen']);
            }
            return $kb - $ka;
        });

        // --- tren 12 bulan (sampai bulan terpilih) ---
        $mulaiTren = $awal->copy()->subMonths(11);
        $trenLabels = [];
        $trenMasuk = [];
        $trenKeluar = [];
        $trenRate = [];
        for ($i = 0; $i < 12; $i++) {
            $b = $mulaiTren->copy()->addMonths($i);
            $bs = $b->copy()->startOfMonth()->toDateString();
            $be = $b->copy()->endOfMonth()->toDateString();

            $m = $base()->whereBetween('TMK', [$bs, $be])->count();
            $k = $base()->whereBetween('TKK', [$bs, $be])->count();
            $h = $base()->where('TMK', '<', $bs)
                ->where(function ($q) use ($bs) {
                    $q->whereNull('TKK')->orWhere('TKK', '>=', $bs);
                })
                ->count();

            $trenLabels[] = $b->translatedFormat('M Y');
            $trenMasuk[] = $m;
            $trenKeluar[] = $k;
            $trenRate[] = $h > 0 ? round($k / $h * 100, 2) : 0.0;
        }

        return response()->json([
            'bulan' => $awal->format('Y-m'),
            'bulan_label' => $awal->translatedFormat('F Y'),
            'dept' => $semuaDept ? 'ALL' : $dept,
            'sewing' => $sewing,
            'ringkasan' => [
                'awal' => $totalAwal,
                'masuk' => $totalMasuk,
                'keluar' => $totalKeluar,
                'net' => $totalMasuk - $totalKeluar,
                'rate' => $totalAwal > 0 ? round($totalKeluar / $totalAwal * 100, 2) : 0.0,
            ],
            'per_departemen' => $perDepartemen,
            'tren' => [
                'labels' => $trenLabels,
                'masuk' => $trenMasuk,
                'keluar' => $trenKeluar,
                'rate' => $trenRate,
            ],
            'daftar_departemen' => $daftarDept,
        ]);
    }
}
