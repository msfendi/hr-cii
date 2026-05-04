<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeptInsentifRole extends Model
{
    protected $table = 'dept_insentif_role';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'id_dept',
        'role',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATION : DEPARTMENT
    |--------------------------------------------------------------------------
    */
    public function department()
    {
        return $this->belongsTo(
            Dept::class,
            'id_dept',
            'ID_DEPT'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | RELATION : ROLE FORMULA
    |--------------------------------------------------------------------------
    */
    public function roleFormula()
    {
        return $this->belongsTo(
            InsentifRoleFormula::class,
            'role',
            'id'
        );
    }
}
