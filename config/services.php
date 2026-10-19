<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'fonnte' => [
        'master_key' => env('FONNTE_MASTER_KEY'),
    ],

    'node_exporter' => [
        'url'     => env('NODE_EXPORTER_URL', 'http://192.168.1.240:9100/metrics'),
        'timeout' => env('NODE_EXPORTER_TIMEOUT', 3), // detik
    ],

    'ssl_monitor' => [
        'host' => env('SSL_MONITOR_HOST'),
    ],

    'ga4' => [
        'property_id' => env('GA4_PROPERTY_ID'),
        'credentials' => env('GA4_CREDENTIALS_PATH', storage_path('app/ga4-service-account.json')),
    ],
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model'   => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
    ],

    'deepseek' => [
        'api_key' => env('DEEPSEEK_API_KEY'),
        'model'   => env('DEEPSEEK_MODEL', 'deepseek-v4-flash'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model'   => env('GEMINI_MODEL', 'gemini-3.5-flash'),
    ],

    'router9' => [
        'api_key' => env('ROUTER9_API_KEY'),
        'model'   => env('ROUTER9_MODEL', 'router-default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Provider — Import (Packing List)
    |--------------------------------------------------------------------------
    |
    | Sumber tunggal daftar pilihan AI yang muncul di form import (baik
    | Import PL MDS System maupun Import Final Packing List / PPIC).
    | Set 'enabled' => false untuk MENYEMBUNYIKAN sebuah provider dari
    | pilihan tanpa perlu menyentuh view. 'order' menentukan urutan tampil.
    | Value 'enabled' bisa dikontrol lewat .env supaya tidak perlu deploy
    | ulang kode hanya untuk mematikan satu provider.
    |
    */
    'ai_providers' => [
        'router9' => [
            // Pintu utama: semua request lewat sini, 9 Router yang menentukan
            // AI/model mana yang dipakai di baliknya. Enabled true by default
            // supaya jadi satu-satunya pilihan yang tampil di form.
            'enabled'     => env('AI_PROVIDER_ROUTER9_ENABLED', true),
            'order'       => 1,
            'label'       => '9 Router',
            'description' => 'Pintu utama — AI yang dipakai ditentukan otomatis oleh 9 Router.',
        ],
    ],

    'ai_import' => [
        // Jumlah baris per request AI, kecilkan jika file besar / token terbatas
        'chunk_size' => env('AI_IMPORT_CHUNK_SIZE', 25),
    ],

];
