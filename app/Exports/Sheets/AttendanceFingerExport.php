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


        $date = $this->date;

        $data = DB::connection('cii')->select("
            SELECT
                b.BARCODE               AS pin,
                b.NAMA_KARYAWAN         AS nama,
                b.NPK                   AS npk,
                d.DEPARTEMENT           AS bagian,
                b.SECTION               AS section,
                b.BAG                   AS jabatan,
                b.STATUS                AS status,

                CASE WHEN masuk.scan_date IS NOT NULL
                    THEN CONVERT(varchar(8), masuk.scan_date, 108)
                    ELSE 'not scanned'
                END                                             AS jam_masuk,

                CASE WHEN pulang.scan_date IS NOT NULL
                    THEN CONVERT(varchar(8), pulang.scan_date, 108)
                    ELSE 'not scanned'
                END                                             AS jam_pulang,

                (CASE WHEN masuk.scan_date IS NOT NULL THEN 1 ELSE 0 END
            + CASE WHEN pulang.scan_date IS NOT NULL THEN 1 ELSE 0 END)  AS total_scan,

                COALESCE(s.name, 'Normal Shift')                AS shift_name,
                CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS shift_start

            FROM BIODATA b
            LEFT JOIN DEPT d ON d.ID_DEPT = b.ID_DEPT

            LEFT JOIN employee_shifts es
                ON es.npk = b.NPK
                AND CAST(es.shift_date AS DATE) = CAST(? AS DATE)
            LEFT JOIN shifts s ON s.id = es.shift_id

            /* hitung datetime start & end shift hari ini (overnight-aware) */
            CROSS APPLY (
                SELECT
                    CAST(? + ' ' + CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME) AS shift_start_dt,
                    CASE
                        WHEN COALESCE(s.work_end, '17:00:00') < COALESCE(s.work_start, '08:00:00')
                            THEN DATEADD(day, 1, CAST(? + ' ' + CONVERT(varchar(8), COALESCE(s.work_end, '17:00:00'), 108) AS DATETIME))
                        ELSE CAST(? + ' ' + CONVERT(varchar(8), COALESCE(s.work_end, '17:00:00'), 108) AS DATETIME)
                    END AS shift_end_dt
            ) sw

            /* scan terdekat ke shift_start = jam masuk */
            OUTER APPLY (
                SELECT TOP 1 a.scan_date
                FROM att_log a
                WHERE CAST(a.pin AS VARCHAR) = CAST(b.BARCODE AS VARCHAR)
                AND a.scan_date BETWEEN DATEADD(hour, -2, sw.shift_start_dt) AND DATEADD(hour, 3, sw.shift_start_dt)
                ORDER BY ABS(DATEDIFF(second, a.scan_date, sw.shift_start_dt))
            ) masuk

            /* scan terdekat ke shift_end = jam pulang (exclude scan yang sudah dipakai jadi masuk) */
            OUTER APPLY (
                SELECT TOP 1 a.scan_date
                FROM att_log a
                WHERE CAST(a.pin AS VARCHAR) = CAST(b.BARCODE AS VARCHAR)
                AND a.scan_date BETWEEN DATEADD(hour, -1, sw.shift_end_dt) AND DATEADD(hour, 6, sw.shift_end_dt)
                AND (masuk.scan_date IS NULL OR a.scan_date <> masuk.scan_date)
                ORDER BY ABS(DATEDIFF(second, a.scan_date, sw.shift_end_dt))
            ) pulang

            ORDER BY d.DEPARTEMENT ASC, b.NPK ASC
        ", [
            $date,   // es.shift_date
            $date,   // shift_start_dt
            $date,   // shift_end_dt (overnight branch)
            $date,   // shift_end_dt (else branch)
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
