<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HRIS v2 — Portal Karyawan
    |--------------------------------------------------------------------------
    | Konfigurasi portal terpisah /v2. Route "/" dan alur lama tidak diubah.
    */

    // Batas waktu sesi karyawan (menit) setelah satu kali login NPK + wajah.
    // 0 = tanpa batas (tidak disarankan pada komputer bersama).
    'session_minutes' => (int) env('HRIS_V2_SESSION_MINUTES', 480),

    // Default mode verifikasi kalau kolomnya belum diisi admin.
    // liveness | random_challenge | none
    'default_verification_mode' => env('HRIS_V2_VERIFICATION_MODE', 'liveness'),
];
