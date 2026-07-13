<?php

namespace App\Exports\Compensation;

use App\Services\PayrollRoleFilterService;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class CompensationDetailSheet
{
    protected $cutoffDate;

    /**
     * Salah satu konstanta PayrollRoleFilterService::ROLE_* (Payroll_STAFF, dst),
     * atau null untuk tidak difilter sama sekali (dipakai untuk role Payroll_ALL,
     * gabungan staff + non staff).
     */
    protected ?string $role;

    public function __construct($cutoffDate, ?string $role = null)
    {
        $this->cutoffDate = $cutoffDate;
        $this->role = $role;
    }

    public function title(): string
    {
        $slug = $this->role
            ? strtoupper(str_replace('Payroll_', '', $this->role))
            : 'ALL';

        // title sheet Excel max 31 karakter
        return substr('Compensation_' . $slug, 0, 31);
    }

    public function exportToSheet(Spreadsheet $spreadsheet, int $sheetIndex = 0)
    {
        $sheet = $sheetIndex === 0
            ? $spreadsheet->getActiveSheet()
            : $spreadsheet->createSheet($sheetIndex);

        $sheet->setTitle($this->title());

        // ======================
        // HEADER
        // ======================
        $headings = $this->headings();

        $col = 1;
        foreach ($headings as $heading) {
            $sheet->setCellValueByColumnAndRow($col, 1, $heading);
            $col++;
        }

        $lastCol = chr(64 + count($headings));

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F81BD']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // ======================
        // DATA
        // ======================
        $rows = $this->query()->get();

        $rowNum = 2;

        foreach ($rows as $row) {
            $data = $this->map($row);

            $col = 1;
            foreach ($data as $value) {
                $sheet->setCellValueByColumnAndRow($col, $rowNum, $value);
                $col++;
            }

            $rowNum++;
        }

        // ======================
        // BORDER TABLE
        // ======================
        $sheet->getStyle("A1:{$lastCol}" . ($rowNum - 1))
            ->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ]);

        // ======================
        // NUMBER FORMAT (Salary & Amount)
        // ======================
        foreach (['H', 'I'] as $colLetter) {
            $sheet->getStyle("{$colLetter}2:{$colLetter}{$rowNum}")
                ->getNumberFormat()
                ->setFormatCode('"Rp" #,##0;[Red]-"Rp" #,##0');
        }

        // ======================
        // AUTO SIZE COLUMN
        // ======================
        foreach (range('A', $lastCol) as $colLetter) {
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }
    }

    public function query()
    {
        // Union biodata aktif + keluar, sama seperti dipakai di
        // PayrollDetailNonStaffSheet / GenerateCompensation.
        $employeeUnion = DB::table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT', 'IS_STAFF')
            ->unionAll(
                DB::table('BIODATA_KELUAR')
                    ->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT', 'IS_STAFF')
            );

        $query = DB::table('employees_contract as ec')
            ->join('compensation_details as cd', function ($join) {
                $join->on('cd.contract_id', '=', 'ec.id')
                    ->whereDate('cd.cutoff_date', $this->cutoffDate)
                    ->where('cd.is_active', 1);
            })
            ->leftJoinSub($employeeUnion, 'bio', function ($join) {
                $join->on('bio.NPK', '=', 'ec.npk');
            })
            ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'bio.ID_DEPT')
            ->leftJoin('PKWT as p', 'p.NPK', '=', 'ec.npk')
            ->select(
                'ec.npk',
                'bio.NAMA_KARYAWAN as employee_name',
                'd.DEPARTEMENT as departement',
                'p.TMK',
                'ec.month_duration',
                'ec.day_duration',
                'ec.end_date',
                'ec.salary',
                'cd.amount',
                'cd.status',
                'cd.is_active',
                'bio.IS_STAFF',
                'd.IS_SEWING'
            );

        // role null (dipakai untuk kategori Payroll_ALL) -> tidak difilter sama sekali.
        if ($this->role !== null) {
            PayrollRoleFilterService::applyToQuery($query, $this->role, 'bio.IS_STAFF', 'd.IS_SEWING');
        }

        return $query
            ->orderBy('d.DEPARTEMENT')
            ->orderBy('ec.npk');
    }

    public function map($row): array
    {
        return [
            $row->npk,
            $row->employee_name,
            $row->departement,
            $row->TMK,
            $row->month_duration,
            $row->day_duration,
            $row->end_date,
            (float)($row->salary ?? 0),
            (float)($row->amount ?? 0),
            $row->status,
            (int)$row->is_active === 1 ? 'Active' : 'Out',
        ];
    }

    public function headings(): array
    {
        return [
            'NPK',
            'Employee Name',
            'Departement',
            'TMK',
            'Month Duration',
            'Day Duration',
            'End Date',
            'Salary',
            'Amount',
            'Status',
            'Active',
        ];
    }
}
