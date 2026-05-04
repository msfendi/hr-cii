<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForeignGuest extends Model
{
    protected $fillable = [
        'guest_name',
        'bank_account',
        'photo',
        'passport',
        'visa_type',
        'visa_application',
        'visa_status',
        'visa_invoice',
        'rent_invoice',
        'flight_detail',
        'flight_eta',
        'eta',
        'return',
        'hotel',
        'hotel_file',
        'hotel_invoice',
        'status',
    ];
}
