<?php

namespace App\Http\Controllers;

use App\Models\EmployeeMutation;
use App\Models\PayrollMaster;
use App\Models\Permission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RealRashid\SweetAlert\Facades\Alert;

class BiodataManageController extends Controller
{
    /**
     * Cek permission untuk export semua dokumen berkas.
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
     * Helper untuk mengambil data PELAMAR dan pelamar_details berdasarkan NPK/KTP.
     */
    private function getPelamarAndDetail($npk, $ktp = null)
    {
        $pelamar = DB::connection('cii')->table('PELAMAR')
            ->where('NPK', $npk)
            ->first();

        if (!$pelamar && !empty($ktp)) {
            $pelamar = DB::connection('cii')->table('PELAMAR')
                ->where('NIK', $ktp)
                ->first();
        }

        $pelamarId = $pelamar->ID ?? ($pelamar->id ?? null);
        $pelamarDetail = null;

        if ($pelamarId) {
            $pelamarDetail = DB::connection('cii')->table('pelamar_details')
                ->where('id_pelamar', $pelamarId)
                ->orderByDesc('id')
                ->first();
        }

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

        if (!$pelamar && $pelamarDetail && !empty($pelamarDetail->id_pelamar)) {
            $pelamar = DB::connection('cii')->table('PELAMAR')
                ->where('ID', $pelamarDetail->id_pelamar)
                ->orWhere('id', $pelamarDetail->id_pelamar)
                ->first();
        }

        return [$pelamar, $pelamarDetail];
    }

    /**
     * Halaman terpadu Manage Karyawan: Show Detail, Update Data, dan Exit Karyawan.
     */
    public function index($NPK)
    {
        $pkwt = DB::connection('cii')->table('PKWT')
            ->leftJoin('BIODATA', 'PKWT.NPK', '=', 'BIODATA.NPK')
            ->leftJoin('sections', 'BIODATA.SECTION', 'sections.id')
            ->select('PKWT.*', 'BIODATA.IS_STAFF', 'BIODATA.SECTION', 'BIODATA.BARCODE', 'BIODATA.ID_DEPT', 'sections.name as section_name', 'sections.line_start', 'sections.line_end')
            ->where('PKWT.NPK', $NPK)
            ->first();

        if (!$pkwt) {
            abort(404, "Data karyawan dengan NPK {$NPK} tidak ditemukan.");
        }

        $pkwt->IS_STAFF = $pkwt->IS_STAFF ?? 0;
        $pkwt->bank_account = PayrollMaster::where('npk', $NPK)->value('bank_account');

        $departments = DB::connection('cii')->table('DEPT')->select('ID_DEPT', 'DEPARTEMENT')->where('SECTION', 'CHUTEX')->get();
        $sections = DB::table('sections')->orderBy('name', 'asc')->get();

        // Riwayat Kontrak
        $contracts = DB::connection('cii')->table('employees_contract')
            ->where('npk', $NPK)
            ->orderBy('contract_ke', 'desc')
            ->get();
        $activeContract = $contracts->firstWhere('status_contract', 'AKTIF');

        // Soft files
        $fileLabels = [
            'file_surat_lamaran'  => 'Surat Lamaran',
            'file_cv'             => 'Curriculum Vitae (CV)',
            'file_ktp'            => 'KTP / Identitas',
            'file_kk'             => 'Kartu Keluarga (KK)',
            'file_ijazah'         => 'Ijazah Terakhir',
            'file_akta_kelahiran' => 'Akta Kelahiran',
            'file_skck'           => 'SKCK Kepolisian',
            'file_surat_sehat'    => 'Surat Keterangan Sehat',
            'file_pas_foto'       => 'Pas Foto Berkas',
        ];

        $softFiles = [];
        foreach ($fileLabels as $field => $label) {
            $path = $pkwt->$field ?? null;
            $hasFile = !empty($path) && Storage::disk('public')->exists($path);
            $softFiles[$field] = [
                'field'   => $field,
                'label'   => $label,
                'path'    => $path,
                'hasFile' => $hasFile,
                'url'     => $hasFile ? asset('storage/' . $path) : null,
                'ext'     => $path ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '',
            ];
        }

        // Profile Photo
        $profileRelative = "img/profile/{$pkwt->BAGIAN}/{$pkwt->NPK}_{$pkwt->NAMA}.jpg";
        $hasProfilePhoto = Storage::disk('public')->exists($profileRelative);
        $profilePhotoUrl = $hasProfilePhoto 
            ? asset('storage/' . $profileRelative) 
            : 'https://via.placeholder.com/160x160.png?text=No+Photo';

        // Hitung masa kerja
        $masaKerja = '-';
        if (!empty($pkwt->TMK)) {
            $tmkDate = Carbon::parse($pkwt->TMK);
            $diff = $tmkDate->diff(now());
            $parts = [];
            if ($diff->y > 0) $parts[] = $diff->y . ' Thn';
            if ($diff->m > 0) $parts[] = $diff->m . ' Bln';
            if ($diff->d > 0 && $diff->y == 0) $parts[] = $diff->d . ' Hari';
            $masaKerja = !empty($parts) ? implode(' ', $parts) : '< 1 Bulan';
        }

        $canExportAllDocs = $this->hasExportAllDocsPermission();

        // ── Data Tambahan dari pelamar_details ───────────────────────
        $ktp = trim($pkwt->KTP ?? '');
        [$pelamar, $pelamarDetail] = $this->getPelamarAndDetail($NPK, $ktp);

        $safeJson = function ($raw) {
            if (empty($raw)) return [];
            if (is_array($raw)) return $raw;
            if (is_object($raw)) return (array) $raw;
            if (is_string($raw)) {
                $raw = trim($raw);
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) return $decoded;
                $decoded2 = json_decode(stripslashes($raw), true);
                if (is_array($decoded2)) return $decoded2;
            }
            return [];
        };

