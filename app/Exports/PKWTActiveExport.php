<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Carbon\Carbon;

class PKWTActiveExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize
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
                $born = Carbon::parse($row->TGLLAHIR);
                $now = Carbon::now();
                $diffUsia = $born->diff($now);
                $row->USIA_SAAT_INI = $diffUsia->y . ' Tahun ' . $diffUsia->m . ' Bulan ' . $diffUsia->d . ' Hari';

                $tmk = Carbon::parse($row->TMK);
                $diffKerja = $tmk->diff($now);
                $row->DURASI_KERJA = $diffKerja->y . ' Tahun ' . $diffKerja->m . ' Bulan ' . $diffKerja->d . ' Hari';

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
}
