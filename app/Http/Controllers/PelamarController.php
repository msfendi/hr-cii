<?php

namespace App\Http\Controllers;

use App\Models\Pelamar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Imports\PelamarImport;
use Maatwebsite\Excel\Facades\Excel;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Storage;
use Psy\Util\Str;
use Carbon\Carbon;

class PelamarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $pelamars = DB::connection('cii')->table('PELAMAR')
            ->select('ID', 'NPK', 'NAMA', 'JENIS_KELAMIN', 'TMPT_LAHIR', 'TGL_LAHIR', 'TMK', 'UMUR', 'NIK', 'KABUPATEN', 'HP') // Added ID
            ->where('IS_KONTRAK', 'FALSE')
            ->orderBy('NPK', 'DESC')
            ->get();

        $departments = DB::connection('cii')->table('DEPT')->select('ID_DEPT', 'DEPARTEMENT')->where('SECTION', 'CHUTEX')->get(); // Fetch departments

        return view('pelamar.index', compact('pelamars', 'departments'));
    }

    public function import(Request $request)
    {
        $this->validate($request, [
            'file' => 'required|mimes:xls,xlsx'
        ]);

        $file = $request->file('file');
        $nama_file = $file->hashName();
        $path = $file->storeAs('public/excel/', $nama_file);

        $import = Excel::import(new PelamarImport(), storage_path('app/public/excel/' . $nama_file));
        Storage::delete($path);

        if ($import) {
            Alert::success('Import Successfully!', 'Pelamar data successfully imported!');
            return redirect()->route('pelamar.index');
        } else {
            return redirect()->back()->with('error', 'Failed to import data');
        }
    }

    public function assign(Request $request)
    {
        // $request->validate([
        //     'id_pelamar' => 'required',
        //     'id_dept' => 'required',
        //     'tmk' => 'required|date',
        //     'npk' => 'required',
        //     'nama' => 'required',
        //     'nik' => 'required',
        //     'jk' => 'required',
        //     'tempat_lahir' => 'required',
        //     'tgl_lahir' => 'required|date',
        //     'umur' => 'required|numeric',
        //     'agama' => 'required',
        //     'status' => 'required',
        //     'hp' => 'required',
        //     'alamat' => 'required',
        //     'kabupaten' => 'required',
        //     'pendidikan' => 'required',
        //     'sekolah' => 'required',
        //     'jurusan' => 'required',
        //     'tb' => 'required|numeric',
        //     'bb' => 'required|numeric',
        // ]);

        try {
            DB::connection('cii')->beginTransaction();
            $id_pelamar = $request->id_pelamar;

            $tgl_lahir = Carbon::parse($request->tgl_lahir);
            $diff = $tgl_lahir->diff(Carbon::now());
            $umur_string = $diff->y . ' Tahun ' . $diff->m . ' Bulan ' . $diff->d . ' Hari';

            // Updated Data from Form
            $updateData = [
                'NPK' => strtoupper($request->npk),
                'NAMA' => strtoupper($request->nama),
                'NIK' => $request->nik,
                'JENIS_KELAMIN' => strtoupper($request->jk),
                'TMPT_LAHIR' => strtoupper($request->tmpt_lahir),
                'TGL_LAHIR' => $request->tgl_lahir,
                'UMUR' => strtoupper($umur_string),
                'AGAMA' => strtoupper($request->agama),
                'STATUS' => strtoupper($request->status),
                'HP' => $request->hp,
                'ALAMAT_LENGKAP' => strtoupper($request->alamat),
                'KABUPATEN' => strtoupper($request->kabupaten),
                'PENDIDIKAN' => strtoupper($request->pendidikan),
                'NAMA_SEKOLAH' => strtoupper($request->sekolah),
                'JURUSAN' => strtoupper($request->jurusan),
                'TINGGI_BADAN' => $request->tb,
                'BERAT_BADAN' => $request->bb,
                'IS_KONTRAK' => 'TRUE',
                'TMK' => $request->tmk
            ];

            $last_barcode = DB::connection('cii')->table('BIODATA')->orderBy('NPK', 'DESC')->orderBy('BARCODE', 'DESC')->first()->BARCODE + 1;
            DB::connection('cii')->table('PELAMAR')->where('ID', $id_pelamar)->update($updateData);
            DB::connection('cii')->table('BIODATA')->insert([
                'NPK' => strtoupper($request->npk),
                'NAMA_KARYAWAN' => strtoupper($request->nama),
                'BAG' => strtoupper($request->bag),
                'ID_DEPT' => $request->id_dept,
                'JENIS_KEL' => strtoupper($request->jk),
                'BARCODE' => $last_barcode,
                'SECTION' => 'CHUTEX',
                'STATUS' => 'A',
            ]);

            DB::connection('cii')->table('PKWT')->insert([
                'NPK' => strtoupper($request->npk),
                'NAMA' => strtoupper($request->nama),
                'JK' => strtoupper($request->jk),
                'TGLLAHIR' => $request->tgl_lahir,
                'PDDK' => strtoupper($request->pendidikan),
                'AGAMA' => strtoupper($request->agama),
                'TMK' => $request->tmk,
                'USIA' => $umur_string,
                'TKK' => $request->tkk,
                'BAGIAN' => strtoupper($request->bag),
                'ALAMAT' => strtoupper($request->alamat),
                'KABUPATEN' => strtoupper($request->kabupaten),
                'KTP' => $request->nik,
                'NO_KK' => $request->no_kk,
                'IBU' => strtoupper($request->ibu),
                'HP' => $request->hp,
                'STATUS' => strtoupper($request->status),
                'TANGGUNGAN' => $request->tanggungan,
                'KETERANGAN' => $request->keterangan,
                'TUTUPBUKU' => strtoupper($request->tutupbuku),
                'TMPTLAHIR' => strtoupper($request->tmpt_lahir),
                'NOREK' => $request->norek,
                'JURUSAN' => strtoupper($request->jurusan),
                'FASKES' => strtoupper($request->faskes),
            ]);

            DB::connection('cii')->commit();

            Alert::success('Success', 'Pelamar berhasil di-assign ke Biodata & PKWT');
            return redirect()->route('pelamar.index');
        } catch (\Exception $e) {
            DB::connection('cii')->rollBack();
            Alert::error('Error', 'Gagal memproses data: ' . $e->getMessage());
            return redirect()->back();
        }
    }

    public function detail($id)
    {
        $pelamar = DB::connection('cii')->table('PELAMAR')->where('ID', $id)->first();
        return response()->json($pelamar);
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
    public function show(Pelamar $pelamar)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Pelamar $pelamar)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Pelamar $pelamar)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Pelamar $pelamar)
    {
        //
    }
}
