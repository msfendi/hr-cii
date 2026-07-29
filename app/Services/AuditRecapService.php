<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * AuditRecapService
 * -------------------------------------------------------------------------
 * Menggabungkan data att_log, overtimes, employee_shifts, employee_lates,
 * holidays, ijin_meninggalkan_pekerjaans, dan payroll_run_details menjadi
 * rekap harian di tabel AUDIT.
 *
 * Semua query menggunakan DB::select() / DB::table() dengan nama tabel
 * langsung (bukan Eloquent Model), sesuai permintaan. Target DB: SQL Server.
 *
 * ALUR PERIODE:
 * 1. User pilih payroll_periods (harus is_closed = 0 / masih terbuka).
 * 2. Rentang tanggal generate = payroll_periods.start_date s/d end_date
 *    (BUKAN awal-akhir bulan kalender).
 * 3. Daftar karyawan (master) diambil dari payroll_run_details milik
 *    payroll_runs TERBARU (processed_at/id terbesar) untuk period_id tsb.
 *    KALAU period BELUM punya payroll_runs sama sekali (belum ada payroll
 *    run yang dijalankan untuk periode ini), generate TIDAK gagal --
 *    fallback ambil daftar karyawan dari BIODATA + BIODATA_KELUAR (union),
 *    di-join ke PKWT (TMK/TKK) untuk menentukan siapa yang aktif selama
 *    periode ini, dan ke DEPT untuk nama departemen. Lihat
 *    getEmployeeMasterFromBiodataPkwt() untuk detail kriteria aktifnya.
 * 4. Jam & status kehadiran (hadir/lembur/kode absen) dihitung dari tabel
 *    overtimes.
 * 5. SUBDIVISI & DEPT_GROUP tetap prioritas dari overtimes (BAGIAN/DEPT_GROUP);
 *    fallback (kalau row overtimes tidak ada) pakai payroll_run_details
 *    (employee_dept / employee_name).
 *
 * ATURAN "TIDAK ADA SCAN" (att_log kosong utk NPK+tanggal tsb, dan tidak ada
 * juga di employee_lates) -- CATATAN: "tidak ada scan" di sini juga mencakup
 * kasus karyawan HANYA punya 1 scan yang jelas merupakan scan pulang (lebih
 * dekat ke jam akhir shift daripada jam mulai shift), karena resolveJamPagi()
 * akan menolak scan seperti itu sebagai kandidat jam masuk (lihat method
 * tsb untuk detail):
 * - Kalau JUMLAH_JAM_LEMBUR di overtimes KOSONG/tidak ada row (type='none')
 *   DAN tidak ada scan/izin sama sekali:
 *   - Kalau hari itu WEEKEND atau terdaftar di tabel holidays -> dianggap
 *     hari LIBUR. STATUS = 'LBR', JAM_PAGI & JAM_SIANG TIDAK digenerate
 *     (tetap null).
 *   - Kalau hari kerja biasa (bukan weekend & bukan holiday) -> jam TETAP
 *     digenerate mengikuti jam shift (estimasi): JAM_PAGI = jam mulai shift
 *     dikurangi random 0-15 menit, JAM_SIANG = jam akhir shift ditambah
 *     random 0-10 menit. STATUS = 'TS' (Tidak Scan) sebagai penanda audit
 *     bahwa jam ini hasil estimasi, bukan dari scan/izin asli.
 * - Kalau JUMLAH_JAM_LEMBUR ADA (numeric/lembur, atau kode 'H'/setengah-hari)
 *   tapi tidak ada scan/izin sama sekali -> JAM_PAGI TETAP diestimasi dari
 *   jam mulai shift dikurangi random 0-15 menit (jadi karyawan "datang"
 *   on-time/lebih awal, tidak pernah dianggap telat), dan JAM_SIANG tetap
 *   dihitung dengan formula normal sesuai tipenya (numeric/half_day).
 *   Ini berlaku juga di hari weekend/libur -- karena data overtimes sudah
 *   mengonfirmasi karyawan tsb memang masuk hari itu. STATUS TIDAK jadi
 *   'TS' di kasus ini karena kehadiran sudah dikonfirmasi lewat overtimes.
 *
 * ATURAN LEMBUR DI HARI LIBUR (weekend/holiday), type='numeric':
 * - JAM_SIANG dihitung dari AWAL shift (bukan akhir shift seperti hari kerja
 *   biasa), karena tidak ada "shift normal" yang sudah dijalani duluan --
 *   jam lembur itu sendiri = total kehadiran hari itu.
 *   Formula: JAM_SIANG = jam_mulai_shift + jam_lembur + (istirahat 1 jam
 *   HANYA kalau jam_lembur > 4 jam) + jitter(0-10 menit).
 *   Contoh: shift 08:00-17:00, lembur 8 jam (>4 jam) -> pulang sekitar
 *   08:00 + 8 jam + 1 jam istirahat = 17:00 (+ jitter).
 * - Hari kerja biasa (bukan weekend/holiday) tetap pakai formula lama:
 *   JAM_SIANG = jam_akhir_shift + istirahat tetap 30 menit + jam_lembur + jitter.
 *
 * OVERRIDE IJIN MENINGGALKAN PEKERJAAN:
 * - Kalau ada row di ijin_meninggalkan_pekerjaans utk NPK+tanggal tsb, dan
 *   jam_kembali NULL atau '17:00:00.0000000' (sentinel "belum kembali") ->
 *   JAM_SIANG dipaksa null, berapa pun hasil perhitungan di atas (JAM_PAGI
 *   & STATUS tidak terpengaruh, override ini murni untuk JAM_SIANG).
 *
 * CATATAN PENTING (asumsi yang dipakai, tolong disesuaikan bila keliru):
 * - "Hari libur" = weekend (Sabtu & Minggu, via Carbon::isWeekend()) ATAU
 *   tanggal yang ada di tabel holidays (semua row dipakai, tidak difilter
 *   is_national -- kalau perlu hanya hari libur nasional yang dihitung,
 *   tambahkan WHERE is_national = 1 di getHolidayDates()).
 * - Kode "H" (setengah hari) belum punya aturan lembur-hari-libur terpisah;
 *   formula half_day tetap sama baik weekday maupun weekend/holiday.
 * - Kalau 1 period_id punya lebih dari 1 payroll_runs, yang dipakai adalah
 *   run PALING BARU (ORDER BY processed_at DESC, id DESC). Tidak difilter
 *   berdasarkan kolom `status` di payroll_runs -- kalau perlu filter status
 *   tertentu (misal hanya run yang sudah "final"), tambahkan WHERE di
 *   method generate().
 * - Tabel AUDIT WAJIB punya unique constraint/index pada (NPK, TANGGAL)
 *   agar upsert() dari Laravel bisa jalan (di SQL Server diterjemahkan
 *   menjadi statement MERGE).
 * - JAM_PAGI / JAM_SIANG disimpan sebagai string biasa format 'HH:MM'
 *   (tanpa detik), diasumsikan kolomnya bertipe VARCHAR.
 * - Shift yang melewati tengah malam (shift malam) belum ditangani secara
 *   khusus; kalau ada shift seperti itu, formula addMinutesToTime() perlu
 *   disesuaikan supaya tidak "wrap" balik ke jam 00:00 dst. Ini juga
 *   berlaku utk estimasi jam (shift start dikurangi 0-15 menit) kalau ada
 *   shift yang mulai persis di sekitar jam 00:00.
 * - att_log & employee_lates masih di-query berdasarkan rentang tanggal
 *   payroll period, join ke biodata/biodata_keluar (untuk pin=BARCODE)
 *   masih dipakai khusus di getScanMap().
 */
