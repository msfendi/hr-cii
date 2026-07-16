<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckPermission
{
    public function handle(Request $request, Closure $next, ?string $routeName = null)
    {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        $routeName = $routeName ?? $request->route()?->getName();

        if (!$routeName) {
            return $next($request);
        }

        // Gunakan hasRole() dari Spatie — dijamin benar
        if ($user->hasRole('Admin')) {
            return $next($request);
        }

        $permission = Permission::where('route_name', $routeName)->first();

        if (!$permission) {
            // Permissive: route belum di-seed, izinkan lolos sambil log
            \Illuminate\Support\Facades\Log::warning(
                "Permission belum terdaftar untuk route [{$routeName}], request diizinkan (permissive)."
            );
            return $next($request);
        }

        // Ambil role ID user via Spatie, cek di tabel role_permission kita
        $userRoleIds = $user->roles()->pluck('id');

        $hasAccess = $userRoleIds->isNotEmpty() && DB::table('role_permission')
            ->where('permission_id', $permission->id)
            ->whereIn('role_id', $userRoleIds)
            ->exists();

        if (!$hasAccess) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
