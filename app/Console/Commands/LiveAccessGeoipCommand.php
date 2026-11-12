<?php

namespace App\Console\Commands;

use App\Http\Middleware\LiveAccessMiddleware;
use App\Models\LiveAccessLog;
use App\Services\LiveAccessGeoResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Isi cache GeoIP lokal untuk Live Map (/live-access).
 *
 * Contoh:
 *   php artisan live-access:geoip              # IP publik dari 60 menit terakhir
 *   php artisan live-access:geoip --minutes=1440
 *
 * Command ini OPSIONAL. Tanpa internet, Live Map tetap jalan: IP publik yang
 * belum ada di cache hanya dihitung sebagai "lokasi belum diketahui" dan tidak
 * digambar (aplikasi tidak menebak lokasi).
 */
class LiveAccessGeoipCommand extends Command
{
    protected $signature = 'live-access:geoip
        {--minutes=60 : Ambil IP publik dari log N menit terakhir}
        {--limit=100 : Jumlah maksimum IP yang dicari sekali jalan}';

    protected $description = 'Isi cache GeoIP lokal untuk peta Live Access (opsional, butuh internet).';

    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $limit = max(1, min(100, (int) $this->option('limit')));

        $resolver = new LiveAccessGeoResolver;
        $endpoint = (string) config('live_access.map.geoip_endpoint');

        $query = LiveAccessLog::where('accessed_at', '>=', now()->subMinutes($minutes));
        $query = LiveAccessMiddleware::applyRouteExclusion($query);

        $ips = $query->whereNotNull('ip_address')
            ->distinct()
            ->limit(2000)
            ->pluck('ip_address')
            ->map(fn ($ip) => trim((string) $ip))
            ->filter()
            ->unique()
            ->values();

        $candidates = [];
        foreach ($ips as $ip) {
            if (LiveAccessGeoResolver::isPrivate($ip) || LiveAccessGeoResolver::isLoopback($ip)) {
                continue;
            }
            $hit = $resolver->resolve($ip);
            if ($hit['scope'] === LiveAccessGeoResolver::SCOPE_PUBLIC) {
                continue; // sudah ada di cache
            }
            $candidates[] = $ip;
            if (count($candidates) >= $limit) {
                break;
            }
        }

        if (empty($candidates)) {
            $this->info('Tidak ada IP publik baru yang perlu dicari (cache sudah lengkap).');

            return self::SUCCESS;
        }

        $this->info('Mencari lokasi ' . count($candidates) . ' IP publik via ' . $endpoint);

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $candidates);
        } catch (\Throwable $e) {
            $this->error('Gagal menghubungi endpoint GeoIP: ' . $e->getMessage());
            $this->warn('Cache tidak berubah. Live Map tetap jalan tanpa koordinat untuk IP tersebut.');

            return self::FAILURE;
        }

        if (!$response->successful()) {
            $this->error('Endpoint GeoIP membalas HTTP ' . $response->status());
            $this->warn('Cache tidak berubah.');

            return self::FAILURE;
        }

        $rows = $response->json();
        if (!is_array($rows)) {
            $this->error('Format balasan GeoIP tidak dikenali.');

            return self::FAILURE;
        }

        $entries = [];
        foreach ($rows as $row) {
            $ip = (string) ($row['query'] ?? '');
            if ($ip === '' || ($row['status'] ?? '') !== 'success') {
                continue;
            }
            $city = trim((string) ($row['city'] ?? ''));
            $country = trim((string) ($row['country'] ?? ''));
            $entries[$ip] = [
                'lat' => (float) ($row['lat'] ?? 0),
                'lng' => (float) ($row['lon'] ?? 0),
                'label' => trim($city . ($city !== '' && $country !== '' ? ', ' : '') . $country) ?: $ip,
            ];
        }

        if (empty($entries)) {
            $this->warn('Tidak ada IP yang berhasil diresolusi.');

            return self::FAILURE;
        }

        $resolver->putCache($entries);
        $this->info('Cache GeoIP diperbarui: ' . count($entries) . ' IP.');

        return self::SUCCESS;
    }
}
