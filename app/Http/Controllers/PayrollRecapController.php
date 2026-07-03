<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PayrollRecapController extends Controller
{
    /**
     * Daftar component payroll yang boleh dipilih di filter.
     * Key HARUS sama persis dengan key JSON di kolom `components`.
     * Whitelist ini juga jadi validasi input dari client.
     */
    private array $components = [
        'earning' => [
            'basic_salary'           => 'Gaji Pokok',
            'overtime_pay'           => 'Lembur',
            'special_overtime_pay'   => 'Lembur Khusus',
            'monthly_premi'          => 'Premi Bulanan',
            'long_service_allowance' => 'Tunjangan Masa Kerja',
            'allowance'              => 'Tunjangan',
            'sewing_insentif'        => 'Insentif Sewing',
            'pad_insentif'           => 'Insentif PAD',
            'cutting_insentif'       => 'Insentif Cutting',
            'heat_insentif'          => 'Insentif Heat',
            'sixs_insentif'          => 'Insentif 6S',
            'adjusment'              => 'Penyesuaian (Adjustment)',
            'pph_21'                 => 'PPh 21 (Earning)',
        ],
        'deduction' => [
            'work_leave_deduction' => 'Potongan Cuti',
            'late_deduction'       => 'Potongan Keterlambatan',
            'bpjs_kesehatan'       => 'BPJS Kesehatan',
            'bpjs_ketenagakerjaan' => 'BPJS Ketenagakerjaan',
            'pph_21_deduction'     => 'PPh 21 (Potongan)',
            'absence_deduction'    => 'Potongan Absensi',
        ],
    ];

    /**
     * Halaman utama Rekap Payroll.
     */
    public function index()
    {
        // employee_dept di payroll_run_details adalah FK ke DEPT.ID_DEPT
        $departments = DB::table('payroll_run_details as prd')
            ->join('DEPT', 'DEPT.ID_DEPT', '=', 'prd.employee_dept')
            ->select('DEPT.ID_DEPT as id', 'DEPT.DEPARTEMENT as name')
            ->distinct()
            ->orderBy('DEPT.DEPARTEMENT')
            ->get();

        return view('payroll_recap.index', [
            'departments' => $departments,
            'components'  => $this->components,
        ]);
    }

    /**
     * AJAX: autocomplete pencarian karyawan (by NPK / nama) untuk Select2.
     * GET /payroll/recap/search-employee?q=xxxx
     */
    public function searchEmployee(Request $request)
    {
        $q = trim((string) $request->get('q'));

        $employees = DB::table('payroll_run_details')
            ->select('employee_npk', 'employee_name')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('employee_npk', 'like', "%{$q}%")
                        ->orWhere('employee_name', 'like', "%{$q}%");
                });
            })
            ->distinct()
            ->orderBy('employee_name')
            ->limit(20)
            ->get()
            ->map(function ($row) {
                return [
                    'id'   => $row->employee_npk,
                    'text' => "{$row->employee_npk} - {$row->employee_name}",
                ];
            });

        return response()->json(['results' => $employees]);
    }

    /**
     * AJAX: data untuk chart rekap payroll.
     * GET /payroll/recap/chart-data
     *
     * Query params:
     *  - end_month   (Y-m)  default: bulan sekarang. Rentang otomatis 12 bulan ke belakang.
     *  - npk         (nullable string)
     *  - dept        (nullable string/int, ID_DEPT)
     *  - component   (nullable string) default: total_salary
     *
     * Catatan: penjumlahan nilai komponen JSON dilakukan di PHP (bukan JSON_VALUE
     * di SQL) karena JSON_VALUE butuh SQL Server 2016+ / compatibility level 130+,
     * dan tidak tersedia di semua environment. Pendekatan ini juga lebih aman
     * (tidak menyisipkan nama komponen ke dalam raw SQL).
     *
     * Status Aktif / Keluar per bulan ditentukan dari PKWT.TKK:
     *  - TKK kosong                       -> selalu Aktif
     *  - bulan-tahun TKK > bulan periode  -> Aktif di bulan itu
     *  - bulan-tahun TKK <= bulan periode -> Keluar di bulan itu
     * Contoh: TKK = Juli, payroll bulan Juni -> Aktif di Juni, baru
     * terhitung Keluar mulai payroll bulan Juli.
     */
    public function chartData(Request $request)
    {
        $endMonth = $request->filled('end_month')
            ? Carbon::createFromFormat('Y-m', $request->end_month)->endOfMonth()
            : Carbon::now()->endOfMonth();

        // Selalu tarik mundur 12 bulan dari bulan akhir yang dipilih
        $startMonth = (clone $endMonth)->subMonths(11)->startOfMonth();
        $endKey     = $endMonth->format('Y-m');

        $npk       = $request->get('npk');
        $dept      = $request->get('dept');
        $component = $request->get('component', 'total_salary');

        $flatComponents = array_merge($this->components['earning'], $this->components['deduction']);

        if ($component !== 'total_salary' && !array_key_exists($component, $flatComponents)) {
            return response()->json(['message' => 'Component tidak valid.'], 422);
        }

        $componentLabel = $component === 'total_salary'
            ? 'Total Take Home Pay'
            : $flatComponents[$component];

        // Union data biodata karyawan aktif & yang sudah keluar, digabung by NPK.
        // Diasumsikan satu NPK hanya muncul di salah satu dari kedua tabel ini.
        $bioUnion = DB::table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'BAG')
            ->unionAll(
                DB::table('BIODATA_KELUAR')->select('NPK', 'NAMA_KARYAWAN', 'BAG')
            );

        // Ambil data mentah (tanpa parsing JSON di SQL), lalu agregasi di PHP.
        // Join ke bio (union) & PKWT dipakai untuk nama, bagian, dan TKK (tanggal keluar).
        $rows = DB::table('payroll_run_details as prd')
            ->join('payroll_runs as pr', 'prd.run_id', '=', 'pr.id')
            ->join('payroll_periods as pp', 'pr.period_id', '=', 'pp.id')
            ->leftJoinSub($bioUnion, 'bio', fn($join) => $join->on('bio.NPK', '=', 'prd.employee_npk'))
            ->leftJoin('PKWT as pkwt', 'pkwt.NPK', '=', 'prd.employee_npk')
            ->whereBetween('pp.start_date', [$startMonth->toDateString(), $endMonth->toDateString()])
            ->when($npk, fn($q) => $q->where('prd.employee_npk', $npk))
            ->when($dept, fn($q) => $q->where('prd.employee_dept', $dept))
            ->select(
                'pp.id as period_id',
                'pp.name as period_name',
                'pp.start_date as period_start',
                'prd.employee_npk',
                'prd.total_salary',
                'prd.components',
                DB::raw('COALESCE(bio.NAMA_KARYAWAN, prd.employee_name) as nama'),
                DB::raw('bio.BAG as bagian'),
                'pkwt.TKK as tkk'
            )
            ->orderBy('pp.start_date')
            ->get();

        // Susun bucket 12 bulan penuh (walau datanya kosong tetap tampil 0)
        $months = collect();
        $cursor = (clone $startMonth);
        while ($cursor->lte($endMonth)) {
            $months->put($cursor->format('Y-m'), [
                'label'            => $cursor->translatedFormat('M Y'),
                'aktif_total'      => 0.0,
                'aktif_employees'  => [],
                'keluar_total'     => 0.0,
                'keluar_employees' => [],
            ]);
            $cursor->addMonth();
        }

        $employees = [];

        foreach ($rows as $row) {
            $periodKey = Carbon::parse($row->period_start)->format('Y-m');
            if (!$months->has($periodKey)) {
                continue;
            }

            if ($component === 'total_salary') {
                $value = (float) $row->total_salary;
            } else {
                $comp  = json_decode($row->components, true) ?? [];
                $value = (float) ($comp[$component]['amount'] ?? 0);
            }

            $tkkKey  = $row->tkk ? Carbon::parse($row->tkk)->format('Y-m') : null;
            $isKeluarThisPeriod = $tkkKey !== null && $tkkKey <= $periodKey;

            $bucket = $months[$periodKey];
            if ($isKeluarThisPeriod) {
                $bucket['keluar_total'] += $value;
                $bucket['keluar_employees'][$row->employee_npk] = true;
            } else {
                $bucket['aktif_total'] += $value;
                $bucket['aktif_employees'][$row->employee_npk] = true;
            }
            $months[$periodKey] = $bucket;

            // Rekap per karyawan (akumulasi total sepanjang rentang bulan terpilih)
            if (!isset($employees[$row->employee_npk])) {
                $employees[$row->employee_npk] = [
                    'npk'          => $row->employee_npk,
                    'nama'         => $row->nama,
                    'bagian'       => $row->bagian,
                    'tkk'          => $row->tkk,
                    'total'        => 0.0,
                    'months_count' => 0,
                ];
            }
            $employees[$row->employee_npk]['total'] += $value;
            $employees[$row->employee_npk]['months_count'] += 1;
        }

        $labels        = $months->pluck('label')->values();
        $aktifValues   = $months->map(fn($m) => round($m['aktif_total'], 2))->values();
        $keluarValues  = $months->map(fn($m) => round($m['keluar_total'], 2))->values();
        $values        = $months->map(fn($m) => round($m['aktif_total'] + $m['keluar_total'], 2))->values();
        $aktifCounts   = $months->map(fn($m) => count($m['aktif_employees']))->values();
        $keluarCounts  = $months->map(fn($m) => count($m['keluar_employees']))->values();
        $employeeCounts = $months->map(fn($m) => count($m['aktif_employees']) + count($m['keluar_employees']))->values();

        $grandTotal   = $values->sum();
        $activeMonths = $values->filter(fn($v) => $v > 0)->count();
        $avgPerMonth  = $activeMonths > 0 ? $grandTotal / $activeMonths : 0;
        $maxEmployees = $employeeCounts->max() ?? 0;

        // Rincian per karyawan: status ditentukan relatif terhadap bulan akhir yang dipilih
        $employeeList = collect($employees)->map(function ($emp) use ($endKey) {
            $tkkKey = $emp['tkk'] ? Carbon::parse($emp['tkk'])->format('Y-m') : null;
            $emp['status']        = ($tkkKey !== null && $tkkKey <= $endKey) ? 'keluar' : 'aktif';
            $emp['tkk_formatted'] = $emp['tkk'] ? Carbon::parse($emp['tkk'])->format('d-m-Y') : null;
            $emp['total']         = round($emp['total'], 2);
            unset($emp['tkk']);
            return $emp;
        })->sortByDesc('total')->values();

        return response()->json([
            'labels'          => $labels,
            'values'          => $values,
            'aktif_values'    => $aktifValues,
            'keluar_values'   => $keluarValues,
            'aktif_counts'    => $aktifCounts,
            'keluar_counts'   => $keluarCounts,
            'employee_counts' => $employeeCounts,
            'employees'       => $employeeList,
            'component_label' => $componentLabel,
            'grand_total'     => $grandTotal,
            'avg_per_month'   => $avgPerMonth,
            'max_employees'   => $maxEmployees,
            'range' => [
                'start' => $startMonth->format('Y-m'),
                'end'   => $endMonth->format('Y-m'),
            ],
        ]);
    }
}
