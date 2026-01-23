<?php

namespace App\Http\Controllers;

use App\Models\Biodata;
use App\Models\PKWT;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;
use App\Exports\PKWTExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

class BiodataController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $biodatas = DB::connection('cii')->table('BIODATA')->select('BIODATA.NPK', 'BIODATA.NAMA_KARYAWAN', 'BIODATA.ID_DEPT', 'DEPT.DEPARTEMENT')->join('DEPT', 'BIODATA.ID_DEPT', 'DEPT.ID_DEPT')->get();
        $departments = DB::connection('cii')->table('DEPT')->select('ID_DEPT', 'DEPARTEMENT')->where('SECTION', 'CHUTEX')->get();
        return view('biodata.index', compact('biodatas', 'departments'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $check_nama = DB::connection('cii')->select("SELECT * FROM PKWT WHERE NAMA = '" . $request->nama . "' AND KTP = '" . $request->nik . "'");

            if (!empty($check_nama) && $check_nama[0]->TKK == null) {
                Alert::error('Error', 'Karyawan ' . $check_nama[0]->NAMA . ' dengan NIK ' . $check_nama[0]->KTP . ' masih aktif.');
                return redirect()->back();
            }

            // Ketika karyawan pernah ada di PKWT dan TKK isi
            if (!empty($check_nama) && $check_nama[0]->TKK != null) {
                $last_barcode = DB::connection('cii')->table('BIODATA')->orderBy('BARCODE', 'desc')->first()->BARCODE;
                $barcode = $last_barcode + 1;

                DB::connection('cii')->beginTransaction();
                DB::connection('cii')->table('BIODATA')->insert([
                    'NPK' => strtoupper($request->npk),
                    'NAMA_KARYAWAN' => strtoupper($request->nama),
                    'ID_DEPT' => $request->id_dept,
                    'JENIS_KEL' => strtoupper($request->jk),
                    'BARCODE' => strtoupper($barcode),
                    'SECTION' => 'CHUTEX',
                    'STATUS' => 'A',
                ]);

                DB::connection('cii')->table('PKWT_OUT')->insert([
                    'NPK' => strtoupper($check_nama[0]->NPK),
                    'NAMA' => strtoupper($check_nama[0]->NAMA),
                    'JK' => strtoupper($check_nama[0]->JK),
                    'TGLLAHIR' => $check_nama[0]->TGLLAHIR,
                    'TMPTLAHIR' => strtoupper($check_nama[0]->TMPTLAHIR),
                    'PDDK' => strtoupper($check_nama[0]->PDDK),
                    'AGAMA' => strtoupper($check_nama[0]->AGAMA),
                    'TMK' => $check_nama[0]->TMK,
                    'USIA' => $check_nama[0]->USIA,
                    'BAGIAN' => strtoupper($check_nama[0]->BAGIAN),
                    'ALAMAT' => strtoupper($check_nama[0]->ALAMAT),
                    'KABUPATEN' => strtoupper($check_nama[0]->KABUPATEN),
                    'KTP' => $check_nama[0]->KTP,
                    'NO_KK' => $check_nama[0]->NO_KK,
                    'IBU' => strtoupper($check_nama[0]->IBU),
                    'HP' => $check_nama[0]->HP,
                    'STATUS' => $check_nama[0]->STATUS,
                    'TANGGUNGAN' => $check_nama[0]->TANGGUNGAN,
                    'TMPTLAHIR' => strtoupper($check_nama[0]->TMPTLAHIR),
                    'JURUSAN' => strtoupper($check_nama[0]->JURUSAN)
                ]);

                // delete from PKWT
                DB::connection('cii')->table('PKWT')->where('NPK', strtoupper($check_nama[0]->NPK))->delete();

                $tgl_lahir = Carbon::parse($request->tgl_lahir);
                $diff = $tgl_lahir->diff($request->tmk);
                $umur_string = $diff->y . ' Tahun ' . $diff->m . ' Bulan ' . $diff->d . ' Hari';

                $dept = DB::connection('cii')->table('DEPT')->select('DEPARTEMENT')->where('ID_DEPT', $request->id_dept)->first();

                // insert into PKWT
                DB::connection('cii')->table('PKWT')->insert([
                    'NPK' => strtoupper($request->npk),
                    'NAMA' => strtoupper($request->nama),
                    'JK' => strtoupper($request->jk),
                    'TGLLAHIR' => $request->tgl_lahir,
                    'TMPTLAHIR' => strtoupper($request->tempat_lahir),
                    'PDDK' => strtoupper($request->pendidikan),
                    'AGAMA' => strtoupper($request->agama),
                    'TMK' => $request->tmk,
                    'USIA' => $umur_string,
                    'BAGIAN' => strtoupper($dept->DEPARTEMENT),
                    'ALAMAT' => strtoupper($request->alamat),
                    'KABUPATEN' => strtoupper($request->kabupaten),
                    'KTP' => $request->nik,
                    'NO_KK' => $request->nkk,
                    'IBU' => strtoupper($request->ibu),
                    'HP' => $request->hp,
                    'STATUS' => $request->status,
                    'TANGGUNGAN' => $request->tanggungan,
                    'TMPTLAHIR' => strtoupper($request->tempat_lahir),
                    'JURUSAN' => strtoupper($request->jurusan)
                ]);

                DB::connection('cii')->commit();
                Alert::success('Success', 'Data berhasil ditambahkan');
                return redirect()->route('biodata.index');
            }

            DB::connection('cii')->beginTransaction();

            $last_barcode = DB::connection('cii')->table('BIODATA')->orderBy('BARCODE', 'desc')->first()->BARCODE;
            $barcode = $last_barcode + 1;

            DB::connection('cii')->table('BIODATA')->insert([
                'NPK' => strtoupper($request->npk),
                'NAMA_KARYAWAN' => strtoupper($request->nama),
                'ID_DEPT' => $request->id_dept,
                'JENIS_KEL' => strtoupper($request->jk),
                'BARCODE' => strtoupper($barcode),
                'SECTION' => 'CHUTEX',
                'STATUS' => 'A',
            ]);

            $tgl_lahir = Carbon::parse($request->tgl_lahir);
            $diff = $tgl_lahir->diff($request->tmk);
            $umur_string = $diff->y . ' Tahun ' . $diff->m . ' Bulan ' . $diff->d . ' Hari';

            $dept = DB::connection('cii')->table('DEPT')->select('DEPARTEMENT')->where('ID_DEPT', $request->id_dept)->first();

            DB::connection('cii')->table('PKWT')->insert([
                'NPK' => strtoupper($request->npk),
                'NAMA' => strtoupper($request->nama),
                'JK' => strtoupper($request->jk),
                'TGLLAHIR' => $request->tgl_lahir,
                'TMPTLAHIR' => strtoupper($request->tempat_lahir),
                'PDDK' => strtoupper($request->pendidikan),
                'AGAMA' => strtoupper($request->agama),
                'TMK' => $request->tmk,
                'USIA' => $umur_string,
                'BAGIAN' => strtoupper($dept->DEPARTEMENT),
                'ALAMAT' => strtoupper($request->alamat),
                'KABUPATEN' => strtoupper($request->kabupaten),
                'KTP' => $request->nik,
                'NO_KK' => $request->nkk,
                'IBU' => strtoupper($request->ibu),
                'HP' => $request->hp,
                'STATUS' => $request->status,
                'TANGGUNGAN' => $request->tanggungan,
                'TMPTLAHIR' => strtoupper($request->tempat_lahir),
                'JURUSAN' => strtoupper($request->jurusan)
            ]);

            DB::connection('cii')->commit();
            Alert::success('Success', 'Data berhasil disimpan');
            return redirect()->route('biodata.index');
        } catch (\Exception $e) {
            DB::connection('cii')->rollBack();
            Alert::error('Error', 'Data gagal disimpan ' . $e->getMessage());
            return redirect()->back();
        }
    }

    // fetch last npk
    public function fetchLastNpk()
    {
        $last_npk = DB::connection('cii')->table('PKWT')->select('NPK')->orderBy('NPK', 'desc')->first()->NPK;
        $explode_npk = explode('-', $last_npk);
        $incr_npk = $explode_npk[1] + 1;
        $format_npk = str_pad($incr_npk, 5, '0', STR_PAD_LEFT);
        $new_npk = 'C-' . $format_npk;
        return response()->json($new_npk);
    }

    public function exit(Request $request, $NPK)
    {
        // jika karyawan keluar, pindahkan data ke biodata_keluar, hapus data dari table biodata, update tkk di pkwt
        try {
            DB::connection('cii')->beginTransaction();
            $biodata = DB::connection('cii')->table('BIODATA')->where('NPK', $NPK)->first();
            $pkwt = DB::connection('cii')->table('PKWT')->where('NPK', $NPK)->first();

            if (!$biodata) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data Biodata tidak ditemukan'
                ], 404);
            }

            DB::connection('cii')->table('BIODATA_KELUAR')->insert([
                'NPK' => $biodata->NPK,
                'NAMA_KARYAWAN' => $biodata->NAMA_KARYAWAN,
                'ID_DEPT' => $biodata->ID_DEPT,
                'JENIS_KEL' => $biodata->JENIS_KEL,
                'BARCODE' => $biodata->BARCODE,
                'SECTION' => $biodata->SECTION,
                'STATUS' => $biodata->STATUS,
            ]);

            DB::connection('cii')->table('BIODATA')->where('NPK', $NPK)->delete();

            if ($pkwt) {
                DB::connection('cii')->table('PKWT')->where('NPK', $NPK)->update([
                    'TKK' => $request->tkk,
                ]);
            }


            DB::connection('cii')->commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Data berhasil dihapus'
            ]);
        } catch (\Throwable $th) {
            DB::connection('cii')->rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Data gagal dihapus: ' . $th->getMessage()
            ], 500);
        }
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
     * Update the specified resource in storage.
     */
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $NPK)
    {
        try {
            DB::connection('cii')->beginTransaction();

            $dept = DB::connection('cii')->table('DEPT')->select('DEPARTEMENT')->where('ID_DEPT', $request->id_dept)->first();

            $oldRef = DB::connection('cii')->table('PKWT')->where('NPK', $NPK)->first();
            $oldName = $oldRef ? $oldRef->NAMA : null;
            $newName = strtoupper($request->nama);

            // Handle Photo Update
            if ($request->hasFile('foto_profil')) {
                $file = $request->file('foto_profil');
                $filename = $newName . '.jpg';
                $file->storeAs('public/img/profile', $filename);

                if ($oldName && $oldName !== $newName) {
                    Storage::delete('public/img/profile/' . $oldName . '.jpg');
                }
            } else {
                if ($oldName && $oldName !== $newName) {
                    $oldPath = 'public/img/profile/' . $oldName . '.jpg';
                    $newPath = 'public/img/profile/' . $newName . '.jpg';
                    if (Storage::exists($oldPath)) {
                        Storage::move($oldPath, $newPath);
                    }
                }
            }

            DB::connection('cii')->table('BIODATA')->where('NPK', $NPK)->update([
                'NAMA_KARYAWAN' => strtoupper($request->nama),
                'ID_DEPT' => $request->id_dept,
                'JENIS_KEL' => strtoupper($request->jk),
            ]);

            $tgl_lahir = Carbon::parse($request->tgl_lahir);
            $diff = $tgl_lahir->diff($request->tmk);
            $umur_string = $diff->y . ' Tahun ' . $diff->m . ' Bulan ' . $diff->d . ' Hari';

            DB::connection('cii')->table('PKWT')->where('NPK', $NPK)->update([
                'NAMA' => strtoupper($request->nama),
                'JK' => strtoupper($request->jk),
                'TGLLAHIR' => $request->tgl_lahir,
                'TMPTLAHIR' => strtoupper($request->tempat_lahir),
                'PDDK' => strtoupper($request->pendidikan),
                'AGAMA' => strtoupper($request->agama),
                'TMK' => $request->tmk,
                'USIA' => $umur_string,
                'BAGIAN' => strtoupper($dept->DEPARTEMENT),
                'ALAMAT' => strtoupper($request->alamat),
                'KABUPATEN' => strtoupper($request->kabupaten),
                'KTP' => $request->nik,
                'NO_KK' => $request->nkk,
                'IBU' => strtoupper($request->ibu),
                'HP' => $request->hp,
                'STATUS' => $request->status,
                'TANGGUNGAN' => $request->tanggungan,
                'JURUSAN' => strtoupper($request->jurusan)
            ]);

            DB::connection('cii')->commit();
            Alert::success('Success', 'Data berhasil diperbarui');
            return redirect()->route('biodata.index');
        } catch (\Throwable $th) {
            DB::connection('cii')->rollBack();
            Alert::error('Error', 'Data gagal diperbarui: ' . $th->getMessage());
            return redirect()->back();
        }
    }
    /**
     * Remove the specified resource from storage.
     */
    public function export()
    {
        return Excel::download(new PKWTExport, 'data_karyawan_pkwt_' . date('Y-m-d_H-i-s') . '.xlsx');
    }
}
