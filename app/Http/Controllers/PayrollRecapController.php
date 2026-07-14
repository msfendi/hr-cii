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
     * Ambil daftar periode payroll (untuk filter section 2 & 3), terbaru dulu.
     */
    private function getPeriods()
    {
        return DB::table('payroll_periods')
            ->select('id', 'name', 'start_date', 'end_date')
            ->where('is_closed', true)
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * Resolve satu periode payroll dari request (?period_id=), default ke
     * periode terbaru bila tidak dikirim / tidak ditemukan.
     */
    private function resolvePeriod(?string $periodId)
    {
        $query = DB::table('payroll_periods')->orderByDesc('start_date');

        return $periodId
            ? $query->where('id', $periodId)->first()
            : $query->first();
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
            'departments'      => $departments,
            'components'       => $this->components,
            'periods'          => $this->getPeriods(),
            'payrollRole'      => $role,
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
     * =====================================================================
     * SECTION 1: REKAP PAYROLL (per bulan, 12 bulan rolling)
     * =====================================================================
     * AJAX: data untuk chart rekap payroll.
     * GET /payroll/recap/chart-data
     *
     * Query params:
     *  - end_month   (Y-m)  default: bulan sekarang. Rentang otomatis 12 bulan ke belakang.
     *  - npk         (nullable string)
     *  - dept        (nullable string/int, ID_DEPT)
     *  - component   (nullable string) default: total_salary
     *
     * Semua sumber data (rekap bulanan) difilter berdasarkan payroll_role
     * user login lewat PayrollRoleFilterService::applyToQuery() -- aturan
     * deny-by-default: role null / tidak terdaftar di role_payrolls -> data kosong.
     */
    public function chartData(Request $request)
    {
        $role = $this->getRole();

        $endMonth = $request->filled('end_month')
            ? Carbon::createFromFormat('Y-m', $request->end_month)->endOfMonth()
            : Carbon::now()->endOfMonth();

        // Selalu tarik mundur 12 bulan dari bulan akhir yang dipilih
        $startMonth = (clone $endMonth)->subMonths(11)->startOfMonth();

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
            ->select('NPK', 'NAMA_KARYAWAN', 'BAG', 'IS_STAFF')
            ->unionAll(
                DB::table('BIODATA_KELUAR')->select('NPK', 'NAMA_KARYAWAN', 'BAG', 'IS_STAFF')
            );

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
                'pp.start_date as period_start',
                'prd.employee_npk',
                'prd.total_salary',
                'prd.components',
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

        foreach ($rows as $row) {
            $periodKey = Carbon::parse($row->period_start)->format('Y-m');
            if (!$months->has($periodKey)) {
                continue;
            }

            $comp = json_decode($row->components, true) ?? [];

            $value = $component === 'total_salary'
                ? (float) $row->total_salary
                : (float) ($comp[$component]['amount'] ?? 0);

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

        return response()->json([
            'labels'          => $labels,
            'values'          => $values,
            'aktif_values'    => $aktifValues,
            'keluar_values'   => $keluarValues,
            'aktif_counts'    => $aktifCounts,
            'keluar_counts'   => $keluarCounts,
            'employee_counts' => $employeeCounts,
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

    /**
     * =====================================================================
     * SECTION 2: DETAIL PAYROLL (Rincian per Karyawan & per Department)
     * =====================================================================
     * AJAX: GET /payroll/recap/detail-data
     *
     * Berbeda dengan section 1, data di sini TIDAK diakumulasi lintas bulan.
     * Semua rincian scoped ke SATU periode payroll (period_id) saja.
     *
     * Query params:
     *  - period_id  (nullable) default: periode payroll terbaru
     *  - npk        (nullable string)
     *  - dept       (nullable string/int, ID_DEPT)
     *  - component  (nullable string) default: total_salary
     */
    public function detailData(Request $request)
    {
        $role = $this->getRole();

        $period = $this->resolvePeriod($request->get('period_id'));
        if (!$period) {
            return response()->json(['message' => 'Periode payroll tidak ditemukan.'], 422);
        }

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

        $bioUnion = DB::table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'BAG', 'IS_STAFF')
            ->unionAll(
                DB::table('BIODATA_KELUAR')->select('NPK', 'NAMA_KARYAWAN', 'BAG', 'IS_STAFF')
            );

        $rowsQuery = DB::table('payroll_run_details as prd')
            ->join('payroll_runs as pr', 'prd.run_id', '=', 'pr.id')
            ->where('pr.period_id', $period->id)
            ->leftJoinSub($bioUnion, 'bio', fn($join) => $join->on('bio.NPK', '=', 'prd.employee_npk'))
            ->leftJoin('PKWT as pkwt', 'pkwt.NPK', '=', 'prd.employee_npk')
            ->leftJoin('DEPT as dept_main', 'dept_main.ID_DEPT', '=', 'prd.employee_dept')
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
                'prd.employee_npk',
                'prd.employee_dept as dept_id',
                'prd.total_salary',
                'prd.components',
                DB::raw('COALESCE(bio.NAMA_KARYAWAN, prd.employee_name) as nama'),
                DB::raw('COALESCE(dept_main.DEPARTEMENT, \'Tanpa Dept\') as dept_name'),
                'pkwt.TKK as tkk'
            )
            ->get();

        $periodKey = Carbon::parse($period->start_date)->format('Y-m');

        $employees          = [];
        $deptRecap          = [];
        $deptEmployeeDetail = [];

        foreach ($rows as $row) {
            $comp = json_decode($row->components, true) ?? [];

            $value = $component === 'total_salary'
                ? (float) $row->total_salary
                : (float) ($comp[$component]['amount'] ?? 0);

            $tkkKey = $row->tkk ? Carbon::parse($row->tkk)->format('Y-m') : null;
            $status = ($tkkKey !== null && $tkkKey <= $periodKey) ? 'keluar' : 'aktif';

            // Satu karyawan = satu baris payroll_run_details per periode,
            // jadi tidak perlu akumulasi lintas baris di sini.
            $employees[$row->employee_npk] = [
                'npk'           => $row->employee_npk,
                'nama'          => $row->nama,
                'bagian'        => $row->dept_name,
                'status'        => $status,
                'tkk_formatted' => $row->tkk ? Carbon::parse($row->tkk)->format('d-m-Y') : null,
                'months_count'  => 1,
                'total'         => round($value, 2),
            ];

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

            // Rincian per karyawan per Department -- SEMUA komponen gaji,
            // dipakai modal detail saat baris department diklik.
            if (!isset($deptEmployeeDetail[$deptKey][$row->employee_npk])) {
                $components = [];
                foreach ($comp as $ck => $cv) {
                    if (!array_key_exists($ck, $flatComponents)) {
                        continue; // hanya komponen yang terdaftar di whitelist
                    }
                    $components[$ck] = round(is_array($cv) ? (float) ($cv['amount'] ?? 0) : (float) $cv, 2);
                }

                $deptEmployeeDetail[$deptKey][$row->employee_npk] = [
                    'npk'          => $row->employee_npk,
                    'nama'         => $row->nama,
                    'total_salary' => round((float) $row->total_salary, 2),
                    'components'   => $components,
                ];
            }
        }

        $employeeList = collect($employees)->sortByDesc('total')->values();

        $payrollByDept = collect($deptRecap)
            ->map(fn($d) => [
                'dept_id'        => $d['dept_id'],
                'dept_name'      => $d['dept_name'],
                'employee_count' => count($d['employees']),
                'total'          => round($d['total'], 2),
            ])
            ->sortBy('dept_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $deptEmployeeDetails = collect($deptEmployeeDetail)
            ->map(fn($employeesInDept) => collect($employeesInDept)->sortByDesc('total_salary')->values())
            ->toArray();
        // Cast ke stdClass supaya SELALU ter-encode sebagai JSON object.
        $deptEmployeeDetails = (object) $deptEmployeeDetails;

        return response()->json([
            'period' => [
                'id'         => $period->id,
                'name'       => $period->name,
                'start_date' => Carbon::parse($period->start_date)->format('Y-m-d'),
            ],
            'component_label'       => $componentLabel,
            'employees'             => $employeeList,
            'payroll_by_dept'       => $payrollByDept,
            'dept_employee_details' => $deptEmployeeDetails,
        ]);
    }

    /**
     * =====================================================================
     * SECTION 3: OVERTIME
     * =====================================================================
     * AJAX: GET /payroll/recap/overtime-data
     *
     * Sebelumnya rincian overtime menggabungkan tanggal dari SEMUA periode
     * sekaligus. Sekarang di-scope ke SATU periode payroll (period_id),
     * dan ditambah agregat per-tanggal (regular vs khusus) untuk chart.
     *
     * Query params:
     *  - period_id (nullable) default: periode payroll terbaru
     *  - dept      (nullable string/int, ID_DEPT)
     */
    public function overtimeData(Request $request)
    {
        $role = $this->getRole();

        $period = $this->resolvePeriod($request->get('period_id'));
        if (!$period) {
            return response()->json(['message' => 'Periode payroll tidak ditemukan.'], 422);
        }

        // Asumsi payroll_periods punya kolom end_date. Jika tidak tersedia,
        // fallback ke akhir bulan dari start_date periode tsb.
        $periodStart = Carbon::parse($period->start_date)->startOfDay();
        $periodEnd   = !empty($period->end_date)
            ? Carbon::parse($period->end_date)->endOfDay()
            : (clone $periodStart)->endOfMonth()->endOfDay();

        $dept = $request->get('dept');

        $bioUnion = DB::table('BIODATA')
            ->select('NPK', 'IS_STAFF')
            ->unionAll(
                DB::table('BIODATA_KELUAR')->select('NPK', 'IS_STAFF')
            );

        // Hari libur nasional dalam rentang periode terpilih
        $holidaySet = DB::table('holidays')
            ->whereBetween('holiday_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->where('is_national', 1)
            ->pluck('holiday_date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->flip();

        // Ambil dept terbaru per NPK dari payroll_run_details, lalu join ke DEPT,
        // supaya filter Department overtime konsisten dengan filter Department utama.
        $latestDeptPerNpk = DB::table('payroll_run_details as prd')
            ->select('prd.employee_npk', 'prd.employee_dept')
            ->whereRaw('prd.id = (SELECT MAX(prd2.id) FROM payroll_run_details prd2 WHERE prd2.employee_npk = prd.employee_npk)');

        $overtimeRowsQuery = DB::table('overtimes_payroll as ot')
            ->leftJoinSub($latestDeptPerNpk, 'ndept', fn($join) => $join->on('ndept.employee_npk', '=', 'ot.NPK'))
            ->leftJoin('DEPT', 'DEPT.ID_DEPT', '=', 'ndept.employee_dept')
            ->leftJoinSub($bioUnion, 'bio_ot', fn($join) => $join->on('bio_ot.NPK', '=', 'ot.NPK'))
            ->whereBetween('ot.OVERTIME_DATE', [$periodStart->toDateString(), $periodEnd->toDateString()])
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
        $overtimeMatrix    = [];
        $overtimeDateKeys  = [];
        // Agregat per tanggal (reguler vs khusus), dipakai untuk chart section 3.
        $overtimeByDate    = [];

        foreach ($overtimeRows as $row) {
            $date      = Carbon::parse($row->OVERTIME_DATE);
            $dateKey   = $date->format('Y-m-d');
            $isWeekend = $date->isWeekend();
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

            if (!isset($overtimeByDate[$dateKey])) {
                $overtimeByDate[$dateKey] = [
                    'is_weekend'  => $isWeekend,
                    'is_holiday'  => $isHoliday,
                    'regular_jam' => 0.0,
                    'special_jam' => 0.0,
                ];
            }

            if ($isSpecial) {
                $overtimeByDept[$deptKey]['special_overtime_jam'] += $jam;
                $overtimeByDate[$dateKey]['special_jam'] += $jam;
            } else {
                $overtimeByDept[$deptKey]['overtime_jam'] += $jam;
                $overtimeByDate[$dateKey]['regular_jam'] += $jam;
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

        foreach ($overtimeMatrix as $deptKey => $dates) {
            foreach ($dates as $dateKey => $jam) {
                $overtimeMatrix[$deptKey][$dateKey] = round($jam, 2);
            }
        }

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

        // Data chart "Total Overtime per Tanggal" (1 periode): lembur biasa vs khusus
        $overtimeByDateChart = collect(array_keys($overtimeDateKeys))
            ->sort()
            ->values()
            ->map(function ($dateKey) use ($overtimeByDate) {
                $d = $overtimeByDate[$dateKey];
                return [
                    'date'        => $dateKey,
                    'label'       => Carbon::parse($dateKey)->format('d/m'),
                    'is_weekend'  => $d['is_weekend'],
                    'is_holiday'  => $d['is_holiday'],
                    'regular_jam' => round($d['regular_jam'], 2),
                    'special_jam' => round($d['special_jam'], 2),
                ];
            });

        // Top 5 Department dengan total jam lembur tertinggi (biasa + khusus)
        $top5Dept = $overtimeByDept->sortByDesc('total_jam')->take(5)->values();

        // Rata-rata jam lembur per minggu dalam periode terpilih
        $totalDaysInPeriod  = $periodStart->diffInDays($periodEnd) + 1;
        $totalWeeksInPeriod = max($totalDaysInPeriod / 7, 0.1); // hindari div by zero utk periode < 1 minggu

        $totalRegularJam = round(collect($overtimeByDate)->sum('regular_jam'), 2);
        $totalSpecialJam = round(collect($overtimeByDate)->sum('special_jam'), 2);

        // Rincian jam lembur per minggu dalam periode terpilih (Week 1 = 7 hari
        // pertama sejak periodStart, dst — bukan minggu kalender Senin-Minggu)
        $overtimeByWeek = [];
        $weekCursor = $periodStart->copy();
        $weekNumber = 1;

        while ($weekCursor->lte($periodEnd)) {
            $weekEndCursor = (clone $weekCursor)->addDays(6);
            if ($weekEndCursor->gt($periodEnd)) {
                $weekEndCursor = $periodEnd->copy();
            }

            $overtimeByWeek[] = [
                'week'        => $weekNumber,
                'start_date'  => $weekCursor->format('Y-m-d'),
                'end_date'    => $weekEndCursor->format('Y-m-d'),
                'regular_jam' => 0.0,
                'special_jam' => 0.0,
            ];

            $weekCursor->addDays(7);
            $weekNumber++;
        }

        // Sebar total jam per tanggal ($overtimeByDate, sudah ada dari loop di atas)
        // ke bucket minggu yang sesuai
        foreach ($overtimeByDate as $dateKey => $d) {
            $daysFromStart = $periodStart->diffInDays(Carbon::parse($dateKey));
            $weekIndex     = (int) floor($daysFromStart / 7);

            if (isset($overtimeByWeek[$weekIndex])) {
                $overtimeByWeek[$weekIndex]['regular_jam'] += $d['regular_jam'];
                $overtimeByWeek[$weekIndex]['special_jam'] += $d['special_jam'];
            }
        }

        $overtimeByWeek = collect($overtimeByWeek)->map(function ($w) {
            $w['regular_jam'] = round($w['regular_jam'], 2);
            $w['special_jam'] = round($w['special_jam'], 2);
            $w['total_jam']   = round($w['regular_jam'] + $w['special_jam'], 2);
            return $w;
        })->values();

        return response()->json([
            'period' => [
                'id'         => $period->id,
                'name'       => $period->name,
                'start_date' => $periodStart->format('Y-m-d'),
                'end_date'   => $periodEnd->format('Y-m-d'),
            ],
            'overtime_by_dept'       => $overtimeByDept,
            'overtime_employees'     => $overtimeEmployees,
            'overtime_dates'         => $overtimeDates,
            'overtime_matrix'        => $overtimeMatrix,
            'overtime_by_date'       => $overtimeByDateChart,
            'top5_dept'               => $top5Dept,
            'total_reguler'           => $totalRegularJam,
            'total_special'           => $totalSpecialJam,
            'overtime_by_week'       => $overtimeByWeek,
            'overtime_total_regular' => round(collect($overtimeByDate)->sum('regular_jam'), 2),
            'overtime_total_special' => round(collect($overtimeByDate)->sum('special_jam'), 2),
        ]);
    }
}
