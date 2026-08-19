<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;

class AttendanceFingerExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents, WithTitle
{
    protected $date;

    public function __construct($date)
    {
        $this->date = $date;
    }

    // ─── OLD collection() ────────────────────────────────────────
    // public function collection()
    // {
    //     $yesterday = \Carbon\Carbon::parse($this->date)->subDay()->format('Y-m-d');
    //     $data = DB::connection('cii')->select("
    //         SELECT
    //             b.BARCODE AS pin, b.NAMA_KARYAWAN AS nama, b.NPK AS npk,
    //             d.DEPARTEMENT AS bagian, b.SECTION AS section, b.BAG AS jabatan, b.STATUS AS status,
    //             CONVERT(varchar(8), MIN(a.scan_date), 108) AS jam_masuk,
    //             CASE WHEN COUNT(a.scan_date) > 1 THEN CONVERT(varchar(8), MAX(a.scan_date), 108)
    //                  ELSE 'not scanned' END AS jam_pulang,
    //             COUNT(a.scan_date) AS total_scan,
    //             COALESCE(s.name, 'Normal Shift') AS shift_name,
    //             CONVERT(varchar, COALESCE(s.work_start, '08:00:00'), 108) AS shift_start
    //         FROM BIODATA b
    //         LEFT JOIN DEPT d ON d.ID_DEPT = b.ID_DEPT
    //         LEFT JOIN employee_shifts es ON es.npk = b.NPK AND CAST(es.shift_date AS DATE) = CAST(? AS DATE)
    //         LEFT JOIN shifts s ON s.id = es.shift_id
    //         LEFT JOIN employee_shifts prev_es ON prev_es.npk = b.NPK AND CAST(prev_es.shift_date AS DATE) = CAST(? AS DATE)
    //         LEFT JOIN shifts prev_s ON prev_s.id = prev_es.shift_id
    //         JOIN att_log a ON CAST(a.pin AS VARCHAR) = CAST(b.BARCODE AS VARCHAR)
    //             AND a.scan_date >= DATEADD(hour, -4, CAST(? + ' ' + CONVERT(varchar, COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME))
    //             AND a.scan_date <= DATEADD(hour, 14, CAST(? + ' ' + CONVERT(varchar, COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME))
    //             AND a.scan_date > COALESCE(
    //                 DATEADD(minute, 60,
    //                     CASE
    //                         WHEN prev_s.work_end < prev_s.work_start
    //                         THEN CAST(? + ' ' + CONVERT(varchar, prev_s.work_end, 108) AS DATETIME)
    //                         ELSE CAST(? + ' ' + CONVERT(varchar, prev_s.work_end, 108) AS DATETIME)
    //                     END
    //                 ),
    //                 CAST('1900-01-01' AS DATETIME)
    //             )
    //         GROUP BY
    //             b.BARCODE, b.NAMA_KARYAWAN, b.NPK, d.DEPARTEMENT,
    //             b.SECTION, b.BAG, b.STATUS, s.name, s.work_start
    //         ORDER BY d.DEPARTEMENT ASC, b.NPK ASC
    //     ", [$this->date, $yesterday, $this->date, $this->date, $this->date, $yesterday]);
    //     return collect($data);
    // }
    // ─── END OLD ─────────────────────────────────────────────────

