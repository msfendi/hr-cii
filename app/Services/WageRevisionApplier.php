<?php

namespace App\Services;

use App\Models\EmployeeWageRevision;
use App\Models\WageRevisionBatch;
use App\Models\WageRevisionItem;
use App\Models\WageRevisionRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes the batch to employees_contract.
 *
 * Apply is per item and idempotent, not one big transaction: the batch tables live on the default
 * connection while employees_contract lives on the `cii` SQL Server connection, so no single
 * transaction can cover both. Each item: insert the revision row first (old values), then update
 * the contract, then mark the item applied. A single failure marks only that item failed and the
 * batch stays resumable.
 */
class WageRevisionApplier
{
    /**
     * The batch must be applied before the payroll run of that period exists, otherwise January
     * payroll would still use the old wage.
     */
    public function payrollRunExistsFor(string $effectiveDate): bool
    {
        return DB::table('payroll_runs')
            ->join('payroll_periods', 'payroll_periods.id', '=', 'payroll_runs.period_id')
            ->whereDate('payroll_periods.start_date', '<=', $effectiveDate)
            ->whereDate('payroll_periods.end_date', '>=', $effectiveDate)
            ->exists();
    }

    public function apply(WageRevisionBatch $batch, ?int $userId = null): array
    {
        $effectiveDate = optional($batch->effective_date)->toDateString() ?? (string) $batch->effective_date;

        if (!in_array($batch->status, [WageRevisionBatch::STATUS_APPROVED, WageRevisionBatch::STATUS_APPLYING], true)) {
            return ['applied' => 0, 'failed' => 0, 'skipped' => 0, 'error' => 'Batch harus berstatus approved sebelum diterapkan.'];
        }

        $batch->update(['status' => WageRevisionBatch::STATUS_APPLYING]);

        $stats = ['applied' => 0, 'failed' => 0, 'skipped' => 0, 'error' => null];

        $items = $batch->items()
            ->where('status', WageRevisionItem::STATUS_VALID)
            ->orderBy('npk')
            ->get();

        foreach ($items as $item) {
            try {
                $this->applyItem($batch, $item, $userId, $effectiveDate);
                $stats['applied']++;
            } catch (Throwable $e) {
                $item->update([
                    'status'  => WageRevisionItem::STATUS_FAILED,
                    'message' => substr($e->getMessage(), 0, 250),
                ]);
                $stats['failed']++;
                Log::warning('Wage revision item failed', ['npk' => $item->npk, 'error' => $e->getMessage()]);
            }
        }

        $stats['skipped'] = $batch->items()->count() - $items->count();

        if ($stats['failed'] === 0) {
            $batch->update([
                'status'     => WageRevisionBatch::STATUS_APPLIED,
                'applied_at' => now(),
            ]);
        }

        return $stats;
    }

    private function applyItem(WageRevisionBatch $batch, WageRevisionItem $item, ?int $userId, string $effectiveDate): void
    {
        // Already applied in an earlier run: nothing to do, do not duplicate the revision row.
        if ($item->status === WageRevisionItem::STATUS_APPLIED) {
            return;
        }

        $contract = DB::connection('cii')
            ->table('employees_contract')
            ->where('id', $item->contract_id)
            ->first();

        if (!$contract) {
            throw new \RuntimeException('Kontrak tidak ditemukan pada koneksi cii.');
        }

        // The contract changed after the simulation: refuse rather than overwrite an unknown value.
        if ((float) $contract->salary !== (float) $item->old_salary
            || (float) $contract->daily_salary !== (float) $item->old_daily_salary
            || (float) $contract->allowance !== (float) $item->old_allowance) {
            throw new \RuntimeException('Nilai kontrak berubah sejak simulasi, jalankan simulasi ulang.');
        }

        if (!EmployeeWageRevision::where('npk', $item->npk)
            ->whereDate('effective_date', $effectiveDate)
            ->where('batch_id', $batch->id)
            ->exists()) {
            EmployeeWageRevision::create([
                'batch_id'         => $batch->id,
                'npk'              => $item->npk,
                'contract_id'      => $item->contract_id,
                'effective_date'   => $effectiveDate,
                'policy'           => $item->input_mode === WageRevisionRule::MODE_PERCENT ? 'percent' : 'nominal',
                'percent'          => $item->input_mode === WageRevisionRule::MODE_PERCENT ? $item->input_value : null,
                'umr_reference'    => $batch->umr_new,
                'old_salary'       => $item->old_salary,
                'new_salary'       => $item->new_salary,
                'old_daily_salary' => $item->old_daily_salary,
                'new_daily_salary' => $item->new_daily_salary,
                'old_allowance'    => $item->old_allowance,
                'new_allowance'    => $item->new_allowance,
                'note'             => $item->message,
                'created_by'       => $userId,
            ]);
        }

        DB::connection('cii')
            ->table('employees_contract')
            ->where('id', $item->contract_id)
            ->update([
                'salary'       => $item->new_salary,
                'daily_salary' => $item->new_daily_salary,
                'allowance'    => $item->new_allowance,
                'updated_at'   => now(),
            ]);

        $item->update([
            'status'     => WageRevisionItem::STATUS_APPLIED,
            'applied_at' => now(),
        ]);
    }

