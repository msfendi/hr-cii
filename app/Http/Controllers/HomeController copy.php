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
        return view('home');
    }

    public function getPKWTChart()
    {
        $pkwts = DB::connection('cii')->table('PKWT')
            ->select('TMK')
            ->where('TMK', '>=', Carbon::now()->subMonths(5))
            ->orderBy('TMK', 'asc')
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

        return response()->json([
            'totalpkwt' => $pkwtCount,
            'deptnonsewing' => $deptNonSewingCount,
            'deptsewing' => $deptSewingCount,
        ]);
    }
}
