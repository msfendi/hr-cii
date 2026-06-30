<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class AttendanceManualSheet implements FromCollection, WithTitle, WithHeadings, WithStyles, WithEvents, WithCustomStartCell
{
    protected $month;
    protected $year;
    protected $deptId;
    protected $deptName;
    protected $daysInMonth;

    public function __construct($month, $year, $deptId, $deptName)
    {
        $this->month = $month;
        $this->year = $year;
        $this->deptId = $deptId;
        $this->deptName = $deptName;
        $this->daysInMonth = Carbon::create($year, $month)->daysInMonth;
    }

    public function startCell(): string
    {
        return 'A4';
    }

    public function collection()
    {
        $employees = DB::connection('cii')
            ->table('BIODATA as b')
            ->leftJoin('PKWT as p', 'p.NPK', '=', 'b.NPK')
            ->where('b.ID_DEPT', $this->deptId)
            ->where('b.STATUS', 'A')
            ->select('b.NPK', 'b.NAMA_KARYAWAN', 'p.TMK')
            ->orderBy('b.NPK', 'asc')
            ->get();

        $data = [];
        $no = 1;
        foreach ($employees as $emp) {
            $row = [
                'No' => $no++,
                'NPK' => $emp->NPK,
                'Nama Karyawan' => $emp->NAMA_KARYAWAN,
                'Department' => $this->deptName,
                'TMK' => $emp->TMK ? Carbon::parse($emp->TMK)->format('d-M-y') : '',
            ];

            for ($day = 1; $day <= $this->daysInMonth; $day++) {
                $row[(string)$day] = '';
            }
            $data[] = $row;
        }

        return collect($data);
    }

    public function headings(): array
    {
        $monthYear = Carbon::create($this->year, $this->month, 1)->translatedFormat('F Y');

        $row1 = ['No', 'NPK', 'Nama Karyawan', 'Department', 'TMK'];
        $row1[] = strtoupper($monthYear);
        for ($day = 2; $day <= $this->daysInMonth; $day++) {
            $row1[] = '';
        }

        $row2 = ['', '', '', '', ''];
        for ($day = 1; $day <= $this->daysInMonth; $day++) {
            $row2[] = (string)$day;
        }

        return [$row1, $row2];
    }

    public function title(): string
    {
        // Sheet title max length is 31 characters, remove special characters
        return substr(str_replace(['*', ':', '/', '\\', '?', '[', ']'], '', $this->deptName), 0, 31);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            4 => ['font' => ['bold' => true]],
            5 => ['font' => ['bold' => true]],
            1 => ['font' => ['bold' => true]],
            2 => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                // Header merge
                $sheet->mergeCells('A1:D1');
                $sheet->mergeCells('A2:D2');

                // Custom Header text
                $sheet->setCellValue('A1', 'PT. CHUTEX INTERNATIONAL INDONESIA');
                $sheet->setCellValue('F1', 'CHIEF : ..............................................................');

                $sheet->setCellValue('A2', 'ABSENSI KARYAWAN');
                $sheet->setCellValue('F2', 'SPV : ..............................................................');
                $sheet->setCellValue('O2', 'ADM : ..............................................................');
                $sheet->setCellValue('X2', $this->deptName);

                // Merge fixed columns
                $sheet->mergeCells('A4:A5');
                $sheet->mergeCells('B4:B5');
                $sheet->mergeCells('C4:C5');
                $sheet->mergeCells('D4:D5');
                $sheet->mergeCells('E4:E5');

                // Merge Month Year across all date columns
                $lastColIndex = 5 + $this->daysInMonth;
                $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColIndex);
                $sheet->mergeCells('F4:' . $lastColLetter . '4');

                // Style for Table Header (Row 4 and 5)
                $sheet->getStyle('A4:' . $highestColumn . '5')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['argb' => 'FFB8CCE4']
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                    ]
                ]);

                // Style for Content Borders
                if ($highestRow > 5) {
                    $sheet->getStyle('A6:' . $highestColumn . $highestRow)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            ],
                        ],
                    ]);
                }

                // Get Holidays from API
                $hariLibur = Cache::remember('holidays_calendar', 86400, function () {
                    try {
                        $response = Http::get('https://raw.githubusercontent.com/guangrei/APIHariLibur_V2/main/calendar.json');
                        if ($response->successful()) {
                            return $response->json();
                        }
                    } catch (\Exception $e) {
                    }
                    return [];
                });

                for ($day = 1; $day <= $this->daysInMonth; $day++) {
                    // Offset by 5 columns (No, NPK, Nama, Dept, TMK) -> so Date 1 is Col 6 (F)
                    $colIndex = 5 + $day;
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);

                    $date = Carbon::create($this->year, $this->month, $day);
                    $dateString = $date->format('Y-m-d');

                    // Sabtu dan Minggu libur
                    $isWeekend = $date->isSunday() || $date->isSaturday();

                    $isHoliday = isset($hariLibur[$dateString]) && ($hariLibur[$dateString]['holiday'] ?? false) === true;

                    // Block red if weekend or holiday
                    if ($isWeekend || $isHoliday) {
                        $sheet->getStyle($colLetter . '5:' . $colLetter . $highestRow)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'color' => ['argb' => 'FFFF0000']
                            ]
                        ]);
                    }

                    // Set width for date columns
                    $sheet->getColumnDimension($colLetter)->setWidth(5);
                }

                // Auto size columns A, B, C, D, E
                // Freeze row 1-5
                $sheet->freezePane('A6');
                $sheet->getColumnDimension('A')->setWidth(5);
                $sheet->getColumnDimension('B')->setAutoSize(true);
                $sheet->getColumnDimension('C')->setAutoSize(true);
                $sheet->getColumnDimension('D')->setAutoSize(true);
                $sheet->getColumnDimension('E')->setAutoSize(true);

                $pageSetup = $sheet->getPageSetup();

                $pageSetup->setOrientation(
                    \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE
                );

                $pageSetup->setPaperSize(
                    \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4
                );

                // Semua kolom muat dalam 1 halaman
                $pageSetup->setFitToWidth(1);
                $pageSetup->setFitToHeight(0);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 5);
                $sheet->getPageMargins()
                    ->setTop(0.3)
                    ->setRight(0.2)
                    ->setLeft(0.2)
                    ->setBottom(0.3);
            },
        ];
    }
}
