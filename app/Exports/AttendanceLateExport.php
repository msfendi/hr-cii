<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;

class AttendanceLateExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
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
    //             b.BARCODE AS pin,
    //             b.NAMA_KARYAWAN AS nama,
    //             b.NPK AS npk,
    //             d.DEPARTEMENT AS bagian,
    //             CONVERT(varchar, MIN(a.scan_date), 108) AS jam_masuk,
    //             CONVERT(varchar, MAX(a.scan_date), 108) AS jam_pulang,
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
    //             b.BARCODE, b.NAMA_KARYAWAN, b.NPK, d.DEPARTEMENT, s.name, s.work_start
    //         HAVING CONVERT(varchar, MIN(a.scan_date), 108) > CONVERT(varchar, COALESCE(s.work_start, '08:00:00'), 108)
    //         ORDER BY MIN(a.scan_date) ASC
    //     ", [$this->date, $yesterday, $this->date, $this->date, $this->date, $yesterday]);
    //     return collect($data);
    // }
    // ─── END OLD ─────────────────────────────────────────────────

    public function collection()
    {
        $yesterday = \Carbon\Carbon::parse($this->date)->subDay()->format('Y-m-d');
        $data = DB::connection('cii')->select("
            SELECT
                b.BARCODE               AS pin,
                b.NAMA_KARYAWAN         AS nama,
                b.NPK                   AS npk,
                d.DEPARTEMENT           AS bagian,

                -- jam_masuk: scan pertama jika > 1, atau single scan di paruh awal shift
                CASE
                    WHEN COUNT(a.scan_date) > 1
                        THEN CONVERT(varchar(8), MIN(a.scan_date), 108)
                    WHEN MIN(a.scan_date) <= DATEADD(
                        hour, 4,
                        CAST(? + ' ' + CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME)
                    )
                        THEN CONVERT(varchar(8), MIN(a.scan_date), 108)
                    ELSE 'not scanned'
                END                                             AS jam_masuk,

                -- jam_pulang: scan terakhir jika > 1, atau single scan di paruh akhir shift
                CASE
                    WHEN COUNT(a.scan_date) > 1
                        THEN CONVERT(varchar(8), MAX(a.scan_date), 108)
                    WHEN MIN(a.scan_date) > DATEADD(
                        hour, 4,
                        CAST(? + ' ' + CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME)
                    )
                        THEN CONVERT(varchar(8), MIN(a.scan_date), 108)
                    ELSE 'not scanned'
                END                                             AS jam_pulang,

                COUNT(a.scan_date)                              AS total_scan,
                COALESCE(s.name, 'Normal Shift')                AS shift_name,
                CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS shift_start

            FROM BIODATA b
            LEFT JOIN DEPT d ON d.ID_DEPT = b.ID_DEPT

            -- Shift hari ini
            LEFT JOIN employee_shifts es
                ON es.npk = b.NPK
                AND CAST(es.shift_date AS DATE) = CAST(? AS DATE)
            LEFT JOIN shifts s ON s.id = es.shift_id

            -- Shift kemarin (untuk filter overflow night shift)
            LEFT JOIN employee_shifts prev_es
                ON prev_es.npk = b.NPK
                AND CAST(prev_es.shift_date AS DATE) = CAST(? AS DATE)
            LEFT JOIN shifts prev_s ON prev_s.id = prev_es.shift_id

            JOIN att_log a
                ON CAST(a.pin AS VARCHAR) = CAST(b.BARCODE AS VARCHAR)

                AND a.scan_date >= DATEADD(
                    hour, -4,
                    CAST(? + ' ' + CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME)
                )
                AND a.scan_date <= DATEADD(
                    hour, 14,
                    CAST(? + ' ' + CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME)
                )
                AND a.scan_date > COALESCE(
                    DATEADD(minute, 60,
                        CASE
                            WHEN prev_s.work_end < prev_s.work_start
                            THEN CAST(? + ' ' + CONVERT(varchar(8), prev_s.work_end, 108) AS DATETIME)
                            ELSE CAST(? + ' ' + CONVERT(varchar(8), prev_s.work_end, 108) AS DATETIME)
                        END
                    ),
                    CAST('1900-01-01 00:00:00' AS DATETIME)
                )

            GROUP BY
                b.BARCODE,
                b.NAMA_KARYAWAN,
                b.NPK,
                d.DEPARTEMENT,
                s.name,
                s.work_start

            -- Hanya yang terlambat: masuk > work_start + 10 menit, dan ada scan masuk
            HAVING MIN(a.scan_date) > DATEADD(
                    minute, 10,
                    CAST(? + ' ' + CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME)
                )
                AND (
                    COUNT(a.scan_date) > 1
                    OR MIN(a.scan_date) <= DATEADD(
                        hour, 4,
                        CAST(? + ' ' + CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME)
                    )
                )

            ORDER BY MIN(a.scan_date) ASC
        ", [
            $this->date,        // jam_masuk midpoint
            $this->date,        // jam_pulang midpoint
            $this->date,        // es.shift_date
            $yesterday,         // prev_es.shift_date
            $this->date,        // scan_date lower bound
            $this->date,        // scan_date upper bound
            $this->date,        // prev night shift work_end
            $yesterday,         // prev normal shift work_end
            $this->date,        // HAVING late tolerance
            $this->date,        // HAVING masuk check
        ]);

        return collect($data);
    }

    public function headings(): array
    {
        return [
            'No',
            'NPK',
            'Nama Karyawan',
            'Bagian',
            'Shift Kerja',
            'Jam Masuk (Shift)',
            'Jam Finger Pagi',
            'Jam Finger Pulang',
        ];
    }

    public function map($row): array
    {
        static $no = 1;
        return [
            $no++,
            $row->npk,
            $row->nama,
            $row->bagian,
            $row->shift_name,
            $row->shift_start,
            $row->jam_masuk,
            $row->jam_pulang,
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
}
