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
use App\Models\Permission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

class BiodataController extends Controller
{
    /**
     * Cek apakah user saat ini memiliki izin untuk export semua dokumen.
     * Mengacu pada tabel permissions ('biodata.export-all-docs') dan tabel pivot role_permission.
     */
    public function hasExportAllDocsPermission(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        if ($user->hasRole('Admin')) {
            return true;
        }

        $permission = Permission::where('route_name', 'biodata.export-all-docs')->first();

        if (!$permission) {
            return false;
        }

        $userRoleIds = $user->roles()->pluck('id');
        if ($userRoleIds->isEmpty()) {
            return false;
        }

        return DB::table('role_permission')
            ->where('permission_id', $permission->id)
            ->whereIn('role_id', $userRoleIds)
            ->exists();
    }
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
                        'leave_reasons' => $request->leave_reasons,
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

            $pkwtUpdateData = [
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
            ];

            // Request input field name kept as 'file_ijasah' (matches the existing
            // form input / pelamar_details spelling), but it is written into the
            // PKWT column 'file_ijazah' (the actual column name on that table).
            $fileFieldMap = [
                'file_surat_lamaran'  => 'file_surat_lamaran',
                'file_cv'             => 'file_cv',
                'file_ktp'            => 'file_ktp',
                'file_kk'             => 'file_kk',
                'file_ijasah'         => 'file_ijazah',
                'file_akta_kelahiran' => 'file_akta_kelahiran',
                'file_skck'           => 'file_skck',
                'file_surat_sehat'    => 'file_surat_sehat',
                'file_pas_foto'       => 'file_pas_foto',
            ];

