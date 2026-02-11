<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Overtime extends Model
{
    use HasFactory;

    protected $connection = 'audit';
    protected $table = 'overtimes';
    protected $fillable = [
        'NPK',
        'NAMA_KARYAWAN',
        'BAGIAN',
        'OVERTIME_DATE',
        'JUMLAH_JAM_LEMBUR',
        'DAY',
    ];
}
