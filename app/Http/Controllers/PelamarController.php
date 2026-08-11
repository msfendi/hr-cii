<?php

namespace App\Http\Controllers;

use App\Models\Pelamar;
use App\Models\SalaryApprove;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            ->select('PELAMAR.ID', 'NPK', 'NAMA', 'JENIS_KELAMIN', 'TMPT_LAHIR', 'TGL_LAHIR', 'TMK', 'UMUR', 'NIK', 'KABUPATEN', 'HP') // Added ID
            ->leftJoin('pelamar_details', 'pelamar_details.id_pelamar', '=', 'PELAMAR.ID')
            ->where('IS_KONTRAK', 'FALSE')
            ->where('pelamar_details.status_apply', 'ONBOARDING')
            ->orderBy('NPK', 'ASC')
            ->get();

        // ── "LEFT JOIN" ke salary_approve buat ambil approved_salary ──────
        // salary_approve ada di koneksi default sedangkan PELAMAR ada di
        // koneksi 'cii' (server DB terpisah), jadi tidak bisa pakai
        // leftJoin() query builder beneran lintas koneksi. Di-emulasi
        // manual: ambil semua approved_salary yang relevan sekali query,
        // lalu ditempelkan ke tiap baris $pelamars (setara hasil LEFT JOIN).
        $pelamarIds = $pelamars->pluck('ID')->filter()->unique()->values()->toArray();

        $approvedSalaryMap = SalaryApprove::whereIn('id_pelamar', $pelamarIds)
            ->whereNotNull('approved_salary')
            ->pluck('approved_salary', 'id_pelamar');

        $canSeeSalary = $this->userCanSeeSalary();

        foreach ($pelamars as $pelamar) {
            $approved = $approvedSalaryMap[$pelamar->ID] ?? null;

            // Nilai mentah TETAP ditempel (dipakai kalau suatu saat butuh di
            // server-side / role berubah), tapi blade hanya boleh menampilkan
            // approved_salary_display, bukan approved_salary langsung — biar
            // role selain PAYROLL_STAFF tidak pernah melihat nominal asli.
            $pelamar->approved_salary = $approved;
            $pelamar->approved_salary_display = is_null($approved)
                ? '-'
                : ($canSeeSalary ? 'Rp ' . number_format($approved, 0, ',', '.') : '***');
        }

        $departments = DB::connection('cii')->table('DEPT')->select('ID_DEPT', 'DEPARTEMENT')->where('SECTION', 'CHUTEX')->get(); // Fetch departments
        $sections = DB::table('sections')->orderBy('name', 'asc')->get();

        return view('pelamar.index', compact('pelamars', 'departments', 'sections', 'canSeeSalary'));
    }

    /**
     * TODO: sesuaikan dengan struktur role/permission user kamu kalau bukan
     * kolom `role` sederhana di tabel users (mis. kalau pakai Spatie
     * Permission, ganti jadi `$user->hasRole('PAYROLL_STAFF')`).
     */
    private function userCanSeeSalary(): bool
    {
        $user = Auth::user();
        return $user && (($user->role ?? null) === 'PAYROLL_STAFF');
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
            // Cek apakah ada data karyawan yang masih aktif (TKK null) berdasarkan KTP saja
            $check_active = DB::connection('cii')->table('PKWT')->where('KTP', $request->nik)->whereNull('TKK')->first();
            if ($check_active != null) {
                Alert::error('Error', 'Karyawan dengan NIK ' . $request->nik . ' masih berstatus aktif!');
                return redirect()->back();
            }

            // Langsung panggil method unified untuk insert data (baik baru maupun re-hire)
            $this->processEmployeeAssignment($request);

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
        if ($pelamar) {
            $last_npk_record = DB::connection('cii')->table('BIODATA')->orderBy('NPK', 'DESC')->first();
            if ($last_npk_record && !empty($last_npk_record->NPK)) {
                $last_npk_str = $last_npk_record->NPK;
                if (preg_match('/^(.*?)(\d+)$/', $last_npk_str, $matches)) {
                    $prefix = $matches[1];
                    $number = $matches[2];
                    $next_number = str_pad((int)$number + 1, strlen($number), '0', STR_PAD_LEFT);
                    $pelamar->NPK = $prefix . $next_number;
                }
            }

            // Approved salary (kalau ada & sudah 'finish') — dipakai buat
            // auto-isi & kunci field Gaji Pokok di modal assign. Nilai mentah
            // HANYA dikirim ke response kalau user PAYROLL_STAFF; role lain
            // cuma dapat flag has_approved_salary + versi ***-nya, supaya
            // nominal asli tidak pernah nyampe ke browser buat role selain itu.
            // processEmployeeAssignment() tetap ambil nilai asli langsung dari
            // DB server-side saat submit, jadi ini murni soal apa yang
            // ditampilkan, bukan apa yang benar-benar dipakai buat kontrak.
            $approvedSalary = SalaryApprove::where('id_pelamar', $id)
                ->whereNotNull('approved_salary')
                ->value('approved_salary');

            $canSeeSalary = $this->userCanSeeSalary();

            $pelamar->has_approved_salary = !is_null($approvedSalary);
            $pelamar->approved_salary_display = is_null($approvedSalary)
                ? null
                : ($canSeeSalary ? 'Rp ' . number_format($approvedSalary, 0, ',', '.') : '***');

            if ($canSeeSalary && !is_null($approvedSalary)) {
                $pelamar->approved_salary = (float) $approvedSalary;
            }
        }
        return response()->json($pelamar);
    }

    private function processEmployeeAssignment($request)
    {
        DB::connection('cii')->beginTransaction();

        $id_pelamar = $request->id_pelamar;
        $pelamar = DB::connection('cii')->table('PELAMAR')->where('ID', $id_pelamar)->first();

        // Fetch the applicant's latest detail record, which is where the
        // uploaded documents (file_surat_lamaran, file_cv, etc.) currently live.
        $pelamarDetail = DB::connection('cii')->table('pelamar_details')
            ->where('id_pelamar', $id_pelamar)
            ->orderByDesc('created_at')
            ->first();

        $npk = strtoupper($request->npk);

        $tgl_lahir = Carbon::parse($request->tgl_lahir);
        $diff = $tgl_lahir->diff(Carbon::now());
        $umur_string = $diff->y . ' Tahun ' . $diff->m . ' Bulan ' . $diff->d . ' Hari';

        $last_barcode = DB::connection('cii')->table('BIODATA')->orderBy('NPK', 'DESC')->orderBy('BARCODE', 'DESC')->first()->BARCODE + 1;
        DB::connection('cii')->table('PELAMAR')->where('ID', $id_pelamar)->update(
            [
                'NPK' => $npk,
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

        $dept = DB::connection('cii')->table('DEPT')->select('*')->where('ID_DEPT', $request->id_dept)->first();

        DB::connection('cii')->table('BIODATA')->insert([
            'NPK' => $npk,
            'NAMA_KARYAWAN' => strtoupper($request->nama),
            'BAG' => $dept->id_parent_dept,
            'ID_DEPT' => $request->id_dept,
            'JENIS_KEL' => strtoupper($request->jk),
            'BARCODE' => $last_barcode,
            'SECTION' => strtoupper($request->section),
            'STATUS' => 'A',
            'IS_STAFF' => $request->has('is_staff') ? 1 : 0,
        ]);

        // Move the applicant's uploaded documents out of the pelamar folder
        // and into the PKWT/employee-owned folder. Returns a map of
        // [pkwt_column => filename] ready to merge into the PKWT insert.
        $pkwtFiles = $this->moveApplicantFilesToPkwt($pelamarDetail, $npk);

        DB::connection('cii')->table('PKWT')->insert(array_merge([
            'NPK' => $npk,
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
        ], $pkwtFiles));

        // Cek kontrak ke berapa (berlaku untuk karyawan baru maupun lama yang kembali)
        $existingContracts = DB::table('employees_contract')
            ->where('npk', $npk)
            ->count();
        $contractKe = $existingContracts + 1;

        $duration = (int) ($request->month_duration ?? 6);
        $startDate = Carbon::parse($request->tmk);
        [$endDate, $dayDuration, $actualMonthDuration] = $this->calculateEndDateAndDayDuration($request, $startDate, $duration);

        // ── Basic salary kontrak ────────────────────────────────────────
        // Kalau pelamar ini punya pengajuan gaji yang SUDAH disetujui
        // (salary_approve.approved_salary), nominal itu yang dipakai
        // LANGSUNG sebagai gaji pokok kontrak — diambil ulang dari DB di
        // sini (bukan dari input form), supaya:
        //  1) Selalu konsisten dengan nominal yang benar-benar disetujui GM,
        //     walaupun field di form sempat di-disable/di-mask di browser.
        //  2) Role selain PAYROLL_STAFF tetap bisa menjalankan proses assign
        //     dengan benar meskipun mereka tidak pernah melihat nominal
        //     aslinya di layar (field tersebut dikirim ke browser sebagai
        //     "***" untuk mereka — lihat detail()).
        // Kalau belum ada pengajuan yang disetujui (mis. bukan posisi staff),
        // fallback ke input manual dari form seperti alur lama.
        $approvedSalary = SalaryApprove::where('id_pelamar', $id_pelamar)
            ->whereNotNull('approved_salary')
            ->value('approved_salary');

        $basicSalary = !is_null($approvedSalary)
            ? (float) $approvedSalary
            : (float) str_replace('.', '', $request->salary_raw ?? $request->salary ?? 2500000);

        DB::table('employees_contract')->insert([
            'id'              => (string) \Illuminate\Support\Str::uuid(),
            'npk'             => $npk,
            'contract_ke'     => $contractKe,
            'start_date'      => $startDate->toDateString(),
            'end_date'        => $endDate->toDateString(),
            'month_duration'  => (string) $actualMonthDuration,
            'day_duration'    => $dayDuration,
            'status_contract' => 'AKTIF',
            'type'            => 'CONTRACT',
            'salary'          => $basicSalary,
            'allowance'       => (float) str_replace('.', '', $request->allowance_raw ?? $request->allowance ?? 0),
            'pph21'           => (float) str_replace('.', '', $request->pph21_raw ?? $request->pph21 ?? 0),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        if ($request->filled('bank_account')) {
            DB::table('payroll_masters')->insert([
                'npk' => $npk,
                'bank_name' => 'PERMATA BANK',
                'bank_account' => $request->bank_account,
            ]);
        }

        DB::connection('cii')->commit();
    }

    /**
     * Move applicant's uploaded documents (stored on the 'public' disk under
     * pelamar/{field}/...) into the PKWT/employee-owned folder
     * (employees/{field}/... on the 'public' disk), and return a
     * [pkwt_column => relative_path] map ready to merge into the PKWT insert.
     *
     * The PKWT file_* columns store the FULL path relative to the 'public'
     * disk (e.g. "employees/file_cv/xxx.pdf"), matching what
     * BiodataController::update() and getSoftFiles() expect — not just a
     * bare filename.
     *
     * Note the column name mismatch between pelamar_details and PKWT:
     * pelamar_details uses "file_ijasah", while PKWT uses "file_ijazah".
     */
    private function moveApplicantFilesToPkwt($pelamarDetail, $npk)
    {
        if (!$pelamarDetail) {
            return [];
        }

        // pelamar_details column => PKWT column
        $fieldMap = [
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

        $result = [];

        foreach ($fieldMap as $pelamarField => $pkwtField) {
            $oldRelativePath = $pelamarDetail->$pelamarField ?? null;

            if (empty($oldRelativePath) || !Storage::disk('public')->exists($oldRelativePath)) {
                continue;
            }

            $extension = pathinfo($oldRelativePath, PATHINFO_EXTENSION);
            $newFileName = $npk . '_' . time() . '_' . $pkwtField . ($extension ? '.' . $extension : '');
            $newFolder = 'employees/' . $pkwtField;
            $newRelativePath = $newFolder . '/' . $newFileName;

            if (!Storage::disk('public')->exists($newFolder)) {
                Storage::disk('public')->makeDirectory($newFolder);
            }

            $moved = Storage::disk('public')->copy($oldRelativePath, $newRelativePath);

            $result[$pkwtField] = $newRelativePath;
        }

        return $result;
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

    private function calculateEndDateAndDayDuration($request, $startDate, $duration)
    {
        $regularEndDate = $startDate->copy()->addMonths($duration)->subDay();

        $options = [
            $regularEndDate->copy()->startOfMonth()->subMonth()->day(20),
            $regularEndDate->copy()->startOfMonth()->day(7),
            $regularEndDate->copy()->startOfMonth()->day(20),
            $regularEndDate->copy()->startOfMonth()->addMonth()->day(7),
        ];

        $closestDate = $options[0];
        $minDiff = abs($regularEndDate->diffInDays($closestDate, false));

        foreach ($options as $opt) {
            $diff = abs($regularEndDate->diffInDays($opt, false));
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closestDate = $opt;
            }
        }

        $endDate = $request->end_date ? Carbon::parse($request->end_date) : $closestDate;

        // Hitung durasi bulan penuh aktual (floor) dari startDate ke endDate
        // Misal: startDate=25/09, endDate=20/12 → 2 bulan (25/09→25/11)
        $actualMonthDuration = (int) $startDate->copy()->diffInMonths($endDate);

        // Batas akhir bulan penuh terakhir
        $fullMonthEnd = $startDate->copy()->addMonths($actualMonthDuration);

        // Hitung sisa hari kerja (Senin–Jumat) setelah bulan penuh tersebut hingga endDate
        // Misal: startDate=25/09, endDate=20/12 → dayDuration = hari kerja 26/11 s.d. 20/12
        $dayDuration = $fullMonthEnd->lt($endDate)
            ? $this->countWorkingDays($fullMonthEnd->copy()->addDay(), $endDate)
            : 0;

        return [$endDate, $dayDuration, $actualMonthDuration];
    }

    /**
     * Hitung jumlah hari kerja (Senin–Jumat) antara dua tanggal (inklusif kedua ujung).
     */
    private function countWorkingDays(Carbon $start, Carbon $end): int
    {
        $count   = 0;
        $current = $start->copy()->startOfDay();
        $endDay  = $end->copy()->startOfDay();

        while ($current->lte($endDay)) {
            if ($current->isWeekday()) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }
}