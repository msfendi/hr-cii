<?php

namespace App\Http\Controllers;

use App\Http\Middleware\LiveAccessMiddleware;
use App\Models\LiveAccessLog;
use App\Services\LiveAccessGeoResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\View\View;

class LiveAccessController extends Controller
{
    /**
     * Halaman utama Live Access — peta/daftar akses real-time ala threat map.
     */
    public function index(Request $request): View
    {
        return view('live_access.index');
    }

    /**
     * Ringkasan angka untuk kartu atas: total request, IP unik aktif,
     * user aktif, dan rata-rata durasi (dalam 5 menit terakhir).
     */
    public function summary(): JsonResponse
    {
        $since = now()->subMinutes(5);

        $query = DB::table('live_access_logs')
            ->selectRaw('COUNT(*) AS total_requests')
            ->selectRaw('COUNT(DISTINCT ip_address) AS unique_ips')
            ->selectRaw('COUNT(DISTINCT user_id) AS active_users')
            ->selectRaw('AVG(duration_ms) AS avg_duration_ms');

        // Polling internal (mis. bell notifikasi) tidak dihitung.
        LiveAccessMiddleware::applyRouteExclusion($query);

        $row = $query->where('accessed_at', '>=', $since)->first();

        return response()->json([
            'window_minutes' => 5,
            'total_requests' => (int) ($row->total_requests ?? 0),
            'unique_ips' => (int) ($row->unique_ips ?? 0),
            'active_users' => (int) ($row->active_users ?? 0),
            'avg_duration_ms' => round((float) ($row->avg_duration_ms ?? 0), 1),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Data tabel/peta: daftar akses dalam N menit terakhir (terbaru dulu),
     * dikelompokkan per IP dan per user untuk tampilan "threat map".
     */
    public function data(Request $request): JsonResponse
    {
        $minutes = (int) $request->query('minutes', 5);
        $minutes = max(1, min(60, $minutes));
        $since = now()->subMinutes($minutes);

        $logs = $this->baseQuery($since)
            ->orderByDesc('accessed_at')
            ->limit(500)
            ->get(['id', 'user_id', 'user_name', 'npk', 'ip_address', 'method', 'path', 'route_name', 'status', 'duration_ms', 'accessed_at']);

        $agg = self::aggregateLogs($logs);

        return response()->json([
            'window_minutes' => $minutes,
            'by_ip' => $agg['by_ip'],
            'by_user' => $agg['by_user'],
            'map' => self::buildMapPayload($agg['by_ip']),
            'recent' => $agg['recent'],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Query dasar log akses: hanya window waktu berjalan + route yang tidak
     * dikecualikan (polling internal seperti api.notifications.* dibuang).
     */
    private function baseQuery(\Carbon\CarbonInterface $since)
    {
        $query = LiveAccessLog::where('accessed_at', '>=', $since);

        return LiveAccessMiddleware::applyRouteExclusion($query);
    }

    /**
     * Ubah kumpulan log menjadi agregasi per IP, per user, dan feed terbaru.
     * Statis & tanpa DB supaya bisa diuji langsung dari unit test.
     */
    public static function aggregateLogs($logs): array
    {
        $byIp = $logs->groupBy('ip_address')->map(function ($rows, $ip) {
            $topRoutes = self::topRoutes($rows);

            return [
                'ip' => $ip,
                'requests' => $rows->count(),
                'users' => $rows->filter(fn ($r) => $r->user_name)->pluck('user_name')->unique()->values()->all(),
                'npk' => $rows->filter(fn ($r) => $r->npk)->pluck('npk')->unique()->values()->all(),
                'top_routes' => $topRoutes,
                'last_path' => $rows->first()->path,
                'last_route' => $rows->first()->route_name,
                'last_at' => optional($rows->first()->accessed_at)->toIso8601String(),
                'status_2xx' => $rows->whereBetween('status', [200, 299])->count(),
                'status_4xx' => $rows->whereBetween('status', [400, 499])->count(),
                'status_5xx' => $rows->where('status', '>=', 500)->count(),
            ];
        })->sortByDesc('requests')->values();

        // Kelompokkan per user (nama akun, fallback NPK, terakhir anonim).
        $byUser = $logs->groupBy(fn ($r) => self::userKey($r))->map(function ($rows, $key) {
            $first = $rows->first();

            return [
                'key' => (string) $key,
                'user' => $first->user_name ?: null,
                'npk' => $first->npk ?: null,
                'requests' => $rows->count(),
                'ips' => $rows->filter(fn ($r) => $r->ip_address)->pluck('ip_address')->unique()->values()->all(),
                'top_routes' => self::topRoutes($rows),
                'last_path' => $first->path,
                'last_route' => $first->route_name,
                'last_at' => optional($first->accessed_at)->toIso8601String(),
                'status_2xx' => $rows->whereBetween('status', [200, 299])->count(),
                'status_4xx' => $rows->whereBetween('status', [400, 499])->count(),
                'status_5xx' => $rows->where('status', '>=', 500)->count(),
            ];
        })->sortByDesc('requests')->values();

        // Feed request terbaru untuk tabel mentah.
        $recent = $logs->map(fn ($r) => [
            'id' => $r->id,
            'ip' => $r->ip_address,
            'user' => $r->user_name,
            'npk' => $r->npk,
            'method' => $r->method,
            'path' => $r->path,
            'route' => $r->route_name,
            'status' => $r->status,
            'duration_ms' => $r->duration_ms,
            'at' => optional($r->accessed_at)->toIso8601String(),
        ])->values();

        return ['by_ip' => $byIp, 'by_user' => $byUser, 'recent' => $recent];
    }

    /**
     * Normalkan nilai jadi array PHP biasa.
     *
     * Sebelumnya dipakai `array_values((array) $value)`; untuk Collection hasil
     * `->values()` cast ke array justru memunculkan properti internal
     * (`items` dan `escapeWhenCastingToString`), sehingga payload berisi
     * `[[...user...], false]` dan UI menampilkan "Nama, false".
     */
    private static function listOf($value): array
    {
        if ($value instanceof \Illuminate\Support\Collection) {
            return array_values($value->all());
        }

        return array_values(is_array($value) ? $value : (array) $value);
    }

    /**
     * Payload peta (Live Map ala threat map): origin + daftar sumber IP,
     * lengkap dengan koordinat bila diketahui. Statis & tanpa DB supaya bisa
     * diuji langsung tanpa SQL Server.
     *
     * Aturan gambar:
     *  - scope "public"  -> koordinat asli dari cache GeoIP.
     *  - scope "lan"     -> tanpa koordinat; UI menggambar cincin di sekitar origin.
     *  - scope "unknown" -> tanpa koordinat; TIDAK digambar (tidak menebak lokasi).
     */
    public static function buildMapPayload($byIp, ?LiveAccessGeoResolver $resolver = null): array
    {
        $resolver = $resolver ?? new LiveAccessGeoResolver;
        $maxSources = (int) config('live_access.map.max_sources', 40);
        $maxSources = max(1, $maxSources);

        $sources = [];
        $counts = ['total' => 0, 'public' => 0, 'lan' => 0, 'loopback' => 0, 'unknown' => 0];

        foreach ($byIp as $entry) {
            $geo = $resolver->resolve($entry['ip'] ?? null);
            $scope = $geo['scope'];
            $counts['total']++;
            $counts[$scope] = ($counts[$scope] ?? 0) + 1;

            $routes = self::listOf($entry['top_routes'] ?? []);
            $routes = array_values(array_filter($routes, fn ($r) => is_array($r)));

            $sources[] = [
                'ip' => $entry['ip'],
                'scope' => $scope,
                'lat' => $geo['lat'],
                'lng' => $geo['lng'],
                'label' => $geo['label'],
                'requests' => (int) ($entry['requests'] ?? 0),
                'users' => self::listOf($entry['users'] ?? []),
                'npk' => self::listOf($entry['npk'] ?? []),
                'top_routes' => $routes,
                'last_path' => $entry['last_path'] ?? null,
                'last_route' => $entry['last_route'] ?? null,
                'last_at' => $entry['last_at'] ?? null,
                'status_2xx' => (int) ($entry['status_2xx'] ?? 0),
                'status_4xx' => (int) ($entry['status_4xx'] ?? 0),
                'status_5xx' => (int) ($entry['status_5xx'] ?? 0),
            ];
        }

        // Yang bisa digambar lebih dulu (publik > LAN > loopback), sisanya dipotong.
        usort($sources, function ($a, $b) {
            $rank = ['public' => 0, 'lan' => 1, 'loopback' => 2, 'unknown' => 3];
            $ra = $rank[$a['scope']] ?? 9;
            $rb = $rank[$b['scope']] ?? 9;

            return $ra === $rb ? $b['requests'] <=> $a['requests'] : $ra <=> $rb;
        });

        $plotted = array_values(array_filter($sources, fn ($s) => $s['scope'] !== LiveAccessGeoResolver::SCOPE_UNKNOWN));
        $plotted = array_slice($plotted, 0, $maxSources);
        $unknown = array_values(array_filter($sources, fn ($s) => $s['scope'] === LiveAccessGeoResolver::SCOPE_UNKNOWN));

        return [
            'origin' => $resolver->origin(),
            'sources' => $plotted,
            'unknown_count' => count($unknown),
            'unknown_ips' => array_values(array_map(fn ($s) => $s['ip'], $unknown)),
            'counts' => $counts,
            'geoip_ready' => count($unknown) === 0,
        ];
    }

    private static function userKey($row): string
    {
        $name = trim((string) $row->user_name);
        if ($name !== '') {
            return $name;
        }

        $npk = trim((string) $row->npk);
        if ($npk !== '') {
            return $npk;
        }

        return '(anonim)';
    }

    private static function topRoutes($rows)
    {
        return $rows->filter(fn ($r) => $r->route_name)
            ->groupBy('route_name')
            ->map(fn ($g) => $g->count())
            ->sortDesc()
            ->take(3)
            ->map(fn ($c, $name) => ['route' => $name, 'count' => $c])
            ->values();
    }

    /**
     * Daftar job/queue yang sedang berjalan (queue:work) + job gagal
     * (failed_jobs) — dibaca via proses & tabel, tanpa kredensial.
     */
    public function jobs(): JsonResponse
    {
        $workers = [];
        try {
            $out = [];
            Process::run('pgrep -a -f "artisan queue:work"', function ($type, $line) use (&$out) {
                if ($type === 'out') {
                    $out[] = trim($line);
                }
            });
            $workers = array_values(array_filter($out));
        } catch (\Throwable $e) {
            $workers = [];
        }

        $pending = 0;
        $failed = 0;
        $failedRecent = [];
        try {
            if (DB::getSchemaBuilder()->hasTable('jobs')) {
                $pending = (int) DB::table('jobs')->count();
            }
            if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                $failed = (int) DB::table('failed_jobs')->count();
                $failedRecent = DB::table('failed_jobs')
                    ->orderByDesc('failed_at')
                    ->limit(10)
                    ->get(['id', 'queue', 'failed_at'])
                    ->map(fn ($r) => [
                        'id' => $r->id,
                        'queue' => $r->queue,
                        'failed_at' => (string) $r->failed_at,
                    ])->values()->all();
            }
        } catch (\Throwable $e) {
            // tabel belum ada -> abaikan
        }

        // Jadwal (schedule) yang terdaftar di Console Kernel.
        $scheduled = [];
        try {
            $events = app(\Illuminate\Console\Scheduling\Schedule::class)->events();
            foreach ($events as $event) {
                $scheduled[] = [
                    'command' => $event->command ?? ($event->description ?? '(closure)'),
                    'expression' => $event->expression,
                    'next_due' => method_exists($event, 'nextRunDate')
                        ? $event->nextRunDate()->toIso8601String()
                        : null,
                ];
            }
        } catch (\Throwable $e) {
            $scheduled = [];
        }

        return response()->json([
            'queue_workers' => $workers,
            'queue_workers_count' => count($workers),
            'pending_jobs' => $pending,
            'failed_jobs' => $failed,
            'failed_recent' => $failedRecent,
            'scheduled' => $scheduled,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Daftar service/systemd yang berjalan di server (hanya nama & status,
     * tanpa detail kredensial).
     */
    public function services(): JsonResponse
    {
        $services = [];

        try {
            $out = [];
            // Daftar unit aktif yang relevan dengan aplikasi (php/laravel/nginx/apache/queue/ocr/hrcii).
            Process::run('systemctl list-units --type=service --state=running --no-legend --plain | grep -Ei "php|laravel|nginx|apache|queue|ocr|hrcii|mysql|sqlsrv"', function ($type, $line) use (&$out) {
                if ($type === 'out') {
                    $out[] = trim($line);
                }
            });

            foreach ($out as $line) {
                // Format: "nama.service loaded active running deskripsi"
                $parts = preg_split('/\s+/', $line);
                if (count($parts) >= 4) {
                    $services[] = [
                        'name' => $parts[0],
                        'load' => $parts[1],
                        'active' => $parts[2],
                        'state' => $parts[3],
                        'description' => implode(' ', array_slice($parts, 4)),
                    ];
                }
            }
        } catch (\Throwable $e) {
            $services = [];
        }

        return response()->json([
            'services' => $services,
            'count' => count($services),
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
