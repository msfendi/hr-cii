<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Cell;
use Maatwebsite\Excel\Sheet;
use Maatwebsite\Excel\Events\AfterSheet;

class FastExcelExport
{
    public static function store($export, $path)
    {
        $writer = new Writer();
        $writer->openToFile(storage_path("app/$path"));

        // support multi sheets
        // support multi sheets
        if (method_exists($export, 'sheets')) {

            $sheets = $export->sheets();
            $isFirstSheet = true;

            foreach ($sheets as $sheetExport) {

                // pakai default sheet pertama
                if (!$isFirstSheet) {
                    $writer->addNewSheetAndMakeItCurrent();
                }

                $isFirstSheet = false;

                // set title
                if (method_exists($sheetExport, 'title')) {
                    $writer->getCurrentSheet()->setName($sheetExport->title());
                }

                $buffer = new SheetBuffer();

                $query = $sheetExport->query();

                foreach ($query->cursor() as $row) {

                    $mapped = self::safeRow($sheetExport->map($row));

                    // 🔥 SKIP EMPTY ROW (SUMMARY MODE)
                    if (count(array_filter($mapped, fn($v) => $v !== null && $v !== '')) === 0) {
                        continue;
                    }

                    $buffer->addRow($mapped);
                }

                // headings
                if (method_exists($sheetExport, 'headings')) {
                    $headings = $sheetExport->headings();

                    if (self::isMultiRow($headings)) {
                        foreach (array_reverse($headings) as $hRow) {
                            $buffer->prependRow(self::safeRow($hRow));
                        }
                    } else {
                        $buffer->prependRow(self::safeRow($headings));
                    }
                }

                // AFTERSHEET tetap sama (tidak perlu diubah)

                if (method_exists($sheetExport, 'registerEvents')) {

                    $events = $sheetExport->registerEvents();

                    if (isset($events[AfterSheet::class])) {

                        /*
        |--------------------------------------------------------------------------
        | Fake Sheet Compatible Laravel Excel & FastExcel
        |--------------------------------------------------------------------------
        */

                        $fakeSheet = new class($buffer) {

                            public function __construct(public $buffer) {}

                            public function setCellValue($cell, $value)
                            {
                                $this->buffer->setCellValue($cell, $value);
                            }

                            // Laravel Excel compatibility
                            public function getDelegate()
                            {
                                return $this;
                            }

                            public function getStyle()
                            {
                                return $this;
                            }

                            public function getNumberFormat()
                            {
                                return $this;
                            }

                            public function setFormatCode()
                            {
                                return $this;
                            }
                        };

                        /*
        |--------------------------------------------------------------------------
        | Fake AfterSheet Event
        |--------------------------------------------------------------------------
        */

                        $event = new class($fakeSheet) {
                            public function __construct(public $sheet) {}
                        };

                        // EXECUTE ORIGINAL EVENT
                        $events[AfterSheet::class]($event);
                    }
                }

                // write rows
                foreach ($buffer->rows as $row) {
                    $writer->addRow(Row::fromValues($row));
                }
            }
        } else {

            // single sheet fallback
            $buffer = new SheetBuffer();

            $buffer->addRow(self::safeRow($export->headings()));

            foreach ($export->query()->cursor() as $row) {
                $buffer->addRow(self::safeRow($export->map($row)));
            }

            foreach ($buffer->rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }
        }

        $currentSheet = $writer->getCurrentSheet();

        foreach ($buffer->widths as $i => $width) {
            $currentSheet->setColumnWidth($i + 1, min($width + 2, 50));
        }

        $writer->close();
    }

    /* ==============================
     * SAFE VALUE FIX (ARRAY ERROR FIX)
     * ============================== */
    private static function safeRow($row): array
    {
        return array_map(function ($cell) {
            if (is_array($cell)) {
                return json_encode($cell);
            }
            return $cell;
        }, $row);
    }

    private static function isMultiRow($array): bool
    {
        return is_array($array) && isset($array[0]) && is_array($array[0]);
    }
}

/* =========================================================
 | SHEET BUFFER (SIMULATE PHPSPREADSHEET MEMORY)
 ========================================================= */
class SheetBuffer
{
    public array $rows = [];

    public array $widths = [];

    public function addRow(array $row)
    {
        foreach ($row as $i => $cell) {

            $len = mb_strlen((string)$cell);

            if (!isset($this->widths[$i])) {
                $this->widths[$i] = $len;
            } else {
                $this->widths[$i] = max($this->widths[$i], $len);
            }
        }

        $this->rows[] = $row;
    }

    public function prependRow(array $row)
    {
        array_unshift($this->rows, $row);
    }

    public function setCellValue(string $cell, $value)
    {
        [$col, $row] = $this->cellToIndex($cell);

        $this->rows[$row - 1][$col - 1] = $value;
    }

    private function cellToIndex($cell)
    {
        preg_match('/([A-Z]+)([0-9]+)/', $cell, $matches);

        $col = $this->lettersToNumber($matches[1]);
        $row = (int)$matches[2];

        return [$col, $row];
    }

    private function lettersToNumber($letters)
    {
        $num = 0;
        $len = strlen($letters);

        for ($i = 0; $i < $len; $i++) {
            $num = $num * 26 + (ord($letters[$i]) - 64);
        }

        return $num;
    }
}

/* =========================================================
 | FAKE SHEET (FOR AfterSheet COMPATIBILITY)
 ========================================================= */
class FakeSheet
{
    public SheetBuffer $buffer;

    public function __construct($buffer)
    {
        $this->buffer = $buffer;
    }

    public function setCellValue($cell, $value)
    {
        $this->buffer->setCellValue($cell, $value);
    }
}
