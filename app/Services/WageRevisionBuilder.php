<?php

namespace App\Services;

use App\Models\WageRevisionBatch;
use App\Models\WageRevisionItem;
use App\Models\WageRevisionRule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads the employee master and the active contract, then turns them into batch items.
 *
 * Employee source (same as the payroll job): PKWT for TMK/TKK, union of BIODATA and
 * BIODATA_KELUAR for NPK/name/department/flags, DEPT for the department name and IS_SEWING.
 * Contracts come from employees_contract, picking the row with the highest contract_ke and the
 * newest start_date that covers the effective date.
 */
class WageRevisionBuilder
{
    /** Excluded in the payroll job as well: a placeholder employee without a real contract. */
    public const EXCLUDED_NPK = ['C-00017'];

    /**
     * Jumlah NPK per statement. SQL Server menolak satu statement dengan lebih dari 2.100
     * parameter (SQLSTATE[IMSSP] 2101), sedangkan karyawan aktif bisa lebih dari itu.
     */
    private const NPK_CHUNK = 1000;

    public function __construct(private WageRevisionCalculator $calculator)
    {
    }

    /**
     * Calendar-month window that contains the effective date. Payroll periods are full calendar
     * months, so the 1st of January lands exactly on a period boundary.
     */
    public function periodWindow(string $effectiveDate): array
    {
        $date = Carbon::parse($effectiveDate);

        return [$date->copy()->startOfMonth()->toDateString(), $date->copy()->endOfMonth()->toDateString()];
    }

    private function biodataUnion()
    {
        $select = ['NPK', 'ID_DEPT', 'SECTION', 'NAMA_KARYAWAN', 'IS_STAFF', 'IS_EXPAT'];

        return DB::connection('cii')->table('BIODATA')
            ->select($select)
            ->unionAll(
                DB::connection('cii')->table('BIODATA_KELUAR')->select($select)
            );
    }

    /**
     * Active employees at the effective month, optionally limited to one segment and to the
     * payroll role's own scope.
     */
    public function candidateQuery(string $effectiveDate, ?string $segment = null, ?string $payrollRole = null)
    {
        [$periodStart, $periodEnd] = $this->periodWindow($effectiveDate);

        $query = DB::connection('cii')
            ->table('PKWT as p')
            ->leftJoinSub($this->biodataUnion(), 'bio', function ($join) {
                $join->on('p.NPK', '=', 'bio.NPK');
            })
            ->leftJoin('DEPT as d', 'bio.ID_DEPT', '=', 'd.ID_DEPT')
            ->where('p.TMK', '<=', $periodEnd)
            ->where(function ($q) use ($periodStart) {
                $q->whereNull('p.TKK')->orWhere('p.TKK', '>=', $periodStart);
            })
            ->whereNotIn('p.NPK', self::EXCLUDED_NPK)
            ->select(
                'p.NPK',
                'bio.NAMA_KARYAWAN',
                'bio.ID_DEPT',
                'd.DEPARTEMENT',
                'bio.SECTION',
                'bio.IS_STAFF',
                'd.IS_SEWING',
                'bio.IS_EXPAT'
            )
            ->distinct();

        if ($segment === WageRevisionItem::SEGMENT_STAFF_EXPAT) {
            $query->where(function ($q) {
                $q->where('bio.IS_STAFF', '1')->orWhere('bio.IS_EXPAT', '1');
            });
        } elseif ($segment === WageRevisionItem::SEGMENT_NON_STAFF) {
            $query->where(function ($q) {
                $q->where('bio.IS_STAFF', '0')->orWhereNull('bio.IS_STAFF');
            })->where(function ($q) {
                $q->where('bio.IS_EXPAT', '0')->orWhereNull('bio.IS_EXPAT');
            });
        }

        // Payroll_SEWING / Payroll_NONSEWING only see their own subset. Passing the aliased column
        // names keeps the filter on the same query the payroll screens use.
        if ($payrollRole !== null) {
            $query = PayrollRoleFilterService::applyToQuery($query, $payrollRole, 'bio.IS_STAFF', 'd.IS_SEWING');
        }

        return $query;
    }

    public function isStaffOrExpat($row): bool
    {
        return (string) ($row->IS_STAFF ?? '') === '1' || (string) ($row->IS_EXPAT ?? '') === '1';
    }

