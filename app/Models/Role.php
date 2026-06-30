<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;
    public $table = "roles";
    protected $fillable = [
        'name',
        'guard_name',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Permission::class, 'role_permission')->withTimestamps();
    }

    public function hasPermissionTo(string $routeName): bool
    {
        return $this->permissions()->where('route_name', $routeName)->exists();
    }
}
