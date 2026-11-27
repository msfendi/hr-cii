<?php

return [
    /*
     * Kunci untuk meng-hash ID perangkat. Jangan diganti setelah dipakai,
     * karena hash lama tidak akan cocok lagi (semua perangkat dianggap belum
     * mengisi). Default mengikuti APP_KEY bila SURVEY_IP_PEPPER tidak diisi.
     */
    'pepper' => env('SURVEY_IP_PEPPER', env('APP_KEY', 'chutex-survey-anti-spam')),

    /*
     * Nama cookie identitas perangkat. Cookie ini diisi otomatis oleh halaman
     * survei; nilainya token acak 64 hex, tanpa data pribadi. Karena dipakai
     * sebagai kunci anti-spam, jangan diubah tanpa mengosongkan tabel log.
     *
     * Catatan: nama ini harus ikut didaftarkan di App\Http\Middleware\EncryptCookies
     * ($except) supaya nilainya dibaca apa adanya.
     */
    'device_cookie' => env('SURVEY_DEVICE_COOKIE', 'satisfaction_device'),

    // Umur cookie perangkat (hari). Default 400 hari.
    'device_cookie_days' => (int) env('SURVEY_DEVICE_COOKIE_DAYS', 400),

    // Kiriman yang berhasil dihitung per perangkat per bulan.
    'max_per_period' => 1,

    // Percobaan POST (termasuk yang ditolak) per perangkat per periode.
    'max_attempts' => (int) env('SURVEY_MAX_ATTEMPTS', 3),

    // Umur hitungan percobaan (hari). 30 hari ~ 1 bulan.
    'limit_decay_days' => (int) env('SURVEY_LIMIT_DECAY_DAYS', 30),
];