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

class ForeignGuestSheetExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles, WithMapping
{
    public function collection()
    {
        return DB::table('foreign_guests')
            ->orderBy('guest_name')
            ->get();
    }

    public function title(): string
    {
        return 'Foreign Guest';
    }

    public function headings(): array
    {
        return [
            'ID',
            'Guest Name',
            'Bank Account',
            'Visa Type',
            'Visa Status',
            'Visa Invoice',
            'Rent Invoice',
            'Flight Detail',
            'Flight ETA',
            'ETA',
            'Return Date',
            'Hotel',
            'Hotel Invoice',
            'Status',
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
            $row->guest_name,
            $row->bank_account,
            $row->visa_type,
            $row->visa_status,
            $row->visa_invoice,
            $row->rent_invoice,
            $row->flight_detail,
            $row->flight_eta,
            $row->eta,
            $row->return,
            $row->hotel,
            $row->hotel_invoice,
            $row->status,
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
            $row->remark,
            $row->created_at,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();

        /*
        |--------------------------------------------------------------------------
        | HEADER  (A..Z = 26 columns)
        |--------------------------------------------------------------------------
        */
        $sheet->getStyle('A1:Z1')->applyFromArray([
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
        | DATE / TIME FORMAT
        | I = Flight ETA (time), J = ETA, K = Return Date,
        | Q = Date Of Birth, U:X = Issue/Must Used/Arrival/Visa Expiry
        |--------------------------------------------------------------------------
        */
        $sheet->getStyle("I2:I{$highestRow}")
            ->getNumberFormat()
            ->setFormatCode('hh:mm');

        $sheet->getStyle("J2:K{$highestRow}")
            ->getNumberFormat()
            ->setFormatCode('yyyy-mm-dd');

        $sheet->getStyle("Q2:Q{$highestRow}")
            ->getNumberFormat()
            ->setFormatCode('yyyy-mm-dd');

        $sheet->getStyle("U2:X{$highestRow}")
            ->getNumberFormat()
            ->setFormatCode('yyyy-mm-dd');

        $sheet->getStyle("Z2:Z{$highestRow}")
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

        $sheet->getStyle("F2:K{$highestRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("M2:O{$highestRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("Q2:R{$highestRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("U2:X{$highestRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("Z2:Z{$highestRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        /*
        |--------------------------------------------------------------------------
        | BORDER TABLE
        |--------------------------------------------------------------------------
        */
        $sheet->getStyle("A1:Z{$highestRow}")
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
