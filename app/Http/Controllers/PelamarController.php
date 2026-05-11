<?php

namespace App\Http\Controllers;

use App\Models\Pelamar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Imports\PelamarImport;
use App\Exports\PelamarTemplateExport;
use Maatwebsite\Excel\Facades\Excel;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Storage;
use Psy\Util\Str as PsyStr;
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
            ->orderBy('NPK', 'ASC')
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

    public function exportTemplate()
    {
        return Excel::download(new PelamarTemplateExport(), 'Template_Import_Pelamar.xlsx');
    }

    public function assign(Request $request)
    {
        try {
            $check_nama = DB::connection('cii')->table('PKWT')->where('KTP', $request->nik)->where('NAMA', $request->nama)->where('TKK', null)->first();
            if ($check_nama != null) {
                $this->handleExistingEmployee($request);
            } else {
                $this->handleNewEmployee($request);
            }
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

    private function handleNewEmployee($request)
    {
        // jika karyawan baru dan tidak pernah exist di pkwt
        DB::connection('cii')->beginTransaction();

        $id_pelamar = $request->id_pelamar;

        $tgl_lahir = Carbon::parse($request->tgl_lahir);
        $diff = $tgl_lahir->diff(Carbon::now());
        $umur_string = $diff->y . ' Tahun ' . $diff->m . ' Bulan ' . $diff->d . ' Hari';

        $last_barcode = DB::connection('cii')->table('BIODATA')->orderBy('NPK', 'DESC')->orderBy('BARCODE', 'DESC')->first()->BARCODE + 1;
        DB::connection('cii')->table('PELAMAR')->where('ID', $id_pelamar)->update(
            [
                'NPK' => strtoupper($request->npk),
                'NAMA' => strtoupper($request->nama),
                'JENIS_KELAMIN' => strtoupper($request->jk),
                'TMPT_LAHIR' => strtoupper($request->tempat_lahir) ?? '',
                'TGL_LAHIR' => $request->tgl_lahir,
                'TMK' => $request->tmk,
                'UMUR' => $umur_string,
                'ALAMAT_LENGKAP' => strtoupper($request->alamat) ?? '',
                'KABUPATEN' => strtoupper($request->kabupaten)  ?? '',
                'PENDIDIKAN' => strtoupper($request->pendidikan) ?? '',
                'NAMA_SEKOLAH' => strtoupper($request->sekolah) ?? '',
                'KABUPATEN_SEKOLAH' => strtoupper($request->kabupaten_sekolah) ?? '',
                'JURUSAN' => strtoupper($request->jurusan) ?? '',
                'TINGGI_BADAN' => $request->tb ?? 0,
                'BERAT_BADAN' => $request->bb ?? 0,
                'HP' => $request->hp ?? '',
                'AGAMA' => strtoupper($request->agama) ?? '',
                'NIK' => $request->nik,
                'NO_KK' => $request->no_kk ?? '',
                'IBU' => strtoupper($request->ibu) ?? '',
                'STATUS' => strtoupper($request->status) ?? '',
                'TANGGUNGAN' => $request->tanggungan ?? '',
                'IS_KONTRAK' => 'TRUE',
            ]
        );

        DB::connection('cii')->table('BIODATA')->insert([
            'NPK' => strtoupper($request->npk),
            'NAMA_KARYAWAN' => strtoupper($request->nama),
            'BAG' => strtoupper($request->bag),
            'ID_DEPT' => $request->id_dept,
            'JENIS_KEL' => strtoupper($request->jk),
            'BARCODE' => $last_barcode,
            'SECTION' => 'CHUTEX',
            'STATUS' => 'A',
            'IS_STAFF' => $request->has('is_staff') ? 1 : 0,
        ]);

        $dept = DB::connection('cii')->table('DEPT')->select('*')->where('ID_DEPT', $request->id_dept)->first();
        $pelamar = DB::connection('cii')->table('PELAMAR')->where('ID', $id_pelamar)->first();

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
            'BAGIAN' => strtoupper($dept->DEPARTEMENT),
            'ALAMAT' => strtoupper($request->alamat),
            'KABUPATEN' => strtoupper($request->kabupaten),
            'KTP' => $request->nik,
            'NO_KK' => $request->no_kk,
            'IBU' => strtoupper($pelamar->IBU),
            'HP' => $request->hp,
            'STATUS' => strtoupper($request->status),
            'TANGGUNGAN' => $request->tanggungan,
            'KETERANGAN' => $request->keterangan,
            'TUTUPBUKU' => strtoupper('TUTUP BUKU TANGGAL 30 / 31'),
            'TMPTLAHIR' => strtoupper($request->tempat_lahir),
            'NOREK' => $request->norek,
            'JURUSAN' => strtoupper($request->jurusan),
            'FASKES' => strtoupper($request->faskes),
        ]);

        // Insert kontrak pertama ke employees_contract
        $duration = (int) ($request->month_duration ?? 6);
        $startDate = Carbon::parse($request->tmk);
        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)
            : $startDate->copy()->addMonths($duration)->subDay();

        DB::table('employees_contract')->insert([
            'id'              => (string) \Illuminate\Support\Str::uuid(),
            'npk'             => strtoupper($request->npk),
            'contract_ke'     => 1,
            'start_date'      => $startDate->toDateString(),
            'end_date'        => $endDate->toDateString(),
            'month_duration'  => (string) $duration,
            'status_contract' => 'AKTIF',
            'salary'          => (float) str_replace('.', '', $request->salary_raw ?? $request->salary ?? 2500000),
            'allowance'       => (float) str_replace('.', '', $request->allowance_raw ?? $request->allowance ?? 0),
            'pph21'           => (float) str_replace('.', '', $request->pph21_raw ?? $request->pph21 ?? 0),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        DB::connection('cii')->commit();
    }

    private function handleExistingEmployee($request)
    {
        DB::connection('cii')->beginTransaction();
        $check_nama = DB::connection('cii')->table('PKWT')->where('KTP', $request->nik)->where('NAMA', $request->nama)->where('TKK', null)->first();
        // jika ada nik dan nama yang sama dan pernah exist di pkwt
        
        DB::connection('cii')->table('PKWT_OUT')->insert([
            'NPK' => $check_nama->NPK,
            'NAMA' => $check_nama->NAMA,
            'JK' => $check_nama->JK,
            'TGLLAHIR' => $check_nama->TGLLAHIR,
            'PDDK' => $check_nama->PDDK,
            'AGAMA' => $check_nama->AGAMA,
            'TMK' => $check_nama->TMK,
            'USIA' => $check_nama->USIA,
            'TKK' => $check_nama->TKK,
            'BAGIAN' => $check_nama->BAGIAN,
            'ALAMAT' => $check_nama->ALAMAT,
            'KABUPATEN' => $check_nama->KABUPATEN,
            'KTP' => $check_nama->KTP,
            'NO_KK' => $check_nama->NO_KK,
            'IBU' => $check_nama->IBU,
            'HP' => $check_nama->HP,
            'STATUS' => $check_nama->STATUS,
            'TANGGUNGAN' => $check_nama->TANGGUNGAN,
            'KETERANGAN' => $check_nama->KETERANGAN,
            'TUTUPBUKU' => $check_nama->TUTUPBUKU,
        ]);
        // delete from pkwt
        DB::connection('cii')->table('PKWT')->where('NIK', $check_nama->KTP)->where('NAMA', $check_nama->NAMA)->delete();

        $tgl_lahir = Carbon::parse($request->tgl_lahir);
        $diff = $tgl_lahir->diff(Carbon::now());
        $umur_string = $diff->y . ' Tahun ' . $diff->m . ' Bulan ' . $diff->d . ' Hari';
        $last_barcode = DB::connection('cii')->table('BIODATA')->orderBy('NPK', 'DESC')->orderBy('BARCODE', 'DESC')->first()->BARCODE + 1;
        DB::connection('cii')->table('PELAMAR')->where('ID', $request->id_pelamar)->update(
            [
                'NPK' => strtoupper($request->npk),
                'NAMA' => strtoupper($request->nama),
                'JENIS_KELAMIN' => strtoupper($request->jk),
                'TMPT_LAHIR' => strtoupper($request->tempat_lahir),
                'TGL_LAHIR' => $request->tgl_lahir,
                'TMK' => $request->tmk,
                'UMUR' => $umur_string,
                'ALAMAT_LENGKAP' => strtoupper($request->alamat),
                'KABUPATEN' => strtoupper($request->kabupaten),
                'PENDIDIKAN' => strtoupper($request->pendidikan),
                'NAMA_SEKOLAH' => strtoupper($request->sekolah),
                'KABUPATEN_SEKOLAH' => strtoupper($request->kabupaten_sekolah),
                'JURUSAN' => strtoupper($request->jurusan),
                'TINGGI_BADAN' => $request->tb,
                'BERAT_BADAN' => $request->bb,
                'HP' => $request->hp,
                'AGAMA' => strtoupper($request->agama),
                'NIK' => $request->nik,
                'NO_KK' => $request->no_kk,
                'IBU' => strtoupper($request->ibu),
                'STATUS' => strtoupper($request->status),
                'TANGGUNGAN' => $request->tanggungan,
                'IS_KONTRAK' => 'TRUE',
            ]
        );

        DB::connection('cii')->table('BIODATA')->insert([
            'NPK' => strtoupper($request->npk),
            'NAMA_KARYAWAN' => strtoupper($request->nama),
            'BAG' => strtoupper($request->bag),
            'ID_DEPT' => $request->id_dept,
            'JENIS_KEL' => strtoupper($request->jk),
            'BARCODE' => $last_barcode,
            'SECTION' => 'CHUTEX',
            'STATUS' => 'A',
            'IS_STAFF' => $request->has('is_staff') ? 1 : 0,
        ]);

        $dept = DB::connection('cii')->table('DEPT')->select('*')->where('ID_DEPT', $request->id_dept)->first();


        DB::connection('cii')->table('PKWT')->insert([
            'NPK' => $request->npk,
            'NAMA' => $request->nama,
            'JK' => $request->jk,
            'TGLLAHIR' => $request->tgl_lahir,
            'PDDK' => $request->pendidikan,
            'AGAMA' => $request->agama,
            'TMK' => $request->tmk,
            'USIA' => $request->umur,
            'TKK' => $request->tkk,
            'BAGIAN' => $dept->DEPARTEMENT,
            'ALAMAT' => $request->alamat,
            'KABUPATEN' => $request->kabupaten,
            'KTP' => $request->nik,
            'NO_KK' => $request->no_kk,
            'IBU' => $request->ibu,
            'HP' => $request->hp,
            'STATUS' => $request->status,
            'TANGGUNGAN' => $request->tanggungan,
            'KETERANGAN' => $request->keterangan,
            'TUTUPBUKU' => strtoupper('TUTUP BUKU TANGGAL 30 / 31'),
            'TMPTLAHIR' => strtoupper($request->tempat_lahir),
            'NOREK' => $request->norek,
            'JURUSAN' => strtoupper($request->jurusan),
            'FASKES' => strtoupper($request->faskes),
        ]);

        // Cek kontrak ke berapa (untuk karyawan lama yang kembali)
        $existingContracts = DB::table('employees_contract')
            ->where('npk', strtoupper($request->npk))
            ->count();
        $contractKe = $existingContracts + 1;

        $duration = (int) ($request->month_duration ?? 6);
        $startDate = Carbon::parse($request->tmk);
        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)
            : $startDate->copy()->addMonths($duration)->subDay();

        DB::table('employees_contract')->insert([
            'id'              => (string) Str::uuid(),
            'npk'             => strtoupper($request->npk),
            'contract_ke'     => $contractKe,
            'start_date'      => $startDate->toDateString(),
            'end_date'        => $endDate->toDateString(),
            'month_duration'  => (string) $duration,
            'status_contract' => 'AKTIF',
            'salary'          => (float) str_replace('.', '', $request->salary_raw ?? $request->salary ?? 2500000),
            'allowance'       => (float) str_replace('.', '', $request->allowance_raw ?? $request->allowance ?? 0),
            'pph21'           => (float) str_replace('.', '', $request->pph21_raw ?? $request->pph21 ?? 0),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        DB::connection('cii')->commit();
        Alert::success('Success', 'Data berhasil disimpan');
        return redirect()->route('pelamar.index');
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
