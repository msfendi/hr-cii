<?php

namespace App\Exports;

use App\Services\SurveySanitizer;
use App\Models\SatisfactionQuestion;
use App\Models\SatisfactionResponse;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
 * Export hasil Kuesioner Kepuasan Karyawan — ANONIM.
 *
 * Data di-gabung (pivot lebar) agar mudah di Excel/SPSS:
 * - 1 baris = 1 partisipan (dibedakan oleh created_at yang sama pada jawaban 1..35),
 * - kolom per pertanyaan: Likert 1-5 (angka) atau Open (teks),
 * - TANPA NPK/nama — tidak ada identitas karyawan.
 *
 * Filter tanggal (null = semua):
 *   $from / $to dalam format Y-m-d.
 */
class SatisfactionSurveyResultExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles, WithEvents
{
    protected $from;
    protected $to;
    protected $questions;

    public function __construct(string $from = '', string $to = '')
    {
        $this->from = $from;
        $this->to = $to;
        $this->questions = SatisfactionQuestion::withCount('responses')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function collection()
    {
        $q = SatisfactionResponse::query()->with('question')->orderBy('created_at')->orderBy('id');
        if ($this->from) {
            $q->whereDate('created_at', '>=', $this->from);
        }
        if ($this->to) {
            $q->whereDate('created_at', '<=', $this->to);
        }
        $responses = $q->get();

        // Group per submission: sejak tidak ada tabel submission, kita group window 3 detik.
        // Jawaban 1 submit disimpan dalam 1 detik yang sama (transaction).
        // Round ke menit+detik untuk bebas dari beda milidetik antar insert.
        $groups = new Collection();
        $bucket = [];
        $lastKey = null;
        foreach ($responses as $r) {
            $key = Carbon::parse($r->created_at)->format('Y-m-d H:i:s');
            // Tolerance 2 detik: bila selisih >2 detik dari key awal group, group baru.
            if ($lastKey === null || Carbon::parse($key)->diffInSeconds(Carbon::parse($lastKey)) > 2) {
                if (!empty($bucket)) {
                    $groups->push($bucket);
                }
                $bucket = [];
                $lastKey = $key;
            }
            $bucket[$r->question_id] = $r;
        }
        if (!empty($bucket)) {
            $groups->push($bucket);
        }
        return $groups;
    }

    public function headings(): array
    {
        $h = ['No.', 'Waktu Submit (WIB)'];
        foreach ($this->questions as $q) {
            // PERBAIKAN V32 (2026-11-09): escape formula-injection pada
            // header. Header kolom bisa diawali "=" atau "+" kalau
            // section/question_text berisi payload dari admin.
            $label = $q->section ? '[' . SurveySanitizer::escapeFormula($q->section) . '] ' : '';
            $h[] = SurveySanitizer::escapeFormula($label . $q->question_text);
        }
        return $h;
    }

    public function map($group): array
    {
        $first = null;
        $values = [];
        foreach ($group as $qid => $r) {
            if ($first === null) {
                $first = $r->created_at;
            }
            $q = $this->questions->firstWhere('id', $qid);
            // PERBAIKAN V32 (2026-11-09): jawaban open question berasal dari
            // input user. Kalau diawali =, +, -, atau @, Excel akan
            // mengeksekusinya sebagai formula saat file dibuka.
            $values[$qid] = $q && $q->isLikert()
                ? $r->score
                : SurveySanitizer::escapeFormula($r->answer_text ?? '');
        }
        $row = [
            0, // placeholder No., diisi nomor urut di events? Diisi di sini pakai random? Kita pakai waktu + urutan group di map via static counter
            $first ? Carbon::parse($first)->format('Y-m-d H:i:s') : '',
        ];
        foreach ($this->questions as $q) {
            $row[] = $values[$q->id] ?? '';
        }
        return $row;
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
                // Isi nomor urut kolom A
                $maxRow = $sheet->getHighestRow();
                for ($i = 1; $i <= $maxRow; $i++) {
                    if ($i === 1) continue;
                    $sheet->setCellValueByColumnAndRow(1, $i, $i - 1);
                }
                $sheet->freezePane('A2');
                $dim = method_exists($sheet, 'calculateWorksheetDimension')
                    ? $sheet->calculateWorksheetDimension()
                    : ('A1:' . $sheet->getHighestColumn() . $sheet->getHighestRow());
                $sheet->setAutoFilter($dim);
                $sheet->getPageSetup()->setFitToPage(true);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
                $sheet->setTitle('Hasil Kuesioner');
                // Lebar kolom B
                $sheet->getColumnDimension('B')->setWidth(20);
                // Kolom pertanyaan lebar 18, wrap text
                $maxCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($this->questions) + 2);
                $sheet->getStyle("A1:{$maxCol}1")->getAlignment()->setWrapText(true);
                $sheet->getStyle("A2:{$maxCol}{$maxRow}")->getAlignment()->setWrapText(true);
                for ($c = 3; $c <= count($this->questions) + 2; $c++) {
                    $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                    $sheet->getColumnDimension($letter)->setWidth(18);
                }
            },
        ];
    }
}
