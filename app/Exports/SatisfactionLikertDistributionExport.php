<?php

namespace App\Exports;

use App\Services\SurveySanitizer;
use App\Models\SatisfactionQuestion;
use App\Models\SatisfactionResponse;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export DISTRIBUSI LIKERT — anonim, 1 baris per pertanyaan Likert.
 * Kolom: No., Bagian, Pertanyaan, Skor 1..5, Total Jawab, Rata-rata.
 * Filter tanggal opsional ($from/$to Y-m-d).
 */
class SatisfactionLikertDistributionExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
{
    protected $from;
    protected $to;
    protected $rows;

    public function __construct(string $from = '', string $to = '')
    {
        $this->from = $from;
        $this->to = $to;
        $questions = SatisfactionQuestion::where('question_type', 'likert')
            ->orderBy('sort_order')->orderBy('id')->get();
        $this->rows = collect();
        foreach ($questions as $q) {
            $query = SatisfactionResponse::where('question_id', $q->id);
            if ($from) $query->whereDate('created_at', '>=', $from);
            if ($to) $query->whereDate('created_at', '<=', $to);
            $counts = $query->selectRaw('score, COUNT(*) as c')->groupBy('score')->pluck('c', 'score');
            $arr = [];
            for ($s = 1; $s <= 5; $s++) $arr[$s] = (int) ($counts[$s] ?? 0);
            $avg = $query->avg('score');
            $this->rows->push([
                'question' => $q,
                'dist' => $arr,
                'total' => array_sum($arr),
                'avg' => $avg !== null ? round((float) $avg, 2) : null,
            ]);
        }
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['No.', 'Bagian', 'Pertanyaan', 'Skor 1', 'Skor 2', 'Skor 3', 'Skor 4', 'Skor 5', 'Total Jawab', 'Rata-rata'];
    }

    public function map($row): array
    {
        static $no = 0;
        $no++;
        $q = $row['question'];
        // PERBAIKAN V32 (2026-11-09): escape formula-injection. Section dan
        // question_text berasal dari input admin dan bisa diawali "=".
        return [
            $no,
            SurveySanitizer::escapeFormula($q->section ?? ''),
            SurveySanitizer::escapeFormula($q->question_text),
            $row['dist'][1], $row['dist'][2], $row['dist'][3], $row['dist'][4], $row['dist'][5],
            $row['total'],
            $row['avg'] !== null ? $row['avg'] : '—',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4E73DF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B0B7D3']]],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $maxRow = $sheet->getHighestRow();
                $sheet->freezePane('A2');
                $dim = method_exists($sheet, 'calculateWorksheetDimension')
                    ? $sheet->calculateWorksheetDimension()
                    : 'A1:J' . $maxRow;
                $sheet->setAutoFilter($dim);
                $sheet->setTitle('Distribusi Likert');
                $sheet->getColumnDimension('C')->setWidth(60);
                $sheet->getColumnDimension('C')->setWidth(60);
                $sheet->getColumnDimension('B')->setWidth(10);
                foreach (range(1, 10) as $c) {
                    if ($c === 3) continue;
                    $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                    $sheet->getColumnDimension($letter)->setWidth(12);
                }
                $sheet->getStyle('A1:J' . $maxRow)->getAlignment()->setVertical('center');
                $sheet->getStyle('C2:C' . $maxRow)->getAlignment()->setWrapText(true);
                $sheet->getRowDimension(1)->setRowHeight(30);
            },
        ];
    }
}
