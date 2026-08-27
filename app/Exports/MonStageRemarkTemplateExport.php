<?php

namespace App\Exports;

use App\Exports\Support\TemplateDropdownHelper;
use App\Services\MonStageDataService;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Template import untuk mon_stage_remarks (ocf_no, department_id, remark).
 * Kolom `ocf_no` (dropdown dari MonStageDataService::distinctOcfList()) dan
 * `department_id` (dropdown Cutting/Sewing/Packing/QC) dibuat sebagai
 * dropdown Excel via TemplateDropdownHelper. Kolom `remark` tetap teks bebas.
 */
class MonStageRemarkTemplateExport implements WithHeadings, WithEvents, WithTitle
{
    public function __construct(private MonStageDataService $service)
    {
    }

    public function headings(): array
    {
        return ['ocf_no', 'department_id', 'remark'];
    }

    public function title(): string
    {
        return 'Template Stage Remark';
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
