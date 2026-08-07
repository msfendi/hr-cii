<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AttendanceExpatExport implements FromArray, WithEvents, WithStyles
{
    public function __construct(
        protected array $employees,
        protected array $dates,
        protected array $offDates = [],   // "YYYY-MM-DD" => 'holiday' | 'weekend'
        protected string $periodLabel = ''
    ) {}

    public function array(): array
    {
        $totalCols = 4 + count($this->dates) + 1; // No, NPK, Nama, Bagian + tanggal-tanggal + Keterangan

        $rows = [];

        // baris judul (di-merge & di-style di AfterSheet)
        $titleRow = array_fill(0, $totalCols, '');
        $titleRow[0] = 'Attendance Report - Expat';
        $rows[] = $titleRow;

        // baris info periode + jumlah karyawan yang di-export
        $infoRow = array_fill(0, $totalCols, '');
        $infoRow[0] = 'Periode: ' . $this->periodLabel . '  |  Jumlah Karyawan: ' . count($this->employees);
        $rows[] = $infoRow;

        // baris header kolom
        $header = ['No', 'NPK', 'Nama', 'Bagian'];
        foreach ($this->dates as $d) {
            // sengaja pakai d/m (bukan cuma dd seperti di tabel web) karena
            // rentang export bisa custom dan lintas bulan -> dd doang bisa ambigu
            $header[] = date('d/m', strtotime($d));
        }
        $header[] = 'Keterangan';
        $rows[] = $header;

        foreach ($this->employees as $emp) {
            $rowMasuk  = [$emp['no'], $emp['npk'], $emp['nama'], $emp['bagian'] ?? '-'];
            $rowPulang = ['', '', '', ''];
            foreach ($this->dates as $d) {
                $rowMasuk[]  = $this->fmtTime($emp['attendance'][$d]['masuk']  ?? null);
                $rowPulang[] = $this->fmtTime($emp['attendance'][$d]['pulang'] ?? null);
            }
            $rowMasuk[]  = 'Masuk';
            $rowPulang[] = 'Pulang';
            $rows[] = $rowMasuk;
            $rows[] = $rowPulang;
        }

        return $rows;
    }

    /** "08:15:32" -> "08:15", kosong / "not scanned" -> "N/A" (samain sama tampilan blade) */
    private function fmtTime(?string $val): string
    {
        if (!$val || $val === 'not scanned') {
            return 'N/A';
        }
        return substr($val, 0, 5);
    }

    public function styles($sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '6B7280']]],
            3 => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();

                // merge baris judul & info periode selebar tabel
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->mergeCells("A2:{$lastCol}2");

                // merge No/NPK/Nama/Bagian per pasangan baris Masuk-Pulang
                // data mulai baris ke-4 (1: judul, 2: info periode, 3: header kolom)
                $row = 4;
                foreach ($this->employees as $emp) {
                    foreach (['A', 'B', 'C', 'D'] as $col) {
                        $sheet->mergeCells("{$col}{$row}:{$col}" . ($row + 1));
                    }
                    $row += 2;
                }
                $lastRow = $row - 1;

                // alignment + border untuk area header s.d. baris data terakhir
                $sheet->getStyle("A3:{$lastCol}{$lastRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle("A3:{$lastCol}{$lastRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // blok fill merah untuk kolom tanggal libur/weekend
                // (kolom tanggal mulai persis setelah No/NPK/Nama/Bagian -> kolom ke-5 / 'E')
                foreach (array_values($this->dates) as $i => $d) {
                    if (empty($this->offDates[$d])) {
                        continue;
                    }

                    $colLetter = Coordinate::stringFromColumnIndex(5 + $i);
                    $range     = "{$colLetter}3:{$colLetter}{$lastRow}";

                    $sheet->getStyle($range)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FECACA');
                    $sheet->getStyle($range)->getFont()->getColor()->setRGB('7F1D1D');
                }

                foreach (range('A', $lastCol) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}