<?php

namespace App\Http\Controllers;

use App\Models\Biodata;
use App\Models\PKWT;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;
use App\Exports\PKWTExport;
use App\Models\EmployeeMutation;
use App\Models\PayrollMaster;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

class BiodataController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $departments = DB::connection('cii')->table('DEPT')->select('ID_DEPT', 'DEPARTEMENT')->where('SECTION', 'CHUTEX')->get();
        $sections = DB::table('sections')->orderBy('name', 'asc')->get();
        return view('biodata.index', compact('departments', 'sections'));
    }

    public function getData(Request $request)
    {
        $query = DB::connection('cii')
            ->table('BIODATA')
            ->select('BIODATA.NPK', 'BIODATA.NAMA_KARYAWAN', 'BIODATA.BARCODE', 'sections.name as section', 'sections.line_start', 'sections.line_end', 'DEPT.DEPARTEMENT', 'employees_contract.status_contract', 'PKWT.TMK', 'employees_contract.end_date')
            ->join('DEPT', 'BIODATA.ID_DEPT', 'DEPT.ID_DEPT')

            ->leftJoin('sections', function ($join) {
                $join->on(
                    DB::raw('TRY_CAST(BIODATA.SECTION AS BIGINT)'),
                    '=',
                    'sections.id'
                );
            })
            // ->join('sections', 'BIODATA.SECTION', 'sections.id')
            ->join('PKWT', 'BIODATA.NPK', 'PKWT.NPK')
            ->leftJoin('employees_contract', function ($join) {
                $join->on('BIODATA.NPK', '=', 'employees_contract.npk')
                    ->where('employees_contract.status_contract', 'AKTIF');
            });

        if ($request->has('department_id') && $request->department_id != '') {
            $query->where('BIODATA.ID_DEPT', $request->department_id);
        }

        $biodatas = $query->get();

        return response()->json(['data' => $biodatas]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Menggunakan Query Builder untuk mencegah SQL Injection
            $check_active = DB::connection('cii')->table('PKWT')
                ->where('KTP', $request->nik)
                ->whereNull('TKK')
                ->first();

            if ($check_active) {
                Alert::error('Error', 'Karyawan dengan NIK ' . $request->nik . ' masih aktif. Silahkan out kan dulu karyawan tersebut.');
                return redirect()->back();
            }

            // Ketika karyawan pernah ada di PKWT dan TKK isi (mantan karyawan)
            $check_mantan = DB::connection('cii')->table('PKWT')
                ->where('KTP', $request->nik)
                ->whereNotNull('TKK')
                ->first();

            if ($check_mantan) {
                $this->storeExistingEmployee($request);
                Alert::success('Success', 'Data berhasil ditambahkan');
                return redirect()->route('biodata.index');
            } else {
                $this->storeNewEmployee($request);
                Alert::success('Success', 'Data berhasil disimpan');
                return redirect()->route('biodata.index');
            }
        } catch (\Exception $e) {
            DB::connection('cii')->rollBack();
            Alert::error('Error', 'Data gagal disimpan ' . $e->getMessage());
            return redirect()->back();
        }
    }

    private function storeExistingEmployee($request)
    {
        DB::connection('cii')->beginTransaction();
        $last_barcode = DB::connection('cii')->table('BIODATA')->whereBetween('BARCODE', [100000000, 999999999])->orderBy('BARCODE', 'desc')->first()->BARCODE;
        $barcode = $last_barcode + 1;

        $dept = DB::connection('cii')->table('DEPT')->select('DEPARTEMENT', 'id_parent_dept')->where('ID_DEPT', $request->id_dept)->first();

        DB::connection('cii')->table('BIODATA')->insert([
            'NPK' => strtoupper($request->npk),
            'NAMA_KARYAWAN' => strtoupper($request->nama),
            'BAG' => $dept->id_parent_dept,
            'ID_DEPT' => $request->id_dept,
            'JENIS_KEL' => strtoupper($request->jk),
            'BARCODE' => strtoupper($barcode),
            'SECTION' => strtoupper($request->section),
            'STATUS' => 'A',
        ]);

        // insert to BIODATA completed above.

        $tgl_lahir = Carbon::parse($request->tgl_lahir);
        $diff = $tgl_lahir->diff($request->tmk);
        $umur_string = $diff->y . ' Tahun ' . $diff->m . ' Bulan ' . $diff->d . ' Hari';

        $dept = DB::connection('cii')->table('DEPT')->select('DEPARTEMENT', 'id_parent_dept')->where('ID_DEPT', $request->id_dept)->first();

        // insert into PKWT
        // insert into PKWT using Eloquent to trigger Observer
        PKWT::create([
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
            'JURUSAN' => strtoupper($request->jurusan)
        ]);

        // Insert bank_account ke payroll_masters jika diisi
        if ($request->filled('bank_account')) {
            PayrollMaster::updateOrCreate(
                ['npk' => strtoupper($request->npk)],
                [
                    'bank_name' => 'PERMATA BANK',
                    'bank_account' => $request->bank_account
                ],
            );
        }

        DB::connection('cii')->commit();
    }

    private function storeNewEmployee($request)
    {
        DB::connection('cii')->beginTransaction();
        $last_barcode = DB::connection('cii')->table('BIODATA')->where('BARCODE', '>=', '111000000')->where('BARCODE', '<=', '113000000')->orderBy('BARCODE', 'desc')->first()->BARCODE;
        $barcode = $last_barcode + 1;

        $dept = DB::connection('cii')->table('DEPT')->select('DEPARTEMENT', 'id_parent_dept')->where('ID_DEPT', $request->id_dept)->first();

        DB::connection('cii')->table('BIODATA')->insert([
            'NPK' => strtoupper($request->npk),
            'NAMA_KARYAWAN' => strtoupper($request->nama),
            'BAG' => $dept->id_parent_dept,
            'ID_DEPT' => $request->id_dept,
            'JENIS_KEL' => strtoupper($request->jk),
            'BARCODE' => strtoupper($barcode),
            'SECTION' => strtoupper($request->section),
            'SECTION' => strtoupper($request->section),
            'SECTION' => strtoupper($request->section),
            'STATUS' => 'A',
            'IS_STAFF' => '0',
        ]);

        $tgl_lahir = Carbon::parse($request->tgl_lahir);
        $diff = $tgl_lahir->diff($request->tmk);
        $umur_string = $diff->y . ' Tahun ' . $diff->m . ' Bulan ' . $diff->d . ' Hari';

        PKWT::create([
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
            'JURUSAN' => strtoupper($request->jurusan)
        ]);

        // Insert bank_account ke payroll_masters jika diisi
        if ($request->filled('bank_account')) {
            PayrollMaster::updateOrCreate(
                ['npk' => strtoupper($request->npk)],
                [
                    'bank_name' => 'PERMATA BANK',
                    'bank_account' => $request->bank_account
                ],
            );
        }

        DB::connection('cii')->commit();
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
                'IS_STAFF' => $biodata->IS_STAFF,
            ]);

            DB::connection('cii')->table('BIODATA')->where('NPK', $NPK)->delete();

            if ($pkwt) {
                DB::connection('cii')->table('PKWT')
                    ->where('NPK', $NPK)
                    ->update([
                        'TKK' => $request->tkk,
                        'KETERANGAN' => $request->status_keluar,
                    ]);
            }

            // Update employees_contract status berdasarkan TKK vs end_date
            $tkkDate = $request->tkk ? \Carbon\Carbon::parse($request->tkk)->toDateString() : null;
            if ($tkkDate) {
                $activeContract = DB::connection('cii')->table('employees_contract')
                    ->where('npk', $NPK)
                    ->where('status_contract', 'AKTIF')
                    ->orderBy('contract_ke', 'desc')
                    ->first();

                if ($activeContract) {
                    $endDate = \Carbon\Carbon::parse($activeContract->end_date)->toDateString();
                    $newStatus = ($tkkDate >= $endDate) ? 'HABIS' : 'DIAKHIRI';

                    DB::connection('cii')->table('employees_contract')
                        ->where('id', $activeContract->id)
                        ->update(['status_contract' => $newStatus]);
                }
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
        $pkwt = DB::connection('cii')->table('PKWT')
            ->leftJoin('BIODATA', 'PKWT.NPK', '=', 'BIODATA.NPK')
            ->leftJoin('sections', 'BIODATA.SECTION', 'sections.id')
            ->select('PKWT.*', 'BIODATA.IS_STAFF', 'BIODATA.SECTION', 'sections.name as section_name', 'sections.line_start', 'sections.line_end')
            ->where('PKWT.NPK', $NPK)
            ->first();

        if ($pkwt) {
            $pkwt->IS_STAFF = $pkwt->IS_STAFF ?? 0;
            $pkwt->bank_account = PayrollMaster::where('npk', $NPK)->value('bank_account');
        }

        return response()->json($pkwt);
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

            $oldIdDept = DB::connection('cii')->table('BIODATA')->select('ID_DEPT')->where('NPK', $NPK)->first();
            $dept = DB::connection('cii')->table('DEPT')->select('DEPARTEMENT')->where('ID_DEPT', $request->id_dept)->first();

            // UBAH FOTO KARYAWAN
            $oldRef = DB::connection('cii')->table('PKWT')->where('NPK', $NPK)->first();
            $oldName = $oldRef ? $oldRef->NAMA : null;
            $newName = strtoupper($request->nama);

            $oldDeptName = $oldRef ? $oldRef->BAGIAN : null;
            $newDeptName = $dept->DEPARTEMENT;

            $fileName = $NPK . '_' . $newName . '.jpg';
            $newPath = 'public/img/profile/' . $newDeptName . '/' . $fileName;
            $oldPath = 'public/img/profile/' . ($oldDeptName ?? $newDeptName) . '/' . $NPK . '_' . $oldName . '.jpg';

            if ($request->hasFile('foto_profil')) {
                $request->file('foto_profil')->storeAs('public/img/profile/' . $newDeptName, $fileName);

                if ($oldName && $oldPath !== $newPath && Storage::exists($oldPath)) {
                    Storage::delete($oldPath);
                }
            } elseif ($oldName && $oldPath !== $newPath && Storage::exists($oldPath)) {
                Storage::move($oldPath, $newPath);
            }

            DB::connection('cii')->table('BIODATA')->where('NPK', $NPK)->update([
                'NAMA_KARYAWAN' => strtoupper($request->nama),
                'ID_DEPT' => $request->id_dept,
                'JENIS_KEL' => strtoupper($request->jk),
                'IS_STAFF' => $request->has('is_staff') ? 1 : 0,
                'SECTION' => strtoupper($request->section),
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

            EmployeeMutation::create([
                'npk' => $NPK,
                'from_dept' => $oldIdDept->ID_DEPT,
                'to_dept' => $request->id_dept,
                'date' => now(),
            ]);

            // Update bank_account di payroll_masters
            if ($request->filled('bank_account')) {
                PayrollMaster::updateOrCreate(
                    ['npk' => $NPK],
                    [
                        'bank_name' => 'Permata Bank',
                        'bank_account' => $request->bank_account
                    ]
                );
            }

            DB::connection('cii')->commit();
            Alert::success('Success', 'Data berhasil diperbarui');
            return redirect()->route('biodata.index');
        } catch (\Throwable $th) {
            DB::connection('cii')->rollBack();
            Alert::error('Error', 'Data gagal diperbarui: ' . $th->getMessage());
            return redirect()->back();
        }
    }

    public function updatePhoto(Request $request, $NPK)
    {
        try {
            $request->validate([
                'foto_profil' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            ]);

            $pkwt = DB::connection('cii')->table('PKWT')->where('NPK', $NPK)->first();

            if (!$pkwt) {
                return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan'], 404);
            }

            $deptName = trim($pkwt->BAGIAN);
            $name = trim($pkwt->NAMA);
            $fileName = $NPK . '_' . $name . '.jpg';
            $fullPath = 'public/img/profile/' . $deptName . '/' . $fileName;

            // Ensure directory exists
            if (!Storage::exists('public/img/profile/' . $deptName)) {
                Storage::makeDirectory('public/img/profile/' . $deptName);
            }

            // Delete old file if exists
            if (Storage::exists($fullPath)) {
                Storage::delete($fullPath);
            }

            // Store new photo
            $request->file('foto_profil')->storeAs('public/img/profile/' . $deptName, $fileName);

            return response()->json(['status' => 'success', 'message' => 'Foto berhasil diperbarui']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function export()
    {
        return Excel::download(new PKWTExport, 'data_karyawan_pkwt_' . date('Y-m-d_H-i-s') . '.xlsx');
    }

    public function viewGender()
    {
        $data = DB::connection('cii')->table('dept as d')
            ->leftJoin('biodata as b', 'b.ID_DEPT', '=', 'd.ID_DEPT')
            ->select(
                'd.DEPARTEMENT',
                DB::raw('COUNT(b.NPK) as total'),
                DB::raw("SUM(CASE WHEN b.JENIS_KEL = 'L' THEN 1 ELSE 0 END) as laki_laki"),
                DB::raw("SUM(CASE WHEN b.JENIS_KEL = 'P' THEN 1 ELSE 0 END) as perempuan")
            )
            ->where('d.DEPARTEMENT', 'not like', '%HOD%')
            ->where('d.DEPARTEMENT', 'not like', '%MANAGER%')
            ->groupBy('d.DEPARTEMENT')
            ->orderBy('d.DEPARTEMENT', 'ASC')
            ->get();
        return view('biodata.gender', compact('data'));
    }

    public function getSoftFiles($npk)
    {
        $pkwt = DB::connection('cii')
            ->table('PKWT')
            ->where('NPK', $npk)
            ->select('KTP')
            ->first();

        $labels = [
            'file_surat_lamaran'  => 'Surat Lamaran',
            'file_cv'             => 'CV',
            'file_ktp'            => 'KTP',
            'file_kk'             => 'KK',
            'file_ijasah'         => 'Ijazah',
            'file_akta_kelahiran' => 'Akta Kelahiran',
            'file_skck'           => 'SKCK',
            'file_surat_sehat'    => 'Surat Sehat',
            'file_pas_foto'       => 'Pas Foto',
        ];

        $docs = [];

        if ($pkwt && $pkwt->KTP) {
            $pelamar = DB::table('PELAMAR')
                ->where('NIK', $pkwt->KTP)
                ->select('id')
                ->first();

            if ($pelamar) {
                $detail = DB::table('pelamar_details')
                    ->where('id_pelamar', $pelamar->id)
                    ->orderByDesc('created_at')
                    ->first();

                if ($detail) {
                    foreach ($labels as $field => $label) {
                        if (!empty($detail->$field)) {
                            $docs[$label] = asset('storage/' . $detail->$field);
                        }
                    }
                }
            }
        }

        return response()->json([
            'npk'   => $npk,
            'count' => count($docs),
            'docs'  => $docs,
        ]);
    }
}
