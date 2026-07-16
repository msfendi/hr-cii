<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    protected $fillable = [
        'parent_id', 'name', 'route_name', 'icon', 'permission_id', 'order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id')->where('is_active', true)->orderBy('order');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    /**
     * Ambil semua menu parent (2 level) beserta children,
     * sudah difilter sesuai permission yang dimiliki role.
     */
    public static function buildSidebarFor(Role $role): \Illuminate\Support\Collection
    {
        $permissionIds = $role->permissions()->pluck('permissions.id');

        return self::whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('order')
            ->get()
            ->filter(function (Menu $menu) use ($permissionIds) {
                // parent tanpa permission_id selalu dicek lewat children-nya saja
                $hasAccess = $menu->permission_id === null || $permissionIds->contains($menu->permission_id);

                $visibleChildren = $menu->children->filter(function (Menu $child) use ($permissionIds) {
                    return $child->permission_id === null || $permissionIds->contains($child->permission_id);
                });

                // parent ditampilkan jika dia sendiri punya akses ATAU minimal 1 child terlihat
                return $hasAccess || $visibleChildren->isNotEmpty();
            })
            ->map(function (Menu $menu) use ($permissionIds) {
                $menu->setRelation('children', $menu->children->filter(function (Menu $child) use ($permissionIds) {
                    return $child->permission_id === null || $permissionIds->contains($child->permission_id);
                })->values());
                return $menu;
            })
            ->values();
    }
}
