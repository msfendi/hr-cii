<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use Illuminate\Support\Facades\DB;

class EmployeeQcAssignmentSheet implements WithTitle, WithHeadings, WithEvents
{
    public function title(): string
    {
        return 'employee_qc_assignments';
    }

    public function headings(): array
    {
        return [
            'npk',
            'name',
            'line_number',
            'role',
            'date',
            'work_hours',
            'buyer',
            'third_party',
        ];
    }

    public static function afterSheet(AfterSheet $event)
    {
        $sheet = $event->sheet->getDelegate();
        $spreadsheet = $sheet->getParent();

        // bold header
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        // auto width
        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->getStyle('E:E')
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);

        // contoh data
        $sheet->setCellValue('A2', 'C-00827');
        $sheet->setCellValue('B2', 'Dimas Galang Ramadhan');
        $sheet->setCellValue('C2', '1');
        $date = Date::stringToExcel('2026-01-12');
        $sheet->setCellValue('D2', 'operator');
        $sheet->setCellValue('E2', $date);
        $sheet->setCellValue('F2', '8');
        $sheet->setCellValue('G2', 'MUJI');

        $sheet->setCellValue('A3', 'C-00827');
        $sheet->setCellValue('B3', 'Dimas Galang Ramadhan');
        $sheet->setCellValue('C3', '1');
        $date = Date::stringToExcel('2026-01-13');
        $sheet->setCellValue('D3', 'operator');
        $sheet->setCellValue('E3', $date);
        $sheet->setCellValue('F3', '10');
        $sheet->setCellValue('G3', 'MUJI');

        $sheet->setCellValue('A4', 'C-00827');
        $sheet->setCellValue('B4', 'Dimas Galang Ramadhan');
        $sheet->setCellValue('C4', '1');
        $date = Date::stringToExcel('2026-01-14');
        $sheet->setCellValue('D4', 'operator');
        $sheet->setCellValue('E4', $date);
        $sheet->setCellValue('F4', '8');
        $sheet->setCellValue('G4', 'MUJI');

        $sheet->setCellValue('A5', 'C-00827');
        $sheet->setCellValue('B5', 'Dimas Galang Ramadhan');
        $sheet->setCellValue('C5', '1');
        $date = Date::stringToExcel('2026-01-15');
        $sheet->setCellValue('D5', 'operator');
        $sheet->setCellValue('E5', $date);
        $sheet->setCellValue('F5', '4');
        $sheet->setCellValue('G5', 'MUJI');

        $sheet->setCellValue('A6', 'C-00827');
        $sheet->setCellValue('B6', 'Dimas Galang Ramadhan');
        $sheet->setCellValue('C6', '1');
        $date = Date::stringToExcel('2026-01-16');
        $sheet->setCellValue('D6', 'operator');
        $sheet->setCellValue('E6', $date);
        $sheet->setCellValue('F6', '8');
        $sheet->setCellValue('G6', 'MUJI');

        $sheet->setCellValue('A7', 'C-00825');
        $sheet->setCellValue('B7', 'Christantie Imanuela');
        $sheet->setCellValue('C7', '1');
        $date = Date::stringToExcel('2026-01-14');
        $sheet->setCellValue('D7', 'operator');
        $sheet->setCellValue('E7', $date);
        $sheet->setCellValue('F7', '10');
        $sheet->setCellValue('G7', 'UNIQLO');

        // contoh khusus role 'qa': line_number (kolom C) tidak dipakai untuk
        // qa, cukup kosongkan. Kolom G (buyer) tetap diisi seperti role lain.
        $sheet->setCellValue('A8', 'C-00830');
        $sheet->setCellValue('B8', 'Contoh QA');
        $sheet->setCellValue('C8', '');
        $date = Date::stringToExcel('2026-01-12');
        $sheet->setCellValue('D8', 'qa');
        $sheet->setCellValue('E8', $date);
        $sheet->setCellValue('F8', '8');
        $sheet->setCellValue('G8', 'MUJI');

        // contoh third_party (kolom H, opsional):
        // - qa dengan third_party  : line diambil by buyer + third_party
        // - qa_leader memegang lebih dari 1 third_party pada tanggal yang
        //   sama => 1 baris per third_party, total insentif dibagi jumlah
        //   third_party yang dipegang (PQC + TENTAC => dibagi 2).
        $sheet->setCellValue('A9', 'C-00831');
        $sheet->setCellValue('B9', 'Contoh QA Third Party');
        $sheet->setCellValue('C9', '');
        $date = Date::stringToExcel('2026-01-12');
        $sheet->setCellValue('D9', 'qa');
        $sheet->setCellValue('E9', $date);
        $sheet->setCellValue('F9', '8');
        $sheet->setCellValue('G9', 'MUJI');
        $sheet->setCellValue('H9', 'PQC');

        $sheet->setCellValue('A10', 'C-00832');
        $sheet->setCellValue('B10', 'Contoh QA Leader');
        $sheet->setCellValue('C10', '');
        $date = Date::stringToExcel('2026-01-12');
        $sheet->setCellValue('D10', 'qa_leader');
        $sheet->setCellValue('E10', $date);
        $sheet->setCellValue('F10', '8');
        $sheet->setCellValue('G10', 'MUJI');
        $sheet->setCellValue('H10', 'PQC');

        $sheet->setCellValue('A11', 'C-00832');
        $sheet->setCellValue('B11', 'Contoh QA Leader');
        $sheet->setCellValue('C11', '');
        $date = Date::stringToExcel('2026-01-12');
        $sheet->setCellValue('D11', 'qa_leader');
        $sheet->setCellValue('E11', $date);
        $sheet->setCellValue('F11', '8');
        $sheet->setCellValue('G11', 'MUJI');
        $sheet->setCellValue('H11', 'TENTAC');

        // NOTE: sesuaikan value 'qc' pada where('dept', ...) ini
        // dengan nama dept yang benar-benar dipakai untuk role QC
        // di tabel insentif_role_formulas.
        $roles = DB::table('insentif_role_formulas')
            ->where('dept', 'qc')
            ->orderBy('role')
            ->pluck('role')
            ->unique()
            ->values()
            ->toArray();

        // Hidden Sheet
        $hiddenSheet = $spreadsheet->createSheet();
        $hiddenSheet->setTitle('role_master');

        foreach ($roles as $index => $role) {
            $hiddenSheet->setCellValue(
                'A' . ($index + 1),
                $role
            );
        }

        $hiddenSheet->setSheetState(
            \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN
        );

        $lastRow = count($roles);

        // Apply dropdown to D2:D5000
        for ($row = 2; $row <= 5000; $row++) {
            $validation = $sheet->getCell('D' . $row)->getDataValidation();

            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Input Salah');
            $validation->setError('Pilih role dari daftar.');
            $validation->setFormula1("=role_master!\$A\$1:\$A\${$lastRow}");
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => [self::class, 'afterSheet'],
        ];
    }
}
