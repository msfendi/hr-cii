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

        // PERBAIKAN V35 HOTFIX (2026-11-09) — jangan 500 kalau DB
        // timeout / tabel permission tidak bisa dibaca.
        //
        // Semua query di middleware ini (Permission, roles, role_permission)
        // memakai koneksi default (cii) ke SQL Server remote. Kalau koneksi
        // itu timeout, middleware lempar exception SEBELUM controller jalan
        // sehingga halaman 500 tanpa sempat di-render.
        //
        // Fail-open: kalau tidak bisa memastikan hak akses, izinkan request
        // lewat (route-nya sendiri sudah dilindungi 'auth'), dan catat di
        // log. Ini TIDAK lewat bypass untuk user anonim karena blok
        // di atas tetap abort(401) kalau tidak login.

        try {
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
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Jangan tertangkap 403/401 yang sengaja di-throw.
            throw $e;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'CheckPermission: gagal cek permission untuk route [' . $routeName . '], diizinkan (fail-open): '
                . $e->getMessage()
            );
            return $next($request);
        }

        return $next($request);
    }
}
