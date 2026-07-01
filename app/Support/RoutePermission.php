<?php

namespace App\Support;

use App\Models\Permission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Helper singleton untuk cek permission per route name.
 * Di-cache dalam satu request supaya tidak query DB berulang
 * untuk route yang sama (penting untuk loop @foreach di blade).
 */
class RoutePermission
{
    /** @var array<string, bool> */
    protected static array $cache = [];

    public static function check(string $routeName): bool
    {
        if (isset(self::$cache[$routeName])) {
            return self::$cache[$routeName];
        }

        $user = Auth::user();

        if (!$user) {
            return self::$cache[$routeName] = false;
        }

        // Gunakan hasRole() dari Spatie — dijamin benar sesuai konfigurasi Spatie
        if ($user->hasRole('Admin')) {
            return self::$cache[$routeName] = true;
        }

        // Cari permission di tabel custom kita
        $permission = Permission::where('route_name', $routeName)->first();

        if (!$permission) {
            // Belum di-seed = permissive (konsisten dengan middleware)
            return self::$cache[$routeName] = true;
        }

        // Ambil semua role ID yang dimiliki user via Spatie
        $userRoleIds = $user->roles()->pluck('id');

        if ($userRoleIds->isEmpty()) {
            return self::$cache[$routeName] = false;
        }

        // Query LANGSUNG ke tabel role_permission kita — bypass ORM ambiguity
        // antara Spatie's role model dan App\Models\Role kita
        $hasAccess = DB::table('role_permission')
            ->where('permission_id', $permission->id)
            ->whereIn('role_id', $userRoleIds)
            ->exists();

        return self::$cache[$routeName] = $hasAccess;
    }

    /** Reset cache (berguna untuk testing) */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
