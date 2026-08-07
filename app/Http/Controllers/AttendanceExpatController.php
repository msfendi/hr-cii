<?php

namespace App\Http\Controllers;

use App\Exports\AttendanceExpatExport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceExpatController extends Controller
{
    public function index()
    {
        return view('attendance_expat.index');
    }

    public function data(Request $request)
    {
        [$start, $end] = $this->resolveRange($request);
        $dates = $this->buildDateList($start, $end);

        $result   = $this->buildMatrix($start, $end, $dates);
        $holidays = $this->getHolidayDates($start, $end);
        $offDates = $this->buildOffDates($dates, $holidays);

        return response()->json([
            'dates'     => $dates,
            'offDates'  => $offDates, // "YYYY-MM-DD" => 'holiday' | 'weekend'
            'employees' => $result,
        ]);
    }

    /**
     * Export bisa dipanggil dengan:
     * - period=YYYY-MM                         -> satu bulan penuh
     * - start_date=YYYY-MM-DD&end_date=YYYY-MM-DD -> rentang custom (bisa lintas bulan)
     * - npks[]=NPK1&npks[]=NPK2...              -> hanya karyawan tertentu
     *   (kalau npks[] tidak dikirim / kosong -> export SEMUA expat)
     */
    public function export(Request $request)
    {
        [$start, $end] = $this->resolveRange($request);
        $dates = $this->buildDateList($start, $end);

        $npks = $request->input('npks');
        $npks = (is_array($npks) && count($npks) > 0) ? array_values($npks) : null;

        $result   = $this->buildMatrix($start, $end, $dates, $npks);
        $holidays = $this->getHolidayDates($start, $end);
        $offDates = $this->buildOffDates($dates, $holidays);

        $periodLabel = $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y');

        $filename = 'Attendance_Expat_' . $start->format('Ymd') . '-' . $end->format('Ymd')
            . ($npks ? '_' . count($npks) . 'emp' : '')
            . '.xlsx';

        return Excel::download(
            new AttendanceExpatExport($result, $dates, $offDates, $periodLabel),
            $filename
        );
    }

    // ------------------------------------------------------------------

    private function resolveRange(Request $request): array
    {
        if ($request->filled('period')) { // <input type="month"> -> "2026-08"
            $start = Carbon::parse($request->period . '-01')->startOfMonth();
            $end   = $start->copy()->endOfMonth();
        } else {
            $start = $request->filled('start_date')
                ? Carbon::parse($request->start_date)->startOfDay()
                : Carbon::now()->startOfMonth();
            $end = $request->filled('end_date')
                ? Carbon::parse($request->end_date)->endOfDay()
                : Carbon::now()->endOfMonth();
        }
        return [$start, $end];
    }

    private function buildDateList(Carbon $start, Carbon $end): array
    {
        $dates  = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }
        return $dates;
    }

    /**
     * Master list expat — dipisah dari perhitungan berat supaya karyawan
     * yang not-scanned sepanjang periode tetap muncul di report.
     * $npks: kalau diisi, hanya kembalikan karyawan dengan NPK tsb (untuk export terfilter).
     */
    private function getExpatList(?array $npks = null): array
    {
        $sql = "
            SELECT b.NPK AS npk, b.NAMA_KARYAWAN AS nama, d.DEPARTEMENT AS bagian
            FROM BIODATA b
            LEFT JOIN DEPT d ON d.ID_DEPT = b.ID_DEPT
            WHERE b.IS_EXPAT = 1
        ";
        $bindings = [];

        if (!empty($npks)) {
            $placeholders = implode(',', array_fill(0, count($npks), '?'));
            $sql       .= " AND b.NPK IN ({$placeholders})";
            $bindings   = $npks;
        }

        $sql .= " ORDER BY d.DEPARTEMENT ASC, b.NPK ASC";

        return DB::connection('cii')->select($sql, $bindings);
    }

    private function buildMatrix(Carbon $start, Carbon $end, array $dates, ?array $npks = null): array
    {
        $master = $this->getExpatList($npks);
        // NOTE: query absensi (getAttendanceMatrix) tetap dihitung untuk SEMUA expat,
        // filter NPK cuma diterapkan di master list. Ini sengaja biar query berat di
        // getAttendanceMatrix() nggak perlu diutak-atik lagi — baris yang NPK-nya
        // tidak ada di $master otomatis kebuang di loop join di bawah.
        $matrix = $this->getAttendanceMatrix($start, $end);

        $employees = [];
        foreach ($master as $m) {
            $employees[$m->npk] = [
                'npk' => $m->npk, 'nama' => $m->nama, 'bagian' => $m->bagian,
                'attendance' => [],
            ];
        }
        foreach ($matrix as $r) {
            if (!isset($employees[$r->npk])) continue;
            $employees[$r->npk]['attendance'][$r->tgl] = [
                'masuk' => $r->jam_masuk, 'pulang' => $r->jam_pulang,
            ];
        }

        $no = 1;
        $result = [];
        foreach ($employees as $emp) {
            foreach ($dates as $d) {
                $emp['attendance'][$d] ??= ['masuk' => 'not scanned', 'pulang' => 'not scanned'];
            }
            $emp['no'] = $no++;
            $result[] = $emp;
        }
        return $result;
    }

    /**
     * Heavy shift-window calc HANYA dijalankan untuk (npk, tanggal) yang
     * punya kandidat scan di att_log — bukan full cross join expat x tanggal.
     * Hasilnya baru di-LEFT JOIN ke grid tanggal penuh di buildMatrix().
     */
    private function getAttendanceMatrix(Carbon $start, Carbon $end): array
    {
        $startStr = $start->toDateString();
        $endStr   = $end->toDateString();
        $scanFrom = $start->copy()->subDay()->toDateString();
        $scanTo   = $end->copy()->addDay()->toDateString();

        return DB::connection('cii')->select("
            WITH expat AS (
                SELECT
                    b.BARCODE       AS pin,
                    b.NAMA_KARYAWAN AS nama,
                    b.NPK           AS npk,
                    d.DEPARTEMENT   AS bagian
                FROM BIODATA b
                LEFT JOIN DEPT d ON d.ID_DEPT = b.ID_DEPT
                WHERE b.IS_EXPAT = 1
            ),

            -- pass murah: (npk, tanggal) mana saja yang punya scan di sekitarnya.
            -- setiap scan di-bucket ke tanggalnya sendiri DAN tanggal-1 (untuk overnight shift),
            -- validasi sebenarnya tetap dilakukan CTE berat di bawah.
            candidate_dates AS (
                SELECT DISTINCT e.pin, e.npk, e.nama, e.bagian, x.tgl
                FROM expat e
                JOIN att_log a
                    ON a.pin = e.pin
                    AND a.scan_date >= ?
                    AND a.scan_date <  DATEADD(day, 1, CAST(? AS DATE))
                CROSS APPLY (VALUES
                    (CAST(a.scan_date AS DATE)),
                    (DATEADD(day, -1, CAST(a.scan_date AS DATE)))
                ) AS x(tgl)
                WHERE x.tgl BETWEEN ? AND ?
            ),

            emp AS (
                SELECT
                    cd.pin, cd.npk, cd.nama, cd.bagian, cd.tgl,
                    COALESCE(s.name, 'Normal Shift')    AS shift_name,
                    COALESCE(s.work_start, '08:00:00')  AS work_start,
                    COALESCE(s.work_end, '17:00:00')    AS work_end,
                    COALESCE(ps.work_start, '08:00:00') AS prev_work_start,
                    COALESCE(ps.work_end, '17:00:00')   AS prev_work_end,
                    COALESCE(ns.work_start, '08:00:00') AS next_work_start
                FROM candidate_dates cd
                LEFT JOIN employee_shifts es
                    ON es.npk = cd.npk AND CAST(es.shift_date AS DATE) = cd.tgl
                LEFT JOIN shifts s ON s.id = es.shift_id

                LEFT JOIN employee_shifts pes
                    ON pes.npk = cd.npk AND CAST(pes.shift_date AS DATE) = DATEADD(day, -1, cd.tgl)
                LEFT JOIN shifts ps ON ps.id = pes.shift_id

                LEFT JOIN employee_shifts nes
                    ON nes.npk = cd.npk AND CAST(nes.shift_date AS DATE) = DATEADD(day, 1, cd.tgl)
                LEFT JOIN shifts ns ON ns.id = nes.shift_id
            ),
            emp_bounds AS (
                SELECT
                    e.*,
                    CAST(CONVERT(varchar(10), e.tgl, 120) + ' ' + CONVERT(varchar(8), e.work_start, 108) AS DATETIME) AS shift_start_dt,
                    CASE
                        WHEN e.work_end < e.work_start
                            THEN DATEADD(day, 1, CAST(CONVERT(varchar(10), e.tgl, 120) + ' ' + CONVERT(varchar(8), e.work_end, 108) AS DATETIME))
                        ELSE CAST(CONVERT(varchar(10), e.tgl, 120) + ' ' + CONVERT(varchar(8), e.work_end, 108) AS DATETIME)
                    END AS shift_end_dt,
                    CASE
                        WHEN e.prev_work_end < e.prev_work_start
                            THEN DATEADD(day, 1, CAST(CONVERT(varchar(10), DATEADD(day,-1,e.tgl), 120) + ' ' + CONVERT(varchar(8), e.prev_work_end, 108) AS DATETIME))
                        ELSE CAST(CONVERT(varchar(10), DATEADD(day,-1,e.tgl), 120) + ' ' + CONVERT(varchar(8), e.prev_work_end, 108) AS DATETIME)
                    END AS prev_shift_end_dt,
                    CAST(CONVERT(varchar(10), DATEADD(day,1,e.tgl), 120) + ' ' + CONVERT(varchar(8), e.next_work_start, 108) AS DATETIME) AS next_shift_start_dt
                FROM emp e
            ),
            emp_window AS (
                SELECT
                    eb.*,
                    CASE
                        WHEN DATEADD(hour, 6, eb.shift_end_dt) < DATEADD(minute, -60, eb.next_shift_start_dt)
                            THEN DATEADD(hour, 6, eb.shift_end_dt)
                        ELSE DATEADD(minute, -60, eb.next_shift_start_dt)
                    END AS scan_upper_bound
                FROM emp_bounds eb
            ),
            scans AS (
                SELECT
                    ew.pin, ew.npk, ew.tgl,
                    a.scan_date,
                    ew.shift_start_dt,
                    ew.shift_end_dt
                FROM emp_window ew
                JOIN att_log a
                    ON a.pin = ew.pin
                    AND a.scan_date >= DATEADD(hour, -4, ew.shift_start_dt)
                    AND a.scan_date <= ew.scan_upper_bound
                    AND a.scan_date > DATEADD(minute, 60, ew.prev_shift_end_dt)
            ),
            scan_ranked AS (
                SELECT
                    npk, tgl, scan_date,
                    ABS(DATEDIFF(minute, scan_date, shift_start_dt)) AS dist_to_start,
                    ABS(DATEDIFF(minute, scan_date, shift_end_dt))   AS dist_to_end,
                    ROW_NUMBER() OVER (PARTITION BY npk, tgl ORDER BY ABS(DATEDIFF(minute, scan_date, shift_start_dt))) AS rn_masuk,
                    ROW_NUMBER() OVER (PARTITION BY npk, tgl ORDER BY ABS(DATEDIFF(minute, scan_date, shift_end_dt)))   AS rn_pulang,
                    COUNT(*) OVER (PARTITION BY npk, tgl) AS total_scan
                FROM scans
            )

            SELECT
                eb.npk, eb.tgl,
                CASE
                    WHEN m.scan_date IS NULL THEN 'not scanned'
                    WHEN m.total_scan = 1 AND m.dist_to_end < m.dist_to_start THEN 'not scanned'
                    ELSE CONVERT(varchar(8), m.scan_date, 108)
                END AS jam_masuk,
                CASE
                    WHEN p.scan_date IS NULL THEN 'not scanned'
                    WHEN p.total_scan = 1 AND p.dist_to_start <= p.dist_to_end THEN 'not scanned'
                    ELSE CONVERT(varchar(8), p.scan_date, 108)
                END AS jam_pulang
            FROM emp_window eb
            LEFT JOIN scan_ranked m ON m.npk = eb.npk AND m.tgl = eb.tgl AND m.rn_masuk = 1
            LEFT JOIN scan_ranked p ON p.npk = eb.npk AND p.tgl = eb.tgl AND p.rn_pulang = 1
        ", [$scanFrom, $scanTo, $startStr, $endStr]);
    }

    /**
     * Tanggal libur (holiday) dalam rentang periode.
     *
     * NOTE: query ini pakai default DB connection (bukan 'cii'), dengan
     * asumsi tabel `holidays` ada di database aplikasi Laravel ini sendiri
     * (bukan di database absensi eksternal). Kalau ternyata tabel `holidays`
     * ada di connection 'cii', tinggal ganti baris DB::select(...) di bawah
     * jadi DB::connection('cii')->select(...).
     */
    protected function getHolidayDates(Carbon $start, Carbon $end): array
    {
        $rows = DB::select("
            SELECT holiday_date
            FROM holidays
            WHERE holiday_date >= ?
              AND holiday_date <= ?
        ", [$start->format('Y-m-d'), $end->format('Y-m-d')]);

        return array_map(
            fn ($row) => Carbon::parse($row->holiday_date)->format('Y-m-d'),
            $rows
        );
    }

    /**
     * Gabungkan holiday + weekend (Sabtu/Minggu) jadi satu map:
     * "YYYY-MM-DD" => 'holiday' | 'weekend'.
     * Kalau tanggalnya holiday sekaligus weekend, holiday yang menang.
     */
    private function buildOffDates(array $dates, array $holidayDates): array
    {
        $holidaySet = array_flip($holidayDates);

        $off = [];
        foreach ($dates as $d) {
            if (isset($holidaySet[$d])) {
                $off[$d] = 'holiday';
            } elseif (Carbon::parse($d)->isWeekend()) {
                $off[$d] = 'weekend';
            }
        }
        return $off;
    }
}