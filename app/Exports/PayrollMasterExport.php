<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Models\PayrollMaster;

class PayrollMasterExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    WithColumnFormatting,
    WithStyles,
    ShouldAutoSize
{
    public function query()
    {
        // Gabungan karyawan aktif + keluar (sama seperti di index)
        $biodataUnion = DB::table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT')
            ->union(
                DB::table('BIODATA_KELUAR')
                    ->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT')
            );

        return PayrollMaster::query()
            ->leftJoinSub($biodataUnion, 'biodata', function ($join) {
                $join->on('payroll_masters.npk', '=', 'biodata.NPK');
            })
            ->leftJoin('DEPT', 'biodata.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->select(
                'payroll_masters.npk',
                'biodata.NAMA_KARYAWAN',
                'DEPT.DEPARTEMENT',
                'payroll_masters.bank_name',
                'payroll_masters.bank_account'
            )
            ->orderBy('payroll_masters.npk', 'asc');
    }

    public function headings(): array
    {
        return [
            'NPK',
            'NAMA_KARYAWAN',
            'DEPARTEMENT',
            'bank_name',
            'bank_account',
        ];
    }

    public function map($row): array
    {
        return [
            trim((string) $row->npk),
            trim((string) $row->NAMA_KARYAWAN),
            trim((string) $row->DEPARTEMENT),
            trim((string) $row->bank_name),
            trim((string) $row->bank_account),
        ];
    }

    // NPK & nomor rekening dipaksa Text supaya angka 0 di depan
    // tidak hilang dan nomor panjang tidak jadi format ilmiah (1.23E+12)
    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
