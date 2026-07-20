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
}
