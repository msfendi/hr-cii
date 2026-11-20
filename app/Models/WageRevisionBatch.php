<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WageRevisionBatch extends Model
{
    use HasFactory;

    public const STATUS_DRAFT      = 'draft';
    public const STATUS_SIMULATED  = 'simulated';
    public const STATUS_APPROVED   = 'approved';
    public const STATUS_APPLYING   = 'applying';
    public const STATUS_APPLIED    = 'applied';
    public const STATUS_ROLLED_BACK = 'rolled_back';
    public const STATUS_CANCELLED  = 'cancelled';

    protected $fillable = [
        'year',
        'umr_new',
        'effective_date',
        'status',
        'note',
        'created_by',
        'approved_by',
        'applied_at',
    ];

    protected $casts = [
        'year'           => 'integer',
        'umr_new'        => 'decimal:2',
        'effective_date' => 'date',
        'applied_at'     => 'datetime',
    ];

    public function rules()
    {
        return $this->hasMany(WageRevisionRule::class, 'batch_id');
    }

    public function items()
    {
        return $this->hasMany(WageRevisionItem::class, 'batch_id');
    }

    public function ruleFor(string $segment): ?WageRevisionRule
    {
        return $this->rules->firstWhere('segment', $segment);
    }

    /**
     * A batch may only be edited while it is still a draft/simulation.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SIMULATED], true);
    }

    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }

    /**
     * Batch yang sudah dibatalkan (gaji lama kembali). Masih terkunci sampai admin membuka
     * kuncinya, karena pemrosesan ulang perlu keputusan admin.
     */
    public function isRolledBack(): bool
    {
        return $this->status === self::STATUS_ROLLED_BACK;
    }
}
