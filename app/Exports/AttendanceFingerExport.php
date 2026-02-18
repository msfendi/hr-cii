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

class AttendanceFingerExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
{
    protected $date;

    public function __construct($date)
    {
        $this->date = $date;
    }

    public function collection()
    {
        // 1. Get Biodata (Main Table)
        // Join with DEPT to get department name and for sorting
        $biodatas = DB::connection('cii')->table('BIODATA')
            ->select('BIODATA.*', 'DEPT.DEPARTEMENT')
            ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->orderBy('DEPT.IS_SEWING', 'asc')
            ->orderBy('DEPT.DEPARTEMENT', 'asc')
            ->orderBy('BIODATA.NPK', 'asc')
            ->get();

        // 2. Get att_log from audit
        $logs = DB::connection('audit')->table('att_log')
            ->whereDate('scan_date', $this->date)
            ->get();

        // Group logs by PIN for easy lookup
        $groupedLogs = $logs->groupBy('pin');

        $data = [];
        $no = 1;

        foreach ($biodatas as $bio) {
            $pin = $bio->BARCODE;
            $pinLogs = $groupedLogs->get($pin);

            $pagi = null;
            $siang = null;
            $malam = null;

            if ($pinLogs && $pinLogs->isNotEmpty()) {
                // Derive Times
                // Sort by scan_date
                $sortedLogs = $pinLogs->sortBy('scan_date')->values();

                $times = $sortedLogs->map(function ($log) {
                    return Carbon::parse($log->scan_date);
                });

                if ($times->count() > 0) {
                    $pagi = $times->first()->format('H:i');
                }

                if ($times->count() >= 3) {
                    // If 3 or more scans, take the second one as Siang (approximate)
                    $siang = $times[1]->format('H:i');
                }

                if ($times->count() > 1) {
                    $siang = $times->last()->format('H:i');
                }

                // If only 1 scan, check time to see where it belongs.
                if ($times->count() == 1) {
                    $hour = $times->first()->hour;
                    if ($hour >= 18) {
                        // It's a night scan
                        $malam = $pagi; // Move from Pagi to Malam
                        $pagi = null;
                    } elseif ($hour >= 12) {
                        // It's an afternoon scan
                        $siang = $pagi; // Move from Pagi to Siang
                        $pagi = null;
                    }
                    // Else: It's morning, keep as Pagi
                } elseif ($times->count() == 2) {
                    // If 2 scans, check if the first scan is actually "Siang" (Shift 2 / Afternoon In)
                    $firstHour = $times->first()->hour;
                    if ($firstHour >= 12) {
                        $pagi = null;
                        $siang = $times->first()->format('H:i');
                        $malam = $times->last()->format('H:i');
                    }
                }

                // If Pagi == Malam (1 scan), clear Malam.
                if ($pagi && $malam && $pagi == $malam) {
                    $malam = null;
                }
            }

            $data[] = [
                'no' => $no++,
                'tanggal' => Carbon::parse($this->date)->format('d/m/Y'),
                'nama' => $bio->NAMA_KARYAWAN,
                'npk' => $bio->NPK,
                'nama_dept' => $bio->DEPARTEMENT,
                'dept' => $bio->SECTION,
                'jabatan' => $bio->BAG,
                'pagi' => $pagi,
                'siang' => $siang,
                'malam' => $malam,
                'status' => $bio->STATUS == 'A' ? 'AKTIF' : $bio->STATUS,
            ];
        }

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
            'Jam Pagi',
            'Jam Siang',
            'Jam Malam',
            'Status'
        ];
    }

    public function map($row): array
    {
        return [
            $row['no'],
            $row['tanggal'],
            $row['nama'],
            $row['npk'],
            $row['nama_dept'],
            $row['dept'],
            $row['jabatan'],
            $row['pagi'],
            $row['siang'],
            $row['malam'],
            $row['status'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text and center alignment
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
                $highestColumn = $sheet->getHighestColumn(); // Should be K (11)

                // 1. Borders for the entire table
                $range = 'A1:' . $highestColumn . $highestRow;
                $sheet->getStyle($range)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ]);

                // 2. Conditional formatting for Time Columns (H, I, J -> 8, 9, 10 in 1-based index? No, A=1... H=8)
                // H = Jam Pagi, I = Jam Siang, J = Jam Malam

                for ($row = 2; $row <= $highestRow; $row++) {
                    // Check H (Pagi)
                    $pagiCell = $sheet->getCell('H' . $row);
                    $pagiVal = $pagiCell->getValue();
                    if (empty($pagiVal)) {
                        $sheet->getStyle('H' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC0CB'); // Pink
                    } elseif ($pagiVal > '08:00') {
                        $sheet->getStyle('H' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00'); // Yellow
                    }

                    // Check I (Siang)
                    $siangCell = $sheet->getCell('I' . $row);
                    $siangVal = $siangCell->getValue();
                    if (empty($siangVal)) {
                        $sheet->getStyle('I' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC0CB'); // Pink
                    } elseif ($siangVal < '17:00') {
                        $sheet->getStyle('I' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00'); // Yellow
                    }

                    // Check J (Malam)
                    $malamCell = $sheet->getCell('J' . $row);
                    $malamVal = $malamCell->getValue();
                    if (empty($malamVal)) {
                        $sheet->getStyle('J' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC0CB'); // Pink
                    } elseif ($malamVal > '21:00') {
                        $sheet->getStyle('J' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00'); // Yellow
                    }
                }
            },
        ];
    }
}
