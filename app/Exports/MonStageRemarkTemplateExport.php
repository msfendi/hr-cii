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
 * Template import untuk mon_stage_remarks (cpo, ocf_no, department_id, remark).
 * Kolom `cpo` (dropdown dari mon_orders.uraian), `ocf_no` (dropdown dari
 * MonStageDataService::distinctOcfList()) dan `department_id` (dropdown
 * Cutting/Sewing/Packing/QC) dibuat sebagai dropdown Excel via
 * TemplateDropdownHelper. Kolom `remark` tetap teks bebas.
 */
class MonStageRemarkTemplateExport implements WithHeadings, WithEvents, WithTitle
{
    public function __construct(private MonStageDataService $service)
    {
    }

    public function headings(): array
    {
        return ['cpo', 'ocf_no', 'department_id', 'remark'];
    }

    public function title(): string
    {
        return 'Template Stage Remark';
    }

    /**
     * Daftar CPO (mon_orders.uraian) untuk dropdown kolom `cpo`. Nilainya
     * harus persis sama dengan uraian karena filter CPO di dashboard
     * mencocokkan kolom mon_stage_remarks.cpo ke mon_orders.uraian.
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
