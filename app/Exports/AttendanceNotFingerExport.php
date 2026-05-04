<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;

class AttendanceNotFingerExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
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
    //     $rows = DB::connection('cii')->select("
    //         SELECT
    //             b.BARCODE       AS pin,
    //             b.NAMA_KARYAWAN AS nama,
    //             b.NPK           AS npk,
    //             b.SECTION       AS section,
    //             d.DEPARTEMENT   AS departemen,
    //             b.STATUS        AS status
    //         FROM BIODATA b
    //         LEFT JOIN DEPT d ON d.ID_DEPT = b.ID_DEPT
    //         LEFT JOIN employee_shifts es ON es.npk = b.NPK AND CAST(es.shift_date AS DATE) = CAST(? AS DATE)
    //         LEFT JOIN shifts s ON s.id = es.shift_id
    //         LEFT JOIN employee_shifts prev_es ON prev_es.npk = b.NPK AND CAST(prev_es.shift_date AS DATE) = CAST(? AS DATE)
    //         LEFT JOIN shifts prev_s ON prev_s.id = prev_es.shift_id
    //         WHERE b.STATUS = 'A'
    //           AND NOT EXISTS (
    //               SELECT 1
    //               FROM att_log a
    //               WHERE CAST(a.pin AS VARCHAR) = CAST(b.BARCODE AS VARCHAR)
    //                 AND a.scan_date >= DATEADD(hour, -4, CAST(? + ' ' + CONVERT(varchar, COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME))
    //                 AND a.scan_date <= DATEADD(hour, 14, CAST(? + ' ' + CONVERT(varchar, COALESCE(s.work_start, '08:00:00'), 108) AS DATETIME))
    //                 AND a.scan_date > COALESCE(
    //                     DATEADD(minute, 60,
    //                         CASE
    //                             WHEN prev_s.work_end < prev_s.work_start
    //                             THEN CAST(? + ' ' + CONVERT(varchar, prev_s.work_end, 108) AS DATETIME)
    //                             ELSE CAST(? + ' ' + CONVERT(varchar, prev_s.work_end, 108) AS DATETIME)
    //                         END
    //                     ),
    //                     CAST('1900-01-01' AS DATETIME)
    //                 )
    //           )
    //         ORDER BY d.DEPARTEMENT ASC, b.NPK ASC
    //     ", [$this->date, $yesterday, $this->date, $this->date, $this->date, $yesterday]);
    //
    //     ...
    // }
    // ─── END OLD ─────────────────────────────────────────────────

    public function collection()
    {
        $yesterday = \Carbon\Carbon::parse($this->date)->subDay()->format('Y-m-d');
        $rows = DB::connection('cii')->select("
            SELECT
                b.BARCODE       AS pin,
                b.NAMA_KARYAWAN AS nama,
                b.NPK           AS npk,
                b.SECTION       AS section,
                d.DEPARTEMENT   AS departemen,
                b.STATUS        AS status
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

            WHERE b.STATUS = 'A'
              AND NOT EXISTS (
                  SELECT 1
                  FROM att_log a
                  WHERE CAST(a.pin AS VARCHAR) = CAST(b.BARCODE AS VARCHAR)
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
              )
            ORDER BY d.DEPARTEMENT ASC, b.NPK ASC
        ", [
            $this->date,
            $yesterday,
            $this->date,
            $this->date,
            $this->date,
            $yesterday,
        ]);

        $data = [];
        $no   = 1;
        foreach ($rows as $row) {
            $data[] = [
                'no'         => $no++,
                'tanggal'    => Carbon::parse($this->date)->format('d/m/Y'),
                'npk'        => $row->npk,
                'nama'       => $row->nama,
                'departemen' => $row->departemen,
                'section'    => $row->section,
            ];
        }

        return collect($data);
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal',
            'NPK',
            'Nama Karyawan',
            'Departemen',
            'Section',
        ];
    }

    public function map($row): array
    {
        return [
            $row['no'],
            $row['tanggal'],
            $row['npk'],
            $row['nama'],
            $row['departemen'],
            $row['section'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFF4444'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet        = $event->sheet;
                $highestRow   = $sheet->getHighestRow();
                $highestCol   = $sheet->getHighestColumn();

                // Borders for entire table
                $sheet->getStyle('A1:' . $highestCol . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ]);

                // Light red fill for all data rows (to highlight "not present")
                for ($row = 2; $row <= $highestRow; $row++) {
                    $sheet->getStyle('A' . $row . ':' . $highestCol . $row)
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFFFF0F0');
                }
            },
        ];
    }
}
