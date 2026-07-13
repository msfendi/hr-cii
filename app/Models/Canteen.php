<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Canteen extends Model
{
    protected $fillable = ['name', 'location', 'pic_name', 'pic_phone', 'is_active'];

    public function foodMenus()
    {
        return $this->hasMany(FoodMenu::class);
    }
}
