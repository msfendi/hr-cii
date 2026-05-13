<?php

namespace App\Exports;

use App\Models\Shift;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;

class EmployeeShiftTemplateExport implements WithHeadings, WithEvents, WithTitle
{
    public function title(): string
    {
        return 'Template';
    }

    public function headings(): array
    {
        return [
            'npk',
            'shift',
            'shift_date'
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $shifts = Shift::all();
                $shiftOptions = [];
                $fallbackOptions = [];
                
                foreach ($shifts as $shift) {
                    // Format time dari 07:00:00.0000000 menjadi 07:00
                    $start = substr($shift->work_start, 0, 5);
                    $end = substr($shift->work_end, 0, 5);
                    
                    $shiftOptions[] = $shift->id . ' - ' . $shift->name . ' (' . $start . '-' . $end . ')';
                    $fallbackOptions[] = $shift->id . ' - ' . $shift->name;
                }

                $optionsStr = implode(',', $shiftOptions);
                $validation = $event->sheet->getCell('B2')->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                $validation->setAllowBlank(false);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setShowDropDown(true);
                $validation->setErrorTitle('Input error');
                $validation->setError('Value is not in list.');
                $validation->setPromptTitle('Pick from list');
                $validation->setPrompt('Please pick a shift from the drop-down list.');
                
                // Set the validation formula (list of shifts)
                if (strlen($optionsStr) <= 255) {
                    $validation->setFormula1('"' . $optionsStr . '"');
                } elseif (strlen(implode(',', $fallbackOptions)) <= 255) {
                    // Fallback 1: Tanpa jam jika string terlalu panjang
                    $validation->setFormula1('"' . implode(',', $fallbackOptions) . '"');
                } else {
                    // Fallback 2: Hanya ID jika masih terlalu panjang (>255 char)
                    $ids = $shifts->pluck('id')->toArray();
                    $validation->setFormula1('"' . implode(',', $ids) . '"');
                }

                // Apply data validation to rows 2 to 1000
                for ($i = 2; $i <= 1000; $i++) {
                    $event->sheet->getCell('B' . $i)->setDataValidation(clone $validation);
                }

                // Make the shift_date column a bit wider and add a format text hint
                $event->sheet->getColumnDimension('C')->setWidth(20);
                $event->sheet->getCell('C2')->setValue(date('Y-m-d')); // Example
            },
        ];
    }
}
