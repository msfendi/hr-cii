<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WageRevisionRule extends Model
{
    public const SEGMENT_NON_STAFF    = 'non_staff';
    public const SEGMENT_STAFF_EXPAT  = 'staff_expat';

    public const MODE_PERCENT = 'percent';
    public const MODE_NOMINAL = 'nominal';

    protected $fillable = [
        'batch_id',
        'segment',
        'mode',
        'percent',
        'round_to',
        'warn_below_umr',
    ];

    protected $casts = [
        'percent'        => 'decimal:3',
        'round_to'       => 'integer',
        'warn_below_umr' => 'boolean',
    ];

    public function batch()
    {
        return $this->belongsTo(WageRevisionBatch::class, 'batch_id');
    }
}