    public function collection()
    {
        // $yesterday = \Carbon\Carbon::parse($this->date)->subDay()->format('Y-m-d');
        // $data = DB::connection('cii')->select("
        //     SELECT
        //         b.BARCODE               AS pin,
        //         b.NAMA_KARYAWAN         AS nama,
        //         b.NPK                   AS npk,
        //         d.DEPARTEMENT           AS bagian,
        //         b.SECTION               AS section,
        //         b.BAG                   AS jabatan,
        //         b.STATUS                AS status,

        //         -- jam_masuk: scan pertama jika > 1, atau single scan di paruh awal shift
        //         CASE
        //             WHEN COUNT(a.scan_date) > 1
        //                 THEN CONVERT(varchar(8), MIN(a.scan_date), 108)
        //             WHEN MIN(a.scan_date) <= DATEADD(
        //                 hour, 4,
        //                 CAST(? + ' ' + CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME)
        //             )
        //                 THEN CONVERT(varchar(8), MIN(a.scan_date), 108)
        //             ELSE 'not scanned'
        //         END                                             AS jam_masuk,

        //         -- jam_pulang: scan terakhir jika > 1, atau single scan di paruh akhir shift
        //         CASE
        //             WHEN COUNT(a.scan_date) > 1
        //                 THEN CONVERT(varchar(8), MAX(a.scan_date), 108)
        //             WHEN MIN(a.scan_date) > DATEADD(
        //                 hour, 4,
        //                 CAST(? + ' ' + CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME)
        //             )
        //                 THEN CONVERT(varchar(8), MIN(a.scan_date), 108)
        //             ELSE 'not scanned'
        //         END                                             AS jam_pulang,

        //         COUNT(a.scan_date)                              AS total_scan,
        //         COALESCE(s.name, 'Normal Shift')                AS shift_name,
        //         CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS shift_start

        //     FROM BIODATA b
        //     LEFT JOIN DEPT d ON d.ID_DEPT = b.ID_DEPT

        //     -- Shift hari ini
        //     LEFT JOIN employee_shifts es
        //         ON es.npk = b.NPK
        //         AND CAST(es.shift_date AS DATE) = CAST(? AS DATE)
        //     LEFT JOIN shifts s ON s.id = es.shift_id

        //     -- Shift kemarin (untuk filter overflow night shift)
        //     LEFT JOIN employee_shifts prev_es
        //         ON prev_es.npk = b.NPK
        //         AND CAST(prev_es.shift_date AS DATE) = CAST(? AS DATE)
        //     LEFT JOIN shifts prev_s ON prev_s.id = prev_es.shift_id

        //     JOIN att_log a
        //         ON CAST(a.pin AS VARCHAR) = CAST(b.BARCODE AS VARCHAR)

        //         AND a.scan_date >= DATEADD(
        //             hour, -4,
        //             CAST(? + ' ' + CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME)
        //         )
        //         AND a.scan_date <= DATEADD(
        //             hour, 14,
        //             CAST(? + ' ' + CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME)
        //         )
        //         AND a.scan_date > COALESCE(
        //             DATEADD(minute, 60,
        //                 CASE
        //                     WHEN prev_s.work_end < prev_s.work_start
        //                     THEN CAST(? + ' ' + CONVERT(varchar(8), prev_s.work_end, 108) AS DATETIME)
        //                     ELSE CAST(? + ' ' + CONVERT(varchar(8), prev_s.work_end, 108) AS DATETIME)
        //                 END
        //             ),
        //             CAST('1900-01-01 00:00:00' AS DATETIME)
        //         )

        //     GROUP BY
        //         b.BARCODE,
        //         b.NAMA_KARYAWAN,
        //         b.NPK,
        //         d.DEPARTEMENT,
        //         b.SECTION,
        //         b.BAG,
        //         b.STATUS,
        //         s.name,
        //         s.work_start
        //     ORDER BY d.DEPARTEMENT ASC, b.NPK ASC
        // ", [
        //     $this->date,        // jam_masuk midpoint
        //     $this->date,        // jam_pulang midpoint
        //     $this->date,        // es.shift_date
        //     $yesterday,         // prev_es.shift_date
        //     $this->date,        // scan_date lower bound
        //     $this->date,        // scan_date upper bound
        //     $this->date,        // prev night shift work_end
        //     $yesterday,         // prev normal shift work_end
        // ]);

        // return collect($data);


        // (LAST WORK)
        // $yesterday = \Carbon\Carbon::parse($this->date)->subDay()->format('Y-m-d');
        // $tomorrow  = \Carbon\Carbon::parse($this->date)->addDay()->format('Y-m-d');

        // $data = DB::connection('cii')->select("
        //     WITH emp AS (
        //         SELECT
        //             b.BARCODE       AS pin,
        //             b.NAMA_KARYAWAN AS nama,
        //             b.NPK           AS npk,
        //             d.DEPARTEMENT   AS bagian,
        //             b.SECTION       AS section,
        //             b.BAG           AS jabatan,
        //             b.STATUS        AS status,
        //             COALESCE(s.name, 'Normal Shift')    AS shift_name,
        //             COALESCE(s.work_start, '08:00:00')  AS work_start,
        //             COALESCE(s.work_end, '17:00:00')    AS work_end,
        //             COALESCE(ps.work_start, '08:00:00') AS prev_work_start,
        //             COALESCE(ps.work_end, '17:00:00')   AS prev_work_end,
        //             COALESCE(ns.work_start, '08:00:00') AS next_work_start
        //         FROM BIODATA b
        //         LEFT JOIN DEPT d ON d.ID_DEPT = b.ID_DEPT

        //         -- shift hari ini
        //         LEFT JOIN employee_shifts es
        //             ON es.npk = b.NPK
        //             AND CAST(es.shift_date AS DATE) = CAST(? AS DATE)
        //         LEFT JOIN shifts s ON s.id = es.shift_id

        //         -- shift kemarin (untuk exclusion window bawah)
        //         LEFT JOIN employee_shifts pes
        //             ON pes.npk = b.NPK
        //             AND CAST(pes.shift_date AS DATE) = CAST(? AS DATE)
        //         LEFT JOIN shifts ps ON ps.id = pes.shift_id

        //         -- shift besok (untuk exclusion window atas)
        //         LEFT JOIN employee_shifts nes
        //             ON nes.npk = b.NPK
        //             AND CAST(nes.shift_date AS DATE) = CAST(? AS DATE)
        //         LEFT JOIN shifts ns ON ns.id = nes.shift_id
        //     ),
        //     emp_bounds AS (
        //         SELECT
        //             e.*,
        //             CAST(? + ' ' + CONVERT(varchar(8), e.work_start, 108) AS DATETIME) AS shift_start_dt,
        //             CASE
        //                 WHEN e.work_end < e.work_start
        //                     THEN DATEADD(day, 1, CAST(? + ' ' + CONVERT(varchar(8), e.work_end, 108) AS DATETIME))
        //                 ELSE CAST(? + ' ' + CONVERT(varchar(8), e.work_end, 108) AS DATETIME)
        //             END AS shift_end_dt,
        //             CASE
        //                 WHEN e.prev_work_end < e.prev_work_start
        //                     THEN DATEADD(day, 1, CAST(? + ' ' + CONVERT(varchar(8), e.prev_work_end, 108) AS DATETIME))
        //                 ELSE CAST(? + ' ' + CONVERT(varchar(8), e.prev_work_end, 108) AS DATETIME)
        //             END AS prev_shift_end_dt,
        //             CAST(? + ' ' + CONVERT(varchar(8), e.next_work_start, 108) AS DATETIME) AS next_shift_start_dt
        //         FROM emp e
        //     ),
        //     emp_window AS (
        //         SELECT
        //             eb.*,
        //             CASE
        //                 WHEN DATEADD(hour, 6, eb.shift_end_dt) < DATEADD(minute, -60, eb.next_shift_start_dt)
        //                     THEN DATEADD(hour, 6, eb.shift_end_dt)
        //                 ELSE DATEADD(minute, -60, eb.next_shift_start_dt)
        //             END AS scan_upper_bound
        //         FROM emp_bounds eb
        //     ),
        //     scans AS (
        //         SELECT
        //             ew.pin, ew.npk,
        //             a.scan_date,
        //             ew.shift_start_dt,
        //             ew.shift_end_dt
        //         FROM emp_window ew
        //         JOIN att_log a
        //             ON CAST(a.pin AS VARCHAR) = CAST(ew.pin AS VARCHAR)
        //             AND a.scan_date >= DATEADD(hour, -4, ew.shift_start_dt)
        //             AND a.scan_date <= ew.scan_upper_bound
        //             AND a.scan_date > DATEADD(minute, 60, ew.prev_shift_end_dt)
        //     ),
        //     scan_ranked AS (
        //         SELECT
        //             npk, scan_date,
        //             ABS(DATEDIFF(minute, scan_date, shift_start_dt)) AS dist_to_start,
        //             ABS(DATEDIFF(minute, scan_date, shift_end_dt))   AS dist_to_end,
        //             ROW_NUMBER() OVER (PARTITION BY npk ORDER BY ABS(DATEDIFF(minute, scan_date, shift_start_dt))) AS rn_masuk,
        //             ROW_NUMBER() OVER (PARTITION BY npk ORDER BY ABS(DATEDIFF(minute, scan_date, shift_end_dt)))   AS rn_pulang,
        //             COUNT(*) OVER (PARTITION BY npk) AS total_scan
        //         FROM scans
        //     )
        //     SELECT
        //         eb.pin, eb.nama, eb.npk, eb.bagian, eb.section, eb.jabatan, eb.status,

        //         CASE
        //             WHEN m.scan_date IS NULL THEN 'not scanned'
        //             WHEN m.total_scan = 1 AND m.dist_to_end < m.dist_to_start THEN 'not scanned'
        //             ELSE CONVERT(varchar(8), m.scan_date, 108)
        //         END AS jam_masuk,

        //         CASE
        //             WHEN p.scan_date IS NULL THEN 'not scanned'
        //             WHEN p.total_scan = 1 AND p.dist_to_start <= p.dist_to_end THEN 'not scanned'
        //             ELSE CONVERT(varchar(8), p.scan_date, 108)
        //         END AS jam_pulang,

        //         COALESCE(m.total_scan, 0) AS total_scan,
        //         eb.shift_name,
        //         CONVERT(varchar(8), eb.work_start, 108) AS shift_start

        //     FROM emp_window eb
        //     LEFT JOIN scan_ranked m ON m.npk = eb.npk AND m.rn_masuk = 1
        //     LEFT JOIN scan_ranked p ON p.npk = eb.npk AND p.rn_pulang = 1
        //     WHERE m.scan_date IS NOT NULL
        //     ORDER BY eb.bagian ASC, eb.npk ASC
        // ", [
        //     $this->date,    // emp: shift hari ini (es.shift_date)
        //     $yesterday,     // emp: shift kemarin (pes.shift_date)
        //     $tomorrow,      // emp: shift besok (nes.shift_date)
        //     $this->date,    // emp_bounds: shift_start_dt
        //     $this->date,    // emp_bounds: shift_end_dt (overnight)
        //     $this->date,    // emp_bounds: shift_end_dt (normal)
        //     $yesterday,     // emp_bounds: prev_shift_end_dt (overnight)
        //     $yesterday,     // emp_bounds: prev_shift_end_dt (normal)
        //     $tomorrow,      // emp_bounds: next_shift_start_dt
        // ]);

        // return collect($data);


        $yesterday = \Carbon\Carbon::parse($this->date)->subDay()->format('Y-m-d');
        // $tomorrow dihapus
        $data = DB::connection('cii')->select("
            WITH emp AS (
                SELECT
                    b.BARCODE       AS pin,
                    b.NAMA_KARYAWAN AS nama,
                    b.NPK           AS npk,
                    d.DEPARTEMENT   AS bagian,
                    b.SECTION       AS section,
                    b.BAG           AS jabatan,
                    b.STATUS        AS status,
                    COALESCE(s.name,  'Normal Shift') AS shift_name,
                    COALESCE(s.work_start, '08:00:00') AS work_start,
                    COALESCE(s.work_end,   '17:00:00') AS work_end,
                    COALESCE(ps.work_start,'08:00:00') AS prev_work_start,
                    COALESCE(ps.work_end,  '17:00:00') AS prev_work_end
                FROM BIODATA b
                LEFT JOIN DEPT d ON d.ID_DEPT = b.ID_DEPT
                LEFT JOIN employee_shifts es
                    ON es.npk = b.NPK
                    AND CAST(es.shift_date AS DATE) = CAST(? AS DATE)
                LEFT JOIN shifts s ON s.id = es.shift_id
                LEFT JOIN employee_shifts pes
                    ON pes.npk = b.NPK
                    AND CAST(pes.shift_date AS DATE) = CAST(? AS DATE)
                LEFT JOIN shifts ps ON ps.id = pes.shift_id
            ),
            emp_bounds AS (
                SELECT
                    e.*,
                    CAST(? + ' ' + CONVERT(varchar(8), e.work_start, 108) AS DATETIME) AS shift_start_dt,
                    CASE
                        WHEN e.work_end < e.work_start
                            THEN DATEADD(day, 1, CAST(? + ' ' + CONVERT(varchar(8), e.work_end, 108) AS DATETIME))
                        ELSE CAST(? + ' ' + CONVERT(varchar(8), e.work_end, 108) AS DATETIME)
                    END AS shift_end_dt,
                    CASE
                        WHEN e.prev_work_end < e.prev_work_start
                            THEN DATEADD(day, 1, CAST(? + ' ' + CONVERT(varchar(8), e.prev_work_end, 108) AS DATETIME))
                        ELSE CAST(? + ' ' + CONVERT(varchar(8), e.prev_work_end, 108) AS DATETIME)
                    END AS prev_shift_end_dt
                FROM emp e
            ),
            emp_window AS (
                SELECT
                    eb.*,
                    DATEADD(minute, 120, eb.shift_end_dt) AS scan_upper_bound  -- FIX: simetris
                FROM emp_bounds eb
            ),
            scans AS (
                SELECT
                    ew.pin, ew.npk,
                    a.scan_date,
                    ew.shift_start_dt,
                    ew.shift_end_dt
                FROM emp_window ew
                JOIN att_log a
                    ON CAST(a.pin AS VARCHAR) = CAST(ew.pin AS VARCHAR)
                    AND a.scan_date >= DATEADD(hour, -4, ew.shift_start_dt)
                    AND a.scan_date <= ew.scan_upper_bound
                    AND a.scan_date > DATEADD(minute, 120, ew.prev_shift_end_dt)  -- FIX: 120, bukan 60
            ),
            scan_ranked AS (
                SELECT
                    npk, scan_date,
                    ABS(DATEDIFF(minute, scan_date, shift_start_dt)) AS dist_to_start,
                    ABS(DATEDIFF(minute, scan_date, shift_end_dt))   AS dist_to_end,
                    ROW_NUMBER() OVER (PARTITION BY npk ORDER BY ABS(DATEDIFF(minute, scan_date, shift_start_dt))) AS rn_masuk,
                    ROW_NUMBER() OVER (PARTITION BY npk ORDER BY ABS(DATEDIFF(minute, scan_date, shift_end_dt)))   AS rn_pulang,
                    COUNT(*) OVER (PARTITION BY npk) AS total_scan
                FROM scans
            )
            SELECT
                eb.pin, eb.nama, eb.npk, eb.bagian, eb.section, eb.jabatan, eb.status,

                CASE
                    WHEN m.scan_date IS NULL                                    THEN 'not scanned'
                    WHEN m.total_scan = 1 AND m.dist_to_end < m.dist_to_start  THEN 'not scanned'
                    ELSE CONVERT(varchar(8), m.scan_date, 108)
                END AS jam_masuk,

                CASE
                    WHEN p.scan_date IS NULL                                    THEN 'not scanned'
                    WHEN p.total_scan = 1 AND p.dist_to_start <= p.dist_to_end THEN 'not scanned'
                    ELSE CONVERT(varchar(8), p.scan_date, 108)
                END AS jam_pulang,

                COALESCE(m.total_scan, 0)               AS total_scan,
                eb.shift_name,
                CONVERT(varchar(8), eb.work_start, 108) AS shift_start

            FROM emp_window eb
            LEFT JOIN scan_ranked m ON m.npk = eb.npk AND m.rn_masuk  = 1
            LEFT JOIN scan_ranked p ON p.npk = eb.npk AND p.rn_pulang = 1
            WHERE m.scan_date IS NOT NULL
            ORDER BY eb.bagian ASC, eb.npk ASC
        ", [
            $this->date,  // emp: shift hari ini
            $yesterday,   // emp: shift kemarin
            $this->date,  // emp_bounds: shift_start_dt
            $this->date,  // emp_bounds: shift_end_dt CASE night
            $this->date,  // emp_bounds: shift_end_dt CASE normal
            $yesterday,   // emp_bounds: prev_shift_end_dt CASE night
            $yesterday,   // emp_bounds: prev_shift_end_dt CASE normal
        ]);