    public function rollback(WageRevisionBatch $batch, ?int $userId = null): array
    {
        if (!$batch->isApplied() && $batch->status !== WageRevisionBatch::STATUS_APPLYING) {
            return ['restored' => 0, 'failed' => 0, 'error' => 'Hanya batch yang sudah diterapkan bisa dibatalkan.'];
        }

        $effectiveDate = optional($batch->effective_date)->toDateString() ?? (string) $batch->effective_date;
        $stats = ['restored' => 0, 'failed' => 0, 'error' => null];

        $revisions = EmployeeWageRevision::where('batch_id', $batch->id)->orderByDesc('id')->get();

        foreach ($revisions as $revision) {
            try {
                $contract = DB::connection('cii')
                    ->table('employees_contract')
                    ->where('id', $revision->contract_id)
                    ->first();

                if (!$contract) {
                    throw new \RuntimeException('Kontrak tidak ditemukan pada koneksi cii.');
                }

                DB::connection('cii')
                    ->table('employees_contract')
                    ->where('id', $revision->contract_id)
                    ->update([
                        'salary'       => $revision->old_salary,
                        'daily_salary' => $revision->old_daily_salary,
                        'allowance'    => $revision->old_allowance,
                        'updated_at'   => now(),
                    ]);

                $batch->items()->where('npk', $revision->npk)->update([
                    'status'     => WageRevisionItem::STATUS_ROLLED_BACK,
                    'applied_at' => null,
                ]);

                $stats['restored']++;
            } catch (Throwable $e) {
                $stats['failed']++;
                Log::warning('Wage revision rollback failed', ['npk' => $revision->npk, 'error' => $e->getMessage()]);
            }
        }

        if ($stats['failed'] === 0) {
            $batch->update([
                'status'     => WageRevisionBatch::STATUS_ROLLED_BACK,
                'applied_at' => null,
            ]);
        }

        return $stats;
    }

    /**
     * Buka kunci batch yang sudah dibatalkan supaya bisa diproses lagi (hanya admin, dijaga policy).
     *
     * Ledger batch dibersihkan karena gajinya sudah dikembalikan ke nilai lama: dengan begitu
     * penerapan ulang menulis ledger baru yang sesuai nilai kontrak saat itu, dan pembatalan
     * berikutnya selalu memakai sumber yang benar. Item yang tadinya `rolled_back` dikembalikan
     * ke `valid` supaya bisa disetujui dan diterapkan lagi.
     */
    public function unlock(WageRevisionBatch $batch): array
    {
        if ($batch->status !== WageRevisionBatch::STATUS_ROLLED_BACK) {
            return [
                'restored_items' => 0,
                'cleared_ledger' => 0,
                'error'          => 'Hanya batch yang sudah dibatalkan (rolled_back) yang bisa dibuka kuncinya.',
            ];
        }

        $clearedLedger = EmployeeWageRevision::where('batch_id', $batch->id)->delete();

        $restoredItems = $batch->items()
            ->where('status', WageRevisionItem::STATUS_ROLLED_BACK)
            ->update(['status' => WageRevisionItem::STATUS_VALID, 'applied_at' => null]);

        // Item yang sudah ada dan valid berarti batch kembali ke status simulasi (siap disetujui),
        // bukan draft kosong: itulah yang dipakai UI untuk menampilkan langkah berikutnya.
        $validItems = $batch->items()->where('status', WageRevisionItem::STATUS_VALID)->count();

        $batch->update([
            'status'     => $validItems > 0
                ? WageRevisionBatch::STATUS_SIMULATED
                : WageRevisionBatch::STATUS_DRAFT,
            'applied_at' => null,
        ]);

        return [
            'restored_items' => (int) $restoredItems,
            'cleared_ledger' => (int) $clearedLedger,
            'error'          => null,
        ];
    }
}
