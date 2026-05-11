<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeesContractExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        private int $month,
        private int $year
    ) {}

    public function collection()
    {
        return DB::table('employees_contract as c')
            ->join('BIODATA as b', 'b.NPK', '=', 'c.npk')
            ->select([
                'c.npk', 'b.NAMA_KARYAWAN as nama', 'b.BAG as bagian',
                'c.contract_ke', 'c.start_date', 'c.end_date',
                'c.month_duration', 'c.status_contract', 'c.salary', 'c.allowance',
                DB::raw("DATEDIFF(DAY, CAST(GETDATE() AS DATE), c.end_date) as sisa_hari"),
                DB::raw("DAY(c.end_date) - 7  as selisih_cutoff7"),
                DB::raw("DAY(c.end_date) - 20 as selisih_cutoff20"),
            ])
            ->whereRaw("MONTH(c.end_date) = ? AND YEAR(c.end_date) = ?", [$this->month, $this->year])
            ->orderBy('c.end_date', 'asc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'NPK', 'Nama', 'Bagian', 'Kontrak Ke-',
            'Tgl Mulai', 'Tgl Berakhir', 'Durasi (bln)',
            'Status', 'Gaji Pokok', 'Tunjangan',
            'Sisa Hari', 'Selisih Cut-off Tgl 7', 'Selisih Cut-off Tgl 20',
        ];
    }

    public function map($row): array
    {
        $selisih7  = $row->selisih_cutoff7;
        $selisih20 = $row->selisih_cutoff20;

        return [
            $row->npk,
            $row->nama,
            $row->bagian,
            $row->contract_ke,
            $row->start_date,
            $row->end_date,
            $row->month_duration,
            $row->status_contract,
            $row->salary,
            $row->allowance,
            $row->sisa_hari,
            ($selisih7 >= 0 ? '+' : '') . $selisih7 . ' hari',
            ($selisih20 >= 0 ? '+' : '') . $selisih20 . ' hari',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
