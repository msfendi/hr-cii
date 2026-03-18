<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'rules_id',
        'name',
        'approval_id',
        'level',
    ];

    public function dept()
    {
        return $this->belongsTo(ApprovalDept::class, 'rules_id');
    }

}
