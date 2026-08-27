<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class GuestMasterSheetExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles, WithMapping
{
    public function collection()
    {
        return DB::table('guest_masters')
            ->orderBy('name')
            ->get();
    }

    public function title(): string
    {
        return 'Guest Master';
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Gender',
            'Place',
            'Date Of Birth',
            'Age',
            'Nationality',
            'Passport No',
            'Issue Date',
            'Must Used Before',
            'Arrival Date',
            'Visa Expiry',
            'Status',
            'Remark',
            'Created At',
        ];
    }

    public function map($row): array
    {
        $age = '-';

        if (!empty($row->date_of_birth)) {
            $dob = Carbon::parse($row->date_of_birth);
            $today = Carbon::today();
            $diff = $dob->diff($today);

            $age = $diff->y . ' Year(s) '
                . $diff->m . ' Month(s) '
                . $diff->d . ' Day(s)';
        }

        return [
            $row->id,
            $row->name,
            $row->gender,
            $row->place,
            $row->date_of_birth,
            $age,
            $row->nationality,
            $row->passport_no,
            $row->issue_date,
            $row->must_used_date,
            $row->arrival_date,
            $row->visa_expiry,
            $row->status,
            $row->remark,
            $row->created_at,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();

        /*
        |--------------------------------------------------------------------------
        | HEADER  (A..O = 15 columns)
        |--------------------------------------------------------------------------
        */
        $sheet->getStyle('A1:O1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => 'D9E1F2',
                ],
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | DATE FORMAT
        | E = Date Of Birth, I:L = Issue/Must Used/Arrival/Visa Expiry
        |--------------------------------------------------------------------------
        */
        $sheet->getStyle("E2:E{$highestRow}")
            ->getNumberFormat()
            ->setFormatCode('yyyy-mm-dd');

        $sheet->getStyle("I2:L{$highestRow}")
            ->getNumberFormat()
            ->setFormatCode('yyyy-mm-dd');

        $sheet->getStyle("O2:O{$highestRow}")
            ->getNumberFormat()
            ->setFormatCode('yyyy-mm-dd hh:mm:ss');

        /*
        |--------------------------------------------------------------------------
        | ALIGNMENT
        |--------------------------------------------------------------------------
        */
        $sheet->getStyle("A2:A{$highestRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("C2:C{$highestRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("E2:F{$highestRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("I2:M{$highestRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("O2:O{$highestRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        /*
        |--------------------------------------------------------------------------
        | BORDER TABLE
        |--------------------------------------------------------------------------
        */
        $sheet->getStyle("A1:O{$highestRow}")
            ->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ]);

        $sheet->freezePane('A2');

        return [];
    }
}