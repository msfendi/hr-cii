<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Google\Auth\Credentials\ServiceAccountCredentials;

class MonitoringController extends Controller
{
    /**
     * Definisi semua server yang dimonitor. Key array ('hris', 'nextcloud',
     * 'passbolt') dipakai juga sebagai section-key di response JSON & di
     * blade, jadi kalau nambah server baru cukup tambah 1 entri di sini.
     */
    protected array $servers;

    /**
     * Realtime API GA4 hanya mengembalikan NAMA kota/negara, bukan lat/lng,
     * jadi untuk bisa menaruh titik di peta kita perlu tabel koordinat sendiri.
     * Difokuskan ke kota-kota Indonesia (karena hris.chutex.id berbasis ID),
     * dengan fallback ke titik tengah negara untuk kota di luar daftar ini.
     */
    protected const CITY_COORDS = [
        'jakarta' => [-6.2088, 106.8456],
        'surabaya' => [-7.2575, 112.7521],
        'bandung' => [-6.9175, 107.6191],
        'medan' => [3.5952, 98.6722],
        'semarang' => [-6.9932, 110.4203],
        'makassar' => [-5.1477, 119.4327],
        'palembang' => [-2.9761, 104.7754],
        'depok' => [-6.4025, 106.7942],
        'tangerang' => [-6.1783, 106.6319],
        'south tangerang' => [-6.2884, 106.7183],
        'bekasi' => [-6.2383, 106.9756],
        'bogor' => [-6.5971, 106.8060],
        'batam' => [1.0456, 104.0305],
        'pekanbaru' => [0.5333, 101.4500],
        'bandar lampung' => [-5.4292, 105.2610],
        'malang' => [-7.9666, 112.6326],
        'padang' => [-0.9492, 100.3543],
        'denpasar' => [-8.6705, 115.2126],
        'samarinda' => [-0.5022, 117.1536],
        'banjarmasin' => [-3.3186, 114.5944],
        'surakarta' => [-7.5755, 110.8243],
        'solo' => [-7.5755, 110.8243],
        'yogyakarta' => [-7.7956, 110.3695],
        'tegal' => [-6.8694, 109.1402],
        'klaten' => [-7.7056, 110.6069],
        'sukabumi' => [-6.9278, 106.9271],
        'cirebon' => [-6.7063, 108.5570],
        'cilegon' => [-6.0175, 106.0530],
        'serang' => [-6.1149, 106.1503],
        'purwokerto' => [-7.4247, 109.2320],
        'magelang' => [-7.4797, 110.2177],
        'salatiga' => [-7.3305, 110.5084],
        'kudus' => [-6.8048, 110.8405],
        'pati' => [-6.7550, 111.0384],
        'blora' => [-6.9693, 111.4184],
        'rembang' => [-6.7098, 111.3427],
        'kebumen' => [-7.6708, 109.6531],
        'wonosobo' => [-7.3616, 109.9016],
        'kuningan' => [-6.9762, 108.4829],
        'garut' => [-7.2158, 107.9083],
        'tasikmalaya' => [-7.3274, 108.2207],
        'pandeglang' => [-6.3050, 106.1004],
        'subang' => [-6.5713, 107.7605],
        'indramayu' => [-6.3268, 108.3200],
        'bojonegoro' => [-7.1502, 111.8817],
        'tuban' => [-6.8973, 112.0653],
        'ngawi' => [-7.4103, 111.4464],
        'madiun' => [-7.6298, 111.5239],
        'sragen' => [-7.4262, 111.0223],
        'ponorogo' => [-7.8681, 111.4595],
        'trenggalek' => [-8.0503, 111.7093],
        'tulungagung' => [-8.0655, 111.9024],
        'kediri' => [-7.8480, 112.0178],
        'brebes' => [-6.8724, 109.0433],
        'pekalongan' => [-6.8898, 109.6753],
        'demak' => [-6.8946, 110.6392],
        'jepara' => [-6.5822, 110.6699],
        'kudus jawa tengah' => [-6.8048, 110.8405],
        'sukoharjo' => [-7.630132933885772, 110.82667806227346],
        'grogol' => [-7.6146, 110.8136],
        'boyolali' => [-7.5307, 110.5943],
        'karanganyar' => [-7.6003, 110.9427],
        'wonogiri' => [-7.8156, 110.9235],
    ];

