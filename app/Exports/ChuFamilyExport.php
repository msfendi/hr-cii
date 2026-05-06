<?php

namespace App\Exports;

use App\Models\ChuFamily;
use Carbon\Carbon;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ChuFamilyExport implements
    FromCollection,
    WithHeadings,
    ShouldAutoSize,
    WithMapping,
    WithStyles
{

    protected $data;

    /*
    |--------------------------------------------------------------------------
    | COLLECTION (LOGIC TIDAK DIUBAH)
    |--------------------------------------------------------------------------
    */
    public function collection()
    {
        $today = Carbon::today();

        $this->data = ChuFamily::latest()->get()->map(function ($row) use ($today) {

            /*
            | AGE
            */
            $age = '-';

            if ($row->birth_date) {
                $birth = Carbon::parse($row->birth_date);
                $diff = $birth->diff($today);

                $age =
                    $diff->y . ' Tahun ' .
                    $diff->m . ' Bulan ' .
                    $diff->d . ' Hari';
            }

            /*
            | VISA STATUS
            */
            $visaStatus = '-';

            if ($row->visa_expiry) {
                $days = $today->diffInDays(
                    Carbon::parse($row->visa_expiry),
                    false
                );

                if ($days < 0)
                    $visaStatus = 'Expired';
                elseif ($days <= 30)
                    $visaStatus = "Warning ($days days)";
                else
                    $visaStatus = "$days days left";
            }

            /*
            | RPTKA STATUS
            */
            $rptkaStatus = '-';

            if ($row->rptka_expiry) {
                $days = $today->diffInDays(
                    Carbon::parse($row->rptka_expiry),
                    false
                );

                if ($days < 0)
                    $rptkaStatus = 'Expired';
                elseif ($days <= 30)
                    $rptkaStatus = "Warning ($days days)";
                else
                    $rptkaStatus = "$days days left";
            }

            $row->age = $age;
            $row->visa_status = $visaStatus;
            $row->rptka_status = $rptkaStatus;

            return $row;
        });

        return $this->data;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Gender',
            'Place',
            'Birth Date',
            'Age',
            'Nationality',
            'Passport',
            'Passport Exp',
            'Visa Type',
            'Visa Exp',
            'Visa Status',
            'KITAS Exp',
            'RPTKA Exp',
            'RPTKA Status',
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->name,
            $row->gender,
            $row->place,
            $row->birth_date,
            $row->age,
            $row->nationality,
            $row->passport_number,
            $row->passport_expiry,
            $row->visa_type,
            $row->visa_expiry,
            $row->visa_status,
            $row->kitas_expiry,
            $row->rptka_expiry,
            $row->rptka_status,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | STYLING EXCEL (SAMA SEPERTI CONTOH ANDA)
    |--------------------------------------------------------------------------
    */
    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();

        /*
        | HEADER STYLE
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
        | ALIGNMENT CENTER
        */
        $sheet->getStyle("A2:A{$highestRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("C2:C{$highestRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("E2:E{$highestRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("I2:O{$highestRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        /*
        | BORDER TABLE
        */
        $sheet->getStyle("A1:O{$highestRow}")
            ->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ]);

        /*
        | FREEZE HEADER
        */
        $sheet->freezePane('A2');

        /*
        | COLOR STATUS AUTO
        */
        for ($i = 2; $i <= $highestRow; $i++) {

            $visa = $sheet->getCell("L{$i}")->getValue();
            $rptka = $sheet->getCell("O{$i}")->getValue();

            if ($visa == 'Expired') {
                $sheet->getStyle("L{$i}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFC7CE');
            }

            if ($rptka == 'Expired') {
                $sheet->getStyle("O{$i}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFC7CE');
            }
        }

        return [];
    }
}
