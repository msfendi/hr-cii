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

    public function getPKWTChart()
    {
        // $pkwts = DB::connection('cii')->table('PKWT')
        //     ->select('TMK')
        //     ->where('TMK', '>=', Carbon::now()->subMonths(5))
        //     ->orderBy('TMK', 'asc')
        //     ->get();

        // $grouped = $pkwts->groupBy(function ($item) {
        //     return Carbon::parse($item->TMK)->format('F'); // Group by Month name
        // });

        // $labels = $grouped->keys();
        // $data = $grouped->map(function ($group) {
        //     return $group->count();
        // })->values();

        // ambil dari table Rekap
        $rekap = DB::connection('cii')->table('Rekap')
            ->select('PKWT', 'BULAN', 'TAHUN')
            ->orderBy('ID', 'desc')
            ->limit(5)
            ->get()
            ->sortBy([['TAHUN', 'asc'], ['BULAN', 'asc']])
            ->values();

        $labels = $rekap->map(function ($item) {
            return Carbon::createFromDate($item->TAHUN, $item->BULAN, 1)->translatedFormat('F') . ' ' . $item->TAHUN;
        });

        $data = $rekap->pluck('PKWT');

        return response()->json([
            'labels' => $labels,
            'data' => $data,
        ]);
    }

    // get pktw tkk != null and group by month
    public function getPKWTTKKChart()
    {
        $pkwts = DB::connection('cii')->table('PKWT')
            ->select('TKK')
            ->where('TKK', '>=', Carbon::now()->subMonths(5))
            ->orderBy('TKK', 'asc')
            ->get();

        $grouped = $pkwts->groupBy(function ($item) {
            return Carbon::parse($item->TMK)->format('F'); // Group by Month name
        });

        $labels = $grouped->keys();
        $data = $grouped->map(function ($group) {
            return $group->count();
        })->values();

        return response()->json([
            'labels' => $labels,
            'data' => $data,
        ]);
    }

    public function getRecapCount()
    {
        $pkwtCount = DB::connection('cii')->table('PKWT')
            ->select('NPK')
            ->where('TKK', null)
            ->count();

        $deptNonSewingCount = DB::connection('cii')->table('DEPT')
            ->select('IS_SEWING')
            ->where('IS_SEWING', '1')
            ->count();

        $deptSewingCount = DB::connection('cii')->table('DEPT')
            ->select('IS_SEWING')
            ->where('IS_SEWING', '0')
            ->count();

        $sewingEmployeesCount = DB::connection('cii')->table('BIODATA')->join('DEPT', 'BIODATA.ID_DEPT', 'DEPT.ID_DEPT')
            ->where('DEPT.IS_SEWING', '0')
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
            ->limit(5)
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
        ]);
    }
}