        $nomorSim = $pelamarDetail->nomor_sim ?? null;
        $pengalamanKerja = $safeJson($pelamarDetail->pengalaman_kerja ?? null);
        $dataAyah = $safeJson($pelamarDetail->data_ayah ?? null);
        $dataIbu = $safeJson($pelamarDetail->data_ibu ?? null);
        if (empty($dataIbu['nama']) && !empty($pkwt->IBU)) {
            $dataIbu['nama'] = $pkwt->IBU;
        }
        $saudaraKandung = $safeJson($pelamarDetail->saudara_kandung ?? null);
        $dataAnak = $safeJson($pelamarDetail->data_anak ?? null);
        $riwayatPendidikan = $safeJson($pelamarDetail->riwayat_pendidikan ?? null);

        return view('biodata.manage', compact(
            'pkwt',
            'departments',
            'sections',
            'contracts',
            'activeContract',
            'softFiles',
            'profilePhotoUrl',
            'masaKerja',
            'canExportAllDocs',
            'nomorSim',
            'pengalamanKerja',
            'dataAyah',
            'dataIbu',
            'saudaraKandung',
            'dataAnak',
            'riwayatPendidikan'
        ));
    }

    /**
     * Update data karyawan dari halaman Manage (BIODATA, PKWT, Payroll, dan pelamar_details).
     */
    public function update(Request $request, $NPK)
    {
        try {
            DB::connection('cii')->beginTransaction();

            $dept = DB::connection('cii')->table('DEPT')->where('ID_DEPT', $request->id_dept)->first();
            $oldIdDept = DB::connection('cii')->table('BIODATA')->select('ID_DEPT')->where('NPK', $NPK)->first();

            // Pindahkan folder foto profil jika departemen berubah
            $oldDept = trim($dept->DEPARTEMENT ?? '');
            $oldPath = 'public/img/profile/' . $oldDept . '/' . $NPK . '_' . strtoupper($request->nama) . '.jpg';
            $newPath = 'public/img/profile/' . trim($dept->DEPARTEMENT ?? '') . '/' . $NPK . '_' . strtoupper($request->nama) . '.jpg';

            if (Storage::exists($oldPath) && $oldPath !== $newPath) {
                Storage::move($oldPath, $newPath);
            }

            // 1. Update BIODATA
            DB::connection('cii')->table('BIODATA')->where('NPK', $NPK)->update([
                'NAMA_KARYAWAN' => strtoupper($request->nama),
                'ID_DEPT'       => $request->id_dept,
                'JENIS_KEL'     => strtoupper($request->jk),
                'IS_STAFF'      => $request->has('is_staff') ? 1 : 0,
                'SECTION'       => strtoupper($request->section),
            ]);

            // Hitung umur
            $tgl_lahir = Carbon::parse($request->tgl_lahir);
            $diff = $tgl_lahir->diff($request->tmk);
            $umur_string = $diff->y . ' Tahun ' . $diff->m . ' Bulan ' . $diff->d . ' Hari';

            // 2. Update PKWT (nama_sekolah dihapus sesuai permintaan)
            $pkwtUpdateData = [
                'NAMA'       => strtoupper($request->nama),
                'JK'         => strtoupper($request->jk),
                'TGLLAHIR'   => $request->tgl_lahir,
                'TMPTLAHIR'  => strtoupper($request->tempat_lahir),
                'PDDK'       => strtoupper($request->pendidikan),
                'AGAMA'      => strtoupper($request->agama),
                'TMK'        => $request->tmk,
                'USIA'       => $umur_string,
                'BAGIAN'     => strtoupper($dept->DEPARTEMENT ?? ''),
                'ALAMAT'     => strtoupper($request->alamat),
                'KABUPATEN'  => strtoupper($request->kabupaten),
                'KTP'        => $request->nik,
                'NO_KK'      => $request->nkk,
                'IBU'        => strtoupper($request->ibu),
                'HP'         => $request->hp,
                'STATUS'     => $request->status,
                'TANGGUNGAN' => $request->tanggungan,
                'JURUSAN'    => strtoupper($request->jurusan),
            ];

            // Upload Berkas Dokumen
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
                    $relativePath = 'employees/' . $pkwtColumn . '/' . $fileName;

                    $file->storeAs('employees/' . $pkwtColumn, $fileName, 'public');

                    $oldFile = DB::connection('cii')->table('PKWT')->where('NPK', $NPK)->value($pkwtColumn);
                    if ($oldFile && Storage::disk('public')->exists($oldFile)) {
                        Storage::disk('public')->delete($oldFile);
                    }

                    $pkwtUpdateData[$pkwtColumn] = $relativePath;
                }
            }

            DB::connection('cii')->table('PKWT')->where('NPK', $NPK)->update($pkwtUpdateData);

            // 3. Mutasi karyawan jika dept berubah
            if ($oldIdDept && $oldIdDept->ID_DEPT != $request->id_dept) {
                EmployeeMutation::create([
                    'npk'       => $NPK,
                    'from_dept' => $oldIdDept->ID_DEPT,
                    'to_dept'   => $request->id_dept,
                    'date'      => now(),
                ]);
            }

            // 4. Update Rekening Bank di payroll_masters
            if ($request->filled('bank_account')) {
                PayrollMaster::updateOrCreate(
                    ['npk' => $NPK],
                    [
                        'bank_name'    => 'Permata Bank',
                        'bank_account' => $request->bank_account,
                    ]
                );
            }

            // 5. Update atau Insert ke pelamar_details
            $ktpVal = trim($request->nik ?? ($request->ktp ?? ''));
            [$pelamarRec, $detailRec] = $this->getPelamarAndDetail($NPK, $ktpVal);

            $cleanArray = function ($arr, $keyCheck = null) {
                if (!is_array($arr)) return null;
                $filtered = array_values(array_filter($arr, function ($item) use ($keyCheck) {
                    if (!is_array($item)) return false;
                    if ($keyCheck && !empty(trim((string)($item[$keyCheck] ?? '')))) {
                        return true;
                    }
                    foreach ($item as $val) {
                        if (!is_null($val) && trim((string)$val) !== '') {
                            return true;
                        }
                    }
                    return false;
                }));
                return !empty($filtered) ? json_encode($filtered) : null;
            };

            $ayahPayload = $request->data_ayah;
            if (is_array($ayahPayload)) {
                $ayahPayload = array_filter($ayahPayload, fn($v) => !is_null($v) && trim((string)$v) !== '');
            }
            $ibuPayload = $request->data_ibu;
            if (is_array($ibuPayload)) {
                $ibuPayload = array_filter($ibuPayload, fn($v) => !is_null($v) && trim((string)$v) !== '');
            }

            $detailData = [
                'nomor_sim'          => $request->nomor_sim ?: null,
                'data_ayah'          => !empty($ayahPayload) ? json_encode($ayahPayload) : null,
                'data_ibu'           => !empty($ibuPayload) ? json_encode($ibuPayload) : null,
                'pengalaman_kerja'   => $cleanArray($request->pengalaman_kerja, 'perusahaan'),
                'saudara_kandung'    => $cleanArray($request->saudara_kandung, 'nama'),
                'data_anak'          => $cleanArray($request->data_anak, 'nama'),
                'riwayat_pendidikan' => $cleanArray($request->riwayat_pendidikan, 'tingkat'),
                'updated_at'         => now(),
            ];

            if ($detailRec) {
                DB::connection('cii')->table('pelamar_details')
                    ->where('id', $detailRec->id)
                    ->update($detailData);
            } else {
                $pelamarId = $pelamarRec->ID ?? ($pelamarRec->id ?? null);
                if (!$pelamarId) {
                    try {
                        $pelamarId = DB::connection('cii')->table('PELAMAR')->insertGetId([
                            'NPK'            => $NPK,
                            'NAMA'           => strtoupper($request->nama),
                            'NIK'            => $request->nik,
                            'NO_KK'          => $request->nkk,
                            'HP'             => $request->hp,
                            'ALAMAT_LENGKAP' => strtoupper($request->alamat),
                            'JENIS_KELAMIN'  => strtoupper($request->jk),
                            'IS_KONTRAK'     => 'TRUE',
                        ]);
                    } catch (\Exception $e) {
                        $pelamarId = $NPK;
                    }
                }

                $detailData['id_pelamar'] = (string) $pelamarId;
                $detailData['created_at'] = now();
                DB::connection('cii')->table('pelamar_details')->insert($detailData);
            }

            DB::connection('cii')->commit();
            Alert::success('Berhasil', 'Data karyawan berhasil diperbarui');

            return redirect()->route('biodata.manage', $NPK);
        } catch (\Throwable $th) {
            DB::connection('cii')->rollBack();
            Alert::error('Gagal', 'Data gagal diperbarui: ' . $th->getMessage());
            return redirect()->back();
        }
    }

    /**
     * Upload dan ganti foto profil karyawan.
     */
    public function updatePhoto(Request $request, $NPK)
    {
        try {
            $request->validate([
                'foto_profil' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            ]);

            $pkwt = DB::connection('cii')->table('PKWT')->where('NPK', $NPK)->first();
            if (!$pkwt) {
                return response()->json(['status' => 'error', 'message' => 'Data karyawan tidak ditemukan'], 404);
            }

            $deptName = trim($pkwt->BAGIAN);
            $name = trim($pkwt->NAMA);
            $fileName = $NPK . '_' . $name . '.jpg';
            $fullPath = 'public/img/profile/' . $deptName . '/' . $fileName;

            if (!Storage::exists('public/img/profile/' . $deptName)) {
                Storage::makeDirectory('public/img/profile/' . $deptName);
            }

            if (Storage::exists($fullPath)) {
                Storage::delete($fullPath);
            }

            $request->file('foto_profil')->storeAs('public/img/profile/' . $deptName, $fileName);

            return response()->json(['status' => 'success', 'message' => 'Foto profil berhasil diperbarui']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Proses Exit Karyawan.
     */
    public function exit(Request $request, $NPK)
    {
        try {
            DB::connection('cii')->beginTransaction();
            $biodata = DB::connection('cii')->table('BIODATA')->where('NPK', $NPK)->first();
            $pkwt = DB::connection('cii')->table('PKWT')->where('NPK', $NPK)->first();

            if (!$biodata) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Data Biodata tidak ditemukan'
                ], 404);
            }

            DB::connection('cii')->table('BIODATA_KELUAR')->insert([
                'NPK'           => $biodata->NPK,
                'NAMA_KARYAWAN' => $biodata->NAMA_KARYAWAN,
                'ID_DEPT'       => $biodata->ID_DEPT,
                'JENIS_KEL'     => $biodata->JENIS_KEL,
                'BARCODE'       => $biodata->BARCODE,
                'SECTION'       => $biodata->SECTION,
                'STATUS'        => $biodata->STATUS,
                'IS_STAFF'      => $biodata->IS_STAFF,
            ]);

            DB::connection('cii')->table('BIODATA')->where('NPK', $NPK)->delete();

            if ($pkwt) {
                DB::connection('cii')->table('PKWT')
                    ->where('NPK', $NPK)
                    ->update([
                        'TKK'           => $request->tkk,
                        'KETERANGAN'    => $request->status_keluar,
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
                'status'  => 'success',
                'message' => 'Data berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::connection('cii')->rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