class AuditRecapService
{
    protected const DEFAULT_SHIFT_START = '08:00:00';
    protected const DEFAULT_SHIFT_END   = '17:00:00';

    protected const OVERTIME_REST_MINUTES = 30; // istirahat tambahan sebelum jam lembur dihitung (hari kerja biasa)
    protected const HALF_DAY_HOURS        = 4;  // untuk kode "H" (masuk setengah hari)

    // Aturan khusus lembur di hari libur (weekend/holiday): dihitung dari
    // AWAL shift (bukan akhir shift), karena tidak ada "shift normal" yang
    // sudah dijalani duluan -- jam lembur itu sendiri = total kehadiran hari itu.
    // Potongan istirahat 1 jam hanya berlaku kalau lembur > 4 jam.
    protected const HOLIDAY_OVERTIME_REST_MINUTES        = 60;
    protected const HOLIDAY_OVERTIME_REST_THRESHOLD_HOURS = 4;

    protected const JITTER_MIN_MINUTES = 0;
    protected const JITTER_MAX_MINUTES = 10;

    // Jitter khusus untuk estimasi JAM_PAGI saat tidak ada scan sama sekali.
    // Selalu DIKURANGI dari jam mulai shift, supaya karyawan tidak pernah
    // "terlihat" telat akibat estimasi ini.
    protected const TS_JITTER_MIN_MINUTES = 0;
    protected const TS_JITTER_MAX_MINUTES = 15;

