<?php

namespace App\Http\Controllers;

use App\Models\PKWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PKWTController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $pkwts = DB::connection('cii')->table('PKWT')->select('NPK', 'NAMA', 'JK', 'TGLLAHIR', 'TMK', 'TKK', 'USIA', 'KTP', 'BAGIAN', 'TUTUPBUKU')->where('TKK', null)->get();
        return view('pkwt.index', compact('pkwts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(PKWT $pKWT)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PKWT $pKWT)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PKWT $pKWT)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PKWT $pKWT)
    {
        //
    }
}
