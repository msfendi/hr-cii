<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use Carbon\Carbon;

class PKWTActiveExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return DB::connection('cii')->table('PKWT')
            ->select(
                'NPK',
                'NAMA',
                'JK',
                'TGLLAHIR',
                'TMPTLAHIR',
                'PDDK',
                'AGAMA',
                'TMK',
                'USIA',
                'BAGIAN',
                'ALAMAT',
                'KABUPATEN',
                'KTP',
                'NO_KK',
                'IBU',
                'HP',
                'STATUS',
                'TANGGUNGAN',
                'JURUSAN'
            )
            ->whereNull('TKK')
            ->get()
            ->map(function ($row) {
                // Parse tanggal untuk kalkulasi (sebelum format)
                $born = Carbon::parse($row->TGLLAHIR);
                $tmk = Carbon::parse($row->TMK);
                $now = Carbon::now();

                // Hitung usia saat ini
                $diffUsia = $born->diff($now);
                $row->USIA_SAAT_INI = $diffUsia->y . ' Tahun ' . $diffUsia->m . ' Bulan ' . $diffUsia->d . ' Hari';

                // Hitung durasi kerja
                $diffKerja = $tmk->diff($now);
                $row->DURASI_KERJA = $diffKerja->y . ' Tahun ' . $diffKerja->m . ' Bulan ' . $diffKerja->d . ' Hari';

                // Format tanggal ke dd-mm-yyyy (setelah kalkulasi)
                $row->TGLLAHIR = $born->format('d-m-Y');
                $row->TMK = $tmk->format('d-m-Y');

                // Tambahkan prefix ' untuk KTP dan NO_KK agar dibaca sebagai text di Excel
                $row->KTP = "'" . $row->KTP;
                $row->NO_KK = "'" . $row->NO_KK;

                return $row;
            });
    }

    public function headings(): array
    {
        return [
            'NPK',
            'NAMA',
            'JK',
            'TGLLAHIR',
            'TMPTLAHIR',
            'PDDK',
            'AGAMA',
            'TMK',
            'USIA',
            'BAGIAN',
            'ALAMAT',
            'KABUPATEN',
            'KTP',
            'NO_KK',
            'IBU',
            'HP',
            'STATUS',
            'TANGGUNGAN',
            'JURUSAN',
            'USIA_SAAT_INI',
            'DURASI_KERJA'
        ];
    }

    public function title(): string
    {
        return 'Active PKWT';
    }

    /**
     * Apply styles to the header row
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style for header row (row 1)
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => Color::COLOR_WHITE],
                    'size' => 12,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4472C4'], // Blue color
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }
}
