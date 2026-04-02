<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThrExport extends Model
{
    protected $fillable = [
        'run_id',
        'progress',
        'status',
        'file_excel',
        'file_pdf',
        'file_bank',
        'file_peng'
    ];
}
