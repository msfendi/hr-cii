<?php

namespace App\Http\Middleware;

use App\Models\LiveAccessLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mencatat setiap request web ke tabel live_access_logs supaya halaman
 * /live-access bisa menampilkan siapa (IP/NPK) sedang membuka route apa
 * secara real-time.
 *
 * Supaya tidak memperlambat aplikasi:
 * - Insert dibungkus try/catch dan tidak pernah melempar exception.
 * - Pembersihan data lama (retensi 7 hari) di-throttle: hanya jalan
 *   pada ~1% request (berdasarkan hash IP) supaya tidak insert/delete
 *   terus-menerus.
 * - Aset statis & endpoint yang dikecualikan tidak dicatat.
 */
class LiveAccessMiddleware
{
    /**
     * Path/prefix yang tidak perlu dicatat (aset, health check, dsb).
     */
    protected const SKIP_PREFIXES = [
        'build/',
        'css/',
        'js/',
        'img/',
        'icons/',
        'vendor/',
        'storage/',
        'favicon.ico',
        'manifest.webmanifest',
        'sw.js',
        'live-access/data', // endpoint polling halaman live access sendiri
        'live-access/summary',
        'live-access/jobs',
        'live-access/services',
        'api/notifications', // polling bell notifikasi (ramai, tidak informatif)
    ];

    /**
     * Nama route yang tidak dicatat. Polling internal (mis. bell notifikasi)
     * membanjiri tabel "Request Terbaru" sehingga menutupi aktivitas user asli.
     * Pola dengan akhiran '*' berarti prefix.
     */
    public const EXCLUDED_ROUTE_NAMES = [
        'api.notifications.*',
    ];

    /**
     * Apakah nama route termasuk yang dikecualikan?
     * Dipakai saat mencatat (middleware) dan saat membaca (controller),
     * supaya baris lama yang sempat tercatat langsung hilang dari tampilan.
     */
    public static function isExcludedRouteName(?string $routeName): bool
    {
        if ($routeName === null || $routeName === '') {
            return false;
        }

        foreach (self::EXCLUDED_ROUTE_NAMES as $pattern) {
            if (str_ends_with($pattern, '*')) {
                if (str_starts_with($routeName, rtrim($pattern, '*'))) {
                    return true;
                }
            } elseif ($routeName === $pattern) {
                return true;
            }
        }

        return false;
    }

    /**
     * Terapkan pengecualian route yang sama pada query pembacaan.
     * Penting: baris dengan route_name NULL tetap dipertahankan (NOT IN pada SQL
     * mengembalikan UNKNOWN untuk NULL sehingga barisnya bisa ikut terbuang).
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Query\Builder
     */
    public static function applyRouteExclusion($query)
    {
        $exact = [];
        $prefixes = [];

        foreach (self::EXCLUDED_ROUTE_NAMES as $pattern) {
            if (str_ends_with($pattern, '*')) {
                $prefixes[] = rtrim($pattern, '*');
            } else {
                $exact[] = $pattern;
            }
        }

        return $query->where(function ($q) use ($exact, $prefixes) {
            $q->whereNull('route_name');

            if (!empty($exact)) {
                $q->orWhereNotIn('route_name', $exact);
            } else {
                $q->orWhereNotNull('route_name');
            }

            foreach ($prefixes as $prefix) {
                $q->where(function ($sub) use ($prefix) {
                    $sub->whereNull('route_name')
                        ->orWhere('route_name', 'not like', $prefix . '%');
                });
            }
        });
    }

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        try {
            $path = $request->path();

            foreach (self::SKIP_PREFIXES as $prefix) {
                if ($path === $prefix || str_starts_with($path, trim($prefix, '/') . '/') || str_starts_with($path, $prefix)) {
                    return $response;
                }
            }

            // Route name dari route yang cocok (kalau sudah lewat SubstituteBindings).
            $routeName = $request->route()?->getName();

            // Polling internal (mis. api.notifications.index) tidak dicatat.
            if (self::isExcludedRouteName($routeName)) {
                return $response;
            }

            $user = $request->user();
            $npk = null;

            if ($user) {
                $npk = $user->npk ?? ($user->username ?? null);
            }

            LiveAccessLog::create([
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'npk' => $npk,
                'ip_address' => $request->ip(),
                'method' => $request->method(),
                'path' => mb_substr($request->path(), 0, 1000),
                'route_name' => $routeName ? mb_substr($routeName, 0, 255) : null,
                'status' => $response->getStatusCode(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
                'accessed_at' => now(),
            ]);

            // Retensi: hapus data > 7 hari. Di-throttle supaya tidak jalan
            // di setiap request — cek hash IP supaya merata.
            $ip = $request->ip() ?? '';
            if (crc32($ip) % 100 === 0) {
                DB::table('live_access_logs')
                    ->where('accessed_at', '<', now()->subDays(7))
                    ->delete();
            }
        } catch (\Throwable $e) {
            // Jangan pernah mengganggu request utama karena pencatatan gagal.
            Log::warning('LiveAccessMiddleware gagal mencatat: ' . $e->getMessage());
        }

        return $response;
    }
}
