<?php

namespace App\Support;

use App\Models\Permission;
use Illuminate\Support\Facades\Auth;

/**
 * Helper singleton untuk cek permission per route name.
 * Di-cache dalam satu request supaya tidak query DB berulang
 * untuk route yang sama (penting untuk loop @foreach di blade).
 */
class RoutePermission
{
    /** @var array<string, bool> */
    protected static array $cache = [];

    /**
     * Cek apakah user yang sedang login memiliki permission untuk $routeName.
     * Admin selalu lolos.
     */
    public static function check(string $routeName): bool
    {
        if (isset(self::$cache[$routeName])) {
            return self::$cache[$routeName];
        }

        $user = Auth::user();

        if (!$user) {
            return self::$cache[$routeName] = false;
        }

        // Admin selalu lolos
        if ($user->roles()->where('name', 'Admin')->exists()) {
            return self::$cache[$routeName] = true;
        }

        // Cari permission di tabel
        $permission = Permission::where('route_name', $routeName)->first();

        if (!$permission) {
            // Belum di-seed = permissive (izinkan), konsisten dengan CheckPermission middleware
            return self::$cache[$routeName] = true;
        }

        // Cek apakah salah satu role user punya permission ini
        $hasAccess = $user->roles()
            ->whereHas('permissions', function ($q) use ($permission) {
                $q->where('permissions.id', $permission->id);
            })
            ->exists();

        return self::$cache[$routeName] = $hasAccess;
    }

    /** Reset cache (berguna untuk testing) */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
