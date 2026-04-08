<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappDevice extends Model
{
    protected $fillable = [
        'name',
        'token',
        'phone',
        'is_active'
    ];

    public function logs()
    {
        return $this->hasMany(WhatsappLog::class, 'device_id');
    }
}
