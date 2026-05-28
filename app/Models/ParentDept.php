<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParentDept extends Model
{
    use HasFactory;

    protected $connection  = 'cii';
    protected $table       = 'parent_dept';

    protected $fillable = [
        'parent_dept_name',
    ];

    /** Relasi ke dept-dept yang berada di bawah parent ini */
    public function depts()
    {
        return $this->hasMany(Dept::class, 'id_parent_dept', 'id');
    }

    /** Relasi ke biodata karyawan yang berada di bawah parent ini */
    public function biodatas()
    {
        return $this->hasMany(Biodata::class, 'BAG', 'id');
    }
}
