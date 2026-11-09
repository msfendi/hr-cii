<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Canteen extends Model
{
    protected $fillable = ['canteen_id', 'name', 'location', 'pic_name', 'pic_phone', 'contact_person', 'contact_phone', 'is_active'];

    protected $casts = ['canteen_id' => 'integer', 'is_active' => 'boolean'];

    public function foodMenus()
    {
        return $this->hasMany(FoodMenu::class);
    }
}
