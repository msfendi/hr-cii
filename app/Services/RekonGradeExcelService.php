<?php

namespace App\Services;

use App\Models\MonRekonLock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Template + import Excel untuk mengisi Grade A / B / C per OCF pada
 * Summary Rekonsiliasi. Grade disimpan di mon_rekon_locks (baris lock
 * terbaru per OCF pada periode yang dipilih).
 *
 * Kolom template:
 *   A lock_id | B ocf_no | C buyer | D style | E lock_type | F lock_date |
 *   G grade_a | H grade_b | I grade_c      (G:I diisi user, satuan PCS)
 */
class RekonGradeExcelService
{
    private const HEADERS = ['lock_id', 'ocf_no', 'buyer', 'style', 'lock_type', 'lock_date', 'grade_a', 'grade_b', 'grade_c'];

    /** Buat file template (sudah terisi daftar OCF yang di-lock pada periode). Return path file sementara. */
    public function buildTemplate(Carbon $from, Carbon $to): string
    {
        $locks = MonRekonLock::query()
            ->whereDate('lock_date', '>=', $from->toDateString())
            ->whereDate('lock_date', '<=', $to->toDateString())
            ->orderBy('lock_date')->orderBy('id')
            ->get()
            ->groupBy('ocf_no')
            ->map->last()          // lock terbaru per OCF -- sama dengan basis RekonSummaryService
            ->sortBy('ocf_no')
            ->values();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Grade');

        $sheet->fromArray(self::HEADERS, null, 'A1');

        $r = 2;
        foreach ($locks as $l) {
            $sheet->fromArray([
                $l->id,
                $l->ocf_no,
                $l->buyer,
                $l->style,
                $l->lock_type,
                $l->lock_date->format('Y-m-d'),
                $l->grade_a,
                $l->grade_b,
                $l->grade_c,
            ], null, "A{$r}");
            $r++;
        }

        $lastRow = max(200, $r + 50);

        // Header
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3864']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        // Kolom referensi (abu-abu) vs kolom isian (kuning)
        $sheet->getStyle("A2:F{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
        $sheet->getStyle("G2:I{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF8DC');
        $sheet->getStyle("A1:I{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("G2:I{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->freezePane('A2');

        // Validasi: Grade harus angka >= 0
        for ($i = 2; $i <= $lastRow; $i++) {
            foreach (['G', 'H', 'I'] as $col) {
                $v = $sheet->getCell("{$col}{$i}")->getDataValidation();
                $v->setType(DataValidation::TYPE_DECIMAL)
                    ->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL)
                    ->setFormula1('0')
                    ->setAllowBlank(true)
                    ->setShowErrorMessage(true)
                    ->setErrorTitle('Nilai tidak valid')
                    ->setError('Grade harus berupa angka >= 0 (PCS).');
            }
        }
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Petunjuk
        $help = $spreadsheet->createSheet();
        $help->setTitle('Petunjuk');
        $help->fromArray([
            ['Petunjuk pengisian'],
            ['1. Isi kolom grade_a, grade_b, grade_c (satuan PCS) pada baris OCF yang bersangkutan.'],
            ['2. Kolom abu-abu (lock_id s/d lock_date) adalah referensi, jangan diubah.'],
            ['3. Baris yang grade_a, grade_b, dan grade_c-nya kosong semua akan dilewati.'],
            ['4. Kalau OCF belum ada di daftar, lakukan "Lock Data OCF" dulu di Dashboard Reconciliation.'],
            ['5. Periode template: ' . $from->format('d-m-Y') . ' s/d ' . $to->format('d-m-Y')],
        ], null, 'A1');
        $help->getStyle('A1')->getFont()->setBold(true);
        $help->getColumnDimension('A')->setWidth(95);
        $spreadsheet->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'rekon_grade_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /**
     * Import file Excel. Return ['updated' => int, 'skipped' => int, 'errors' => string[]].
     * Kalau ada error validasi, TIDAK ada data yang disimpan (all-or-nothing).
     */
    public function import(string $filePath, Carbon $from, Carbon $to, ?string $importedBy = null): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getSheet(0)->toArray(null, true, true, false);

        if (count($rows) < 2) {
            return ['updated' => 0, 'skipped' => 0, 'errors' => ['File kosong atau tidak ada baris data.']];
        }

        // Peta nama kolom -> index (header dinormalisasi: huruf kecil, spasi -> underscore).
        $map = [];
        foreach ($rows[0] as $idx => $name) {
            $key = str_replace([' ', '-'], '_', strtolower(trim((string) $name)));
            if ($key !== '') {
                $map[$key] = $idx;
            }
        }
        foreach (['ocf_no', 'grade_a', 'grade_b', 'grade_c'] as $required) {
            if (!array_key_exists($required, $map)) {
                return ['updated' => 0, 'skipped' => 0, 'errors' => ["Kolom \"{$required}\" tidak ditemukan. Gunakan template yang diunduh dari halaman ini."]];
            }
        }

        $errors  = [];
        $updates = []; // lock_id => [grade_a, grade_b, grade_c]
        $skipped = 0;

        foreach (array_slice($rows, 1, null, true) as $i => $row) {
            $line = $i + 1;
            $ocf  = trim((string) ($row[$map['ocf_no']] ?? ''));
            if ($ocf === '') {
                continue; // baris kosong
            }

            $grades = [];
            $anyFilled = false;
            foreach (['grade_a', 'grade_b', 'grade_c'] as $g) {
                $raw = $row[$map[$g]] ?? null;
                if ($raw === null || trim((string) $raw) === '') {
                    $grades[$g] = null;
                    continue;
                }
                $anyFilled = true;
                $num = $this->parseNumber($raw);
                if ($num === null || $num < 0) {
                    $errors[] = "Baris {$line} (OCF {$ocf}): nilai {$g} \"{$raw}\" bukan angka >= 0.";
                    continue;
                }
                $grades[$g] = $num;
            }

            if (!$anyFilled) {
                $skipped++;
                continue;
            }

            $lock = $this->findLock($row, $map, $ocf, $from, $to);
            if (!$lock) {
                $errors[] = "Baris {$line}: OCF {$ocf} tidak ditemukan di tabel lock pada periode "
                    . $from->format('d-m-Y') . ' s/d ' . $to->format('d-m-Y') . '.';
                continue;
            }

            $updates[$lock->id] = $grades;
        }

        if ($errors) {
            return ['updated' => 0, 'skipped' => $skipped, 'errors' => $errors];
        }

        DB::transaction(function () use ($updates, $importedBy) {
            foreach ($updates as $lockId => $g) {
                MonRekonLock::whereKey($lockId)->update([
                    'grade_a'           => $g['grade_a'],
                    'grade_b'           => $g['grade_b'],
                    'grade_c'           => $g['grade_c'],
                    'grade_imported_at' => now(),
                    'grade_imported_by' => $importedBy,
                ]);
            }
        });

        return ['updated' => count($updates), 'skipped' => $skipped, 'errors' => []];
    }

    protected function findLock(array $row, array $map, string $ocf, Carbon $from, Carbon $to): ?MonRekonLock
    {
        // 1) lock_id dari template -- paling presisi.
        $lockId = isset($map['lock_id']) ? trim((string) ($row[$map['lock_id']] ?? '')) : '';
        if ($lockId !== '' && ctype_digit($lockId)) {
            $lock = MonRekonLock::find((int) $lockId);
            if ($lock && strcasecmp($lock->ocf_no, $ocf) === 0) {
                return $lock;
            }
        }

        // 2) Tanpa lock_id: lock terbaru OCF itu pada periode terpilih.
        return MonRekonLock::query()
            ->where('ocf_no', $ocf)
            ->whereDate('lock_date', '>=', $from->toDateString())
            ->whereDate('lock_date', '<=', $to->toDateString())
            ->orderByDesc('lock_date')->orderByDesc('id')
            ->first();
    }

    protected function parseNumber(mixed $raw): ?float
    {
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }
        $s = str_replace(' ', '', trim((string) $raw));
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $s)) {      // 1.234.567,89
            $s = str_replace(['.', ','], ['', '.'], $s);
        } elseif (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $s)) { // 1,234,567.89
            $s = str_replace(',', '', $s);
        } else {
            $s = str_replace(',', '.', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }
}