    protected const UPSERT_CHUNK = 100; // 12 kolom/row -> 100*12=1200 parameter, aman di bawah limit 2100 SQL Server

    /**
     * Entry point utama. Dipanggil dari controller saat tombol Generate diklik.
     *
     * @throws \RuntimeException kalau periode tidak ditemukan/sudah closed.
     */
    public function generate(int $periodId): array
    {
        $period = DB::table('payroll_periods')
            ->where('id', $periodId)
            ->where('is_closed', 0)
            ->first();

        if (!$period) {
            throw new \RuntimeException('Periode payroll tidak ditemukan atau sudah closed.');
        }

        $start = Carbon::parse($period->start_date)->startOfDay();
        $end   = Carbon::parse($period->end_date)->endOfDay();

        $run = DB::table('payroll_runs')
            ->where('period_id', $periodId)
            ->orderByDesc('processed_at')
            ->orderByDesc('id')
            ->first();

        if ($run) {
            $employees = $this->getEmployeeMasterFromPayrollRun($run->id);
            $employeeSource = 'payroll_run';
        } else {
            // Belum ada payroll_run untuk periode ini -> fallback ambil daftar
            // karyawan dari BIODATA/BIODATA_KELUAR + PKWT (TMK/TKK) + DEPT.
            $employees = $this->getEmployeeMasterFromBiodataPkwt($start, $end);
            $employeeSource = 'biodata_pkwt';
        }

        $overtimeMap      = $this->getOvertimeMap($start, $end);
        $shiftMap         = $this->getShiftMap($start, $end);
        $lateMap          = $this->getLateMap($start, $end);
        $scanMap          = $this->getScanMap($start, $end);
        $holidays         = $this->getHolidayDates($start, $end);
        $leaveNoReturnMap = $this->getLeaveNoReturnMap($start, $end);

        $buffer = [];
        $totalRows = 0;

        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $dateKey  = $cursor->format('Y-m-d');
            // Hari libur = weekend (Sabtu/Minggu) ATAU terdaftar di tabel holidays
            $isDayOff = $cursor->isWeekend() || in_array($dateKey, $holidays, true);

            foreach ($employees as $npk => $emp) {
                $buffer[] = $this->buildRow($npk, $emp, $dateKey, $isDayOff, $overtimeMap, $shiftMap, $lateMap, $scanMap, $leaveNoReturnMap);

                if (count($buffer) >= self::UPSERT_CHUNK) {
                    $this->upsert($buffer);
                    $totalRows += count($buffer);
                    $buffer = [];
                }
            }

            $cursor->addDay();
        }

        if (!empty($buffer)) {
            $this->upsert($buffer);
            $totalRows += count($buffer);
        }

