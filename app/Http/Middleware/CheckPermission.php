<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CheckPermission
{
    /**
     * Daftar di Kernel.php sebagai alias 'permission', lalu pakai di route:
     *   ->middleware('permission')   // otomatis ambil route name saat ini
     * atau eksplisit:
     *   ->middleware('permission:biodata.index')
     */
    public function handle(Request $request, Closure $next, ?string $routeName = null)
    {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        // Jika tidak diberi parameter, gunakan nama route yang sedang diakses
        $routeName = $routeName ?? $request->route()?->getName();

        if (!$routeName) {
            // Tidak ada nama route terdaftar, lewatkan saja (atau abort sesuai kebijakan Anda)
            return $next($request);
        }

        // Permission yang belum terdaftar di DB dianggap bebas akses oleh Admin,
        // sisanya wajib terdaftar dan dimiliki oleh salah satu role user.
        $isAdmin = $user->roles()->where('name', 'Admin')->exists();

        $permission = Permission::where('route_name', $routeName)->first();

        if (!$permission) {
            // Route belum dipetakan ke permission. Selama masa migrasi bertahap,
            // izinkan lolos (permissive) supaya tidak lockout user yang belum sempat di-seed,
            // TAPI tetap log supaya ketahuan mana yang masih perlu di-seed.
            \Illuminate\Support\Facades\Log::warning("Permission belum terdaftar untuk route [$routeName], request diizinkan lewat (permissive default).");
            return $next($request);

            // Setelah seeder permission sudah lengkap untuk semua route,
            // ganti jadi strict mode (uncomment 2 baris di bawah, hapus 2 baris di atas):
            // if ($isAdmin) return $next($request);
            // throw new AccessDeniedHttpException("Permission untuk route [$routeName] belum terdaftar.");
        }

        if ($isAdmin) {
            // Admin selalu lolos tanpa perlu dicek granular ke role_permission
            return $next($request);
        }

        $hasAccess = $permission->roles()
            ->whereIn('roles.id', $user->roles()->pluck('id'))
            ->exists();

        // dd(
        //     $hasAccess,
        // );

        if (!$hasAccess) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
