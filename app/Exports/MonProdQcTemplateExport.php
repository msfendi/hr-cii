<?php

namespace App\Exports;

use App\Exports\Support\TemplateDropdownHelper;
use App\Services\MonStageDataService;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Template import untuk mon_prod_qc (code_prod, department_id, jumlah).
 * Kolom `code_prod` pakai daftar dropdown yang SAMA dengan `ocf_no` di
 * template Stage Remark (MonStageDataService::distinctOcfList(), hasil
 * ekstraksi mon_rekonsiliasis.code_prod), dan `department_id` dropdown
 * Cutting/Sewing/Packing/QC. Kolom `jumlah` tetap input angka bebas.
 */
class MonProdQcTemplateExport implements WithHeadings, WithEvents, WithTitle
{
    public function __construct(private MonStageDataService $service)
    {
    }

    public function headings(): array
    {
        return ['code_prod', 'department_id', 'jumlah'];
    }

    public function title(): string
    {
        return 'Template Prod QC';
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
                        'A' => $this->service->distinctOcfList(),
                        'B' => MonStageDataService::DEPARTMENTS,
                    ]
                );
            },
        ];
    }
}
