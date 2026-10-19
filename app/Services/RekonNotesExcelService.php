<?php

namespace App\Services;

use App\Models\MonRekonLock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Template + import Excel untuk kartu "REKOMENDASI & NOTES" (diisi manual).
 * Disimpan di mon_rekon_locks (kolom note_*), pada lock terbaru per OCF
 * di periode yang dipilih -- basis yang sama dengan RekonSummaryService.
 *
 * Kolom template:
 *   A lock_id | B ocf_no | C buyer | D style |
 *   E prioritas | F status | G masalah | H tindakan | I pic | J target | K estimasi_penghematan
 *
 * Baris yang kolom E:K-nya kosong semua akan MENGHAPUS note OCF tersebut
 * (template sudah terisi note yang ada, jadi mengosongkan sel = menghapus).
 */
class RekonNotesExcelService extends RekonGradeExcelService
{
    public const STATUSES = ['Investigasi', 'Monitor', 'Follow-up'];

    private const HEADERS = ['lock_id', 'ocf_no', 'buyer', 'style', 'prioritas', 'status', 'masalah', 'tindakan', 'pic', 'target', 'estimasi_penghematan'];

    public function buildNotesTemplate(Carbon $from, Carbon $to): string
    {
        $locks = MonRekonLock::query()
            ->whereDate('lock_date', '>=', $from->toDateString())
            ->whereDate('lock_date', '<=', $to->toDateString())
            ->orderBy('lock_date')->orderBy('id')
            ->get()
            ->groupBy('ocf_no')
            ->map->last()
            ->sortBy('ocf_no')
            ->values();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Notes');
        $sheet->fromArray(self::HEADERS, null, 'A1');

        $r = 2;
        foreach ($locks as $l) {
            $sheet->fromArray([
                $l->id, $l->ocf_no, $l->buyer, $l->style,
                $l->note_prioritas, $l->note_status, $l->note_masalah, $l->note_tindakan,
                $l->note_pic, $l->note_target, $l->note_estimasi,
            ], null, "A{$r}");
            $r++;
        }
        $lastRow = max(200, $r + 50);

        $sheet->getStyle('A1:K1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3864']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle("A2:D{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
        $sheet->getStyle("E2:K{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF8DC');
        $sheet->getStyle("A1:K{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("G2:H{$lastRow}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("K2:K{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->freezePane('A2');

        for ($i = 2; $i <= $lastRow; $i++) {
            $st = $sheet->getCell("F{$i}")->getDataValidation();
            $st->setType(DataValidation::TYPE_LIST)
                ->setFormula1('"' . implode(',', self::STATUSES) . '"')
                ->setAllowBlank(true)->setShowDropDown(true)->setShowErrorMessage(true)
                ->setErrorTitle('Status tidak valid')->setError('Pilih: ' . implode(' / ', self::STATUSES));

            $pr = $sheet->getCell("E{$i}")->getDataValidation();
            $pr->setType(DataValidation::TYPE_WHOLE)
                ->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL)->setFormula1('1')
                ->setAllowBlank(true)->setShowErrorMessage(true)
                ->setErrorTitle('Prioritas tidak valid')->setError('Prioritas harus bilangan bulat >= 1.');

            $es = $sheet->getCell("K{$i}")->getDataValidation();
            $es->setType(DataValidation::TYPE_DECIMAL)
                ->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL)->setFormula1('0')
                ->setAllowBlank(true)->setShowErrorMessage(true)
                ->setErrorTitle('Nilai tidak valid')->setError('Estimasi harus berupa angka >= 0 (Rp).');
        }
        foreach (['A' => 9, 'B' => 14, 'C' => 14, 'D' => 22, 'E' => 10, 'F' => 14, 'G' => 40, 'H' => 40, 'I' => 20, 'J' => 12, 'K' => 22] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $help = $spreadsheet->createSheet();
        $help->setTitle('Petunjuk');
        $help->fromArray([
            ['Petunjuk pengisian REKOMENDASI & NOTES'],
            ['1. Isi kolom kuning (prioritas s/d estimasi_penghematan) pada baris OCF yang bersangkutan.'],
            ['2. prioritas = urutan tampil di kartu (1 paling atas). status: ' . implode(' / ', self::STATUSES) . '.'],
            ['3. Status juga dipakai di kolom Status pada tabel "Detail Top 5 OCF dengan Masalah Terbesar".'],
            ['4. Kolom abu-abu (lock_id s/d style) adalah referensi, jangan diubah.'],
            ['5. Mengosongkan semua kolom kuning pada satu baris = menghapus note OCF tersebut.'],
            ['6. Periode template: ' . $from->format('d-m-Y') . ' s/d ' . $to->format('d-m-Y')],
        ], null, 'A1');
        $help->getStyle('A1')->getFont()->setBold(true);
        $help->getColumnDimension('A')->setWidth(100);
        $spreadsheet->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'rekon_notes_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /** Return ['updated' => int, 'skipped' => int, 'errors' => string[]]. All-or-nothing. */
    public function importNotes(string $filePath, Carbon $from, Carbon $to, ?string $importedBy = null): array
    {
        $rows = IOFactory::load($filePath)->getSheet(0)->toArray(null, true, true, false);
        if (count($rows) < 2) {
            return ['updated' => 0, 'skipped' => 0, 'errors' => ['File kosong atau tidak ada baris data.']];
        }

        $map = [];
        foreach ($rows[0] as $idx => $name) {
            $key = str_replace([' ', '-'], '_', strtolower(trim((string) $name)));
            if ($key !== '') {
                $map[$key] = $idx;
            }
        }
        foreach (['ocf_no', 'prioritas', 'status', 'masalah', 'tindakan', 'pic', 'target', 'estimasi_penghematan'] as $required) {
            if (!array_key_exists($required, $map)) {
                return ['updated' => 0, 'skipped' => 0, 'errors' => ["Kolom \"{$required}\" tidak ditemukan. Gunakan template yang diunduh dari halaman ini."]];
            }
        }

        $errors = [];
        $updates = [];
        $cell = fn(array $row, string $k) => trim((string) ($row[$map[$k]] ?? ''));
        $statusByLower = collect(self::STATUSES)->mapWithKeys(fn($s) => [strtolower($s) => $s]);

        foreach (array_slice($rows, 1, null, true) as $i => $row) {
            $line = $i + 1;
            $ocf = $cell($row, 'ocf_no');
            if ($ocf === '') {
                continue;
            }

            $note = [
                'note_prioritas' => null, 'note_status' => null, 'note_masalah' => null, 'note_tindakan' => null,
                'note_pic' => null, 'note_target' => null, 'note_estimasi' => null,
            ];

            $pr = $cell($row, 'prioritas');
            if ($pr !== '') {
                if (!is_numeric($pr) || (int) $pr < 1 || (float) $pr != (int) $pr) {
                    $errors[] = "Baris {$line} (OCF {$ocf}): prioritas \"{$pr}\" harus bilangan bulat >= 1.";
                } else {
                    $note['note_prioritas'] = (int) $pr;
                }
            }

            $st = $cell($row, 'status');
            if ($st !== '') {
                $norm = $statusByLower->get(strtolower($st));
                if (!$norm) {
                    $errors[] = "Baris {$line} (OCF {$ocf}): status \"{$st}\" harus salah satu dari " . implode(' / ', self::STATUSES) . '.';
                } else {
                    $note['note_status'] = $norm;
                }
            }

            foreach (['masalah' => 'note_masalah', 'tindakan' => 'note_tindakan', 'pic' => 'note_pic', 'target' => 'note_target'] as $col => $field) {
                $v = $cell($row, $col);
                $note[$field] = $v !== '' ? $v : null;
            }
            if ($note['note_pic'] !== null && mb_strlen($note['note_pic']) > 150) {
                $errors[] = "Baris {$line} (OCF {$ocf}): pic maksimal 150 karakter.";
            }
            if ($note['note_target'] !== null && mb_strlen($note['note_target']) > 100) {
                $errors[] = "Baris {$line} (OCF {$ocf}): target maksimal 100 karakter.";
            }

            $es = $cell($row, 'estimasi_penghematan');
            if ($es !== '') {
                $num = $this->parseNumber($row[$map['estimasi_penghematan']]);
                if ($num === null || $num < 0) {
                    $errors[] = "Baris {$line} (OCF {$ocf}): estimasi_penghematan \"{$es}\" bukan angka >= 0.";
                } else {
                    $note['note_estimasi'] = $num;
                }
            }

            $lock = $this->findLock($row, $map, $ocf, $from, $to);
            if (!$lock) {
                $errors[] = "Baris {$line}: OCF {$ocf} tidak ditemukan di tabel lock pada periode "
                    . $from->format('d-m-Y') . ' s/d ' . $to->format('d-m-Y') . '.';
                continue;
            }

            $updates[$lock->id] = $note;
        }

        if ($errors) {
            return ['updated' => 0, 'skipped' => 0, 'errors' => $errors];
        }

        DB::transaction(function () use ($updates, $importedBy) {
            foreach ($updates as $lockId => $note) {
                MonRekonLock::whereKey($lockId)->update($note + [
                    'note_imported_at' => now(),
                    'note_imported_by' => $importedBy,
                ]);
            }
        });

        return ['updated' => count($updates), 'skipped' => 0, 'errors' => []];
    }
}
