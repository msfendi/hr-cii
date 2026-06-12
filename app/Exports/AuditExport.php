<?php

namespace App\Exports;

use App\Models\Audit;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AuditExport implements WithHeadings, WithStrictNullComparison, FromView, ShouldAutoSize, WithStyles
{
    /**
     * @return \Illuminate\Support\Collection
     */

    use Exportable;
    protected $fromdate;
    protected $todate;
    protected $department;
    protected $days;

    function __construct($fromdate, $todate, $department, $days)
    {
        $this->fromdate = $fromdate;
        $this->todate = $todate;
        $this->department = $department;
        $this->days = $days;
    }

    public function view(): View
    {
        $employeeGroupChutex = DB::connection('cii')->table('AUDIT')->select('NPK', 'KODE_BAGIAN', 'SUBDIVISI')->distinct('NPK', 'KODE_BAGIAN', 'SUBDIVISI')->whereIn('KODE_BAGIAN', $this->department);
        $employeeGroup = $employeeGroupChutex->orderBy('KODE_BAGIAN', 'ASC')->orderBy('NPK', 'ASC')->get();

        $employeesChutex = DB::connection('cii')->table('AUDIT')->select('NPK', 'NAMA_KARYAWAN', 'KODE_BAGIAN', 'SUBDIVISI', 'TANGGAL', 'JAM_PAGI', 'JAM_SIANG', 'JAM_MALAM', 'STATUS AS KETERANGAN')->whereIn('KODE_BAGIAN', $this->department)->whereBetween('TANGGAL', [$this->fromdate, $this->todate]);
        $employees = $employeesChutex->orderBy('KODE_BAGIAN', 'ASC')->orderBy('NPK', 'ASC')->orderBy('TANGGAL', 'ASC')->get();

        $days = $this->days;
        return view('template.report-final-excel', [
            'employees' => $employees,
            'employeeGroup' => $employeeGroup,
            'days' => $days,
        ]);
    }

    public function headings(): array
    {
        $headings = [
            'Dept',
            'NPK',
            'Nama Karyawan',
        ];

        $start = Carbon::parse($this->fromdate);
        $end = Carbon::parse($this->todate);

        for ($date = $start; $date->lte($end); $date->addDay()) {
            $headings[] = $date->format('d');
        }

        return $headings;
    }

    public function styles(Worksheet $sheet)
    {
        // Get the dynamic highest row and column
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $cellRange = 'A1:' . $highestColumn . $highestRow;

        // Apply borders and alignment to the entire used range
        $sheet->getStyle($cellRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ]
        ]);

        // Make header row bold
        $sheet->getStyle('A1:' . $highestColumn . '1')->getFont()->setBold(true);

        // Align 'Nama Karyawan' column to left
        $sheet->getStyle('C1:C' . $highestRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
    }
}
