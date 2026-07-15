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


        // ================================= new query =================================
        $yesterday = \Carbon\Carbon::parse($this->date)->subDay()->format('Y-m-d');
        $tomorrow  = \Carbon\Carbon::parse($this->date)->addDay()->format('Y-m-d'); // baru

        $data = DB::connection('cii')->select("
            SELECT
                b.BARCODE               AS pin,
                b.NAMA_KARYAWAN         AS nama,
                b.NPK                   AS npk,
                d.DEPARTEMENT           AS bagian,
                b.SECTION               AS section,
                b.BAG                   AS jabatan,
                b.STATUS                AS status,

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

                -- upper bound baru: pakai jam pulang shift + toleransi lembur,
                -- fallback ke window lama (+14 jam) kalau karyawan tidak punya shift/work_end
                AND a.scan_date <= COALESCE(
                    DATEADD(
                        hour, 3, -- toleransi lembur, silakan sesuaikan
                        CASE
                            WHEN s.work_end < s.work_start
                                THEN CAST(? + ' ' + CONVERT(varchar(8), s.work_end, 108) AS DATETIME) -- $tomorrow
                            ELSE
                                CAST(? + ' ' + CONVERT(varchar(8), s.work_end, 108) AS DATETIME)      -- $this->date
                        END
                    ),
                    DATEADD(
                        hour, 14,
                        CAST(? + ' ' + CONVERT(varchar(8), COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME)
                    ) -- $this->date, fallback lama
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
                b.SECTION,
                b.BAG,
                b.STATUS,
                s.name,
                s.work_start
            ORDER BY d.DEPARTEMENT ASC, b.NPK ASC
        ", [
            $this->date,        // jam_masuk midpoint
            $this->date,        // jam_pulang midpoint
            $this->date,        // es.shift_date
            $yesterday,         // prev_es.shift_date
            $this->date,        // scan_date lower bound
            $tomorrow,          // upper bound: shift overnight -> work_end jatuh besok
            $this->date,        // upper bound: shift normal -> work_end hari ini
            $this->date,        // upper bound fallback (+14h lama)
            $this->date,        // prev night shift work_end
            $yesterday,         // prev normal shift work_end
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