    /**
     * Active contract rows for the given NPKs, already filtered to the window that covers the
     * effective date. The newest one per NPK is picked in PHP: one query per chunk instead of
     * one query per row.
     *
     * NPK dipecah per potongan (`NPK_CHUNK`) karena satu statement SQL Server hanya menerima
     * 2.100 parameter; satu NPK hanya ada di satu potongan sehingga urutan contract_ke dan
     * start_date di dalam potongan tetap menentukan kontrak yang terpilih.
     */
    public function contractsFor(array $npks, string $effectiveDate): Collection
    {
        $npks = array_values(array_unique($npks));

        if (empty($npks)) {
            return collect();
        }

        [$periodStart, $periodEnd] = $this->periodWindow($effectiveDate);

        $chunks = [];

        foreach (array_chunk($npks, self::NPK_CHUNK) as $chunk) {
            $chunks[] = DB::connection('cii')
                ->table('employees_contract as ec')
                ->whereIn('ec.npk', $chunk)
                ->whereDate('ec.start_date', '<=', $periodEnd)
                ->whereDate('ec.end_date', '>=', $periodStart)
                ->select(
                    'ec.id',
                    'ec.npk',
                    'ec.type',
                    'ec.salary',
                    'ec.daily_salary',
                    'ec.allowance',
                    'ec.contract_ke',
                    'ec.start_date',
                    'ec.end_date',
                    'ec.status_contract'
                )
                ->orderByRaw('CAST(ec.contract_ke AS INT) DESC')
                ->orderBy('ec.start_date', 'DESC')
                ->get();
        }

        $rows = collect($chunks)->collapse();

        return $rows->groupBy('npk')->map(fn ($group) => $group->first());
    }

    public function contractFor(string $npk, string $effectiveDate)
    {
        return $this->contractsFor([$npk], $effectiveDate)->get($npk);
    }

    private function contractToArray($contract): array
    {
        if (!$contract) {
            return [];
        }

        return [
            'type'         => $contract->type,
            'salary'       => (float) $contract->salary,
            'daily_salary' => (float) $contract->daily_salary,
            'allowance'    => (float) $contract->allowance,
        ];
    }

    /**
     * Generate one item per active non-staff employee from the batch's percent rule.
     * Items that were typed or imported by hand are never overwritten.
     */
    public function buildNonStaffItems(WageRevisionBatch $batch, ?string $payrollRole = null): array
    {
        $rule = $batch->ruleFor(WageRevisionItem::SEGMENT_NON_STAFF);

        if (!$rule || $rule->mode !== WageRevisionRule::MODE_PERCENT || (float) $rule->percent <= 0) {
            return ['created' => 0, 'updated' => 0, 'kept' => 0, 'invalid' => 0, 'skipped' => 0];
        }

        $effectiveDate = optional($batch->effective_date)->toDateString() ?? (string) $batch->effective_date;
        $countDays     = $this->calculator->daysInMonthOf($effectiveDate);

        $candidates = $this->candidateQuery($effectiveDate, WageRevisionItem::SEGMENT_NON_STAFF, $payrollRole)->get();
        $contracts  = $this->contractsFor($candidates->pluck('NPK')->all(), $effectiveDate);
        $existing   = $batch->items()->get()->keyBy('npk');

        $stats = ['created' => 0, 'updated' => 0, 'kept' => 0, 'invalid' => 0, 'skipped' => 0];

        foreach ($candidates as $row) {
            $npk      = (string) $row->NPK;
            $contract = $contracts->get($npk);
            $current  = $existing->get($npk);

            if ($current && $current->source !== 'generated') {
                $stats['kept']++;
                continue;
            }

            if (!$contract) {
                $this->upsertItem($batch, $npk, [
                    'segment'   => WageRevisionItem::SEGMENT_NON_STAFF,
                    'is_expat'  => false,
                    'status'    => WageRevisionItem::STATUS_SKIPPED,
                    'message'   => 'Tidak ada kontrak yang menutupi tanggal efektif.',
                    'source'    => 'generated',
                ]);
                $stats['skipped']++;
                continue;
            }

            $calc = $this->calculator->forNonStaff($this->contractToArray($contract), [
                'percent'        => (float) $rule->percent,
                'round_to'       => (int) $rule->round_to,
                'warn_below_umr' => (bool) $rule->warn_below_umr,
            ], [
                'count_days' => $countDays,
                'umr_new'    => (float) $batch->umr_new,
            ]);

            $this->upsertItem($batch, $npk, [
                'segment'                  => WageRevisionItem::SEGMENT_NON_STAFF,
                'contract_id'              => (string) $contract->id,
                'is_expat'                 => false,
                'input_mode'               => $calc['input_mode'],
                'input_value'              => $calc['input_value'],
                'old_salary'               => (float) $contract->salary,
                'old_daily_salary'         => (float) $contract->daily_salary,
                'old_allowance'            => (float) $contract->allowance,
                'new_salary'               => $calc['new_salary'],
                'new_daily_salary'         => $calc['new_daily_salary'],
                'new_allowance'            => $calc['new_allowance'],
                'delta_monthly'            => $calc['delta_monthly'],
                'warn_below_umr'           => $calc['warn_below_umr'],
                'warn_overtime_threshold'  => $calc['warn_overtime_threshold'],
                'warn_column_mismatch'     => $calc['warn_column_mismatch'],
                'status'                   => $calc['status'],
                'message'                  => $calc['message'],
                'source'                   => 'generated',
            ]);

            $calc['status'] === WageRevisionItem::STATUS_INVALID ? $stats['invalid']++ : ($current ? $stats['updated']++ : $stats['created']++);
        }

        return $stats;
    }

