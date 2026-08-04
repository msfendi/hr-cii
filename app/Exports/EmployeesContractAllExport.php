<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EmployeesContractAllExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        private bool $roleAdmin,
        private bool $roleStaff,
        private bool $roleNonStaff,
        private bool $roleSewing,
        private bool $roleNonSewing
    ) {}

    public function collection()
    {
        $query = DB::table('employees_contract as c')
            ->join('BIODATA as b', 'b.NPK', '=', 'c.npk')
            ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'b.ID_DEPT')
            ->select([
                'c.npk',
                'b.NAMA_KARYAWAN as nama',
                'b.BAG            as bagian',
                'b.IS_STAFF',
                'd.IS_SEWING',
                'c.contract_ke',
                'c.start_date',
                'c.end_date',
                'c.month_duration',
                'c.day_duration',
                'c.status_contract',
                'c.type',
                'c.salary',
                'c.allowance',
                'c.pph21',
                'c.daily_salary'
            ]);

        // Role-based filtering
        if (!$this->roleAdmin) {
            $query->where(function ($q) {
                if ($this->roleStaff) {
                    $q->orWhere('b.IS_STAFF', 1);
                }
                if ($this->roleNonStaff) {
                    $q->orWhere('b.IS_STAFF', 0);
                }
                if ($this->roleSewing) {
                    $q->orWhere(function ($q2) {
                        $q2->where('d.IS_SEWING', 0)->where('b.IS_STAFF', 0);
                    });
                }
                if ($this->roleNonSewing) {
                    $q->orWhere(function ($q2) {
                        $q2->where('d.IS_SEWING', 1)->where('b.IS_STAFF', 0);
                    });
                }
                // Jika tidak punya akses sama sekali, filter habis
                if (!$this->roleStaff && !$this->roleSewing && !$this->roleNonSewing) {
                    $q->whereRaw('1 = 0');
                }
            });
        }

        return $query->orderBy('c.npk', 'asc')
            ->orderBy('c.contract_ke', 'asc')
            ->orderBy('c.start_date', 'asc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'NPK',
            'Nama',
            'Bagian',
            'Kontrak Ke-',
            'Tgl Mulai',
            'Tgl Berakhir',
            'Durasi (bln)',
            'Durasi (hari)',
            'Status',
            'Type',
            'Gaji Pokok',
            'Tunjangan',
            'PPH21',
            'Gaji Harian',
        ];
    }

    public function map($row): array
    {
        return [
            $row->npk,
            $row->nama,
            $row->bagian,
            $row->contract_ke,
            $row->start_date,
            $row->end_date,
            $row->month_duration,
            $row->day_duration,
            $row->status_contract,
            $row->type,
            (float) $row->salary,
            (float) $row->allowance,
            (float) $row->pph21,
            (float) $row->daily_salary,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