    protected const COUNTRY_COORDS = [
        'indonesia' => [-2.5489, 118.0149],
        'singapore' => [1.3521, 103.8198],
        'malaysia' => [4.2105, 101.9758],
        'united states' => [37.0902, -95.7129],
        'japan' => [36.2048, 138.2529],
        'australia' => [-25.2744, 133.7751],
        'india' => [20.5937, 78.9629],
        'china' => [35.8617, 104.1954],
        'united kingdom' => [55.3781, -3.4360],
        'germany' => [51.1657, 10.4515],
        'netherlands' => [52.1326, 5.2913],
        'south korea' => [35.9078, 127.7669],
        'thailand' => [15.8700, 100.9925],
        'vietnam' => [14.0583, 108.2772],
        'philippines' => [12.8797, 121.7740],
        'hong kong' => [22.3193, 114.1694],
        'taiwan' => [23.6978, 120.9605],
        'france' => [46.2276, 2.2137],
        'canada' => [56.1304, -106.3468],
        'brazil' => [-14.2350, -51.9253],
        'united arab emirates' => [23.4241, 53.8478],
        'saudi arabia' => [23.8859, 45.0792],
    ];

    /**
     * Cari koordinat untuk sebuah kota; kalau kota tidak dikenal, fallback ke
     * titik tengah negaranya. Kembalikan null kalau keduanya tidak dikenal.
     */
    protected function resolveCoords(?string $city, ?string $country): ?array
    {
        $cityKey = strtolower(trim((string) $city));
        if ($cityKey !== '' && isset(self::CITY_COORDS[$cityKey])) {
            return self::CITY_COORDS[$cityKey];
        }

        $countryKey = strtolower(trim((string) $country));
        if ($countryKey !== '' && isset(self::COUNTRY_COORDS[$countryKey])) {
            return self::COUNTRY_COORDS[$countryKey];
        }

        return null;
    }

    public function __construct()
    {
        // Semua diambil dari config/services.php supaya bisa di-override lewat .env.
        // Fallback value = kondisi jaringan saat ini (dikasih user).
        $this->servers = [
            'hris' => [
                'name'         => 'HRIS Chutex',
                'exporter_url' => config('services.node_exporter.url', 'http://192.168.1.240:9100/metrics'),
                'timeout'      => (int) config('services.node_exporter.timeout', 3),
                'ssl_host'     => config('services.ssl_monitor.host')
                    ?: (parse_url(config('app.url'), PHP_URL_HOST) ?: 'hris.chutex.id'),
                'ssl_port'     => 443,
                'ga4'          => true, // hanya HRIS yang punya Google Analytics
            ],
            'nextcloud' => [
                'name'         => 'Nextcloud',
                'exporter_url' => config('services.node_exporter_nextcloud.url', 'http://192.168.1.242:9100/metrics'),
                'timeout'      => (int) config('services.node_exporter_nextcloud.timeout', 3),
                'ssl_host'     => config('services.ssl_monitor.nextcloud_host', 'nextcloud.chutex.id'),
                'ssl_port'     => (int) config('services.ssl_monitor.nextcloud_port', 8010),
                'ga4'          => false,
            ],
            'passbolt' => [
                'name'         => 'Passbolt',
                'exporter_url' => config('services.node_exporter_passbolt.url', 'http://192.168.1.245:9100/metrics'),
                'timeout'      => (int) config('services.node_exporter_passbolt.timeout', 3),
                'ssl_host'     => config('services.ssl_monitor.passbolt_host', 'passbolt.chutex.id'),
                'ssl_port'     => (int) config('services.ssl_monitor.passbolt_port', 8012),
                'ga4'          => false,
            ],
        ];
    }

    public function index()
    {
        return view('server.index');
    }

