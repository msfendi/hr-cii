<?php

namespace App\Imports;

use App\Models\WageRevisionBatch;
use App\Services\WageRevisionBuilder;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;

/**
 * Reads the staff/expat salary file and turns it into batch items.
 *
 * It never writes employees_contract: apply stays the single write path, so a bad file cannot
 * reach payroll. Columns: npk, nama, departemen, is_staff, is_expat, gaji_pokok_sekarang,
 * gaji_pokok_baru, nominal_kenaikan, keterangan.
 */
class WageRevisionItemImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    /** @var array<int, array<string, string>> */
    public array $report = [];

    public int $valid = 0;
    public int $invalid = 0;
    public int $skipped = 0;

    public function __construct(
        private WageRevisionBatch $batch,
        private WageRevisionBuilder $builder,
    ) {
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $npk = strtoupper(trim((string) ($row['npk'] ?? '')));

            if ($npk === '') {
                continue;
            }

            $target = $this->numericOrNull($row['gaji_pokok_baru'] ?? null);
            $delta  = $this->numericOrNull($row['nominal_kenaikan'] ?? null);
            $note   = trim((string) ($row['keterangan'] ?? '')) ?: null;

            $mode  = 'target';
            $value = $target;

            if ($target === null && $delta !== null) {
                $mode  = 'delta';
                $value = $delta;
            }

            if ($target === null && $delta === null) {
                $this->record($npk, 'invalid', 'Kolom gaji_pokok_baru atau nominal_kenaikan wajib diisi.');
                continue;
            }

            // Both columns filled: they must agree, otherwise the amount is ambiguous.
            if ($target !== null && $delta !== null) {
                $current   = $this->numericOrNull($row['gaji_pokok_sekarang'] ?? null);
                $expected  = $current !== null ? $current + $delta : null;

                if ($expected !== null && abs($expected - $target) > 0.5) {
                    $this->record($npk, 'invalid', 'gaji_pokok_baru dan nominal_kenaikan tidak konsisten.');
                    continue;
                }
            }

            $item = $this->builder->saveStaffItem($this->batch, $npk, $value, $mode, 'import', $note);

            $this->record($npk, (string) $item->status, (string) $item->message);
        }
    }

    private function numericOrNull($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = preg_replace('/[^0-9,.\-]/', '', (string) $value);

        // Indonesian format: 8.350.000 or 8,350,000 for grouping.
        if (substr_count($clean, ',') === 1 && substr_count($clean, '.') > 0) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif (substr_count($clean, '.') > 1 || (substr_count($clean, '.') === 1 && strlen(substr(strrchr($clean, '.'), 1)) === 3)) {
            $clean = str_replace('.', '', $clean);
        } else {
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function record(string $npk, string $status, string $message): void
    {
        $this->report[] = ['npk' => $npk, 'status' => $status, 'message' => $message];

        match ($status) {
            'valid', 'applied'               => $this->valid++,
            'skipped'                        => $this->skipped++,
            default                          => $this->invalid++,
        };
    }
}
