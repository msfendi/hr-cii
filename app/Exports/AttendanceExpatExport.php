<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class AttendanceExpatExport implements FromArray, WithEvents, WithStyles
{
    public function __construct(protected array $employees, protected array $dates) {}

    public function array(): array
    {
        $rows = [];
        $header = ['No', 'NPK', 'Nama', 'Bagian'];
        foreach ($this->dates as $d) $header[] = date('d/m', strtotime($d));
        $header[] = 'Keterangan';
        $rows[] = $header;

        foreach ($this->employees as $emp) {
            $rowMasuk  = [$emp['no'], $emp['npk'], $emp['nama'], $emp['bagian'] ?? '-'];
            $rowPulang = ['', '', '', ''];
            foreach ($this->dates as $d) {
                $rowMasuk[]  = $emp['attendance'][$d]['masuk']  ?? 'not scanned';
                $rowPulang[] = $emp['attendance'][$d]['pulang'] ?? 'not scanned';
            }
            $rowMasuk[]  = 'Masuk';
            $rowPulang[] = 'Pulang';
            $rows[] = $rowMasuk;
            $rows[] = $rowPulang;
        }
        return $rows;
    }

    public function styles($sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();

                $row = 2;
                foreach ($this->employees as $emp) {
                    foreach (['A', 'B', 'C', 'D'] as $col) {
                        $sheet->mergeCells("{$col}{$row}:{$col}" . ($row + 1));
                    }
                    $row += 2;
                }

                $sheet->getStyle("A1:{$lastCol}" . ($row - 1))->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A1:{$lastCol}" . ($row - 1))
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                foreach (range('A', $lastCol) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}