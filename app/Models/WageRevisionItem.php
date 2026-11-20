<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WageRevisionItem extends Model
{
    public const STATUS_DRAFT       = 'draft';
    public const STATUS_VALID       = 'valid';
    public const STATUS_INVALID     = 'invalid';
    public const STATUS_APPLIED     = 'applied';
    public const STATUS_FAILED      = 'failed';
    public const STATUS_ROLLED_BACK = 'rolled_back';
    public const STATUS_SKIPPED     = 'skipped';

    public const SEGMENT_NON_STAFF   = WageRevisionRule::SEGMENT_NON_STAFF;
    public const SEGMENT_STAFF_EXPAT = WageRevisionRule::SEGMENT_STAFF_EXPAT;

    protected $fillable = [
        'batch_id',
        'npk',
        'segment',
        'contract_id',
        'is_expat',
        'input_mode',
        'input_value',
        'old_salary',
        'old_daily_salary',
        'old_allowance',
        'new_salary',
        'new_daily_salary',
        'new_allowance',
        'delta_monthly',
        'warn_below_umr',
        'warn_overtime_threshold',
        'warn_column_mismatch',
        'status',
        'message',
        'source',
        'applied_at',
    ];

    protected $casts = [
        'is_expat'                 => 'boolean',
        'input_value'              => 'decimal:2',
        'old_salary'               => 'decimal:2',
        'old_daily_salary'         => 'decimal:2',
        'old_allowance'            => 'decimal:2',
        'new_salary'               => 'decimal:2',
        'new_daily_salary'         => 'decimal:2',
        'new_allowance'            => 'decimal:2',
        'delta_monthly'            => 'decimal:2',
        'warn_below_umr'           => 'boolean',
        'warn_overtime_threshold'  => 'boolean',
        'warn_column_mismatch'     => 'boolean',
        'applied_at'               => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(WageRevisionBatch::class, 'batch_id');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_VALID]);
    }
}
