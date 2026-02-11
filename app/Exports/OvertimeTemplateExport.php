<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Carbon\CarbonPeriod;

class OvertimeTemplateExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $date;

    public function __construct($date)
    {
        $this->date = $date;
    }

    public function collection()
    {
        return DB::connection('cii')
            ->table('BIODATA')
            ->leftJoin('PKWT', 'BIODATA.NPK', '=', 'PKWT.NPK')
            ->select('BIODATA.NPK', 'BIODATA.NAMA_KARYAWAN', 'BIODATA.BAG', 'PKWT.TMK')
            ->where('BIODATA.STATUS', 'A')
            ->orderBy('BIODATA.NAMA_KARYAWAN')
            ->get();
    }

    public function headings(): array
    {
        return ['NPK', 'NAMA', 'BAGIAN', 'TMK', 'OVERTIME DATE', 'JUMLAH JAM LEMBUR'];
    }

    public function map($row): array
    {
        return [
            $row->NPK,
            $row->NAMA_KARYAWAN,
            $row->BAG,
            $row->TMK,
            $this->date,
            '',
        ];
    }
}
