<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Live Map — titik origin (lokasi server/pabrik)
    |--------------------------------------------------------------------------
    | Dipakai Live Map di /live-access sebagai titik tujuan semua garis akses.
    | Nilai bawaan = lokasi pabrik PT. Chutex International Indonesia
    | (Menggungan, Telukan, Grogol, Sukoharjo, Jawa Tengah). Ubah lewat .env
    | bila server dipindah, tanpa mengutak-atik kode:
    |
    |   LIVE_MAP_ORIGIN_LAT=-7.630187
    |   LIVE_MAP_ORIGIN_LNG=110.826562
    |   LIVE_MAP_ORIGIN_LABEL="PT. Chutex International Indonesia"
    |
    */

    'map' => [
        'origin' => [
            'lat' => (float) env('LIVE_MAP_ORIGIN_LAT', -7.630187),
            'lng' => (float) env('LIVE_MAP_ORIGIN_LNG', 110.826562),
            'label' => (string) env('LIVE_MAP_ORIGIN_LABEL', 'PT. Chutex International Indonesia'),
        ],

        // Cache GeoIP lokal (diisi oleh `php artisan live-access:geoip`).
        // Selama file ini kosong, IP publik ditampilkan sebagai
        // "lokasi belum diketahui" dan TIDAK digambar di peta (tidak menebak).
        'geoip_cache' => storage_path('app/live_access_geoip.json'),

        // Endpoint pencarian GeoIP massal (dipakai command live-access:geoip).
        'geoip_endpoint' => env('LIVE_MAP_GEOIP_ENDPOINT', 'http://ip-api.com/batch?fields=status,country,city,lat,lon,query'),

        // Jumlah maksimum sumber IP yang digambar pada peta.
        'max_sources' => (int) env('LIVE_MAP_MAX_SOURCES', 40),
    ],

];
