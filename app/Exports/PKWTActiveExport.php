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
                'PKWT.NPK',
                'PKWT.NAMA',
                'PKWT.JK',
                'PKWT.TGLLAHIR',
                'PKWT.TMPTLAHIR',
                'PKWT.PDDK',
                'PKWT.AGAMA',
                'PKWT.TMK',
                'PKWT.USIA',
                'PKWT.BAGIAN',
                'PKWT.ALAMAT',
                'PKWT.KABUPATEN',
                'PKWT.KTP',
                'PKWT.NO_KK',
                'PKWT.IBU',
                'PKWT.HP',
                'PKWT.STATUS',
                'PKWT.TANGGUNGAN',
                'PKWT.JURUSAN',
                'employees_contract.status_contract as STATUS_KONTRAK',
                'employees_contract.end_date as AKHIR_KONTRAK'
            )
            ->leftJoin('employees_contract', function($join) {
                $join->on('PKWT.NPK', '=', 'employees_contract.npk')
                     ->where('employees_contract.status_contract', 'AKTIF');
            })
            ->whereNull('PKWT.TKK')
            ->get()
            ->map(function ($row) {
                // Simpan dan hapus properti agar urutannya di akhir sesuai headings
                $statusKontrak = $row->STATUS_KONTRAK;
                $akhirKontrak = $row->AKHIR_KONTRAK;
                unset($row->STATUS_KONTRAK);
                unset($row->AKHIR_KONTRAK);

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

                // Tambahkan kembali status dan akhir kontrak agar posisinya di paling akhir (sesuai headings)
                if (empty($akhirKontrak)) {
                    $row->STATUS_KONTRAK = 'BELUM ADA KONTRAK';
                } else {
                    $today = Carbon::today();
                    $endDate = Carbon::parse($akhirKontrak)->startOfDay();
                    
                    if ($endDate->greaterThanOrEqualTo($today)) {
                        $row->STATUS_KONTRAK = 'AKTIF';
                    } else {
                        $row->STATUS_KONTRAK = 'BELUM DIPERPANJANG';
                    }
                }

                $row->AKHIR_KONTRAK = !empty($akhirKontrak) ? Carbon::parse($akhirKontrak)->format('d-m-Y') : null;

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
            'DURASI_KERJA',
            'STATUS_KONTRAK',
            'AKHIR_KONTRAK'
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