    public function stats()
    {
        $servers = [];

        foreach ($this->servers as $key => $server) {
            $metrics = $this->fetchMetrics($server['exporter_url'], $server['timeout']);

            $servers[$key] = [
                'key'          => $key,
                'name'         => $server['name'],
                'cpu'          => $this->getCpuUsage($metrics, $key),
                'ram'          => $this->getRamUsage($metrics),
                'disk'         => $this->getDiskUsage($metrics),
                'network'      => $this->getNetworkTraffic($metrics, $key),
                'ssl'          => $this->getSslForServer($key, $server['ssl_host'], $server['ssl_port']),
                'exporter_ok'  => $metrics !== null,
                'exporter_url' => $server['exporter_url'],
            ];

            // GA4 cuma relevan buat HRIS (satu-satunya yang punya property GA4).
            if ($server['ga4']) {
                $servers[$key]['ga4'] = $this->getGa4Analytics();
            }
        }

        return response()->json([
            'servers'     => $servers,
            'server_time' => now()->format('H:i:s'),
        ]);
    }

    /**
     * Ambil data visitor GA4 via REST API (Google Analytics Data API v1beta),
     * tidak butuh ekstensi grpc. Di-cache 60 detik karena kuota Realtime API ketat.
     *
     * Catatan penting: dimensi Realtime API resmi hanya:
     * appVersion, audienceId, audienceName, city, cityId, country, countryId,
     * deviceCategory, eventName, minutesAgo, platform, streamId, streamName,
     * unifiedScreenName. TIDAK ADA dimensi "firstUserSource"/"sessionSource" di
     * Realtime API (itu hanya ada di Core Reporting / runReport), jadi panel
     * "Active users by First user source" pada tampilan realtime GA4 memang
     * selalu kosong lewat API publik ini — kita tampilkan apa adanya, bukan
     * data palsu.
     */
    protected function getGa4Analytics(): array
    {
        return Cache::remember('monitoring_ga4_info', 60, function () {
            $propertyId  = config('services.ga4.property_id');
            $credentials = config('services.ga4.credentials');

            if (!$propertyId || !file_exists($credentials)) {
                return [
                    'available' => false,
                    'error'     => 'GA4 belum dikonfigurasi (property_id / credentials tidak ditemukan)',
                ];
            }

            try {
                $token = $this->getGa4AccessToken($credentials);

                $base = "https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}";
                $headers = [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type'  => 'application/json',
                ];

                // --- 1) Active users: last 30 menit & last 5 menit (angka besar di kiri atas) ---
                $rangeRes = Http::withHeaders($headers)->timeout(10)->post("{$base}:runRealtimeReport", [
                    'metrics'      => [['name' => 'activeUsers']],
                    'minuteRanges' => [
                        ['name' => 'last30', 'startMinutesAgo' => 29],
                        ['name' => 'last5',  'startMinutesAgo' => 4],
                    ],
                ]);
                if (!$rangeRes->ok()) {
                    throw new \Exception('Realtime range API error: ' . $rangeRes->body());
                }
                $activeLast30 = 0;
                $activeLast5  = 0;
                foreach (($rangeRes->json()['rows'] ?? []) as $row) {
                    $rangeName = $row['dimensionValues'][0]['value'] ?? '';
                    $val       = (int) ($row['metricValues'][0]['value'] ?? 0);
                    if ($rangeName === 'last30') $activeLast30 = $val;
                    if ($rangeName === 'last5')  $activeLast5  = $val;
                }

                // --- 2) Active users per menit, 30 menit terakhir (bar chart) ---
                $perMinuteRes = Http::withHeaders($headers)->timeout(10)->post("{$base}:runRealtimeReport", [
                    'dimensions' => [['name' => 'minutesAgo']],
                    'metrics'    => [['name' => 'activeUsers']],
                    'orderBys'   => [['dimension' => ['dimensionName' => 'minutesAgo']]],
                ]);
                $byMinute = array_fill(0, 30, 0);
                if ($perMinuteRes->ok()) {
                    foreach (($perMinuteRes->json()['rows'] ?? []) as $row) {
                        $m = (int) ($row['dimensionValues'][0]['value'] ?? -1);
                        if ($m >= 0 && $m <= 29) {
                            $byMinute[$m] = (int) ($row['metricValues'][0]['value'] ?? 0);
                        }
                    }
                }
                $perMinute = [];
                for ($i = 29; $i >= 0; $i--) {
                    $perMinute[] = [
                        'label' => $i === 0 ? 'now' : "-{$i} min",
                        'value' => $byMinute[$i],
                    ];
                }

                // --- 3) Active users by Audience ---
                $audienceRes = Http::withHeaders($headers)->timeout(10)->post("{$base}:runRealtimeReport", [
                    'dimensions' => [['name' => 'audienceName']],
                    'metrics'    => [['name' => 'activeUsers']],
                ]);
                $byAudience = [];
                if ($audienceRes->ok()) {
                    foreach (($audienceRes->json()['rows'] ?? []) as $row) {
                        $byAudience[] = [
                            'name'  => $row['dimensionValues'][0]['value'] ?? '(not set)',
                            'value' => (int) ($row['metricValues'][0]['value'] ?? 0),
                        ];
                    }
                }
                usort($byAudience, fn($a, $b) => $b['value'] <=> $a['value']);

                // --- 4) Views by Page title and screen name ---
                $pageViewsRes = Http::withHeaders($headers)->timeout(10)->post("{$base}:runRealtimeReport", [
                    'dimensions' => [['name' => 'unifiedScreenName']],
                    'metrics'    => [['name' => 'screenPageViews']],
                ]);
                $byPageViews = [];
                if ($pageViewsRes->ok()) {
                    foreach (($pageViewsRes->json()['rows'] ?? []) as $row) {
                        $byPageViews[] = [
                            'name'  => $row['dimensionValues'][0]['value'] ?? '(not set)',
                            'value' => (int) ($row['metricValues'][0]['value'] ?? 0),
                        ];
                    }
                }
                usort($byPageViews, fn($a, $b) => $b['value'] <=> $a['value']);
                $byPageViewsFull = $byPageViews;
                $byPageViews = array_slice($byPageViews, 0, 10);

                // --- 4b) Active users per halaman (dipakai untuk card kanan "Halaman Paling Aktif") ---
                $activeByPageRes = Http::withHeaders($headers)->timeout(10)->post("{$base}:runRealtimeReport", [
                    'dimensions' => [['name' => 'unifiedScreenName']],
                    'metrics'    => [['name' => 'activeUsers']],
                ]);
                $activeNow = 0;
                $topPages  = [];
                if ($activeByPageRes->ok()) {
                    foreach (($activeByPageRes->json()['rows'] ?? []) as $row) {
                        $count = (int) ($row['metricValues'][0]['value'] ?? 0);
                        $activeNow += $count;
                        $topPages[] = [
                            'page'   => $row['dimensionValues'][0]['value'] ?? '(not set)',
                            'active' => $count,
                        ];
                    }
                }
                usort($topPages, fn($a, $b) => $b['active'] <=> $a['active']);
                $topPagesFull = $topPages;
                $topPages = array_slice($topPages, 0, 5);

                // --- 5) Event count by Event name ---
                $eventsRes = Http::withHeaders($headers)->timeout(10)->post("{$base}:runRealtimeReport", [
                    'dimensions' => [['name' => 'eventName']],
                    'metrics'    => [['name' => 'eventCount']],
                ]);
                $byEvent = [];
                if ($eventsRes->ok()) {
                    foreach (($eventsRes->json()['rows'] ?? []) as $row) {
                        $byEvent[] = [
                            'name'  => $row['dimensionValues'][0]['value'] ?? '(not set)',
                            'value' => (int) ($row['metricValues'][0]['value'] ?? 0),
                        ];
                    }
                }
                usort($byEvent, fn($a, $b) => $b['value'] <=> $a['value']);

                // --- 6) Active users by City (pengganti peta, karena Realtime API tidak
                //        mengembalikan lat/lng — hanya nama kota/negara) ---
                $cityRes = Http::withHeaders($headers)->timeout(10)->post("{$base}:runRealtimeReport", [
                    'dimensions' => [['name' => 'city'], ['name' => 'country']],
                    'metrics'    => [['name' => 'activeUsers']],
                ]);
                $byCity = [];
                if ($cityRes->ok()) {
                    foreach (($cityRes->json()['rows'] ?? []) as $row) {
                        $city    = $row['dimensionValues'][0]['value'] ?? '(not set)';
                        $country = $row['dimensionValues'][1]['value'] ?? '';
                        $coords  = $this->resolveCoords($city, $country);

                        $byCity[] = [
                            'city'    => $city,
                            'country' => $country,
                            'value'   => (int) ($row['metricValues'][0]['value'] ?? 0),
                            'lat'     => $coords[0] ?? null,
                            'lng'     => $coords[1] ?? null,
                        ];
                    }
                }
                usort($byCity, fn($a, $b) => $b['value'] <=> $a['value']);
                $byCity = array_slice($byCity, 0, 20);

                // --- 6b) Page path & screen class detail (tabel "Page path and screen
                //         class in last 30 minutes"): gabungan active users + views per halaman ---
                $activeMap = [];
                foreach ($topPagesFull as $tp) {
                    $activeMap[$tp['page']] = $tp['active'];
                }
                $viewsMap = [];
                foreach ($byPageViewsFull as $pv) {
                    $viewsMap[$pv['name']] = $pv['value'];
                }
                $allPageNames = array_unique(array_merge(array_keys($activeMap), array_keys($viewsMap)));
                $pageDetail = [];
                foreach ($allPageNames as $name) {
                    $pageDetail[] = [
                        'path'   => $name,
                        'active' => $activeMap[$name] ?? 0,
                        'views'  => $viewsMap[$name] ?? 0,
                    ];
                }
                usort($pageDetail, fn($a, $b) => $b['active'] <=> $a['active']);

                // --- Ringkasan hari ini (tidak berubah dari versi sebelumnya) ---
                $todayRes = Http::withHeaders($headers)
                    ->timeout(10)
                    ->post("{$base}:runReport", [
                        'dateRanges' => [['startDate' => 'today', 'endDate' => 'today']],
                        'metrics'    => [
                            ['name' => 'totalUsers'],
                            ['name' => 'sessions'],
                            ['name' => 'screenPageViews'],
                            ['name' => 'averageSessionDuration'],
                            ['name' => 'bounceRate'],
                        ],
                    ]);

                if (!$todayRes->ok()) {
                    throw new \Exception('Report API error: ' . $todayRes->body());
                }

                $todayJson = $todayRes->json();
                $row = $todayJson['rows'][0]['metricValues'] ?? null;

                $todayStats = [
                    'total_users'     => $row ? (int) $row[0]['value'] : 0,
                    'sessions'        => $row ? (int) $row[1]['value'] : 0,
                    'page_views'      => $row ? (int) $row[2]['value'] : 0,
                    'avg_session_sec' => $row ? (int) round((float) $row[3]['value']) : 0,
                    'bounce_rate'     => $row ? round((float) $row[4]['value'] * 100, 1) : 0,
                ];

                // --- Trend 7 hari terakhir (tidak berubah dari versi sebelumnya) ---
                $trendRes = Http::withHeaders($headers)
                    ->timeout(10)
                    ->post("{$base}:runReport", [
                        'dateRanges' => [['startDate' => '6daysAgo', 'endDate' => 'today']],
                        'dimensions' => [['name' => 'date']],
                        'metrics'    => [['name' => 'totalUsers']],
                        'orderBys'   => [['dimension' => ['dimensionName' => 'date']]],
                    ]);

                if (!$trendRes->ok()) {
                    throw new \Exception('Trend API error: ' . $trendRes->body());
                }

                $trendJson = $trendRes->json();
                $trendData = [];
                foreach (($trendJson['rows'] ?? []) as $r) {
                    $raw = $r['dimensionValues'][0]['value']; // YYYYMMDD
                    $trendData[] = [
                        'date'  => \Carbon\Carbon::createFromFormat('Ymd', $raw)->format('d/m'),
                        'users' => (int) $r['metricValues'][0]['value'],
                    ];
                }

                return [
                    'available'       => true,
                    'active_now'      => $activeNow,
                    'active_last_30'  => $activeLast30,
                    'active_last_5'   => $activeLast5,
                    'per_minute'      => $perMinute,
                    'by_source'       => [], // tidak tersedia di Realtime API (lihat catatan di atas method)
                    'by_audience'     => $byAudience,
                    'by_page_views'   => $byPageViews,
                    'by_event'        => $byEvent,
                    'by_city'         => $byCity,
                    'top_pages'       => $topPages,
                    'page_detail'     => $pageDetail,
                    'today'           => $todayStats,
                    'trend'           => $trendData,
                ];
            } catch (\Throwable $e) {
                Log::warning('Gagal ambil data GA4: ' . $e->getMessage());
                return ['available' => false, 'error' => $e->getMessage()];
            }
        });
    }

