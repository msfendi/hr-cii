<?php

namespace App\Http\Controllers;

use App\Models\Biodata;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BiodataController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $biodatas = DB::connection('cii')->table('BIODATA')->select('*')->get();
        $departments = DB::connection('cii')->table('DEPT')->select('ID_DEPT', 'DEPARTEMENT')->where('SECTION', 'CHUTEX')->get();
        return view('biodata.index', compact('biodatas', 'departments'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        dd($request->all());
        // 'NPK',
        // 'NAMA_KARYAWAN',
        // 'BAG',
        // 'ID_DEPT',
        // 'JENIS_KEL',
        // 'BARCODE',
        // 'SECTION',
        // 'STATUS'

        // Biodata::create([
        //     'NPK' => $request->npk,
        //     'NAMA_KARYAWAN' => $request->nama_karyawan,
        //     'BAG' => $request->bag,
        //     'ID_DEPT' => $request->id_dept,
        //     'JENIS_KEL' => $request->jenis_kel,
        //     'BARCODE' => $request->barcode,
        //     'SECTION' => $request->section,
        //     'STATUS' => $request->status,
        // ]);
    }

    /**
     * Display the specified resource.
     */
    public function show($NPK)
    {
        $biodata = DB::connection('cii')->table('PKWT')->select('*')->where('NPK', $NPK)->first();
        return response()->json($biodata);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Biodata $biodata)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Biodata $biodata)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Biodata $biodata)
    {
        //
    }
}
