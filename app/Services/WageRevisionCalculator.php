<?php

namespace App\Services;

use App\Models\WageRevisionItem;
use App\Models\WageRevisionRule;

/**
 * Pure wage arithmetic for one revision item. No database access, so it can be unit tested.
 *
 * Contract shape expected as input:
 *   ['type' => 'Contract'|'Daily', 'salary' => float, 'daily_salary' => float, 'allowance' => float]
 *
 * Output shape (same for both segments):
 *   [
 *     'status', 'message', 'input_mode', 'input_value',
 *     'new_salary', 'new_daily_salary', 'new_allowance', 'delta_monthly',
 *     'warn_below_umr', 'warn_overtime_threshold', 'warn_column_mismatch',
 *     'changed_column', 'basic_wage_old', 'basic_wage_new',
 *   ]
 */
class WageRevisionCalculator
{
    /** Statutory overtime threshold. Fixed by decision: never parameterised here. */
    public const OVERTIME_THRESHOLD = 3800000.0;

    /** Reporting helper only (monthly comparison of a daily wage). Payroll never uses this. */
    public const MONTHLY_DAY_FACTOR = 21;

    /**
     * Round to the nearest rupiah step (100, 500, 1000, ...). A step of 0 or 1 means "no rounding".
     */
    public function roundTo(float $value, int $step): float
    {
        if ($step <= 1) {
            return round($value, 2);
        }

        return round($value / $step) * $step;
    }

    /**
     * Normalised contract type, or null when the value is unknown/empty.
     */
    public function contractType(array $contract): ?string
    {
        $type = ucfirst(strtolower(trim((string) ($contract['type'] ?? ''))));

        return in_array($type, ['Contract', 'Daily'], true) ? $type : null;
    }

    public function daysInMonthOf(string $date): int
    {
        return (int) date('t', strtotime($date));
    }

    /**
     * Basic wage (without allowance) as payroll sees it for the period.
     */
    public function basicWage(array $contract, int $countDays): float
    {
        $type = $this->contractType($contract);

        if ($type === 'Daily') {
            return (float) ($contract['daily_salary'] ?? 0) * $countDays;
        }

        return (float) ($contract['salary'] ?? 0);
    }

    /**
     * Mirrors the overtime eligibility test used by the payroll jobs and the recap:
     *   (salary + allowance) >= 3.800.000 OR (daily_salary * count_days + allowance) >= 3.800.000
     * Above that line the overtime pay is zeroed, which is why a raise across it is warned about.
     */
    public function isOverOvertimeThreshold(array $contract, int $countDays): bool
    {
        $salaryPlusAllowance = (float) ($contract['salary'] ?? 0) + (float) ($contract['allowance'] ?? 0);
        $dailyPlusAllowance  = ((float) ($contract['daily_salary'] ?? 0) * $countDays) + (float) ($contract['allowance'] ?? 0);

        return $salaryPlusAllowance >= self::OVERTIME_THRESHOLD || $dailyPlusAllowance >= self::OVERTIME_THRESHOLD;
    }

    /**
     * Non-staff rule: one uniform percent for every employee, applied to the single column the
     * payroll formula actually reads for that contract type. No floor is applied to the minimum
     * wage; being below it only raises a warning.
     */
    public function forNonStaff(array $contract, array $rule, array $options = []): array
    {
        $countDays = (int) ($options['count_days'] ?? self::MONTHLY_DAY_FACTOR);
        $umrNew    = (float) ($options['umr_new'] ?? 0);
        $warnUmr   = (bool) ($rule['warn_below_umr'] ?? true);
        $roundTo   = (int) ($rule['round_to'] ?? 100);
        $percent   = $rule['percent'];

        $oldSalary = (float) ($contract['salary'] ?? 0);
        $oldDaily  = (float) ($contract['daily_salary'] ?? 0);
        $oldAllow  = (float) ($contract['allowance'] ?? 0);

        $base = [
            'input_mode'               => WageRevisionRule::MODE_PERCENT,
            'input_value'              => $percent !== null ? (float) $percent : null,
            'new_salary'               => $oldSalary,
            'new_daily_salary'         => $oldDaily,
            'new_allowance'            => $oldAllow,
            'delta_monthly'            => 0.0,
            'warn_below_umr'           => false,
            'warn_overtime_threshold'  => false,
            'warn_column_mismatch'     => false,
            'changed_column'           => null,
            'basic_wage_old'           => $this->basicWage($contract, $countDays),
            'basic_wage_new'           => $this->basicWage($contract, $countDays),
        ];

        if ($percent === null || (float) $percent <= 0) {
            return array_merge($base, [
                'status'  => WageRevisionItem::STATUS_INVALID,
                'message' => 'Persentase kenaikan belum diisi.',
            ]);
        }

        $type = $this->contractType($contract);

        if ($type === null) {
            return array_merge($base, [
                'status'  => WageRevisionItem::STATUS_INVALID,
                'message' => 'Type kontrak tidak dikenal, kolom yang dinaikkan tidak bisa ditentukan.',
            ]);
        }

        $factor   = 1 + ((float) $percent / 100);
        $newRow   = $contract;
        $column   = $type === 'Daily' ? 'daily_salary' : 'salary';

        $newRow[$column] = $this->roundTo(((float) $contract[$column]) * $factor, $roundTo);

        $result = array_merge($base, [
            'status'          => WageRevisionItem::STATUS_VALID,
            'message'         => null,
            'new_salary'      => (float) $newRow['salary'],
            'new_daily_salary' => (float) $newRow['daily_salary'],
            'new_allowance'   => $oldAllow,
            'changed_column'  => $column,
            'basic_wage_new'  => $this->basicWage($newRow, $countDays),
        ]);

        $result['delta_monthly'] = round($result['basic_wage_new'] - $result['basic_wage_old'], 2);
        $result['warn_below_umr'] = $warnUmr && $umrNew > 0 && $result['basic_wage_new'] < $umrNew;
        $result['warn_overtime_threshold'] = !$this->isOverOvertimeThreshold($contract, $countDays)
            && $this->isOverOvertimeThreshold($newRow, $countDays);

        // Daily contracts: the overtime and absence formulas read the `salary` column through the
        // basic_salary variable, so a non-zero salary there keeps part of the payroll unchanged.
        $result['warn_column_mismatch'] = $type === 'Daily' && $oldSalary > 0;

        return $result;
    }

