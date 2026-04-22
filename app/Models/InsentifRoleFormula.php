<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsentifRoleFormula extends Model
{
    protected $table = 'insentif_role_formulas';

    protected $fillable = [
        'role',
        'dept',
        'formula'
    ];
}
