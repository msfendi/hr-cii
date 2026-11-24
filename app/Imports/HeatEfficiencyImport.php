<?php

namespace App\Imports;

use App\Models\HeatEfficiency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Import heat_efficiencies per periode:
 *   1. hapus semua data pada period_id yang dipilih
 *   2. insert data baru dari Excel
 *
 * Kolom template: npk, role, efficiency, piece, date, tim, group
 */
class HeatEfficiencyImport implements ToCollection, WithHeadingRow, WithCalculatedFormulas
{
    protected $periodId;

    public function __construct($periodId)
    {
        $this->periodId = $periodId;
    }

    public function collection(Collection $rows)
    {
        $now  = now();
        $data = [];

        foreach ($rows as $row) {
            // lewati baris kosong
            if (!isset($row['npk']) || trim((string) $row['npk']) === '') {
                continue;
            }

            $data[] = [
                'period_id'  => (string) $this->periodId,
                'npk'        => trim((string) $row['npk']),
                'role'       => strtolower(trim((string) ($row['role'] ?? ''))),
                'date'       => $this->parseDate($row['date'] ?? null),

                // PENTING: semua nilai angka dikirim dengan tipe PHP yang SAMA
                // untuk setiap baris (string desimal). Kalau campur int (0) dan
                // float, SQL Server menganggap kolom itu int dan gagal convert.
                'efficiency' => $this->decimal($row['efficiency'] ?? null),
                'piece'      => $this->decimal($row['piece'] ?? null),

                // kolom tim NOT NULL di database -> kosong = 0 (tanpa pemisahan tim)
                'tim'        => (isset($row['tim']) && $row['tim'] !== '')
                    ? (int) $row['tim']
                    : 0,

                // group: sewing / cutting, kosong = null
                'group'      => (isset($row['group']) && trim((string) $row['group']) !== '')
                    ? strtolower(trim((string) $row['group']))
                    : null,

                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($data)) {
            throw new \Exception('File Excel tidak berisi data yang valid.');
        }

        // pakai koneksi milik model (cii), bukan koneksi default
        $connection = (new HeatEfficiency)->getConnection();

        $connection->beginTransaction();
        try {
            // 1. hapus data lama sesuai periode
            HeatEfficiency::where('period_id', (string) $this->periodId)->delete();

            // 2. insert data baru (10 kolom x 100 baris = 1000 parameter, batas SQL Server 2100)
            foreach (array_chunk($data, 100) as $chunk) {
                HeatEfficiency::insert($chunk);
            }

            $connection->commit();
        } catch (\Throwable $e) {
            Log::error('HeatEfficiencyImport gagal: ' . $e->getMessage());

            try {
                $connection->rollBack();
            } catch (\Throwable $ignored) {
                // transaksi sudah dibatalkan oleh SQL Server
            }

            throw new \Exception($e->getMessage(), 0, $e);
        }
    }

    /**
     * Angka -> string desimal 2 digit ("106.33"). Kosong/non-angka -> "0.00"
     * (kolom efficiency & piece NOT NULL).
     */
    private function decimal($value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }

        return is_numeric($value)
            ? number_format((float) $value, 2, '.', '')
            : '0.00';
    }

    /**
     * Tanggal Excel (angka serial) atau teks -> "Y-m-d". Kosong -> null.
     */
    private function parseDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        $time = strtotime((string) $value);

        return $time ? date('Y-m-d', $time) : null;
    }
}