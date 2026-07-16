<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class GuestMasterExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithMapping
{
    public function collection()
    {
        return DB::table('guest_masters')
            ->select(
                'guest_masters.*',
                'foreign_guests.guest_name',
                'foreign_guests.return as return_date',
                'foreign_guests.visa_type',
                'foreign_guests.visa_status',
            )->leftJoin('foreign_guests', 'guest_masters.foreign_guest_id', '=', 'foreign_guests.id')
            ->orderBy('foreign_guests.guest_name')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Guest Name',
            'Gender',
            'Place',
            'Date Of Birth',
            'Age',
            'Nationality',
            'Passport No',
            'Visa Type',
            'Issue Date',
            'Must Used Before',
            'Visa Status',
            'Arrival Date',
            'Visa Expiry',
            'Return Date',
            'Status',
            'Remark',
            'Created At'
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
            $row->gender,
            $row->place,
            $row->date_of_birth,
            $age,
            $row->nationality,
            $row->passport_no,
            $row->visa_type,
            $row->issue_date,
            $row->must_used_date,
            $row->visa_status,
            $row->arrival_date,
            $row->visa_expiry,
            $row->return_date,
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
        | HEADER
        |--------------------------------------------------------------------------
        */
        $sheet->getStyle('A1:R1')->applyFromArray([
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
        |--------------------------------------------------------------------------
        */
        $sheet->getStyle("E2:E{$highestRow}")
            ->getNumberFormat()
            ->setFormatCode('yyyy-mm-dd');

        $sheet->getStyle("J2:K{$highestRow}")
            ->getNumberFormat()
            ->setFormatCode('yyyy-mm-dd');

        $sheet->getStyle("M2:O{$highestRow}")
            ->getNumberFormat()
            ->setFormatCode('yyyy-mm-dd');

        $sheet->getStyle("R2:R{$highestRow}")
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

        $sheet->getStyle("J2:O{$highestRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("P2:P{$highestRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("R2:R{$highestRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        /*
        |--------------------------------------------------------------------------
        | BORDER TABLE
        |--------------------------------------------------------------------------
        */
        $sheet->getStyle("A1:R{$highestRow}")
            ->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ]);

        /*
        |--------------------------------------------------------------------------
        | FREEZE HEADER
        |--------------------------------------------------------------------------
        */
        $sheet->freezePane('A2');

        return [];
    }
}
