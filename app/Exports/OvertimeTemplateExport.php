<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Carbon\CarbonPeriod;

class OvertimeTemplateExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $date;
    protected $type;

    public function __construct($date, $type = 'all')
    {
        $this->date = $date;
        $this->type = $type;
    }

    public function collection()
    {
        // $query = DB::connection('cii')
        //     ->table('BIODATA')
        //     ->leftJoin('PKWT', 'BIODATA.NPK', '=', 'PKWT.NPK')
        //     ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
        //     ->select('BIODATA.NPK', 'BIODATA.NAMA_KARYAWAN', 'BIODATA.BAG', 'PKWT.TMK', 'DEPT.DEPARTEMENT', 'DEPT.IS_SEWING', 'BIODATA.ID_DEPT', 'BIODATA.STATUS', 'BIODATA.IS_STAFF')
        //     ->where('BIODATA.STATUS', 'A');

        $query = DB::connection('cii')->table('PKWT')
            ->leftJoin('BIODATA', 'PKWT.NPK', '=', 'BIODATA.NPK')
            ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->select('BIODATA.NPK', 'BIODATA.NAMA_KARYAWAN', 'PKWT.TMK', 'PKWT.TKK', 'DEPT.DEPARTEMENT', 'DEPT.IS_SEWING', 'BIODATA.ID_DEPT', 'BIODATA.STATUS', 'BIODATA.IS_STAFF')
            ->where('BIODATA.STATUS', 'A')
            ->where(function ($q) {
                $q->whereNull('PKWT.TKK')
                    ->orWhere(function ($q2) {
                        $q2->whereMonth('PKWT.TKK', Carbon::now()->month)
                            ->whereYear('PKWT.TKK', Carbon::now()->year);
                    });
            });

        switch ($this->type) {
            case 'sewing':
                $query->where('DEPT.IS_SEWING', 0)->where('BIODATA.IS_STAFF', 1);
                break;
            case 'non_sewing':
                $query->where('DEPT.IS_SEWING', 1)->where('BIODATA.IS_STAFF', 1);
                break;
            case 'staff':
                $query->where('DEPT.IS_SEWING', 1)->where('BIODATA.IS_STAFF', 0);
                break;
            case 'all':
            default:
                // No filter — export all employees
                break;
        }

        return $query
            ->orderBy('DEPT.IS_SEWING', 'asc')
            ->orderBy('BIODATA.ID_DEPT', 'asc')
            ->orderBy('BIODATA.NPK', 'asc')
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
            $row->DEPARTEMENT,
            $row->TMK,
            $this->date,
            $row->TKK ? 'MA' : '',
        ];
    }
}
