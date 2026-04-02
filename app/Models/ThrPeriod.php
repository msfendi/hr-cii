<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThrPeriod extends Model
{
    protected $fillable = ['name', 'cutoff_date'];

    public function run()
    {
        return $this->hasOne(ThrRun::class, 'period_id');
    }
}
