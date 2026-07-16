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
            ->select('PELAMAR.ID', 'NPK', 'NAMA', 'JENIS_KELAMIN', 'TMPT_LAHIR', 'TGL_LAHIR', 'TMK', 'UMUR', 'NIK', 'KABUPATEN', 'HP') // Added ID
            ->leftJoin('pelamar_details', 'pelamar_details.id_pelamar', '=', 'PELAMAR.ID')
            ->where('IS_KONTRAK', 'FALSE')
            ->where('pelamar_details.status_apply', 'ONBOARDING')
            ->orderBy('NPK', 'ASC')
            ->get();

        $departments = DB::connection('cii')->table('DEPT')->select('ID_DEPT', 'DEPARTEMENT')->where('SECTION', 'CHUTEX')->get(); // Fetch departments
        $sections = DB::table('sections')->orderBy('name', 'asc')->get();

        return view('pelamar.index', compact('pelamars', 'departments', 'sections'));
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
        }
        return response()->json($pelamar);
    }

    private function processEmployeeAssignment($request)
    {
        DB::connection('cii')->beginTransaction();

        $id_pelamar = $request->id_pelamar;
        $pelamar = DB::connection('cii')->table('PELAMAR')->where('ID', $id_pelamar)->first();

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

        $dept = DB::connection('cii')->table('DEPT')->select('*')->where('ID_DEPT', $request->id_dept)->first();

        DB::connection('cii')->table('BIODATA')->insert([
            'NPK' => strtoupper($request->npk),
            'NAMA_KARYAWAN' => strtoupper($request->nama),
            'BAG' => $dept->id_parent_dept,
            'ID_DEPT' => $request->id_dept,
            'JENIS_KEL' => strtoupper($request->jk),
            'BARCODE' => $last_barcode,
            'SECTION' => strtoupper($request->section),
            'STATUS' => 'A',
            'IS_STAFF' => $request->has('is_staff') ? 1 : 0,
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

        // Cek kontrak ke berapa (berlaku untuk karyawan baru maupun lama yang kembali)
        $existingContracts = DB::table('employees_contract')
            ->where('npk', strtoupper($request->npk))
            ->count();
        $contractKe = $existingContracts + 1;

        $duration = (int) ($request->month_duration ?? 6);
        $startDate = Carbon::parse($request->tmk);
        [$endDate, $dayDuration, $actualMonthDuration] = $this->calculateEndDateAndDayDuration($request, $startDate, $duration);

        DB::table('employees_contract')->insert([
            'id'              => (string) \Illuminate\Support\Str::uuid(),
            'npk'             => strtoupper($request->npk),
            'contract_ke'     => $contractKe,
            'start_date'      => $startDate->toDateString(),
            'end_date'        => $endDate->toDateString(),
            'month_duration'  => (string) $actualMonthDuration,
            'day_duration'    => $dayDuration,
            'status_contract' => 'AKTIF',
            'type'            => 'CONTRACT',
            'salary'          => (float) str_replace('.', '', $request->salary_raw ?? $request->salary ?? 2500000),
            'allowance'       => (float) str_replace('.', '', $request->allowance_raw ?? $request->allowance ?? 0),
            'pph21'           => (float) str_replace('.', '', $request->pph21_raw ?? $request->pph21 ?? 0),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        if ($request->filled('bank_account')) {
            DB::table('payroll_masters')->insert([
                'npk' => strtoupper($request->npk),
                'bank_name' => 'PERMATA BANK',
                'bank_account' => $request->bank_account,
            ]);
        }

        DB::connection('cii')->commit();
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
