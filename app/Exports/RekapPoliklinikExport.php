<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RekapPoliklinikExport implements FromView, ShouldAutoSize, WithStyles
{
    protected $kunjungans;
    protected $karyawanMap;

    public function __construct($kunjungans, $karyawanMap)
    {
        $this->kunjungans = $kunjungans;
        $this->karyawanMap = $karyawanMap;
    }

    public function view(): View
    {
        return view('reports.exports.rekap-excel', [
            'kunjungans' => $this->kunjungans,
            'karyawanMap' => $this->karyawanMap
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }
}