    /**
     * Ambil OAuth2 access token dari service account JSON via google/auth,
     * di-cache terpisah karena token berlaku ~1 jam (cache 50 menit untuk aman).
     */
    protected function getGa4AccessToken(string $credentialsPath): string
    {
        return Cache::remember('monitoring_ga4_token', 3000, function () use ($credentialsPath) {
            $creds = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/analytics.readonly',
                $credentialsPath
            );

            $token = $creds->fetchAuthToken();

            if (!isset($token['access_token'])) {
                throw new \Exception('Gagal fetch access token dari service account');
            }

            return $token['access_token'];
        });
    }

    /**
     * Ambil teks mentah dari node_exporter server tertentu lalu parse ke array.
     * Return null kalau exporter tidak bisa dihubungi.
     */
    protected function fetchMetrics(string $url, int $timeout): ?array
    {
        try {
            $response = Http::timeout($timeout)->get($url);

            if (!$response->ok()) {
                Log::warning('node_exporter merespon non-200', ['url' => $url, 'status' => $response->status()]);
                return null;
            }

            return $this->parseMetrics($response->body());
        } catch (\Throwable $e) {
            Log::warning("Gagal konek ke node_exporter ({$url}): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse format teks Prometheus (yang diekspos node_exporter) menjadi:
     * ['nama_metric' => [ ['labels' => ['device' => 'eth0', ...], 'value' => 123.45], ... ] ]
     */
    protected function parseMetrics(string $body): array
    {
        $result = [];

        foreach (explode("\n", $body) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!preg_match('/^([a-zA-Z_:][a-zA-Z0-9_:]*)(\{(.*)\})?\s+([0-9eE+\-.]+)\s*$/', $line, $m)) {
                continue;
            }

            $name      = $m[1];
            $labelsRaw = $m[3] ?? '';
            $value     = (float) $m[4];

            $labels = [];
            if ($labelsRaw !== '') {
                preg_match_all('/(\w+)="([^"]*)"/', $labelsRaw, $lm, PREG_SET_ORDER);
                foreach ($lm as $lmatch) {
                    $labels[$lmatch[1]] = $lmatch[2];
                }
            }

            $result[$name][] = ['labels' => $labels, 'value' => $value];
        }

        return $result;
    }

    /**
     * % CPU dihitung dari counter node_cpu_seconds_total (mode=idle vs total)
     * dengan membandingkan dua titik waktu (disimpan di cache antar polling).
     * $serverKey dipakai supaya snapshot tiap server tidak saling timpa.
     */
    protected function getCpuUsage(?array $metrics, string $serverKey): array
    {
        if (!$metrics || !isset($metrics['node_cpu_seconds_total'])) {
            return ['percent' => 0, 'source' => 'node_exporter_unreachable'];
        }

        $idle = 0.0;
        $total = 0.0;

        foreach ($metrics['node_cpu_seconds_total'] as $row) {
            $total += $row['value'];
            if (($row['labels']['mode'] ?? '') === 'idle') {
                $idle += $row['value'];
            }
        }

        $cacheKey = "monitoring_cpu_snapshot_{$serverKey}";
        $now  = microtime(true);
        $prev = Cache::get($cacheKey);
        Cache::put($cacheKey, ['idle' => $idle, 'total' => $total, 'time' => $now], 60);

        if (!$prev) {
            return ['percent' => 0, 'source' => 'node_exporter_warmup']; // butuh 1x polling lagi
        }

        $totalDelta = $total - $prev['total'];
        $idleDelta  = $idle - $prev['idle'];
        $percent    = $totalDelta > 0 ? round((1 - ($idleDelta / $totalDelta)) * 100, 2) : 0;

        return ['percent' => max(min($percent, 100), 0), 'source' => 'node_exporter'];
    }

    /**
     * RAM dari gauge node_memory_MemTotal_bytes & node_memory_MemAvailable_bytes.
     */
    protected function getRamUsage(?array $metrics): array
    {
        if (!$metrics || !isset($metrics['node_memory_MemTotal_bytes'])) {
            return ['total_gb' => 0, 'used_gb' => 0, 'percent' => 0, 'source' => 'node_exporter_unreachable'];
        }

        $total     = $metrics['node_memory_MemTotal_bytes'][0]['value'] ?? 0;
        $available = $metrics['node_memory_MemAvailable_bytes'][0]['value'] ?? 0;
        $used      = max($total - $available, 0);

        return [
            'total_gb' => round($total / 1024 / 1024 / 1024, 2),
            'used_gb'  => round($used / 1024 / 1024 / 1024, 2),
            'percent'  => $total > 0 ? round(($used / $total) * 100, 2) : 0,
            'source'   => 'node_exporter',
        ];
    }

    /**
     * Storage capacity & sisa ruang dari node_filesystem_size_bytes /
     * node_filesystem_avail_bytes, per-mountpoint (skip filesystem virtual
     * seperti tmpfs/overlay/proc supaya tidak bias).
     */
    protected function getDiskUsage(?array $metrics): array
    {
        if (!$metrics || !isset($metrics['node_filesystem_size_bytes'])) {
            return ['main' => null, 'filesystems' => [], 'source' => 'node_exporter_unreachable'];
        }

        $ignoreFstypes = ['tmpfs', 'devtmpfs', 'overlay', 'squashfs', 'proc', 'sysfs', 'cgroup', 'cgroup2', 'devpts', 'debugfs', 'mqueue', 'tracefs', 'ramfs', 'iso9660'];
        $ignoreMountPrefixes = ['/boot', '/snap', '/dev', '/run', '/sys', '/proc'];

        // Index nilai avail berdasarkan device+mountpoint agar mudah dipasangkan dengan size
        $availByKey = [];
        foreach (($metrics['node_filesystem_avail_bytes'] ?? []) as $row) {
            $key = ($row['labels']['device'] ?? '') . '|' . ($row['labels']['mountpoint'] ?? '');
            $availByKey[$key] = $row['value'];
        }

        $filesystems = [];

        foreach ($metrics['node_filesystem_size_bytes'] as $row) {
            $labels = $row['labels'];
            $fstype = $labels['fstype'] ?? '';
            $mount  = $labels['mountpoint'] ?? '';
            $device = $labels['device'] ?? '';
            $total  = $row['value'];

            if ($total <= 0 || in_array($fstype, $ignoreFstypes)) {
                continue;
            }

            $skip = false;
            foreach ($ignoreMountPrefixes as $prefix) {
                if ($mount !== '/' && str_starts_with($mount, $prefix)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $avail = $availByKey[$device . '|' . $mount] ?? 0;
            $used  = max($total - $avail, 0);

            $filesystems[] = [
                'mountpoint' => $mount,
                'device'     => $device,
                'fstype'     => $fstype,
                'total_gb'   => round($total / (1024 ** 3), 2),
                'used_gb'    => round($used / (1024 ** 3), 2),
                'avail_gb'   => round($avail / (1024 ** 3), 2),
                'percent'    => $total > 0 ? round(($used / $total) * 100, 2) : 0,
            ];
        }

        // Root filesystem ("/") ditaruh paling atas, sisanya urut abjad mountpoint
        usort($filesystems, function ($a, $b) {
            if ($a['mountpoint'] === '/') return -1;
            if ($b['mountpoint'] === '/') return 1;
            return strcmp($a['mountpoint'], $b['mountpoint']);
        });

        $main = null;
        foreach ($filesystems as $fs) {
            if ($fs['mountpoint'] === '/') {
                $main = $fs;
                break;
            }
        }
        $main = $main ?? ($filesystems[0] ?? null);

        return [
            'main'        => $main,
            'filesystems' => $filesystems,
            'source'      => 'node_exporter',
        ];
    }

    protected function getNetworkTraffic(?array $metrics, string $serverKey): array
    {
        if (!$metrics || !isset($metrics['node_network_receive_bytes_total'])) {
            return ['rx_mbps' => 0, 'tx_mbps' => 0, 'source' => 'node_exporter_unreachable'];
        }

        $ignorePrefixes = ['lo', 'docker', 'veth', 'virbr', 'br-'];

        $rx = 0.0;
        foreach ($metrics['node_network_receive_bytes_total'] as $row) {
            $dev = $row['labels']['device'] ?? '';
            if ($this->shouldSkipInterface($dev, $ignorePrefixes)) continue;
            $rx += $row['value'];
        }

        $tx = 0.0;
        foreach (($metrics['node_network_transmit_bytes_total'] ?? []) as $row) {
            $dev = $row['labels']['device'] ?? '';
            if ($this->shouldSkipInterface($dev, $ignorePrefixes)) continue;
            $tx += $row['value'];
        }

        $cacheKey = "monitoring_net_snapshot_{$serverKey}";
        $now  = microtime(true);
        $prev = Cache::get($cacheKey);
        Cache::put($cacheKey, ['rx' => $rx, 'tx' => $tx, 'time' => $now], 60);

        if (!$prev) {
            return ['rx_mbps' => 0, 'tx_mbps' => 0, 'source' => 'node_exporter_warmup'];
        }

        $interval = max($now - $prev['time'], 0.001);
        $rxMbps   = max((($rx - $prev['rx']) * 8) / $interval / 1_000_000, 0);
        $txMbps   = max((($tx - $prev['tx']) * 8) / $interval / 1_000_000, 0);

        return [
            'rx_mbps' => round($rxMbps, 3),
            'tx_mbps' => round($txMbps, 3),
            'source'  => 'node_exporter',
        ];
    }

    protected function shouldSkipInterface(string $device, array $ignorePrefixes): bool
    {
        foreach ($ignorePrefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($device, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Cek SSL untuk 1 server tertentu (dipanggil per section: hris, nextcloud,
     * passbolt). Di-cache 1 jam per server supaya tidak buka koneksi TLS
     * tiap kali polling 5 detik.
     */
    protected function getSslForServer(string $serverKey, string $host, int $port): array
    {
        return Cache::remember("monitoring_ssl_info_{$serverKey}", 3600, function () use ($host, $port) {
            return $this->checkSslCert($host, $port);
        });
    }

    /**
     * Cek sertifikat SSL untuk satu host:port tertentu. Diekstrak dari
     * getSslInfo() lama supaya bisa dipakai ulang untuk multi-host
     * (hris, nextcloud, passbolt, dst).
     */
    protected function checkSslCert(string $host, int $port): array
    {
        $label = $port === 443 ? $host : "{$host}:{$port}";

        try {
            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                ],
            ]);

            $socket = @stream_socket_client(
                "ssl://{$host}:{$port}",
                $errno,
                $errstr,
                5,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$socket) {
                Log::warning("Gagal konek SSL ke {$label}: {$errstr}");
                return [
                    'valid' => false,
                    'host'  => $host,
                    'port'  => $port,
                    'label' => $label,
                    'error' => $errstr ?: "Tidak bisa konek ke port {$port}",
                ];
            }

            $params = stream_context_get_params($socket);
            fclose($socket);

            $cert = $params['options']['ssl']['peer_certificate'] ?? null;
            if (!$cert) {
                return [
                    'valid' => false,
                    'host'  => $host,
                    'port'  => $port,
                    'label' => $label,
                    'error' => 'Sertifikat tidak ditemukan',
                ];
            }

            $certInfo = openssl_x509_parse($cert);

            $validFrom = $certInfo['validFrom_time_t'] ?? null;
            $validTo   = $certInfo['validTo_time_t'] ?? null;
            $daysLeft  = $validTo ? (int) floor(($validTo - time()) / 86400) : null;

            return [
                'valid'       => true,
                'host'        => $host,
                'port'        => $port,
                'label'       => $label,
                'common_name' => $certInfo['subject']['CN'] ?? $host,
                'issuer'      => $certInfo['issuer']['O'] ?? ($certInfo['issuer']['CN'] ?? '-'),
                'valid_from'  => $validFrom ? date('Y-m-d', $validFrom) : null,
                'valid_to'    => $validTo ? date('Y-m-d', $validTo) : null,
                'days_left'   => $daysLeft,
                'expired'     => $daysLeft !== null && $daysLeft < 0,
            ];
        } catch (\Throwable $e) {
            Log::warning("Gagal cek SSL certificate {$label}: " . $e->getMessage());
            return [
                'valid' => false,
                'host'  => $host,
                'port'  => $port,
                'label' => $label,
                'error' => $e->getMessage(),
            ];
        }
    }
}