<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PelamarTemplateExport implements FromArray, ShouldAutoSize, WithStyles
{
    public function array(): array
    {
        return [[
            'NO',
            'NPK',
            'NAMA',
            'JK',
            'TEMPAT LAHIR',
            'TANGGAL LAHIR',
            'TMK',
            'USIA SAAT INI',
            'ALAMAT SESUAI KTP',
            'KABUPATEN/KOTA TEMPAT TINGGAL',
            'ALAMAT SEKARANG',
            'PENDIDIKAN',
            'NAMA SEKOLAH',
            'KABUPATEN SEKOLAH',
            'JURUSAN PENDIDIKAN',
            'TINGGI BADAN',
            'BERAT BADAN',
            'NOMOR HP AKTIF (WA)',
            'AGAMA',
            'NIK',
            'NO KK',
            'NAMA IBU KANDUNG',
            'STATUS',
            'JUMLAH TANGGUNGAN (HANYA ANAK KANDUNG)'
        ]];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1    => [
                'font' => ['bold' => true],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => [
                        'argb' => 'FFE2EFDA',
                    ]
                ]
            ],
        ];
    }
}