        return collect($data);
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal',
            'Nama Karyawan',
            'NPK',
            'Nama Departemen',
            'Departemen',
            'Jabatan',
            'Shift',
            'Shift Masuk',
            'Jam Masuk',
            'Jam Pulang',
            'Status'
        ];
    }

    public function map($row): array
    {
        static $no = 1;
        return [
            $no++,
            Carbon::parse($this->date)->format('d/m/Y'),
            $row->nama,
            $row->npk,
            $row->bagian,
            $row->section,
            $row->jabatan,
            $row->shift_name,
            $row->shift_start,
            $row->jam_masuk,
            $row->jam_pulang,
            $row->status == 'A' ? 'AKTIF' : $row->status,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => [
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                $range = 'A1:' . $highestColumn . $highestRow;
                $sheet->getStyle($range)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ]);

                for ($row = 2; $row <= $highestRow; $row++) {
                    $shiftStartVal = $sheet->getCell('I' . $row)->getValue();
                    $masukVal = $sheet->getCell('J' . $row)->getValue();
                    $pulangVal = $sheet->getCell('K' . $row)->getValue();

                    // Highlight 'not scanned' masuk
                    if ($masukVal === 'not scanned') {
                        $sheet->getStyle('J' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC0CB'); // Pink
                    } elseif ($masukVal > $shiftStartVal) {
                        $sheet->getStyle('J' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00'); // Yellow
                    }

                    // Highlight 'not scanned' pulang
                    if ($pulangVal === 'not scanned') {
                        $sheet->getStyle('K' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC0CB'); // Pink
                    }
                }
            },
        ];
    }

    public function title(): string
    {
        return 'Attendance Finger';
    }
}
