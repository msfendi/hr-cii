<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class PKWTNonActiveExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize
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
                'TKK',
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
            ->whereNotNull('TKK')
            ->get();
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
            'TKK',
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
        ];
    }

    public function title(): string
    {
        return 'Non-Active PKWT';
    }
}