            foreach ($fileFieldMap as $inputField => $pkwtColumn) {
                if ($request->hasFile($inputField)) {
                    $file = $request->file($inputField);
                    $fileName = $NPK . '_' . strtoupper($request->nama) . '_' . $pkwtColumn . '.' . $file->getClientOriginalExtension();

                    // Path relative to the 'public' disk (storage/app/public/...).
                    // This exact string is what gets saved into the PKWT column,
                    // so getSoftFiles() and any other reader can use it as-is.
                    $relativePath = 'employees/' . $pkwtColumn . '/' . $fileName;

                    $file->storeAs('employees/' . $pkwtColumn, $fileName, 'public');

                    // Delete the old file using the path that was previously
                    // stored in the DB (not reconstructed from a filename).
                    $oldPath = DB::connection('cii')->table('PKWT')->where('NPK', $NPK)->value($pkwtColumn);
                    if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }

                    $pkwtUpdateData[$pkwtColumn] = $relativePath;
                }
            }

            DB::connection('cii')->table('PKWT')->where('NPK', $NPK)->update($pkwtUpdateData);

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

    /**
     * Return the list of an employee's documents.
     *
     * IMPORTANT: This now reads directly from the PKWT table (file_* columns),
     * NOT from PELAMAR / pelamar_details. Documents are moved into the PKWT-owned
     * folder ("berkas/karyawan/{field}/...") at assign-time by
     * PelamarController::moveApplicantFilesToPkwt(), so this endpoint no longer
     * depends on the applicant record still existing or being unchanged.
     *
     * Response shape is unchanged: {npk, count, docs}, so no blade/JS changes
     * are required on the frontend that consumes this endpoint.
     */
    public function getSoftFiles($npk)
    {
        $pkwt = DB::connection('cii')->table('PKWT')->where('NPK', $npk)->first();

        $labels = [
            'file_surat_lamaran'  => 'Surat Lamaran',
            'file_cv'             => 'CV',
            'file_ktp'            => 'KTP',
            'file_kk'             => 'KK',
            'file_ijazah'         => 'Ijazah',
            'file_akta_kelahiran' => 'Akta Kelahiran',
            'file_skck'           => 'SKCK',
            'file_surat_sehat'    => 'Surat Sehat',
            'file_pas_foto'       => 'Pas Foto',
        ];

        $docs = [];

        if ($pkwt) {
            foreach ($labels as $field => $label) {
                // PKWT file_* columns now store the full path relative to the
                // 'public' disk (e.g. "employees/file_cv/xxx.pdf"), not just a
                // bare filename, so no folder-naming convention needs to be
                // guessed here.
                $relativePath = $pkwt->$field ?? null;

                if (empty($relativePath)) {
                    continue;
                }

                if (Storage::disk('public')->exists($relativePath)) {
                    $docs[$label] = asset('storage/' . $relativePath);
                }
            }
        }

        return response()->json([
            'npk'   => $npk,
            'count' => count($docs),
            'docs'  => $docs,
            'can_export_all' => $this->hasExportAllDocsPermission(),
        ]);
    }

    /**
     * Helper to prepare data for Biodata Diri PDF.
     * Returns [$data, $empName] or null if not found.
     */
    private function buildBiodataPdfData($npk)
    {
        $pkwt = DB::connection('cii')->table('PKWT')->where('NPK', $npk)->first();
        $biodata = DB::connection('cii')->table('BIODATA')->where('NPK', $npk)->first();

        if (!$pkwt && !$biodata) {
            return null;
        }

        // Resolusi Department & Section
        $deptName = null;
        $sectionName = null;
        $lineInfo = null;

        if ($biodata) {
            if (!empty($biodata->ID_DEPT)) {
                $dept = DB::connection('cii')->table('DEPT')->where('ID_DEPT', $biodata->ID_DEPT)->first();
                $deptName = $dept->DEPARTEMENT ?? null;
            }

            if (!empty($biodata->SECTION)) {
                $sec = DB::table('sections')->where('id', $biodata->SECTION)->first();
                if ($sec) {
                    $sectionName = $sec->name;
                    if (!empty($sec->line_start) || !empty($sec->line_end)) {
                        $lineInfo = 'Line ' . $sec->line_start . ($sec->line_end ? ' - ' . $sec->line_end : '');
                    }
                } else {
                    $sectionName = $biodata->SECTION;
                }
            }
        }
        if (!$deptName && $pkwt) {
            $deptName = $pkwt->BAGIAN ?? null;
        }

        // Cari data PELAMAR & pelamar_details dengan JOIN langsung via NPK
        $ktp = trim($pkwt->KTP ?? ($biodata->KTP ?? ''));

        $pelamar = DB::connection('cii')->table('PELAMAR')
            ->where('NPK', $npk)
            ->first();

        // Fallback jika NPK belum terisi di PELAMAR, cari via NIK/KTP
        if (!$pelamar && !empty($ktp)) {
            $pelamar = DB::connection('cii')->table('PELAMAR')
                ->where('NIK', $ktp)
                ->first();
        }

        // Ambil ID dari pelamar (support ID kapital di SQL Server atau lowercase id)
        $pelamarId = $pelamar->ID ?? ($pelamar->id ?? null);

        // Ambil data pelamar_details berdasarkan id_pelamar
        $pelamarDetail = null;
        if ($pelamarId) {
            $pelamarDetail = DB::connection('cii')->table('pelamar_details')
                ->where('id_pelamar', $pelamarId)
                ->orderByDesc('id')
                ->first();
        }

        // Jika belum dapat, JOIN langsung pelamar_details dengan PELAMAR berdasarkan NPK
        if (!$pelamarDetail) {
            $pelamarDetail = DB::connection('cii')->table('pelamar_details')
                ->join('PELAMAR', function ($join) {
                    $join->on('pelamar_details.id_pelamar', '=', 'PELAMAR.ID')
                        ->orOn('pelamar_details.id_pelamar', '=', 'PELAMAR.id');
                })
                ->where('PELAMAR.NPK', $npk)
                ->select('pelamar_details.*')
                ->orderByDesc('pelamar_details.id')
                ->first();
        }

        // Fallback: JOIN via NIK/KTP
        if (!$pelamarDetail && !empty($ktp)) {
            $pelamarDetail = DB::connection('cii')->table('pelamar_details')
                ->join('PELAMAR', function ($join) {
                    $join->on('pelamar_details.id_pelamar', '=', 'PELAMAR.ID')
                        ->orOn('pelamar_details.id_pelamar', '=', 'PELAMAR.id');
                })
                ->where('PELAMAR.NIK', $ktp)
                ->select('pelamar_details.*')
                ->orderByDesc('pelamar_details.id')
                ->first();
        }

        // Jika pelamarDetail ketemu tapi pelamar belum, ambil dari id_pelamar
        if (!$pelamar && $pelamarDetail && !empty($pelamarDetail->id_pelamar)) {
            $pelamar = DB::connection('cii')->table('PELAMAR')
                ->where('ID', $pelamarDetail->id_pelamar)
                ->orWhere('id', $pelamarDetail->id_pelamar)
                ->first();
        }

        // Riwayat Kontrak & Rekening
        $contract = DB::connection('cii')->table('employees_contract')
            ->where('npk', $npk)
            ->orderBy('contract_ke', 'desc')
            ->first();

        $bankAccount = PayrollMaster::where('npk', $npk)->value('bank_account') ?: ($pkwt->NOREK ?? null);

        // Helper untuk parse JSON dengan aman
        $safeJson = function ($raw) {
            if (empty($raw)) {
                return [];
            }
            if (is_array($raw)) {
                return $raw;
            }
            if (is_object($raw)) {
                return (array) $raw;
            }
            if (is_string($raw)) {
                $raw = trim($raw);
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
                if (is_string($decoded)) {
                    $decoded2 = json_decode($decoded, true);
                    if (is_array($decoded2)) {
                        return $decoded2;
                    }
                }
                $decoded = json_decode(stripslashes($raw), true);
                if (is_array($decoded)) {
                    return $decoded;
                }
                $decoded = json_decode(html_entity_decode($raw), true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
            return [];
        };

        // Data Array / JSON
        $riwayatPendidikan = $safeJson($pelamarDetail->riwayat_pendidikan ?? null);
        if (empty($riwayatPendidikan) && $pkwt && !empty($pkwt->PDDK)) {
            $riwayatPendidikan[] = [
                'tingkat'   => $pkwt->PDDK,
                'institusi' => $pelamar->NAMA_SEKOLAH ?? ($pelamar->KABUPATEN_SEKOLAH ?? '-'),
                'jurusan'   => $pkwt->JURUSAN ?? ($pelamar->JURUSAN ?? '-'),
                'dari'      => '-',
                'sampai'    => '-',
                'lulus'     => 1,
            ];
        }

        $pengalamanKerja = $safeJson($pelamarDetail->pengalaman_kerja ?? null);

        $dataAyah = $safeJson($pelamarDetail->data_ayah ?? null);
        $dataIbu = $safeJson($pelamarDetail->data_ibu ?? null);
        if (empty($dataIbu['nama']) && (!empty($pkwt->IBU) || !empty($pelamar->IBU))) {
            $dataIbu['nama'] = $pkwt->IBU ?? $pelamar->IBU;
        }

        $saudaraKandung = $safeJson($pelamarDetail->saudara_kandung ?? null);
        $dataAnak = $safeJson($pelamarDetail->data_anak ?? null);

        // Pas Foto Karyawan (Cari file lalu encode ke Base64)
        $photoBase64 = null;
        $possiblePhotoPaths = [];

        $empName = trim($pkwt->NAMA ?? ($biodata->NAMA_KARYAWAN ?? ''));
        if (!empty($deptName) && !empty($empName)) {
            $possiblePhotoPaths[] = storage_path('app/public/img/profile/' . trim($deptName) . '/' . $npk . '_' . $empName . '.jpg');
        }
        $profileDir = storage_path('app/public/img/profile');
        if (is_dir($profileDir)) {
            $globMatches = glob($profileDir . '/*/' . $npk . '_*.jpg');
            if (!empty($globMatches)) {
                $possiblePhotoPaths = array_merge($possiblePhotoPaths, $globMatches);
            }
        }
        if (!empty($pkwt->file_pas_foto)) {
            $possiblePhotoPaths[] = storage_path('app/public/' . $pkwt->file_pas_foto);
        }
        if ($pelamarDetail && !empty($pelamarDetail->file_pas_foto)) {
            $possiblePhotoPaths[] = storage_path('app/public/' . $pelamarDetail->file_pas_foto);
        }

        foreach ($possiblePhotoPaths as $path) {
            if (file_exists($path) && is_readable($path) && filesize($path) > 0) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $mime = ($ext === 'jpg' || $ext === 'jpeg') ? 'image/jpeg' : ($ext === 'png' ? 'image/png' : 'image/jpeg');
                $photoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
                break;
            }
        }

        // Logo Perusahaan ke Base64
        $logoBase64 = null;
        $logoPath = public_path('img/chutex_logo.png');
        if (file_exists($logoPath) && is_readable($logoPath)) {
            $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }

        $data = compact(
            'npk',
            'pkwt',
            'biodata',
            'deptName',
            'sectionName',
            'lineInfo',
            'pelamar',
            'pelamarDetail',
            'contract',
            'bankAccount',
            'photoBase64',
            'logoBase64',
            'riwayatPendidikan',
            'pengalamanKerja',
            'dataAyah',
            'dataIbu',
            'saudaraKandung',
            'dataAnak'
        );

        return [$data, $empName];
    }

    /**
     * Export ALL documents (Biodata Diri + PKWT files + contracts) into a single merged PDF.
     * 1. Biodata Diri (PDF) hasil generate sistem ditempatkan di halaman pertama.
     * 2. Soft files dari PKWT (KTP, Ijazah, dll) dan kontrak kerja disatukan.
     * 3. Images dikonversi ke PDF page via DomPDF.
     * 4. Semua PDF digabungkan menggunakan FPDI.
     */
    public function exportAllDocs($npk)
    {
        if (!$this->hasExportAllDocsPermission()) {
            abort(403, 'Anda tidak memiliki akses untuk mendownload semua dokumen.');
        }

        $pkwt = DB::connection('cii')->table('PKWT')->where('NPK', $npk)->first();
        $empName = trim($pkwt->NAMA ?? 'Karyawan');

        // File-file temporary yang dibuat dan harus dibersihkan setelah proses selesai
        $cleanupFiles = [];
        $tempPdfs = [];

        // 1. Dokumen Pertama: Biodata Diri (PDF) hasil generate sistem
        $bioRes = $this->buildBiodataPdfData($npk);
        if ($bioRes) {
            list($bioData, $bioEmpName) = $bioRes;
            if ($bioEmpName && $empName === 'Karyawan') {
                $empName = $bioEmpName;
            }
            try {
                $bioPdf = Pdf::loadView('biodata.pdf_biodata', $bioData)->setPaper('a4', 'portrait');
                $tmpBioPath = tempnam(sys_get_temp_dir(), 'biodata_') . '.pdf';
                file_put_contents($tmpBioPath, $bioPdf->output());
                $tempPdfs[] = $tmpBioPath;
                $cleanupFiles[] = $tmpBioPath;
            } catch (\Throwable $e) {
                Log::warning("exportAllDocs: Gagal generate PDF biodata: " . $e->getMessage());
            }
        }

        // 2. Kumpulkan soft files dari PKWT
        $labels = [
            'file_surat_lamaran'  => 'Surat Lamaran',
            'file_cv'             => 'CV',
            'file_ktp'            => 'KTP',
            'file_kk'             => 'KK',
            'file_ijazah'         => 'Ijazah',
            'file_akta_kelahiran' => 'Akta Kelahiran',
            'file_skck'           => 'SKCK',
            'file_surat_sehat'    => 'Surat Sehat',
            'file_pas_foto'       => 'Pas Foto',
        ];

        $otherFiles = [];

        if ($pkwt) {
            foreach ($labels as $field => $label) {
                $relativePath = $pkwt->$field ?? null;
                if (empty($relativePath)) continue;

                $absPath = storage_path('app/public/' . $relativePath);
                if (file_exists($absPath) && filesize($absPath) > 0) {
                    $otherFiles[] = ['label' => $label, 'path' => $absPath];
                }
            }
        }

        // 3. Kontrak karyawan dari employees_contract
        $contracts = DB::connection('cii')->table('employees_contract')
            ->where('npk', $npk)
            ->whereNotNull('file_contract')
            ->where('file_contract', '!=', '')
            ->orderBy('contract_ke', 'asc')
            ->get();

        foreach ($contracts as $contract) {
            $absPath = storage_path('app/public/' . $contract->file_contract);
            if (file_exists($absPath) && filesize($absPath) > 0) {
                $otherFiles[] = [
                    'label' => 'Kontrak ke-' . ($contract->contract_ke ?? '?'),
                    'path'  => $absPath,
                ];
            }
        }

        // 4. Konversi file gambar ke PDF & kumpulkan semua file PDF
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

        foreach ($otherFiles as $file) {
            $ext = strtolower(pathinfo($file['path'], PATHINFO_EXTENSION));

            if ($ext === 'pdf') {
                $tempPdfs[] = $file['path'];
            } elseif (in_array($ext, $imageExtensions)) {
                $imgData = @file_get_contents($file['path']);
                if (!$imgData) continue;

                $imgBase64 = base64_encode($imgData);
                $mime = ($ext === 'png') ? 'image/png'
                    : (($ext === 'gif') ? 'image/gif'
                    : (($ext === 'webp') ? 'image/webp'
                    : 'image/jpeg'));

                $html = '<html><body style="margin:0;padding:0;text-align:center;">';
                $html .= '<p style="font-family:sans-serif;font-size:11px;color:#666;margin:10px 0;">' . e($file['label']) . '</p>';
                $html .= '<img src="data:' . $mime . ';base64,' . $imgBase64 . '" style="max-width:96%;max-height:92%;">';
                $html .= '</body></html>';

                try {
                    $imgPdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
                    $tmpImgPath = tempnam(sys_get_temp_dir(), 'imgpdf_') . '.pdf';
                    file_put_contents($tmpImgPath, $imgPdf->output());
                    $tempPdfs[] = $tmpImgPath;
                    $cleanupFiles[] = $tmpImgPath;
                } catch (\Throwable $e) {
                    Log::warning("exportAllDocs: Gagal konversi gambar ke PDF {$file['path']}: " . $e->getMessage());
                }
            }
        }

        if (empty($tempPdfs)) {
            abort(404, "Tidak ada dokumen yang bisa diproses untuk NPK {$npk}.");
        }

        // 5. Merge semua PDF menggunakan FPDI
        $merger = new Fpdi();

        foreach ($tempPdfs as $pdfPath) {
            try {
                $pageCount = $merger->setSourceFile($pdfPath);
                for ($i = 1; $i <= $pageCount; $i++) {
                    $tplId = $merger->importPage($i);
                    $size = $merger->getTemplateSize($tplId);

                    $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                    $merger->AddPage($orientation, [$size['width'], $size['height']]);
                    $merger->useTemplate($tplId);
                }
            } catch (\Throwable $e) {
                Log::warning("exportAllDocs: Gagal merge {$pdfPath}: " . $e->getMessage());
                continue;
            }
        }

        // Cleanup temporary files
        foreach ($cleanupFiles as $f) {
            if (file_exists($f)) {
                @unlink($f);
            }
        }

        $cleanName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $empName ?: 'Karyawan');
        $fileName = "AllDocs_{$npk}_{$cleanName}.pdf";

        return response($merger->Output('S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Generate PDF Formulir Biodata Diri Karyawan
     * menggabungkan data PKWT, BIODATA, PELAMAR, dan pelamar_details berdasarkan NPK.
     */
    public function generatePdf($npk)
    {
        $res = $this->buildBiodataPdfData($npk);
        if (!$res) {
            abort(404, "Data karyawan dengan NPK {$npk} tidak ditemukan.");
        }

        list($data, $empName) = $res;
        $cleanName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $empName ?: 'Karyawan');
        $pdf = Pdf::loadView('biodata.pdf_biodata', $data)
            ->setPaper('a4', 'portrait');

        return $pdf->stream("Biodata_{$npk}_{$cleanName}.pdf");
    }
}