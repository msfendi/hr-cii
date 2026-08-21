<?php

namespace App\Http\Controllers;

use App\Exports\EmployeesContractAllExport;
use App\Exports\EmployeesContractExport;
use App\Imports\EmployeesContractImport;
use App\Models\EmployeesContract;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class EmployeesContractController extends Controller
{
    public function index()
    {
        return view('employees_contract.index');
    }

    /**
     * GET — JSON data untuk DataTables.
     *
     * Mode filter:
     *  - npk    → tampilkan SEMUA status kontrak karyawan tersebut (redirect dari biodata)
     *  - month  → kontrak yang berakhir di bulan YYYY-MM
     *  - default → kontrak aktif yang berakhir dalam 30 hari ke depan (sisa -7 s/d +30)
     *
     * BUG FIX: query sebelumnya tidak punya GROUP BY / distinct sehingga
     * join ke BIODATA menghasilkan duplikat baris per kontrak.
     * Solusi: select eksplisit hanya kolom yang dibutuhkan (tanpa wildcard).
     */
    public function getData(Request $request)
    {
        $npk    = trim($request->input('npk', ''));
        $month  = $request->input('month');   // format YYYY-MM
        $status = $request->input('status', 'AKTIF');
        $bagian = $request->input('bagian');
        $urgensi = $request->input('urgensi');

        // ── Cek hak akses user ────────────────────────────────────────────────
        $user = Auth::user();

        $roleAdmin = $user ? $user->hasRole('Admin') : false;
        $roleStaff = $user ? $user->hasRole('Payroll_STAFF') : false;
        $roleNonStaff = $user ? $user->hasRole('Payroll_NONSTAFF') : false;
        $roleSewing = $user ? $user->hasRole('Payroll_SEWING') : false;
        $roleNonSewing = $user ? $user->hasRole('Payroll_NONSEWING') : false;

        $query = DB::table('employees_contract as c')
            ->join('BIODATA as b', 'b.NPK', '=', 'c.npk')
            ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'b.ID_DEPT')
            ->select([
                'c.id',
                'c.npk',
                'b.NAMA_KARYAWAN as nama',
                'b.BAG            as bagian',
                'b.IS_STAFF',
                'd.IS_SEWING',
                'c.contract_ke',
                'c.start_date',
                'c.end_date',
                'c.month_duration',
                'c.status_contract',
                'c.salary',
                'c.allowance',
                'c.pph21',
                'c.daily_salary',
                DB::raw("DATEDIFF(DAY, CAST(GETDATE() AS DATE), c.end_date) AS sisa_hari"),
                DB::raw("DAY(c.end_date) AS end_day"),
            ]);

        // ── Role-based filtering ──────────────────────────────────────────────
        if (!$roleAdmin) {
            $query->where(function ($q) use ($roleStaff, $roleNonStaff, $roleSewing, $roleNonSewing) {
                if ($roleStaff) {
                    $q->orWhere('b.IS_STAFF', 1);
                }
                if ($roleNonStaff) {
                    $q->orWhere('b.IS_STAFF', 0);
                }
                if ($roleSewing) {
                    $q->orWhere(function ($q2) {
                        $q2->where('d.IS_SEWING', 0)->where('b.IS_STAFF', 0);
                    });
                }
                if ($roleNonSewing) {
                    $q->orWhere(function ($q2) {
                        $q2->where('d.IS_SEWING', 1)->where('b.IS_STAFF', 0);
                    });
                }
                // Jika tidak punya akses sama sekali, filter habis
                if (!$roleStaff && !$roleNonStaff && !$roleSewing && !$roleNonSewing) {
                    $q->whereRaw('1 = 0');
                }
            });
        }

        // ── Filter utama ──────────────────────────────────────────────────────
        if ($npk !== '') {
            // Redirect dari biodata: tampilkan semua kontrak karyawan (semua status)
            $query->where(function ($q) use ($npk) {
                $q->where('c.npk', 'like', "%{$npk}%")
                    ->orWhere('b.NAMA_KARYAWAN', 'like', "%{$npk}%");
            });
        } elseif (!empty($month) && str_contains($month, '-')) {
            [$y, $m] = array_map('intval', explode('-', $month));
            $query->whereRaw('MONTH(c.end_date) = ? AND YEAR(c.end_date) = ?', [$m, $y]);
        } else {
            // Default: kontrak yang berakhir -7 s/d +30 hari dari hari ini
            // $query->whereRaw('DATEDIFF(DAY, CAST(GETDATE() AS DATE), c.end_date) BETWEEN -7 AND 30');
            $query->where(function ($q) {
                $q->whereRaw('DATEDIFF(DAY, CAST(GETDATE() AS DATE), c.end_date) BETWEEN -7 AND 30')
                    ->orWhere('c.status_contract', 'AKTIF');
            });
        }

        // ── Filter status ─────────────────────────────────────────────────────
        if ($status && $status !== 'ALL') {
            $query->where('c.status_contract', $status);
        }

        // ── Filter bagian ─────────────────────────────────────────────────────
        if (!empty($bagian)) {
            $query->where('b.BAG', $bagian);
        }

        // ── Filter urgensi ─────────────────────────────────────────────────────
        if (!empty($urgensi)) {
            match ($urgensi) {
                'urgent'   => $query->whereRaw('DATEDIFF(DAY, CAST(GETDATE() AS DATE), c.end_date) <= 7'),
                'soon'     => $query->whereRaw('DATEDIFF(DAY, CAST(GETDATE() AS DATE), c.end_date) BETWEEN 8 AND 14'),
                'upcoming' => $query->whereRaw('DATEDIFF(DAY, CAST(GETDATE() AS DATE), c.end_date) BETWEEN 15 AND 30'),
                'normal'   => $query->where('c.status_contract', 'AKTIF'),
                // default    => null,
            };
        }

        // ── Map urgensi & cutoff ──────────────────────────────────────────────
        $rows = $query->orderByRaw("
            CASE WHEN c.status_contract = 'AKTIF' THEN 0 ELSE 1 END
        ")->orderBy('c.end_date', 'asc')
            ->get()
            ->map(function ($row) use ($roleAdmin, $roleStaff, $roleNonStaff, $roleSewing, $roleNonSewing) {
                $sisa = (int) $row->sisa_hari;
                $day  = (int) $row->end_day;

                $row->ke_cutoff7  = $day - 7;
                $row->ke_cutoff20 = $day - 20;
                $row->urgensi     = match (true) {
                    $sisa <= 7  => 'urgent',
                    $sisa <= 14 => 'soon',
                    $sisa <= 30 => 'upcoming',
                    default     => 'normal',
                };

                // Transform financial data based on role
                $canSeeSalary = false;
                if ($roleAdmin) {
                    $canSeeSalary = true;
                } elseif ($roleStaff && $row->IS_STAFF == 1) {
                    $canSeeSalary = true;
                } elseif ($roleNonStaff && $row->IS_STAFF == 0) {
                    $canSeeSalary = true;
                } elseif ($roleSewing && $row->IS_SEWING == 0 && $row->IS_STAFF == 0) {
                    $canSeeSalary = true;
                } elseif ($roleNonSewing && $row->IS_SEWING == 1 && $row->IS_STAFF == 0) {
                    $canSeeSalary = true;
                }

                $row->salary       = $canSeeSalary ? (float) $row->salary : '***';
                $row->allowance    = $canSeeSalary ? (float) $row->allowance : '***';
                $row->pph21        = $canSeeSalary ? (float) $row->pph21 : '***';
                $row->daily_salary = $canSeeSalary ? (float) $row->daily_salary : '***';
                $row->can_edit     = $canSeeSalary;

                return $row;
            });




        return response()->json(['data' => $rows]);
    }

    /**
     * GET — Riwayat kontrak satu karyawan (untuk modal di halaman biodata).
     */
    public function getByNpk(string $npk)
    {
        // ── Cek hak akses user ────────────────────────────────────────────────
        $user = Auth::user();

        $roleAdmin = $user ? $user->hasRole('Admin') : false;
        $roleStaff = $user ? $user->hasRole('Payroll_STAFF') : false;
        $roleNonStaff = $user ? $user->hasRole('Payroll_NONSTAFF') : false;
        $roleSewing = $user ? $user->hasRole('Payroll_SEWING') : false;
        $roleNonSewing = $user ? $user->hasRole('Payroll_NONSEWING') : false;

        $query = DB::table('employees_contract as c')
            ->join('BIODATA as b', 'b.NPK', '=', 'c.npk')
            ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'b.ID_DEPT')
            ->select('c.*', 'b.IS_STAFF', 'd.IS_SEWING')
            ->where('c.npk', strtoupper($npk))
            ->orderBy('c.contract_ke', 'asc');

        $rows = $query->get()
            ->map(function ($row) use ($roleAdmin, $roleStaff, $roleNonStaff, $roleSewing, $roleNonSewing) {
                // Transform financial data based on role
                $canSeeSalary = false;
                if ($roleAdmin) {
                    $canSeeSalary = true;
                } elseif ($roleStaff && $row->IS_STAFF == 1) {
                    $canSeeSalary = true;
                } elseif ($roleNonStaff && $row->IS_STAFF == 0) {
                    $canSeeSalary = true;
                } elseif ($roleSewing && $row->IS_SEWING == 0 && $row->IS_STAFF == 0) {
                    $canSeeSalary = true;
                } elseif ($roleNonSewing && $row->IS_SEWING == 1 && $row->IS_STAFF == 0) {
                    $canSeeSalary = true;
                }

                $row->salary       = $canSeeSalary ? (float) $row->salary : '***********';
                $row->allowance    = $canSeeSalary ? (float) $row->allowance : '***********';
                $row->pph21        = $canSeeSalary ? (float) $row->pph21 : '***********';
                $row->daily_salary = $canSeeSalary ? (float) $row->daily_salary : '***********';
                $row->can_edit     = $canSeeSalary;

                return $row;
            });

        return response()->json($rows);
    }

    /**
     * POST — Simpan kontrak baru.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'npk'             => 'required|string',
            'contract_ke'     => 'required|integer|min:1',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after:start_date',
            'month_duration'  => 'required|in:1,3,6,9,12',
            'status_contract' => 'required|in:AKTIF,HABIS,DIPERPANJANG,DIAKHIRI',
            'salary'          => 'nullable|numeric|min:0',
            'allowance'       => 'nullable|numeric|min:0',
        ]);

        EmployeesContract::create($data);

        return response()->json(['success' => true, 'message' => 'Kontrak berhasil disimpan.']);
    }

    /**
     * POST — Tandai kontrak DIAKHIRI (resign sebelum kontrak habis).
     */
    public function stop(string $id)
    {
        $contract = EmployeesContract::findOrFail($id);
        $contract->update(['status_contract' => 'DIAKHIRI']);

        return response()->json(['success' => true, 'message' => 'Kontrak telah diakhiri.']);
    }

    /**
     * POST — Tandai kontrak HABIS (berakhir normal).
     */
    public function finish(string $id)
    {
        $contract = EmployeesContract::findOrFail($id);
        $contract->update(['status_contract' => 'HABIS']);

        return response()->json(['success' => true, 'message' => 'Kontrak telah selesai.']);
    }

    /**
     * POST — Perpanjang kontrak.
     * Kontrak lama → DIPERPANJANG, kontrak baru dibuat otomatis ke+1.
     */
    public function extend(Request $request, string $id)
    {
        $data = $request->validate([
            'month_duration' => 'required|numeric',
            'salary'         => 'nullable|numeric|min:0',
            'allowance'      => 'nullable|numeric|min:0',
            'pph21'          => 'nullable|numeric|min:0',
            'daily_salary'   => 'nullable|numeric|min:0',
        ]);

        $old = EmployeesContract::findOrFail($id);

        if ($old->status_contract !== 'AKTIF') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya kontrak AKTIF yang bisa diperpanjang.',
            ], 422);
        }

        DB::connection('cii')->beginTransaction();

        try {

            // Kontrak lama
            $old->update([
                'status_contract' => 'DIPERPANJANG',
            ]);

            // Contract ke berikutnya
            $newContractKe = (int) $old->contract_ke + 1;

            // Tanggal mulai kontrak baru
            $newStart = Carbon::parse($old->end_date)
                ->addDay();

            // Tanggal akhir berdasarkan duration
            $newEnd = $newStart
                ->copy()
                ->addMonths((int) $data['month_duration'])
                ->subDay();

            // Cut-off tanggal 7 / 20
            if ($newEnd->day <= 13) {
                $newEnd->day(7);
            } else {
                $newEnd->day(20);
            }

            // Buat kontrak baru
            $newContract = EmployeesContract::create([
                'npk'             => $old->npk,
                'contract_ke'     => $newContractKe,
                'start_date'      => $newStart->toDateString(),
                'end_date'        => $newEnd->toDateString(),
                'month_duration'  => (string) $data['month_duration'],
                'day_duration'    => 0,
                'status_contract' => 'AKTIF',

                'salary'          => (isset($data['salary']) && $data['salary'] !== null && $data['salary'] !== '')
                    ? $data['salary'] 
                    : $old->salary,

                'allowance'       => (isset($data['allowance']) && $data['allowance'] !== null && $data['allowance'] !== '')
                    ? $data['allowance'] 
                    : $old->allowance,

                'pph21'           => (isset($data['pph21']) && $data['pph21'] !== null && $data['pph21'] !== '')
                    ? $data['pph21'] 
                    : $old->pph21,

                'daily_salary'    => (isset($data['daily_salary']) && $data['daily_salary'] !== null && $data['daily_salary'] !== '')
                    ? $data['daily_salary'] 
                    : $old->daily_salary,

                'type'            => $old->type,
            ]);

            // Pastikan object benar-benar tersimpan
            if (!$newContract->exists) {
                throw new \Exception('Kontrak baru gagal dibuat.');
            }

            DB::connection('cii')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Kontrak berhasil diperpanjang.',
                'data'    => $newContract,
            ]);

        } catch (\Exception $e) {

            DB::connection('cii')->rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST — Split kontrak (ubah gaji/tunjangan/pph21/daily_salary di tengah kontrak).
     * Kontrak lama → end_date diset ke tanggal split form, status DIPERPANJANG.
     * Kontrak baru → start_date diset dari form, contract_ke tetap sama, status AKTIF.
     */
    public function split(Request $request, string $id)
    {
        $data = $request->validate([
            'split_date'     => 'required|date_format:Y-m',
            'month_duration' => 'nullable|numeric',
            'salary'         => 'nullable|numeric|min:0',
            'allowance'      => 'nullable|numeric|min:0',
            'pph21'          => 'nullable|numeric|min:0',
            'daily_salary'   => 'nullable|numeric|min:0',
            'day_duration'   => 'nullable|numeric|min:0',
        ]);

        $old = EmployeesContract::findOrFail($id);

        if ($old->status_contract !== 'AKTIF') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya kontrak AKTIF yang bisa displit.',
            ], 422);
        }

        DB::connection('cii')->beginTransaction();

        try {
            // split_date berupa YYYY-MM, set ke tanggal 7 bulan tersebut, reset waktu agar komparasi akurat
            $splitMonth = \Carbon\Carbon::createFromFormat('Y-m', $data['split_date'])->day(7)->startOfDay();

            // Simpan end_date asli untuk dilanjutkan oleh kontrak baru
            $originalEnd = \Carbon\Carbon::parse($old->end_date)->startOfDay();

            // Hitung ulang durasi untuk adjustment lama
            $oldStart = \Carbon\Carbon::parse($old->start_date)->startOfDay();

            // Normalisasi start_date ke awal siklus (tanggal 8) agar penghitungan bulan genap
            // Contoh: 14 Mei dihitung dari 8 Mei. 5 Mei dihitung dari 8 April.
            $normalizedStart = $oldStart->day <= 7
                ? $oldStart->copy()->subMonth()->day(8)
                : $oldStart->copy()->day(8);

            $oldEnd = $splitMonth->copy();

            // Tambahkan 1 hari ke end_date saat menghitung bulan agar 8 s/d 7 terhitung pas 1 bulan penuh
            $oldMonthDuration = $normalizedStart->diffInMonths($oldEnd->copy()->addDay());
            $oldExactEndForDuration = $normalizedStart->copy()->addMonths($oldMonthDuration)->subDay();
            $oldDayDuration = $oldExactEndForDuration->diffInDays($oldEnd, false);

            // Update adjustment lama: end_date di tgl 7, durasi diperbarui
            $old->update([
                'end_date'        => $oldEnd->toDateString(),
                'month_duration'  => (string) $oldMonthDuration,
                'day_duration'    => (string) $oldDayDuration,
                'status_contract' => 'ADJUSTMENT'
            ]);

            // Start date adjustment baru: esok harinya dari tgl 7, yaitu tgl 8
            $newStart = $splitMonth->copy()->addDay();

            // End date adjustment baru meneruskan sisa dari kontrak lama
            $newEnd = $originalEnd;
            // Hitung durasi sisa untuk adjustment baru
            $newMonthDuration = $newStart->diffInMonths($newEnd->copy()->addDay());
            $newExactEnd = $newStart->copy()->addMonths($newMonthDuration)->subDay();
            $newDayDuration = $newExactEnd->diffInDays($newEnd, false);

            // Buat adjustment baru
            EmployeesContract::create([
                'npk'             => $old->npk,
                'contract_ke'     => $old->contract_ke,
                'start_date'      => $newStart->toDateString(),
                'end_date'        => $newEnd->toDateString(),
                'month_duration'  => (string) $newMonthDuration,
                'day_duration'    => (string) $newDayDuration,
                'status_contract' => 'AKTIF',
                'type'            => 'CONTRACT',
                'salary'          => (isset($data['salary']) && $data['salary'] !== null && $data['salary'] !== '') ? $data['salary'] : $old->salary,
                'allowance'       => (isset($data['allowance']) && $data['allowance'] !== null && $data['allowance'] !== '') ? $data['allowance'] : $old->allowance,
                'pph21'           => (isset($data['pph21']) && $data['pph21'] !== null && $data['pph21'] !== '') ? $data['pph21'] : $old->pph21,
                'daily_salary'    => (isset($data['daily_salary']) && $data['daily_salary'] !== null && $data['daily_salary'] !== '') ? $data['daily_salary'] : $old->daily_salary,
            ]);

            DB::connection('cii')->commit();

            return response()->json(['success' => true, 'message' => 'Kontrak berhasil displit.']);
        } catch (\Exception $e) {
            DB::connection('cii')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST — Update gaji / tunjangan / PPH21.
     * Hanya untuk kontrak berstatus AKTIF.
     */
    public function updateSalary(Request $request, string $id)
    {
        $data = $request->validate([
            'salary'       => 'nullable|numeric|min:0',
            'allowance'    => 'nullable|numeric|min:0',
            'pph21'        => 'nullable|numeric|min:0',
            'daily_salary' => 'nullable|numeric|min:0',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date',
        ]);

        $contract = EmployeesContract::findOrFail($id);

        // if ($contract->status_contract !== 'AKTIF') {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Hanya kontrak AKTIF yang bisa diupdate.',
        //     ], 422);
        // }


        // Calculate duration if dates are updated
        if (isset($data['start_date']) || isset($data['end_date'])) {
            $start = \Carbon\Carbon::parse($data['start_date'] ?? $contract->start_date);
            $end   = \Carbon\Carbon::parse($data['end_date']   ?? $contract->end_date);

            $fullMonths           = $start->diffInMonths($end);
            $afterFullMonths      = $start->copy()->addMonths($fullMonths);
            $data['month_duration'] = $fullMonths;
            $data['day_duration']   = $afterFullMonths->diffInDays($end); // sisa hari setelah bulan penuh
        }

        // Hanya update field yang dikirim (bukan null)
        $contract->update(array_filter($data, fn($v) => !is_null($v)));

        return response()->json(['success' => true, 'message' => 'Data finansial berhasil diperbarui.']);
    }

    /**
     * DELETE — Hapus kontrak.
     */
    public function destroy(string $id)
    {
        EmployeesContract::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Kontrak berhasil dihapus.']);
    }

    /**
     * GET — Daftar bagian unik untuk dropdown filter.
     */
    public function getBagian()
    {
        $bagian = DB::table('BIODATA')
            ->whereNotNull('BAG')
            ->distinct()
            ->orderBy('BAG')
            ->pluck('BAG');

        return response()->json($bagian);
    }

    /**
     * POST — Import kontrak dari file Excel.
     * Kolom: npk | contract_ke | start_date | end_date | month_duration | status_contract | salary | allowance
     */
    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls']);

        $import = new EmployeesContractImport();
        Excel::import($import, $request->file('file'));

        return response()->json([
            'success'  => true,
            'inserted' => $import->inserted,
            'skipped'  => $import->skipped,
            'message'  => "Import selesai: {$import->inserted} berhasil, {$import->skipped} diskip.",
        ]);
    }

    /**
     * GET — Download template import
     */
    public function template()
    {
        return Excel::download(new \App\Exports\EmployeesContractTemplateExport, 'template_kontrak.xlsx');
    }

    /**
     * GET — Export kontrak berakhir di bulan tertentu ke Excel.
     */
    public function export(Request $request)
    {
        $month    = (int) $request->input('month', date('m'));
        $year     = (int) $request->input('year',  date('Y'));
        $filename = 'kontrak-berakhir_' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . $year . '.xlsx';

        return Excel::download(new EmployeesContractExport($month, $year), $filename);
    }

    /**
     * GET — Export SEMUA kontrak ke Excel, difilter berdasarkan role user.
     * Sort: NPK → contract_ke → start_date
     */
    public function exportAll()
    {
        $user = Auth::user();

        $roleAdmin     = $user ? $user->hasRole('Admin') : false;
        $roleStaff     = $user ? $user->hasRole('Payroll_STAFF') : false;
        $roleNonStaff  = $user ? $user->hasRole('Payroll_NONSTAFF') : false;
        $roleSewing    = $user ? $user->hasRole('Payroll_SEWING') : false;
        $roleNonSewing = $user ? $user->hasRole('Payroll_NONSEWING') : false;

        $filename = 'semua-kontrak_' . date('Ymd_His') . '.xlsx';

        return Excel::download(
            new EmployeesContractAllExport($roleAdmin, $roleStaff, $roleNonStaff, $roleSewing, $roleNonSewing),
            $filename
        );
    }
}
