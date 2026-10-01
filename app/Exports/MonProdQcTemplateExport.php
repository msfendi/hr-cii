<?php

namespace App\Exports;

use App\Exports\Support\TemplateDropdownHelper;
use App\Services\MonStageDataService;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Template import untuk mon_prod_qc (cpo, code_prod, department_id, jumlah).
 * Kolom `cpo` pakai dropdown mon_orders.uraian, `code_prod` pakai daftar
 * dropdown yang SAMA dengan `ocf_no` di template Stage Remark
 * (MonStageDataService::distinctOcfList(), hasil ekstraksi
 * mon_rekonsiliasis.code_prod), dan `department_id` dropdown
 * Cutting/Sewing/Packing/QC. Kolom `jumlah` tetap input angka bebas.
 */
class MonProdQcTemplateExport implements WithHeadings, WithEvents, WithTitle
{
    public function __construct(private MonStageDataService $service)
    {
    }

    public function headings(): array
    {
        return ['cpo', 'code_prod', 'department_id', 'jumlah'];
    }

    public function title(): string
    {
        return 'Template Prod QC';
    }

    /**
     * Daftar CPO (mon_orders.uraian) untuk dropdown kolom `cpo`. Nilainya
     * harus persis sama dengan uraian karena filter CPO di dashboard
     * mencocokkan kolom mon_prod_qc.cpo ke mon_orders.uraian.
     */
    private function cpoList(): array
    {
        return DB::table('mon_orders')
            ->whereNotNull('uraian')
            ->distinct()
            ->orderBy('uraian')
            ->pluck('uraian')
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values()
            ->all();
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $worksheet = $event->sheet->getDelegate();

                TemplateDropdownHelper::apply(
                    $worksheet,
                    $worksheet->getParent(),
                    [
                        'A' => $this->cpoList(),
                        'B' => $this->service->distinctOcfList(),
                        'C' => MonStageDataService::DEPARTMENTS,
                    ]
                );
            },
        ];
    }
}
