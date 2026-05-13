<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class OvertimeCalendarTemplateExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    protected $month; // format: Y-m  e.g. "2026-03"
    protected $type;

    public function __construct(string $month, string $type = 'all')
    {
        $this->month = $month;
        $this->type  = $type;
    }

    public function title(): string
    {
        return 'Template ' . $this->month;
    }

    public function collection()
    {
        $targetMonth = Carbon::parse($this->month);

        $query = DB::connection('cii')->table('BIODATA')
            ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->leftJoin('PKWT', 'BIODATA.NPK', '=', 'PKWT.NPK')
            ->select('BIODATA.NPK', 'BIODATA.NAMA_KARYAWAN', 'DEPT.DEPARTEMENT', 'DEPT.IS_SEWING', 'BIODATA.IS_STAFF', 'BIODATA.ID_DEPT', 'PKWT.TMK')
            ->where('BIODATA.STATUS', 'A');

        switch ($this->type) {
            case 'sewing':
                $query->where('DEPT.IS_SEWING', 0)->where('BIODATA.IS_STAFF', 0);
                break;
            case 'non_sewing':
                $query->where('DEPT.IS_SEWING', 1)->where('BIODATA.IS_STAFF', 0);
                break;
            case 'staff':
                $query->where('DEPT.IS_SEWING', 1)->where('BIODATA.IS_STAFF', 1);
                break;
        }

        return $query
            ->orderBy('DEPT.IS_SEWING', 'asc')
            ->orderBy('BIODATA.ID_DEPT', 'asc')
            ->orderBy('BIODATA.NPK', 'asc')
            ->get();
    }

    public function headings(): array
    {
        $headers = ['NPK', 'NAMA', 'TMK', 'BAGIAN'];
        $daysInMonth = Carbon::parse($this->month)->daysInMonth;
        for ($i = 1; $i <= $daysInMonth; $i++) {
            $headers[] = $i;
        }
        return $headers;
    }

    public function map($row): array
    {
        $daysInMonth = Carbon::parse($this->month)->daysInMonth;
        $mapped = [
            $row->NPK ?? '',
            $row->NAMA_KARYAWAN ?? '',
            $row->TMK ? Carbon::parse($row->TMK)->format('Y-m-d') : '',
            $row->DEPARTEMENT ?? '',
        ];

        for ($i = 1; $i <= $daysInMonth; $i++) {
            $mapped[] = ''; // Empty cell per day
        }

        return $mapped;
    }

    public function styles(Worksheet $sheet)
    {
        $startOfMonth = Carbon::parse($this->month)->startOfMonth();
        $daysInMonth  = $startOfMonth->daysInMonth;
        $yearMonth    = $startOfMonth->format('Y-m');

        $totalCols = 4 + $daysInMonth; // NPK, NAMA, TMK, BAGIAN + dates
        $lastCol   = Coordinate::stringFromColumnIndex($totalCols);
        $totalRows = $sheet->getHighestRow();

        // 1. Global borders & headers alignment
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // 2. Data area styling
        if ($totalRows >= 2) {
            $sheet->getStyle("A2:{$lastCol}{$totalRows}")->applyFromArray([
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            // Left-align text columns
            $sheet->getStyle("A2:C{$totalRows}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        // 3. Highlight Weekends & Holidays
        $holidays = Cache::remember('holidays_calendar', 86400, function () {
            try {
                $json = file_get_contents(storage_path('app/calendar.json'));
                return json_decode($json, true) ?? [];
            } catch (\Exception $e) {
                try {
                    $response = Http::get('https://raw.githubusercontent.com/guangrei/APIHariLibur_V2/main/calendar.json');
                    return $response->json() ?? [];
                } catch (\Exception $e2) {
                    return [];
                }
            }
        });

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = $yearMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
            $dateObj = Carbon::parse($dateStr);
            $isWeekend = $dateObj->isWeekend();

            $isHoliday = false;
            if (isset($holidays[$dateStr]) && ($holidays[$dateStr]['holiday'] ?? false) === true) {
                $summary = $holidays[$dateStr]['summary'] ?? [];
                if (!str_contains(implode(' ', (array) $summary), 'Cuti')) {
                    $isHoliday = true;
                }
            }

            if ($isWeekend || $isHoliday) {
                $colLetter = Coordinate::stringFromColumnIndex(4 + $d);

                // Header (Red background, White text)
                $sheet->getStyle("{$colLetter}1")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E74A3B']],
                    'font' => ['color' => ['rgb' => 'FFFFFF']]
                ]);

                // Body (Light red background)
                if ($totalRows >= 2) {
                    $sheet->getStyle("{$colLetter}2:{$colLetter}{$totalRows}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E74A3B']]
                    ]);
                }
            }
        }

        // Freeze NPK, NAMA, BAGIAN columns and header row
        $sheet->freezePane('D2');

        return [];
    }
}
