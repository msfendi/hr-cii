<?php

namespace App\Imports;

use App\Models\EmployeeLate;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\Importable;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Import Employee Late dari Excel/CSV.
 *
 * Kolom yang diharapkan (heading row, urutan bebas): NPK, Date, Arrival Time, Reason.
 * WithHeadingRow otomatis mengubah heading menjadi snake_case:
 *   NPK -> npk, Date -> date, Arrival Time -> arrival_time, Reason -> reason
 *
 * Penanganan khusus:
 * - Kolom Date & Arrival Time bisa datang dalam bentuk serial number Excel
 *   (misal saat cell diformat sebagai Date/Time) ATAU dalam bentuk string
 *   biasa (misal "2026-07-03", "03/07/2026", "8:30", "08:30:00").
 * - Baris dengan NPK kosong, atau format Date/Arrival Time yang tidak bisa
 *   diparsing sama sekali, akan DILEWATI (tidak menyebabkan import gagal
 *   total) dan dicatat di $failedRows supaya bisa ditampilkan ke user.
 */
class EmployeeLateImport implements ToModel, WithHeadingRow, SkipsEmptyRows
{
    use Importable;

    /** @var string[] */
    public array $failedRows = [];

    public int $importedCount = 0;

    /** Row counter dimulai dari 1 karena baris 1 adalah heading. */
    private int $rowNumber = 1;

    public function model(array $row)
    {
        $this->rowNumber++;

        $npk = isset($row['npk']) ? trim((string) $row['npk']) : '';

        if ($npk === '') {
            $this->failedRows[] = "Baris {$this->rowNumber}: NPK kosong, baris dilewati.";
            return null;
        }

        $rawDate = $row['date'] ?? null;
        $date = $this->parseDate($rawDate);

        if (!$date) {
            $this->failedRows[] = "Baris {$this->rowNumber}: format Date tidak valid ('" . $this->rawValueForLog($rawDate) . "'), baris dilewati.";
            return null;
        }

        $rawTime = $row['arrival_time'] ?? null;
        $time = $this->parseTime($rawTime);

        if (!$time) {
            $this->failedRows[] = "Baris {$this->rowNumber}: format Arrival Time tidak valid ('" . $this->rawValueForLog($rawTime) . "'), baris dilewati.";
            return null;
        }

        $this->importedCount++;

        return new EmployeeLate([
            'npk'          => $npk,
            'date'         => $date,
            'arrival_time' => $time,
            'reason'       => $row['reason'] ?? null,
        ]);
    }

    /**
     * Konversi nilai Date dari Excel (serial number ATAU string) menjadi 'Y-m-d'.
     * Return null jika tidak bisa diparsing sama sekali (bukan exception).
     */
    private function parseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            }

            return Carbon::parse(trim((string) $value))->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Konversi nilai Arrival Time dari Excel (serial number ATAU string) menjadi 'H:i:s'.
     * Return null jika tidak bisa diparsing sama sekali (bukan exception).
     */
    private function parseTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                // Excel time disimpan sebagai pecahan hari (mis. 0.354166... = 08:30)
                return ExcelDate::excelToDateTimeObject((float) $value)->format('H:i:s');
            }

            return Carbon::parse(trim((string) $value))->format('H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function rawValueForLog($value): string
    {
        if ($value === null) {
            return '-';
        }

        return (string) $value;
    }
}
