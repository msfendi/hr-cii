<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpatCostComponent extends Model
{
    protected $table = 'expat_cost_components';

    protected $fillable = [
        'component',
        'component_type'
    ];
}
