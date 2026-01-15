<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Biodata extends Model
{
    use HasFactory;
    protected $fillable = [
        'NPK',
        'NAMA_KARYAWAN',
        'BAG',
        'ID_DEPT',
        'JENIS_KEL',
        'BARCODE',
        'SECTION',
        'STATUS'
    ]; 
}
