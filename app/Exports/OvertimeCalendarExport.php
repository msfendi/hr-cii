<?php

namespace App\Exports;

use App\Models\Overtime;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class OvertimeCalendarExport implements FromArray, WithStyles, ShouldAutoSize, WithTitle
{
    protected $month;
    protected $type;

    public function __construct(string $month, string $type = 'all')
    {
        $this->month = $month;
        $this->type = $type;
    }

    public function title(): string
    {
        return 'Overtime ' . $this->month;
    }

    public function array(): array
    {
        $data = $this->buildCalendarData();

        $rows = [];

        // Row 1: Week headers (merged later in styles)
        $headerRow1 = ['NPK', 'NAMA', 'BAGIAN'];
        foreach ($data['weeks'] as $week) {
            $headerRow1[] = $week['label'];
            // Fill remaining columns of this week with empty string (will be merged)
            for ($i = 1; $i < count($week['days']); $i++) {
                $headerRow1[] = '';
            }
        }
        $headerRow1[] = 'Kehadiran';
        $headerRow1[] = '1';
        $headerRow1[] = '2';
        $headerRow1[] = 'Total';
        $headerRow1[] = 'Lembur Khusus';
        $headerRow1[] = 'CT';
        $headerRow1[] = 'MA';
        $rows[] = $headerRow1;

        // Row 2: Date numbers
        $headerRow2 = ['', '', ''];
        foreach ($data['weeks'] as $week) {
            foreach ($week['days'] as $day) {
                $headerRow2[] = str_pad($day, 2, '0', STR_PAD_LEFT);
            }
        }
        $headerRow2[] = '';
        $headerRow2[] = '';
        $headerRow2[] = '';
        $headerRow2[] = '';
        $headerRow2[] = '';
        $headerRow2[] = '';
        $headerRow2[] = '';
        $rows[] = $headerRow2;

        // Data rows
        $prefix = $this->month;
        foreach ($data['pivotData'] as $employee) {
            $dataRow = [
                $employee['NPK'] ?? '',
                $employee['NAMA_KARYAWAN'] ?? '',
                $employee['BAGIAN'] ?? '',
            ];

            foreach ($data['weeks'] as $week) {
                foreach ($week['days'] as $day) {
                    $key = $prefix . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                    $dataRow[] = $employee[$key] ?? '';
                }
            }

            $dataRow[] = $employee['total_kehadiran'] ?? '0';
            $dataRow[] = $employee['1'] ?? '0';
            $dataRow[] = $employee['2'] ?? '0';
            $dataRow[] = $employee['total'] ?? '0';
            $dataRow[] = $employee['lembur_khusus'] < 8 ? '0' : $employee['lembur_khusus'];
            $dataRow[] = $employee['CT'] ?? '0';
            $dataRow[] = $employee['MA'] ?? '0';

            $rows[] = $dataRow;
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $data = $this->buildCalendarData();
        $totalDataCols = 3; // NPK, NAMA, BAGIAN
        foreach ($data['weeks'] as $week) {
            $totalDataCols += count($week['days']);
        }
        $totalDataCols += 7; // summary columns
        $lastCol = $this->colLetter($totalDataCols);
        $lastRow = count($data['pivotData']) + 2; // 2 header rows + data

        // Merge week header cells in row 1
        $colIdx = 4; // Column D (1-indexed, A=1)
        foreach ($data['weeks'] as $week) {
            $dayCount = count($week['days']);
            if ($dayCount > 1) {
                $startCol = $this->colLetter($colIdx);
                $endCol = $this->colLetter($colIdx + $dayCount - 1);
                $sheet->mergeCells("{$startCol}1:{$endCol}1");
            }
            $colIdx += $dayCount;
        }


        // Merge NPK, NAMA, BAGIAN across rows 1-2
        $sheet->mergeCells('A1:A2');
        $sheet->mergeCells('B1:B2');
        $sheet->mergeCells('C1:C2');

        // Merge summary sub-headers across rows 1-2
        for ($i = 0; $i < 7; $i++) {
            $col = $this->colLetter($colIdx + $i);
            $sheet->mergeCells("{$col}1:{$col}2");
        }

        // Style: Header rows
        $sheet->getStyle("A1:{$lastCol}2")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '000000'], 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Week headers background (blue)
        $colIdx = 4;
        foreach ($data['weeks'] as $week) {
            $dayCount = count($week['days']);
            $startCol = $this->colLetter($colIdx);
            $endCol = $this->colLetter($colIdx + $dayCount - 1);
            // $sheet->getStyle("{$startCol}1:{$endCol}1")->getFill()
            //     ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4E73DF');
            $sheet->getStyle("{$startCol}1:{$endCol}1")->getFill()
                ->setFillType(Fill::FILL_NONE);
            $colIdx += $dayCount;
        }

        // Info header background (gray) for NPK, NAMA, BAGIAN
        // $sheet->getStyle('A1:C2')->getFill()
        //     ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('858796');
        $sheet->getStyle('A1:C2')->getFill()
            ->setFillType(Fill::FILL_NONE);

        // Summary header background (green)
        $summaryColStart = $this->colLetter($colIdx);
        $summaryColEnd = $this->colLetter($colIdx + 6);
        // $sheet->getStyle("{$summaryColStart}1:{$summaryColEnd}2")->getFill()
        //     ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1CC88A');
        $sheet->getStyle("{$summaryColStart}1:{$summaryColEnd}2")->getFill()
            ->setFillType(Fill::FILL_NONE);

        // Date row (row 2) background - apply per day
        $colIdx = 4;
        foreach ($data['weeks'] as $week) {
            foreach ($week['days_meta'] as $dm) {
                $col = $this->colLetter($colIdx);
                $isWeekend = ($dm['day_of_week'] == 0 || $dm['day_of_week'] == 6);
                $isHoliday = isset($data['holidays'][$dm['day']]);

                if ($isWeekend || $isHoliday) {
                    // Red for weekend/holiday header
                    $sheet->getStyle("{$col}2")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E74A3B');
                    // Light red for body column
                    if ($lastRow >= 3) {
                        $sheet->getStyle("{$col}3:{$col}{$lastRow}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCE4E4');
                    }
                } else {
                    // Default blue for date header
                    // $sheet->getStyle("{$col}2")->getFill()
                    //     ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4E73DF');
                    $sheet->getStyle("{$col}2")->getFill()
                        ->setFillType(Fill::FILL_NONE);
                }
                $colIdx++;
            }
        }

        // Data rows borders
        if ($lastRow >= 3) {
            $sheet->getStyle("A3:{$lastCol}{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            // Left-align NPK, NAMA, BAGIAN
            $sheet->getStyle("A3:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        return [];
    }

    /**
     * Build the calendar data — reuses the same logic as OvertimeController::calendarDisplay
     */
    private function buildCalendarData(): array
    {
        // Cache the result within the request so array() and styles() don't re-calculate
        static $cached = null;
        if ($cached !== null) return $cached;

        $tanggalAwal  = Carbon::parse($this->month)->startOfMonth();
        $tanggalAkhir = Carbon::parse($this->month)->endOfMonth();
        $jumlahHari   = $tanggalAkhir->day;

        // Group days into weeks
        $grupMinggu = [];
        for ($hari = 1; $hari <= $jumlahHari; $hari++) {
            $tanggal    = Carbon::create($tanggalAwal->year, $tanggalAwal->month, $hari);
            $awalMinggu = $tanggal->copy()->startOfWeek(Carbon::SUNDAY)->format('Y-m-d');
            $grupMinggu[$awalMinggu][] = $hari;
        }
        ksort($grupMinggu);

        $rangeMinggu  = [];
        $metaMinggu   = [];
        $urutanMinggu = 0;
        foreach ($grupMinggu as $awalMinggu => $daftarHari) {
            $urutanMinggu++;
            $rangeMinggu[] = ['days' => $daftarHari];

            $daysWithMeta = [];
            foreach ($daftarHari as $h) {
                $tglObj = Carbon::create($tanggalAwal->year, $tanggalAwal->month, $h);
                $daysWithMeta[] = [
                    'day'         => $h,
                    'day_of_week' => $tglObj->dayOfWeek,
                ];
            }

            $metaMinggu[] = [
                'label'     => 'Week ' . $urutanMinggu,
                'key'       => 'week_' . $urutanMinggu . '_sum',
                'days'      => $daftarHari,
                'days_meta' => $daysWithMeta,
            ];
        }

        // Get overtime data
        $queryLembur = Overtime::leftJoin(
            DB::raw('(SELECT NPK, ID_DEPT FROM BIODATA UNION SELECT NPK, ID_DEPT FROM BIODATA_KELUAR) AS BIO'),
            'overtimes.NPK',
            '=',
            'BIO.NPK'
        )
            ->leftJoin('DEPT', 'BIO.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->select('overtimes.NPK', 'overtimes.NAMA_KARYAWAN', 'overtimes.OVERTIME_DATE', 'overtimes.JUMLAH_JAM_LEMBUR', 'overtimes.DAY', 'overtimes.DEPT_GROUP', 'DEPT.DEPARTEMENT')
            ->whereBetween('OVERTIME_DATE', [$tanggalAwal, $tanggalAkhir]);

        if ($this->type !== 'all') {
            $queryLembur->where('DEPT_GROUP', $this->type);
        }

        $dataLembur = $queryLembur->orderBy('OVERTIME_DATE')->get();

        // Get holidays
        $hariLibur = Cache::remember('holidays_calendar', 86400, function () {
            try {
                $response = Http::get('https://raw.githubusercontent.com/guangrei/APIHariLibur_V2/main/calendar.json');
                return $response->json();
            } catch (\Exception $e) {
                return [];
            }
        });

        // Pivot data
        $hasilPivot = $dataLembur->groupBy('NPK')->map(function ($grupKaryawan) use ($hariLibur, $rangeMinggu, $tanggalAwal) {
            $employee = $grupKaryawan->first();
            $row = [
                'NPK'           => $employee->NPK,
                'NAMA_KARYAWAN' => $employee->NAMA_KARYAWAN,
                'BAGIAN'        => $employee->DEPARTEMENT,
            ];

            foreach ($grupKaryawan as $record) {
                $tgl = Carbon::parse($record->OVERTIME_DATE)->format('Y-m-d');
                $row[$tgl] = $record->JUMLAH_JAM_LEMBUR;
            }

            $lemburResmi = $grupKaryawan->filter(function ($record) use ($hariLibur) {
                $tanggal   = Carbon::parse($record->OVERTIME_DATE);
                $hariKerja = !$tanggal->isWeekend();
                $tglString = $tanggal->format('Y-m-d');
                $holidayData = $hariLibur[$tglString] ?? null;
                $isHoliday = ($holidayData['holiday'] ?? false) === true
                    && !str_contains(implode(' ', (array)($holidayData['summary'] ?? [])), 'Cuti');
                $jamLembur = $record->JUMLAH_JAM_LEMBUR;
                return $hariKerja && !$isHoliday && is_numeric($jamLembur) && $jamLembur >= 1 && $jamLembur <= 8;
            });

            $jumlahHariLembur = $lemburResmi->count();
            $jamLebihDariSatu = $lemburResmi->filter(fn($r) => $r->JUMLAH_JAM_LEMBUR > 1);
            $jamEkstra = $jamLebihDariSatu->sum('JUMLAH_JAM_LEMBUR') - $jamLebihDariSatu->count();

            $totalKehadiran = $grupKaryawan->filter(function ($record) use ($hariLibur) {
                $tanggal   = Carbon::parse($record->OVERTIME_DATE);
                $hariKerja = !$tanggal->isWeekend();
                $tglString = $tanggal->format('Y-m-d');
                $holidayData = $hariLibur[$tglString] ?? null;
                $isHoliday = ($holidayData['holiday'] ?? false) === true
                    && !str_contains(implode(' ', (array)($holidayData['summary'] ?? [])), 'Cuti');
                $nilai = $record->JUMLAH_JAM_LEMBUR;
                return $hariKerja && !$isHoliday && (is_numeric($nilai) || is_null($nilai) || $nilai === '');
            })->count();

            $lemburKhusus = $grupKaryawan->filter(function ($record) use ($hariLibur) {
                $nilai = $record->JUMLAH_JAM_LEMBUR;
                if (!is_numeric($nilai) || $nilai <= 4) return false;
                $tanggal   = Carbon::parse($record->OVERTIME_DATE);
                $tglString = $tanggal->format('Y-m-d');
                $isHoliday = isset($hariLibur[$tglString]) && $hariLibur[$tglString]['holiday'] === true;
                return $tanggal->isWeekend() || $isHoliday;
            })->sum('JUMLAH_JAM_LEMBUR');

            $lemburKarakter = $grupKaryawan->filter(fn($r) => !is_numeric($r->JUMLAH_JAM_LEMBUR))->groupBy('JUMLAH_JAM_LEMBUR');
            foreach ($lemburKarakter as $kode => $daftarRecord) {
                $row[$kode] = $daftarRecord->count();
            }

            $prefixBulan = $tanggalAwal->format('Y-m');
            foreach ($rangeMinggu as $idx => $minggu) {
                $totalMinggu = 0;
                foreach ($minggu['days'] as $hari) {
                    $keyDate = $prefixBulan . '-' . str_pad($hari, 2, '0', STR_PAD_LEFT);
                    $tglObj = Carbon::parse($keyDate);
                    $hData = $hariLibur[$keyDate] ?? null;
                    $isH = ($hData['holiday'] ?? false) === true
                        && !str_contains(implode(' ', (array)($hData['summary'] ?? [])), 'Cuti');
                    if (!$tglObj->isWeekend() && !$isH) {
                        if (isset($row[$keyDate]) && is_numeric($row[$keyDate])) {
                            $totalMinggu += (float) $row[$keyDate];
                        }
                    }
                }
                $row['week_' . ($idx + 1) . '_sum'] = $totalMinggu;
            }

            $row['total_kehadiran'] = $totalKehadiran;
            $row['1']               = $jumlahHariLembur;
            $row['2']               = $jamEkstra;
            $row['total']           = $jumlahHariLembur + $jamEkstra;
            $row['lembur_khusus']   = $lemburKhusus;

            return $row;
        })->sortBy('NPK')->sortBy('BAGIAN')->values()->toArray();

        // Holidays for highlighting
        $holidaysThisMonth = [];
        for ($d = 1; $d <= $jumlahHari; $d++) {
            $keyDate = $tanggalAwal->format('Y-m') . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
            if (isset($hariLibur[$keyDate]) && ($hariLibur[$keyDate]['holiday'] ?? false) === true) {
                $summary = $hariLibur[$keyDate]['summary'] ?? [];
                if (!str_contains(implode(' ', (array) $summary), 'Cuti')) {
                    $holidaysThisMonth[$d] = $summary;
                }
            }
        }

        $cached = [
            'pivotData' => $hasilPivot,
            'weeks'     => $metaMinggu,
            'holidays'  => $holidaysThisMonth,
        ];

        return $cached;
    }

    /**
     * Convert 1-based column index to Excel letter (1=A, 27=AA, etc.)
     */
    private function colLetter(int $col): string
    {
        $letter = '';
        while ($col > 0) {
            $mod = ($col - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $col = (int)(($col - $mod) / 26);
        }
        return $letter;
    }
}
