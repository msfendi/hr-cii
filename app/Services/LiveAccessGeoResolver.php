<?php

namespace App\Services;

/**
 * Resolver koordinat untuk Live Map (/live-access).
 *
 * Aturan (jujur, tanpa menebak lokasi):
 *   1. Loopback (127.0.0.0/8, ::1)          -> scope "loopback", digambar di titik origin.
 *   2. Jaringan internal RFC1918/link-local -> scope "lan", digambar sebagai cincin
 *      di sekitar origin (memang lokal, bukan lokasi geografis negara lain).
 *   3. IP publik yang ADA di cache GeoIP    -> scope "public", koordinat asli dari cache.
 *   4. IP publik yang TIDAK ada di cache    -> scope "unknown", TANPA koordinat
 *      (tidak digambar di peta; hanya dihitung jumlahnya) supaya tidak menampilkan
 *      titik yang mengarang lokasi.
 *
 * Cache GeoIP diisi opsional oleh command `php artisan live-access:geoip`
 * (lihat config/live_access.php), jadi aplikasi tetap jalan tanpa internet.
 */
class LiveAccessGeoResolver
{
    public const SCOPE_LOOPBACK = 'loopback';
    public const SCOPE_LAN = 'lan';
    public const SCOPE_PUBLIC = 'public';
    public const SCOPE_UNKNOWN = 'unknown';

    /** @var array{lat:float,lng:float,label:string} */
    private array $origin;

    /** @var array<string,array>|null */
    private ?array $cache = null;

    public function __construct(?array $origin = null)
    {
        $origin = $origin ?? (array) config('live_access.map.origin', []);

        $this->origin = [
            'lat' => (float) ($origin['lat'] ?? -7.630187),
            'lng' => (float) ($origin['lng'] ?? 110.826562),
            'label' => (string) ($origin['label'] ?? 'Lokasi server'),
        ];
    }

    /**
     * Titik origin (lokasi server/pabrik) untuk digambar di peta.
     */
    public function origin(): array
    {
        return $this->origin;
    }

    /**
     * Apakah IP termasuk jaringan internal (tidak punya lokasi geografis publik)?
     */
    public static function isPrivate(?string $ip): bool
    {
        $ip = trim((string) $ip);

        if ($ip === '') {
            return false;
        }

        // IPv6 loopback & link-local/unique-local.
        if (str_contains($ip, ':')) {
            $normalized = strtolower($ip);

            if ($normalized === '::1' || $normalized === '::') {
                return true;
            }

            $first = substr($normalized, 0, 2);

            return in_array($first, ['fc', 'fd', 'fe'], true);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        $long = ip2long($ip);
        if ($long === false) {
            return false;
        }

        $ranges = [
            ['0.0.0.0', 8],        // "this" network
            ['10.0.0.0', 8],       // RFC1918
            ['100.64.0.0', 10],    // CGNAT
            ['127.0.0.0', 8],      // loopback
            ['169.254.0.0', 16],   // link-local
            ['172.16.0.0', 12],    // RFC1918
            ['192.168.0.0', 16],   // RFC1918
        ];

        foreach ($ranges as [$network, $bits]) {
            $mask = -1 << (32 - $bits);
            if (($long & $mask) === (ip2long($network) & $mask)) {
                return true;
            }
        }

        return false;
    }

    public static function isLoopback(?string $ip): bool
    {
        $ip = trim((string) $ip);

        if ($ip === '') {
            return false;
        }

        if ($ip === '::1') {
            return true;
        }

        return str_starts_with($ip, '127.');
    }

    /**
     * Resolusi satu IP menjadi titik peta.
     *
     * @return array{ip:string,scope:string,lat:?float,lng:?float,label:string}
     */
    public function resolve(?string $ip): array
    {
        $ip = trim((string) $ip);

        if (self::isLoopback($ip)) {
            return [
                'ip' => $ip,
                'scope' => self::SCOPE_LOOPBACK,
                'lat' => $this->origin['lat'],
                'lng' => $this->origin['lng'],
                'label' => 'Server lokal',
            ];
        }

        if (self::isPrivate($ip)) {
            // Lokal: titik digambar sebagai cincin di sekitar origin oleh UI.
            return [
                'ip' => $ip,
                'scope' => self::SCOPE_LAN,
                'lat' => null,
                'lng' => null,
                'label' => 'Jaringan lokal (LAN)',
            ];
        }

        $hit = $this->lookupCache($ip);

        if ($hit !== null) {
            return [
                'ip' => $ip,
                'scope' => self::SCOPE_PUBLIC,
                'lat' => (float) $hit['lat'],
                'lng' => (float) $hit['lng'],
                'label' => (string) ($hit['label'] ?? 'IP publik'),
            ];
        }

        return [
            'ip' => $ip,
            'scope' => self::SCOPE_UNKNOWN,
            'lat' => null,
            'lng' => null,
            'label' => 'Lokasi belum diketahui',
        ];
    }

    /**
     * Isi cache GeoIP lokal (dipakai command live-access:geoip).
     *
     * @param  array<string,array{lat:float,lng:float,label:string}>  $entries
     */
    public function putCache(array $entries): void
    {
        $cache = $this->loadCache();
        foreach ($entries as $ip => $entry) {
            if (!isset($entry['lat'], $entry['lng'])) {
                continue;
            }
            $cache[$ip] = [
                'lat' => (float) $entry['lat'],
                'lng' => (float) $entry['lng'],
                'label' => (string) ($entry['label'] ?? $ip),
                'at' => $entry['at'] ?? now()->toIso8601String(),
            ];
        }
        $this->cache = $cache;
        $this->saveCache($cache);
    }

    private function lookupCache(string $ip): ?array
    {
        $cache = $this->loadCache();

        return $cache[$ip] ?? null;
    }

    private function loadCache(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $path = (string) config('live_access.map.geoip_cache');
        $this->cache = [];

        if ($path !== '' && is_readable($path)) {
            $decoded = json_decode((string) @file_get_contents($path), true);
            if (is_array($decoded)) {
                $this->cache = $decoded;
            }
        }

        return $this->cache;
    }

    private function saveCache(array $cache): void
    {
        $path = (string) config('live_access.map.geoip_cache');
        if ($path === '') {
            return;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($path, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
