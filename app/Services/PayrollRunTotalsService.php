<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Menjaga header payroll_runs tetap sama dengan isi payroll_run_details.
 *
 * Kenapa perlu: /payroll-process/index menampilkan payroll_runs.total_payroll
 * (snapshot saat generate) untuk role Payroll_ALL, sedangkan /payroll/recap
 * menjumlah payroll_run_details.total_salary secara langsung. Begitu detail
 * diubah setelah generate (mis. koreksi PPh 21), dua halaman itu berbeda.
 */
class PayrollRunTotalsService
{
    /**
     * Hitung ulang total_payroll + employee_count dari detail milik run.
     *
     * @return array{total_payroll: float, employee_count: int}
     */
    public static function sync(int $runId): array
    {
        $agg = DB::table('payroll_run_details')
            ->where('run_id', $runId)
            ->selectRaw('COALESCE(SUM(total_salary), 0) as total')
            ->selectRaw('COUNT(DISTINCT employee_npk) as jumlah')
            ->first();

        $total  = round((float) ($agg->total ?? 0), 0);
        $jumlah = (int) ($agg->jumlah ?? 0);

        DB::table('payroll_runs')
            ->where('id', $runId)
            ->update([
                'total_payroll'  => $total,
                'employee_count' => $jumlah,
            ]);

        return [
            'total_payroll'  => $total,
            'employee_count' => $jumlah,
        ];
    }
}