<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollExport extends Model
{

    protected $table = 'payroll_exports';

    protected $fillable = [
        'run_id',
        'status',
        'progress',
        'file_excel',
        'file_pdf',
        'file_bank_active',
        'file_bank_resign',
        'file_peng',
    ];
}