    /**
     * Create or refresh one staff/expat item. Returns the stored item; an item with an
     * invalid/skipped status carries the reason in `message`.
     */
    public function saveStaffItem(
        WageRevisionBatch $batch,
        string $npk,
        $value,
        string $mode = 'target',
        string $source = 'manual',
        ?string $note = null
    ): WageRevisionItem {
        $effectiveDate = optional($batch->effective_date)->toDateString() ?? (string) $batch->effective_date;
        $countDays     = $this->calculator->daysInMonthOf($effectiveDate);

        $candidate = $this->candidateQuery($effectiveDate, WageRevisionItem::SEGMENT_STAFF_EXPAT)
            ->where('p.NPK', $npk)
            ->first();

        if (!$candidate) {
            return $this->upsertItem($batch, $npk, [
                'segment'  => WageRevisionItem::SEGMENT_STAFF_EXPAT,
                'status'   => WageRevisionItem::STATUS_INVALID,
                'message'  => 'NPK bukan karyawan staff/expat yang aktif pada tanggal efektif.',
                'source'   => $source,
                'input_mode'  => $mode,
                'input_value' => $value !== null ? (float) $value : null,
            ]);
        }

        $contract = $this->contractFor($npk, $effectiveDate);

        // Nilai di bawah Rp1.000 tidak wajar untuk gaji bulanan: biasanya pemisah ribuan
        // ("250.000") terbaca sebagai desimal sehingga menjadi 250. Tolak, jangan simpan.
        if ($value !== null && (float) $value < 1000) {
            return $this->upsertItem($batch, $npk, [
                'segment'  => WageRevisionItem::SEGMENT_STAFF_EXPAT,
                'is_expat' => (string) $candidate->IS_EXPAT === '1',
                'status'   => WageRevisionItem::STATUS_INVALID,
                'message'  => 'Nilai di bawah Rp1.000 tidak wajar. Tulis angka penuh tanpa pemisah, contoh: 250000.',
                'source'   => $source,
                'input_mode'  => $mode,
                'input_value' => (float) $value,
            ]);
        }

        if (!$contract) {
            return $this->upsertItem($batch, $npk, [
                'segment'  => WageRevisionItem::SEGMENT_STAFF_EXPAT,
                'is_expat' => (string) $candidate->IS_EXPAT === '1',
                'status'   => WageRevisionItem::STATUS_SKIPPED,
                'message'  => 'Tidak ada kontrak yang menutupi tanggal efektif.',
                'source'   => $source,
                'input_mode'  => $mode,
                'input_value' => $value !== null ? (float) $value : null,
            ]);
        }

        $rule = $batch->ruleFor(WageRevisionItem::SEGMENT_STAFF_EXPAT);

        $calc = $this->calculator->forStaff($this->contractToArray($contract), [
            'mode'  => $mode,
            'value' => $value,
        ], [
            'count_days' => $countDays,
            'umr_new'    => (float) $batch->umr_new,
            'warn_below_umr' => $rule ? (bool) $rule->warn_below_umr : true,
        ]);

        return $this->upsertItem($batch, $npk, [
            'segment'                  => WageRevisionItem::SEGMENT_STAFF_EXPAT,
            'contract_id'              => (string) $contract->id,
            'is_expat'                 => (string) $candidate->IS_EXPAT === '1',
            'input_mode'               => $calc['input_mode'],
            'input_value'              => $calc['input_value'],
            'old_salary'               => (float) $contract->salary,
            'old_daily_salary'         => (float) $contract->daily_salary,
            'old_allowance'            => (float) $contract->allowance,
            'new_salary'               => $calc['new_salary'],
            'new_daily_salary'         => $calc['new_daily_salary'],
            'new_allowance'            => $calc['new_allowance'],
            'delta_monthly'            => $calc['delta_monthly'],
            'warn_below_umr'           => $calc['warn_below_umr'],
            'warn_overtime_threshold'  => $calc['warn_overtime_threshold'],
            'warn_column_mismatch'     => $calc['warn_column_mismatch'],
            'status'                   => $calc['status'],
            'message'                  => $calc['message'] ?? $note,
            'source'                   => $source,
        ]);
    }

    private function upsertItem(WageRevisionBatch $batch, string $npk, array $attributes): WageRevisionItem
    {
        $item = $batch->items()->firstOrNew(['npk' => $npk]);

        $item->fill(array_merge([
            'batch_id' => $batch->id,
            'npk'      => $npk,
        ], $attributes));

        $item->save();

        return $item;
    }

    /**
     * Drop generated items that were never touched by hand, so "Hitung ulang" can rebuild them
     * after the percent changes. Manually typed or imported items stay.
     */
    public function clearGeneratedItems(WageRevisionBatch $batch): int
    {
        return $batch->items()
            ->whereIn('segment', [WageRevisionItem::SEGMENT_NON_STAFF])
            ->where('source', 'generated')
            ->whereIn('status', [WageRevisionItem::STATUS_DRAFT, WageRevisionItem::STATUS_VALID, WageRevisionItem::STATUS_SKIPPED, WageRevisionItem::STATUS_INVALID])
            ->delete();
    }
}
