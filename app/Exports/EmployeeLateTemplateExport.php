<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Template export untuk import Employee Late.
 * Kolom mengikuti struktur tabel employee_lates TANPA kolom id
 * (id, npk, date, arrival_time, reason) -> id tidak ditampilkan.
 */
class EmployeeLateTemplateExport implements FromArray, WithHeadings, WithEvents
{
    /**
     * Baris contoh (opsional). Silakan dihapus oleh user sebelum diisi data asli.
     */
    public function array(): array
    {
        return [
            ['00123', '2026-07-03', '08:30', 'Contoh: terlambat karena kendala transportasi'],
        ];
    }

    public function headings(): array
    {
        return ['NPK', 'Date', 'Arrival Time', 'Reason'];
    }

    /**
     * Set format kolom Date & Arrival Time agar tampil rapi dan
     * tidak berubah jadi angka serial acak saat dibuka user di Excel.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getColumnDimension('A')->setWidth(15); // NPK
                $sheet->getColumnDimension('B')->setWidth(15); // Date
                $sheet->getColumnDimension('C')->setWidth(15); // Arrival Time
                $sheet->getColumnDimension('D')->setWidth(45); // Reason

                $sheet->getStyle('B2:B1000')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
                $sheet->getStyle('C2:C1000')->getNumberFormat()->setFormatCode('hh:mm');

                $sheet->getStyle('A1:D1')->getFont()->setBold(true);
            },
        ];
    }
}
