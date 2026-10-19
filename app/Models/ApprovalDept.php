<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalDept extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'dept',
        'section',
    ];

    protected $casts = [
        'dept' => 'array',
    ];

    public function rules()
    {
        return $this->hasMany(ApprovalRule::class, 'rules_id');
    }

    public function sectionDetail()
    {
        return $this->belongsTo(Section::class, 'section');
    }
}