    /**
     * Staff/expat rule: nominal per employee. Default mode is the new gross amount (target);
     * delta mode is also supported.
     */
    public function forStaff(array $contract, array $input, array $options = []): array
    {
        $countDays = (int) ($options['count_days'] ?? self::MONTHLY_DAY_FACTOR);
        $umrNew    = (float) ($options['umr_new'] ?? 0);
        $warnUmr   = (bool) ($options['warn_below_umr'] ?? true);

        $mode  = (string) ($input['mode'] ?? 'target');
        $value = $input['value'] ?? null;

        $oldSalary = (float) ($contract['salary'] ?? 0);
        $oldDaily  = (float) ($contract['daily_salary'] ?? 0);
        $oldAllow  = (float) ($contract['allowance'] ?? 0);

        $base = [
            'input_mode'               => $mode === WageRevisionRule::MODE_NOMINAL ? WageRevisionRule::MODE_NOMINAL : $mode,
            'input_value'              => $value !== null ? (float) $value : null,
            'new_salary'               => $oldSalary,
            'new_daily_salary'         => $oldDaily,
            'new_allowance'            => $oldAllow,
            'delta_monthly'            => 0.0,
            'warn_below_umr'           => false,
            'warn_overtime_threshold'  => false,
            'warn_column_mismatch'     => false,
            'changed_column'           => 'salary',
            'basic_wage_old'           => $oldSalary,
            'basic_wage_new'           => $oldSalary,
        ];

        if (!in_array($mode, ['target', 'delta'], true)) {
            return array_merge($base, [
                'status'  => WageRevisionItem::STATUS_INVALID,
                'message' => 'Mode input tidak dikenal (target atau delta).',
            ]);
        }

        if ($value === null || (float) $value <= 0) {
            return array_merge($base, [
                'status'  => WageRevisionItem::STATUS_INVALID,
                'message' => 'Nominal wajib diisi.',
            ]);
        }

        $newSalary = $mode === 'delta'
            ? $oldSalary + (float) $value
            : (float) $value;

        if ($newSalary < $oldSalary) {
            return array_merge($base, [
                'status'  => WageRevisionItem::STATUS_INVALID,
                'message' => 'Gaji baru lebih kecil dari gaji sekarang.',
            ]);
        }

        $newRow = $contract;
        $newRow['salary'] = $newSalary;

        $result = array_merge($base, [
            'status'          => WageRevisionItem::STATUS_VALID,
            'message'         => null,
            'new_salary'      => $newSalary,
            'new_daily_salary' => $oldDaily,
            'new_allowance'   => $oldAllow,
            'changed_column'  => 'salary',
            'basic_wage_new'  => $newSalary,
        ]);

        $result['delta_monthly'] = round($newSalary - $oldSalary, 2);
        $result['warn_below_umr'] = $warnUmr && $umrNew > 0 && $newSalary < $umrNew;
        $result['warn_overtime_threshold'] = !$this->isOverOvertimeThreshold($contract, $countDays)
            && $this->isOverOvertimeThreshold($newRow, $countDays);

        return $result;
    }
}
