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

    public function collection()
    {
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
            WHERE b.STATUS = 'A'
              AND NOT EXISTS (
                  SELECT 1
                  FROM att_log a
                  WHERE CAST(a.pin AS VARCHAR) = CAST(b.BARCODE AS VARCHAR)
                    AND a.scan_date >= ? AND a.scan_date <= ?
              )
            ORDER BY d.DEPARTEMENT ASC, b.NPK ASC
        ", [$this->date . ' 00:00:00', $this->date . ' 23:59:59']);

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
                'font'      => ['bold' => true],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFF4444'],
                ],
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
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