        return [
            'period_id'       => $periodId,
            'period_name'     => $period->name,
            'run_id'          => $run->id ?? null,
            'employee_source' => $employeeSource, // 'payroll_run' atau 'biodata_pkwt' (fallback)
            'total_rows'      => $totalRows,
        ];
    }

    /**
     * Susun satu baris AUDIT untuk 1 NPK + 1 tanggal.
     */
    protected function buildRow(string $npk, array $emp, string $dateKey, bool $isDayOff, array $overtimeMap, array $shiftMap, array $lateMap, array $scanMap, array $leaveNoReturnMap): array
    {
        $ot = $overtimeMap[$npk][$dateKey] ?? null;

        $shift = $shiftMap[$npk][$dateKey] ?? [
            'work_start' => self::DEFAULT_SHIFT_START,
            'work_end'   => self::DEFAULT_SHIFT_END,
        ];

        [$type, $value] = $this->classifyOvertime($ot['JUMLAH_JAM_LEMBUR'] ?? null);

        if ($type === 'absent_code') {
            // CT / P1 / MA / SD / dll -> tidak hadir, jam masuk-pulang null, status = kode apa adanya
            $jamPagi  = null;
            $jamSiang = null;
            $status   = $value;
        } elseif ($type === 'none') {
            // Tidak ada JUMLAH_JAM_LEMBUR sama sekali di overtimes.
            $realJamPagi = $this->resolveJamPagi($npk, $dateKey, $shift, $lateMap, $scanMap);

            if ($realJamPagi !== null) {
                // Ada scan asli / izin terlambat -> hadir normal, jam dihitung seperti biasa.
                $jamPagi  = $this->toHHMM($realJamPagi);
                $jamSiang = $this->addMinutesToTime($shift['work_end'], $this->jitter());
                $status   = null;
            } elseif ($isDayOff) {
                // Weekend/holiday & tidak ada scan & tidak ada data lembur -> hari libur,
                // tidak perlu digenerate jamnya sama sekali.
                $jamPagi  = null;
                $jamSiang = null;
                $status   = 'LBR';
            } else {
                // Hari kerja biasa (bukan weekend/holiday), tidak ada scan & tidak ada data
                // lembur -> tetap digenerate mengikuti jam shift-nya (estimasi), sama seperti
                // kasus numeric/half_day. STATUS tetap ditandai 'TS' sebagai jejak audit bahwa
                // jam ini hasil estimasi, bukan dari scan/izin asli.
                $jamPagi  = $this->addMinutesToTime($shift['work_start'], -$this->tsJitter());
                $jamSiang = $this->addMinutesToTime($shift['work_end'], $this->jitter());
                $status   = 'TS';
            }
        } else {
            // numeric (lembur) atau half_day ("H") -> ada data lembur/setengah-hari,
            // kehadiran sudah dikonfirmasi lewat overtimes, jadi jam TETAP digenerate.
            $realJamPagi = $this->resolveJamPagi($npk, $dateKey, $shift, $lateMap, $scanMap);

            if ($realJamPagi !== null) {
                $jamPagi = $this->toHHMM($realJamPagi);
            } else {
                // Tidak ada scan/izin asli, tapi overtimes sudah konfirmasi hadir -> estimasi
                // JAM_PAGI dari jam mulai shift dikurangi random 0-15 menit (tidak pernah "telat").
                $jamPagi = $this->addMinutesToTime($shift['work_start'], -$this->tsJitter());
            }

            if ($type === 'numeric') {
                $lemburMinutes = $value * 60;

                if ($isDayOff) {
                    // Lembur di hari libur (weekend/holiday): dihitung dari AWAL shift,
                    // bukan akhir shift -- karena tidak ada "shift normal" yang sudah
                    // dijalani duluan, jam lembur itu sendiri = total kehadiran hari itu.
                    // Potongan istirahat 1 jam hanya kalau lembur > 4 jam.
                    $restMinutes = ($value > self::HOLIDAY_OVERTIME_REST_THRESHOLD_HOURS)
                        ? self::HOLIDAY_OVERTIME_REST_MINUTES
                        : 0;
                    $jamSiang = $this->addMinutesToTime(
                        $shift['work_start'],
                        $lemburMinutes + $restMinutes + $this->jitter()
                    );
                } else {
                    // Lembur di hari kerja biasa: dihitung dari AKHIR shift (shift normal
                    // sudah dijalani duluan), ditambah istirahat tetap 30 menit.
                    $jamSiang = $this->addMinutesToTime(
                        $shift['work_end'],
                        self::OVERTIME_REST_MINUTES + $lemburMinutes + $this->jitter()
                    );
                }
                $status = null;
            } else { // half_day ("H")
                $jamSiang = $this->addMinutesToTime(
                    $shift['work_start'],
                    (self::HALF_DAY_HOURS * 60) + $this->jitter()
                );
                $status = $value; // 'H'
            }
        }

        // Override: kalau ada data di ijin_meninggalkan_pekerjaans untuk NPK+tanggal ini
        // dan jam_kembali kosong/null atau '17:00:00.0000000' (sentinel "belum kembali"),
        // JAM_SIANG dipaksa null berapa pun hasil perhitungan di atas. JAM_PAGI & STATUS
        // tidak terpengaruh.
        if (isset($leaveNoReturnMap[$npk][$dateKey])) {
            $jamSiang = null;
        }

        // Prioritas NAMA_KARYAWAN / SUBDIVISI / DEPT_GROUP: dari overtimes kalau row-nya ada,
        // fallback ke payroll_run_details (employee_name / employee_dept) kalau row overtime
        // tidak ada. DEPT_GROUP tidak punya sumber fallback (tetap null kalau overtime kosong).
        if ($ot) {
            $nama      = $ot['NAMA_KARYAWAN'] ?? $emp['NAMA_KARYAWAN'];
            $subdivisi = $ot['BAGIAN'] ?? $emp['SUBDIVISI_FALLBACK'];
            $deptGroup = $ot['DEPT_GROUP'] ?? null;
        } else {
            $nama      = $emp['NAMA_KARYAWAN'];
            $subdivisi = $emp['SUBDIVISI_FALLBACK'];
            $deptGroup = null;
        }

        return [
            'NPK'           => $npk,
            'TANGGAL'       => $dateKey,
            'SUBDIVISI'     => $subdivisi,
            'JAM_PAGI'      => $jamPagi,
            'JAM_SIANG'     => $jamSiang,
            'JAM_MALAM'     => null, // tidak dipakai, selalu null
            'STATUS'        => $status,
            'VOID'          => null,
            'NAMA_KARYAWAN' => $nama,
            'KODE_BAGIAN'   => 'a1',
            'DEPT_GROUP'    => $deptGroup,
            'created_at'    => now(),
            'updated_at'    => now(),
        ];
    }

    /**
     * Tentukan JAM_PAGI dari data ASLI saja (bukan estimasi): employee_lates
     * lebih diprioritaskan, kalau tidak ada baru ambil scan att_log yang
     * paling dekat dengan jam mulai shift -- TAPI hanya scan yang memang
     * lebih dekat ke jam MULAI shift dibanding ke jam AKHIR shift yang
     * dianggap kandidat "jam masuk" (lihat catatan di dalam method).
     * Return null kalau tidak ketemu kandidat yang valid (estimasi TS
     * ditangani di buildRow(), bukan di sini).
     */
    protected function resolveJamPagi(string $npk, string $dateKey, array $shift, array $lateMap, array $scanMap): ?string
    {
        if (isset($lateMap[$npk][$dateKey])) {
            return $lateMap[$npk][$dateKey];
        }

        $scans = $scanMap[$npk][$dateKey] ?? [];
        if (empty($scans)) {
            return null;
        }

        $shiftStartMinutes = $this->timeToMinutes($shift['work_start']);
        $shiftEndMinutes   = $this->timeToMinutes($shift['work_end']);

        $closest = null;
        $closestDiff = null;
        foreach ($scans as $scanTime) {
            $scanMinutes = $this->timeToMinutes($scanTime);
            $diffToStart = abs($scanMinutes - $shiftStartMinutes);
            $diffToEnd   = abs($scanMinutes - $shiftEndMinutes);

            // Kalau scan ini lebih dekat ke jam AKHIR shift dibanding jam MULAI
            // shift, ini kemungkinan besar scan pulang, bukan scan masuk -- skip.
            // Ini mencegah kasus karyawan yang cuma sekali finger (pas pulang)
            // salah ke-assign jadi JAM_PAGI, karena kalau tidak dicek, scan itu
            // akan "menang" sebagai satu-satunya pilihan yang ada.
            if ($diffToStart > $diffToEnd) {
                continue;
            }

            if ($closestDiff === null || $diffToStart < $closestDiff) {
                $closestDiff = $diffToStart;
                $closest = $scanTime;
            }
        }

        return $closest;
    }

    /**
     * Klasifikasikan isi kolom overtimes.JUMLAH_JAM_LEMBUR.
     * Return: [type, value] dengan type in: none | numeric | half_day | absent_code
     */
    protected function classifyOvertime($lembur): array
    {
        if ($lembur === null || trim((string) $lembur) === '') {
            return ['none', null];
        }

        $trimmed = trim((string) $lembur);

        if (is_numeric($trimmed)) {
            return ['numeric', (float) $trimmed];
        }

        if (strtoupper($trimmed) === 'H') {
            return ['half_day', $trimmed];
        }

        return ['absent_code', $trimmed];
    }

    /**
     * Data master karyawan untuk 1 payroll run tertentu. Daftar karyawan
     * yang di-generate persis mengikuti siapa saja yang ada di
     * payroll_run_details milik run tsb.
     *
     * NAMA_KARYAWAN & SUBDIVISI_FALLBACK di sini dipakai sebagai fallback di
     * buildRow() kalau NPK+tanggal terkait tidak punya row di overtimes.
     */
    protected function getEmployeeMasterFromPayrollRun(int $runId): array
    {
        $rows = DB::select("
            SELECT employee_npk AS npk, employee_name, employee_dept
            FROM payroll_run_details
            WHERE run_id = ?
        ", [$runId]);

        $employees = [];
        foreach ($rows as $row) {
            $employees[$row->npk] = [
                'NAMA_KARYAWAN'      => $row->employee_name,
                'SUBDIVISI_FALLBACK' => $row->employee_dept,
            ];
        }

        return $employees;
    }

    /**
     * FALLBACK employee master kalau period_id belum punya payroll_runs sama
     * sekali: ambil dari BIODATA + BIODATA_KELUAR (union), di-join ke PKWT
     * untuk menentukan siapa saja yang aktif selama periode ini berdasarkan
     * TMK (tanggal masuk kerja) dan TKK (tanggal keluar kerja), lalu di-join
     * ke DEPT untuk nama departemen.
     *
     * Kriteria aktif selama periode [$periodStart, $periodEnd]:
     * - TMK <= periodEnd (sudah masuk kerja sebelum/pas periode berakhir)
     * - TKK null ATAU TKK >= periodStart (belum keluar sebelum periode mulai;
     *   TKK yang jatuh SETELAH periodEnd tetap dianggap aktif PENUH di
     *   periode ini, jadi batas atas periodEnd sengaja TIDAK dipakai di sini).
     */
    protected function getEmployeeMasterFromBiodataPkwt(Carbon $periodStart, Carbon $periodEnd): array
    {
        $biodataUnion = DB::table('BIODATA')
            ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', 'IS_STAFF', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'), 'IS_EXPAT')
            ->unionAll(
                DB::table('BIODATA_KELUAR')
                    ->select('NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', 'IS_STAFF', DB::raw('CAST(BARCODE AS VARCHAR(50)) AS BARCODE'), 'IS_EXPAT')
            );

        $rows = DB::table('PKWT as p')
            ->leftJoinSub($biodataUnion, 'bio', function ($join) {
                $join->on('p.NPK', '=', 'bio.NPK');
            })
            ->leftJoin('DEPT as d', 'bio.ID_DEPT', '=', 'd.ID_DEPT')
            ->where('p.TMK', '<=', $periodEnd->format('Y-m-d'))
            ->where(function ($q) use ($periodStart) {
                // Karyawan dianggap masih aktif di periode ini selama TKK
                // belum lewat SEBELUM periode dimulai. TKK yang jatuh SETELAH
                // periodEnd (mis. resign 1 Juli sedangkan periode payroll
                // adalah Juni) tetap harus dianggap aktif penuh di periode
                // Juni -- jadi batas atas (periodEnd) TIDAK boleh dipakai
                // di sini, cukup batas bawah (periodStart).
                $q->whereNull('p.TKK')
                    ->orWhere('p.TKK', '>=', $periodStart->format('Y-m-d'));
            })
            ->select('p.NPK as npk', 'bio.NAMA_KARYAWAN as nama_karyawan', 'd.DEPARTEMENT as dept_name')
            ->get();

        $employees = [];
        foreach ($rows as $row) {
            $employees[$row->npk] = [
                'NAMA_KARYAWAN'      => $row->nama_karyawan,
                'SUBDIVISI_FALLBACK' => $row->dept_name,
            ];
        }

        return $employees;
    }

    /**
     * Ambil semua row overtimes dalam rentang tanggal, di-index by npk+tanggal.
     */
    protected function getOvertimeMap(Carbon $start, Carbon $end): array
    {
        $rows = DB::select("
            SELECT NPK AS npk, NAMA_KARYAWAN, BAGIAN, DEPT_GROUP, OVERTIME_DATE, JUMLAH_JAM_LEMBUR
            FROM overtimes
            WHERE OVERTIME_DATE >= ? AND OVERTIME_DATE <= ?
        ", [$start->format('Y-m-d'), $end->format('Y-m-d')]);

        $map = [];
        foreach ($rows as $row) {
            $day = Carbon::parse($row->OVERTIME_DATE)->format('Y-m-d');
            $map[$row->npk][$day] = [
                'NAMA_KARYAWAN'      => $row->NAMA_KARYAWAN,
                'BAGIAN'             => $row->BAGIAN,
                'DEPT_GROUP'         => $row->DEPT_GROUP,
                'JUMLAH_JAM_LEMBUR'  => $row->JUMLAH_JAM_LEMBUR,
            ];
        }

        return $map;
    }

    /**
     * Ambil shift terjadwal per npk+tanggal dari employee_shifts + shifts.
     */
    protected function getShiftMap(Carbon $start, Carbon $end): array
    {
        $rows = DB::select("
            SELECT es.npk AS npk, es.shift_date AS shift_date,
                   CONVERT(varchar(8), s.work_start, 108) AS work_start,
                   CONVERT(varchar(8), s.work_end, 108)   AS work_end
            FROM employee_shifts es
            INNER JOIN shifts s ON s.id = es.shift_id
            WHERE es.shift_date >= ? AND es.shift_date <= ?
        ", [$start->format('Y-m-d'), $end->format('Y-m-d')]);

        $map = [];
        foreach ($rows as $row) {
            $day = Carbon::parse($row->shift_date)->format('Y-m-d');
            $map[$row->npk][$day] = [
                'work_start' => $row->work_start,
                'work_end'   => $row->work_end,
            ];
        }

        return $map;
    }

    /**
     * Ambil izin terlambat (employee_lates) per npk+tanggal.
     */
    protected function getLateMap(Carbon $start, Carbon $end): array
    {
        $rows = DB::select("
            SELECT npk, [date] AS late_date, CONVERT(varchar(8), arrival_time, 108) AS arrival_time
            FROM employee_lates
            WHERE [date] >= ? AND [date] <= ?
        ", [$start->format('Y-m-d'), $end->format('Y-m-d')]);

        $map = [];
        foreach ($rows as $row) {
            $day = Carbon::parse($row->late_date)->format('Y-m-d');
            $map[$row->npk][$day] = $row->arrival_time;
        }

        return $map;
    }

    /**
     * Ambil semua scan att_log dalam rentang tanggal, join ke biodata/biodata_keluar
     * via pin=BARCODE untuk dapat NPK. Hasil di-group per npk+tanggal (list of time string).
     */
    protected function getScanMap(Carbon $start, Carbon $end): array
    {
        // CATATAN: BARCODE (varchar) dan pin (kemungkinan int/bigint) di-cast
        // eksplisit ke VARCHAR sebelum dibandingkan. Tanpa ini, SQL Server akan
        // otomatis convert varchar -> int (aturan data type precedence) dan bisa
        // overflow kalau nilai pin/BARCODE melebihi batas INT (~2.1 milyar).
        $rows = DB::select("
            SELECT b.NPK AS npk,
                   CAST(a.scan_date AS DATE) AS scan_day,
                   CONVERT(varchar(8), a.scan_date, 108) AS scan_time
            FROM att_log a
            INNER JOIN (
                SELECT BARCODE, NPK FROM biodata
                UNION
                SELECT BARCODE, NPK FROM biodata_keluar
            ) b ON CAST(b.BARCODE AS VARCHAR(20)) = CAST(a.pin AS VARCHAR(20))
            WHERE a.scan_date >= ? AND a.scan_date <= ?
        ", [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')]);

        $map = [];
        foreach ($rows as $row) {
            $day = Carbon::parse($row->scan_day)->format('Y-m-d');
            $map[$row->npk][$day][] = $row->scan_time;
        }

        return $map;
    }

    /**
     * Ambil daftar tanggal libur (holidays) dalam rentang periode, sebagai
     * array string 'Y-m-d'. Semua row di tabel holidays dipakai (tidak
     * difilter is_national) -- kalau ternyata hanya hari libur nasional
     * yang harus dihitung sebagai hari libur, tambahkan WHERE is_national = 1.
     */
    protected function getHolidayDates(Carbon $start, Carbon $end): array
    {
        $rows = DB::select("
            SELECT holiday_date
            FROM holidays
            WHERE holiday_date >= ? AND holiday_date <= ?
        ", [$start->format('Y-m-d'), $end->format('Y-m-d')]);

        return array_map(
            fn ($row) => Carbon::parse($row->holiday_date)->format('Y-m-d'),
            $rows
        );
    }

    /**
     * Ambil daftar NPK+tanggal yang punya record di ijin_meninggalkan_pekerjaans
     * dengan jam_kembali kosong/null ATAU '17:00:00.0000000' (sentinel "belum
     * kembali" yang dipakai sistem ini). Kalau NPK+tanggal ada di map ini,
     * JAM_SIANG di AUDIT dipaksa null (lihat buildRow()).
     */
    protected function getLeaveNoReturnMap(Carbon $start, Carbon $end): array
    {
        $rows = DB::select("
            SELECT npk, tanggal
            FROM ijin_meninggalkan_pekerjaans
            WHERE tanggal >= ? AND tanggal <= ?
              AND (jam_kembali IS NULL OR CONVERT(varchar(8), jam_kembali, 108) = '17:00:00')
        ", [$start->format('Y-m-d'), $end->format('Y-m-d')]);

        $map = [];
        foreach ($rows as $row) {
            $day = Carbon::parse($row->tanggal)->format('Y-m-d');
            $map[$row->npk][$day] = true;
        }

        return $map;
    }

    /**
     * Upsert batch ke tabel AUDIT (insert baru / overwrite yang sudah ada
     * berdasarkan unique key NPK+TANGGAL). Butuh unique index di DB.
     */
    protected function upsert(array $rows): void
    {
        DB::table('AUDIT')->upsert(
            $rows,
            ['NPK', 'TANGGAL'],
            ['SUBDIVISI', 'JAM_PAGI', 'JAM_SIANG', 'JAM_MALAM', 'STATUS', 'VOID', 'NAMA_KARYAWAN', 'KODE_BAGIAN', 'DEPT_GROUP', 'updated_at']
        );
    }

    // ------------------------------------------------------------------
    // Helper waktu
    // ------------------------------------------------------------------

    protected function timeToMinutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));
        return ($h * 60) + $m;
    }

    protected function addMinutesToTime(string $time, float $minutes): string
    {
        $base = Carbon::createFromFormat('H:i:s', $time);
        $base->addMinutes((int) round($minutes));
        return $base->format('H:i'); // disimpan sebagai string biasa 'HH:MM', tanpa detik
    }

    /**
     * Potong string waktu 'HH:MM:SS' (atau lebih panjang) menjadi 'HH:MM' saja.
     */
    protected function toHHMM(?string $time): ?string
    {
        if ($time === null) {
            return null;
        }

        return substr($time, 0, 5);
    }

    protected function jitter(): int
    {
        return random_int(self::JITTER_MIN_MINUTES, self::JITTER_MAX_MINUTES);
    }

    protected function tsJitter(): int
    {
        return random_int(self::TS_JITTER_MIN_MINUTES, self::TS_JITTER_MAX_MINUTES);
    }
}