<?php

namespace App\Http\Controllers;

use App\Models\RolePayroll;
use App\Services\PayrollRoleFilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
     * Role payroll efektif user yang sedang login, sumber kebenaran dari
     * table role_payrolls (lihat PayrollRoleFilterService::getRole()).
     */
    private function getRole(): ?string
    {
        return PayrollRoleFilterService::getRole(Auth::user());
    }

    /**
     * Label untuk ditampilkan di UI, mis. "Staff", "Sewing", dst.
     */
    private function getRoleLabel(?string $role): ?string
    {
        if ($role === null) {
            return null;
        }

        return RolePayroll::ROLES[$role] ?? $role;
    }

    /**
     * Halaman utama Rekap Payroll.
     */
    public function index()
    {
        $role = $this->getRole();

        // bio dipakai untuk kolom IS_STAFF (sumber filter role payroll)
        $bioUnion = DB::table('BIODATA')
            ->select('NPK', 'IS_STAFF')
            ->unionAll(
                DB::table('BIODATA_KELUAR')->select('NPK', 'IS_STAFF')
            );

        // employee_dept di payroll_run_details adalah FK ke DEPT.ID_DEPT
        $departmentsQuery = DB::table('payroll_run_details as prd')
            ->join('DEPT', 'DEPT.ID_DEPT', '=', 'prd.employee_dept')
            ->leftJoinSub($bioUnion, 'bio', fn($join) => $join->on('bio.NPK', '=', 'prd.employee_npk'));

        $departmentsQuery = PayrollRoleFilterService::applyToQuery(
            $departmentsQuery,
            $role,
            'bio.IS_STAFF',
            'DEPT.IS_SEWING'
        );

        $departments = $departmentsQuery
            ->select('DEPT.ID_DEPT as id', 'DEPT.DEPARTEMENT as name')
            ->distinct()
            ->orderBy('DEPT.DEPARTEMENT')
            ->get();

        return view('payroll_recap.index', [
            'departments'    => $departments,
            'components'     => $this->components,
            'payrollRole'    => $role,
            'payrollRoleLabel' => $this->getRoleLabel($role),
        ]);
    }

    /**
     * AJAX: autocomplete pencarian karyawan (by NPK / nama) untuk Select2.
     * GET /payroll/recap/search-employee?q=xxxx
     *
     * Ikut difilter role_payrolls supaya user tidak bisa mencari/memilih
     * NPK di luar cakupan role-nya.
     */
    public function searchEmployee(Request $request)
    {
        $role = $this->getRole();
        $q    = trim((string) $request->get('q'));

        $bioUnion = DB::table('BIODATA')
            ->select('NPK', 'IS_STAFF')
            ->unionAll(
                DB::table('BIODATA_KELUAR')->select('NPK', 'IS_STAFF')
            );

        $query = DB::table('payroll_run_details as prd')
            ->leftJoinSub($bioUnion, 'bio', fn($join) => $join->on('bio.NPK', '=', 'prd.employee_npk'))
            ->leftJoin('DEPT as dept_main', 'dept_main.ID_DEPT', '=', 'prd.employee_dept')
            ->select('prd.employee_npk', 'prd.employee_name')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('prd.employee_npk', 'like', "%{$q}%")
                        ->orWhere('prd.employee_name', 'like', "%{$q}%");
                });
            });

        $query = PayrollRoleFilterService::applyToQuery(
            $query,
            $role,
            'bio.IS_STAFF',
            'dept_main.IS_SEWING'
        );

        $employees = $query
            ->distinct()
            ->orderBy('prd.employee_name')
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
     * Ubah nilai JUMLAH_JAM_LEMBUR (nvarchar) menjadi float dengan aman.
     * Menangani pemisah desimal koma ("2,5" -> 2.5). Sesuaikan di sini jika
     * format datanya ternyata berbeda (mis. "HH:MM").
     */
    private function parseJam($value): float
    {
        if ($value === null) return 0.0;
        $value = trim((string) $value);
        if ($value === '') return 0.0;
        $value = str_replace(',', '.', $value);
        return is_numeric($value) ? (float) $value : 0.0;
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
     * Semua sumber data (rekap bulanan, rekap karyawan, rekap dept, rekap
     * overtime) difilter berdasarkan payroll_role user login lewat
     * PayrollRoleFilterService::applyToQuery() -- aturan deny-by-default:
     * role null / tidak terdaftar di role_payrolls -> data kosong.
     */
    public function chartData(Request $request)
    {
        $role = $this->getRole();

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
        // IS_STAFF ditambahkan agar bisa dipakai untuk filter payroll_role.
        $bioUnion = DB::table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'BAG', 'IS_STAFF')
            ->unionAll(
                DB::table('BIODATA_KELUAR')->select('NPK', 'NAMA_KARYAWAN', 'BAG', 'IS_STAFF')
            );

        // Ambil data mentah (tanpa parsing JSON di SQL), lalu agregasi di PHP.
        // Join ke bio (union) & PKWT dipakai untuk nama, bagian, dan TKK (tanggal keluar).
        $rowsQuery = DB::table('payroll_run_details as prd')
            ->join('payroll_runs as pr', 'prd.run_id', '=', 'pr.id')
            ->join('payroll_periods as pp', 'pr.period_id', '=', 'pp.id')
            ->leftJoinSub($bioUnion, 'bio', fn($join) => $join->on('bio.NPK', '=', 'prd.employee_npk'))
            ->leftJoin('PKWT as pkwt', 'pkwt.NPK', '=', 'prd.employee_npk')
            ->leftJoin('DEPT as dept_main', 'dept_main.ID_DEPT', '=', 'prd.employee_dept')
            ->whereBetween('pp.start_date', [$startMonth->toDateString(), $endMonth->toDateString()])
            ->when($npk, fn($q) => $q->where('prd.employee_npk', $npk))
            ->when($dept, fn($q) => $q->where('prd.employee_dept', $dept));

        $rowsQuery = PayrollRoleFilterService::applyToQuery(
            $rowsQuery,
            $role,
            'bio.IS_STAFF',
            'dept_main.IS_SEWING'
        );

        $rows = $rowsQuery
            ->select(
                'pp.id as period_id',
                'pp.name as period_name',
                'pp.start_date as period_start',
                'prd.employee_npk',
                'prd.employee_dept as dept_id',
                'prd.total_salary',
                'prd.components',
                DB::raw('COALESCE(bio.NAMA_KARYAWAN, prd.employee_name) as nama'),
                DB::raw('bio.BAG as bagian'),
                DB::raw('COALESCE(dept_main.DEPARTEMENT, \'Tanpa Dept\') as dept_name'),
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
        // Rekap payroll per Department (mengikuti filter dept/npk/component/rentang bulan
        // yang sama), berdasarkan employee_dept yang tercatat pada masing-masing periode.
        $deptRecap = [];

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

            // Rekap per Department
            $deptKey = $row->dept_id ?? '-';
            if (!isset($deptRecap[$deptKey])) {
                $deptRecap[$deptKey] = [
                    'dept_id'   => $deptKey,
                    'dept_name' => $row->dept_name,
                    'total'     => 0.0,
                    'employees' => [],
                ];
            }
            $deptRecap[$deptKey]['total'] += $value;
            $deptRecap[$deptKey]['employees'][$row->employee_npk] = true;
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

        // Rincian payroll per Department (mengikuti filter yang sama, termasuk
        // filter Department itu sendiri jika dipilih -> tinggal 1 baris)
        $payrollByDept = collect($deptRecap)
            ->map(function ($d) {
                return [
                    'dept_id'        => $d['dept_id'],
                    'dept_name'      => $d['dept_name'],
                    'employee_count' => count($d['employees']),
                    'total'          => round($d['total'], 2),
                ];
            })
            ->sortBy('dept_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        // ===================== OVERTIME / LEMBUR =====================

        // Hari libur nasional dalam rentang tanggal terpilih (untuk menentukan special overtime)
        $holidaySet = DB::table('holidays')
            ->whereBetween('holiday_date', [$startMonth->toDateString(), $endMonth->toDateString()])
            ->where('is_national', 1)
            ->pluck('holiday_date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->flip();

        // Ambil dept terbaru per NPK dari payroll_run_details, lalu join ke DEPT.
        // Dipakai supaya filter Department pada overtime konsisten dengan filter
        // Department utama (yang bersumber dari DEPT.ID_DEPT).
        $latestDeptPerNpk = DB::table('payroll_run_details as prd')
            ->select('prd.employee_npk', 'prd.employee_dept')
            ->whereRaw('prd.id = (SELECT MAX(prd2.id) FROM payroll_run_details prd2 WHERE prd2.employee_npk = prd.employee_npk)');

        $overtimeRowsQuery = DB::table('overtimes_payroll as ot')
            ->leftJoinSub($latestDeptPerNpk, 'ndept', fn($join) => $join->on('ndept.employee_npk', '=', 'ot.NPK'))
            ->leftJoin('DEPT', 'DEPT.ID_DEPT', '=', 'ndept.employee_dept')
            ->leftJoinSub($bioUnion, 'bio_ot', fn($join) => $join->on('bio_ot.NPK', '=', 'ot.NPK'))
            ->whereBetween('ot.OVERTIME_DATE', [$startMonth->toDateString(), $endMonth->toDateString()])
            ->when($npk, fn($q) => $q->where('ot.NPK', $npk))
            ->when($dept, fn($q) => $q->where('DEPT.ID_DEPT', $dept));

        $overtimeRowsQuery = PayrollRoleFilterService::applyToQuery(
            $overtimeRowsQuery,
            $role,
            'bio_ot.IS_STAFF',
            'DEPT.IS_SEWING'
        );

        $overtimeRows = $overtimeRowsQuery
            ->select(
                'ot.NPK',
                'ot.NAMA_KARYAWAN',
                'ot.OVERTIME_DATE',
                'ot.JUMLAH_JAM_LEMBUR',
                DB::raw('COALESCE(DEPT.ID_DEPT, ot.DEPT_GROUP, \'-\') as dept_key'),
                DB::raw('COALESCE(DEPT.DEPARTEMENT, ot.BAGIAN, \'Tanpa Dept\') as dept_name')
            )
            ->orderBy('ot.OVERTIME_DATE')
            ->get();

        $overtimeByDept    = [];
        $overtimeEmployees = [];
        $overtimeTotalReg  = 0.0;
        $overtimeTotalSpec = 0.0;

        // Matrix: dept_key => [ 'Y-m-d' => total jam pada tanggal itu (reguler + khusus) ]
        $overtimeMatrix = [];
        // Kumpulan tanggal unik yang benar-benar punya data lembur, dipakai
        // sebagai kolom dinamis di tabel "Rincian Overtime per Dept".
        $overtimeDateKeys = [];

        foreach ($overtimeRows as $row) {
            $date     = Carbon::parse($row->OVERTIME_DATE);
            $dateKey  = $date->format('Y-m-d');
            $isWeekend = $date->isWeekend(); // Sabtu/Minggu
            $isHoliday = $holidaySet->has($dateKey);
            $isSpecial = $isWeekend || $isHoliday;

            $jam = $this->parseJam($row->JUMLAH_JAM_LEMBUR);

            $deptKey  = $row->dept_key;
            $deptName = $row->dept_name;

            if (!isset($overtimeByDept[$deptKey])) {
                $overtimeByDept[$deptKey] = [
                    'dept_id'              => $deptKey,
                    'dept_name'            => $deptName,
                    'overtime_jam'         => 0.0,
                    'special_overtime_jam' => 0.0,
                ];
            }

            if ($isSpecial) {
                $overtimeByDept[$deptKey]['special_overtime_jam'] += $jam;
                $overtimeTotalSpec += $jam;
            } else {
                $overtimeByDept[$deptKey]['overtime_jam'] += $jam;
                $overtimeTotalReg += $jam;
            }

            $overtimeMatrix[$deptKey][$dateKey] = ($overtimeMatrix[$deptKey][$dateKey] ?? 0) + $jam;
            $overtimeDateKeys[$dateKey] = true;

            $overtimeEmployees[] = [
                'dept_id'           => $deptKey,
                'dept_name'         => $deptName,
                'npk'               => $row->NPK,
                'nama'              => $row->NAMA_KARYAWAN,
                'tanggal'           => $dateKey,
                'tanggal_formatted' => $date->format('d-m-Y'),
                'jam'               => round($jam, 2),
                'jenis'             => $isSpecial ? 'special_overtime' : 'overtime',
                'is_weekend'        => $isWeekend,
                'is_holiday'        => $isHoliday,
            ];
        }

        $overtimeByDept = collect($overtimeByDept)
            ->map(function ($d) {
                $d['overtime_jam']         = round($d['overtime_jam'], 2);
                $d['special_overtime_jam'] = round($d['special_overtime_jam'], 2);
                $d['total_jam']            = round($d['overtime_jam'] + $d['special_overtime_jam'], 2);
                return $d;
            })
            ->sortBy('dept_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        // Bulatkan matrix per tanggal
        foreach ($overtimeMatrix as $deptKey => $dates) {
            foreach ($dates as $dateKey => $jam) {
                $overtimeMatrix[$deptKey][$dateKey] = round($jam, 2);
            }
        }

        // Susun daftar tanggal (kolom) terurut kronologis, dengan label singkat d/m.
        // is_weekend / is_holiday dipakai frontend untuk highlight merah kolom
        // tanggal yang jatuh pada hari libur (Sabtu/Minggu atau libur nasional).
        $overtimeDates = collect(array_keys($overtimeDateKeys))
            ->sort()
            ->values()
            ->map(function ($dateKey) use ($holidaySet) {
                $date = Carbon::parse($dateKey);
                return [
                    'key'        => $dateKey,
                    'label'      => $date->format('d/m'),
                    'is_weekend' => $date->isWeekend(),
                    'is_holiday' => $holidaySet->has($dateKey),
                ];
            });

        return response()->json([
            'labels'          => $labels,
            'values'          => $values,
            'aktif_values'    => $aktifValues,
            'keluar_values'   => $keluarValues,
            'aktif_counts'    => $aktifCounts,
            'keluar_counts'   => $keluarCounts,
            'employee_counts' => $employeeCounts,
            'employees'       => $employeeList,
            'payroll_by_dept' => $payrollByDept,
            'component_label' => $componentLabel,
            'grand_total'     => $grandTotal,
            'avg_per_month'   => $avgPerMonth,
            'max_employees'   => $maxEmployees,
            'range' => [
                'start' => $startMonth->format('Y-m'),
                'end'   => $endMonth->format('Y-m'),
            ],

            // Overtime
            'overtime_by_dept'        => $overtimeByDept,
            'overtime_employees'      => $overtimeEmployees,
            'overtime_dates'          => $overtimeDates,
            'overtime_matrix'         => $overtimeMatrix,
            'overtime_total'          => round($overtimeTotalReg + $overtimeTotalSpec, 2),
            'overtime_total_regular'  => round($overtimeTotalReg, 2),
            'overtime_total_special'  => round($overtimeTotalSpec, 2),
        ]);
    }
}
