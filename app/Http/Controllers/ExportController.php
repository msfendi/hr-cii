<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExportController extends Controller
{
    public function exportAbsenManual()
    {
        $employees = DB::connection('cii')->table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'DEPARTEMENT')
            ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->orderBy('DEPARTEMENT', 'ASC')
            ->orderBy('NPK', 'ASC')
            ->get();
        
        // dd($employees);
        return view('template.absen-manual', compact('employees'));
    }
}
