<?php

namespace App\Exports\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Helper bersama untuk menambahkan dropdown (data validation) ke kolom
 * template import Excel (dipakai MonStageRemarkTemplateExport &
 * MonProdQcTemplateExport).
 *
 * Nilai dropdown DITARUH di sheet tersembunyi "Lists", BUKAN ditulis
 * inline sebagai formula1 -- Excel data validation list hanya bisa
 * menampung sampai 255 karakter kalau ditulis inline, sementara daftar
 * OCF hasil ekstraksi mon_rekonsiliasis.code_prod bisa jauh lebih panjang
 * dari itu.
 */
class TemplateDropdownHelper
{
    /** Jumlah baris kosong yang disiapkan untuk diisi user setelah header. */
    public const MAX_ROWS = 2000;

    /**
     * @param  Worksheet    $sheet         Sheet utama (tempat user mengisi data).
     * @param  Spreadsheet  $spreadsheet    Workbook induk, untuk menambah sheet "Lists".
     * @param  array<string, array<int, string>> $columnLists  Peta kolom target (mis. 'A') => daftar nilai dropdown.
     */
    public static function apply(Worksheet $sheet, Spreadsheet $spreadsheet, array $columnLists): void
    {
        $listSheet = $spreadsheet->createSheet();
        $listSheet->setTitle('Lists');

        $colIndex = 1;
        foreach ($columnLists as $targetColumn => $values) {
            $values = array_values(array_filter($values, fn ($v) => $v !== null && $v !== ''));

            $listColLetter = Coordinate::stringFromColumnIndex($colIndex);
            $listSheet->setCellValue("{$listColLetter}1", $targetColumn . '_list');

            $row = 2;
            foreach ($values as $value) {
                $listSheet->setCellValue("{$listColLetter}{$row}", $value);
                $row++;
            }

            // Data validation butuh range yang tidak kosong -- kalau daftar
            // sumbernya kosong (mis. belum ada data mon_rekonsiliasis sama
            // sekali), tetap sediakan 1 baris kosong supaya formula valid.
            $lastRow = max($row - 1, 2);
            $range = "Lists!\${$listColLetter}\$2:\${$listColLetter}\${$lastRow}";

            for ($r = 2; $r <= self::MAX_ROWS + 1; $r++) {
                $validation = $sheet->getCell("{$targetColumn}{$r}")->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(true);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setShowDropDown(true);
                $validation->setErrorTitle('Input tidak valid');
                $validation->setError('Pilih nilai dari daftar dropdown yang tersedia.');
                $validation->setPromptTitle('Pilih dari daftar');
                $validation->setPrompt('Gunakan dropdown atau ketik nilai yang sesuai.');
                $validation->setFormula1($range);
            }

            $colIndex++;
        }

        // Sheet "Lists" cuma sumber dropdown, tidak perlu terlihat/diedit user.
        $listSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    }
}
