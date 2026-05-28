<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Biodata extends Model
{
    use HasFactory;
    protected $table = 'BIODATA';
    protected $connection = 'cii';
    protected $fillable = [
        'NPK',
        'NAMA_KARYAWAN',
        'BAG',
        'ID_DEPT',
        'JENIS_KEL',
        'BARCODE',
        'SECTION',
        'STATUS',
        'IS_STAFF'
    ]; 

    public function contract()
    {
        return $this->hasMany(EmployeesContract::class, 'npk', 'NPK');
    }

    /** Parent Department dari karyawan ini (via BAG = id parent_dept) */
    public function parentDept()
    {
        return $this->belongsTo(ParentDept::class, 'BAG', 'id');
    }
}
