<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provider AI untuk ekstraksi dokumen (vision)
    |--------------------------------------------------------------------------
    | Daftar provider DIURUTKAN. Ekstraktor mencoba satu per satu sampai
    | ada yang berhasil, jadi ketika provider utama kena kuota / rate limit
    | fitur tetap jalan lewat provider cadangan.
    |
    | Tipe:
    |   gemini  → API Gemini (contents + inline_data). Support PDF inline
    |             & responseSchema (JSON terstruktur paling akurat).
    |   openai  → API OpenAI-compatible (chat/completions). Support image_url
    |             & response_format json_object. PDF dikirim sebagai teks layer.
    */
    'providers' => [

        /*
         * 1) 9Router (Chutex) — dipakai PERTAMA supaya model aktif mudah
         *    dimanage dari dashboard 9Router (ganti CHUTEX_MODEL di .env,
         *    tanpa perlu ubah kode).
         */
        'router' => [
            'type'                => 'openai',
            'base_url'            => rtrim(env('CHUTEX_BASE_URL', 'http://192.168.0.43:20128/v1'), '/'),
            'api_key'             => env('CHUTEX_API_KEY'),
            'model'               => env('CHUTEX_MODEL', 'Chutex'),
            'fallback_models'     => array_values(array_filter(array_map('trim', explode(',', (string) env('RECRUITMENT_AI_ROUTER_FALLBACK_MODELS', ''))))),
            'timeout'             => (int) env('RECRUITMENT_AI_TIMEOUT', 150),
            'max_output_tokens'   => (int) env('RECRUITMENT_AI_MAX_TOKENS', 4096),
            'supports_schema'     => false,
            'supports_pdf_inline' => false,
            // response_format=json_object TIDAK dipakai di endpoint ini —
            // membuat model membalas kosong (finish_reason: length).
            'supports_json_object' => false,
        ],

        /*
         * 2) Gemini — paling akurat (PDF inline + responseSchema).
         */
        'gemini' => [
            'type'                => 'gemini',
            'base_url'            => rtrim(env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/'),
            'api_key'             => env('GEMINI_API_KEY'),
            'model'               => env('RECRUITMENT_AI_MODEL', env('GEMINI_MODEL', 'gemini-3.6-flash')),
            'fallback_models'     => array_values(array_filter(array_map('trim', explode(',', (string) env('RECRUITMENT_AI_FALLBACK_MODELS', 'gemini-3.5-flash'))))),
            'timeout'             => (int) env('RECRUITMENT_AI_TIMEOUT', 150),
            'max_output_tokens'   => (int) env('RECRUITMENT_AI_MAX_TOKENS', 4096),
            'supports_schema'     => true,
            'supports_pdf_inline' => true,
        ],

        /*
         * 3) Cadangan terakhir — OpenAI-compatible (vision).
         */
        'openai' => [
            'type'                => 'openai',
            'base_url'            => rtrim(env('DEEPSEEK_BASE_URL', 'https://api-inference.bitdeer.ai/v1'), '/'),
            'api_key'             => env('DEEPSEEK_API_KEY'),
            'model'               => env('DEEPSEEK_MODEL', 'Qwen/Qwen3.8-27B'),
            'fallback_models'     => [],
            'timeout'             => (int) env('RECRUITMENT_AI_TIMEOUT', 150),
            'max_output_tokens'   => (int) env('RECRUITMENT_AI_MAX_TOKENS', 4096),
            'supports_schema'     => false,
            'supports_pdf_inline' => false,
            'supports_json_object' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Batas & tool pendukung
    |--------------------------------------------------------------------------
    */
    'max_upload_kb'      => (int) env('RECRUITMENT_AI_MAX_UPLOAD_KB', 5120),   // 5 MB per file
    'pdf_text_binary'    => env('PDF_TO_TEXT_BINARY_PATH', 'C:/laragon/bin/git/mingw64/bin/pdftotext.exe'),
    'disk'               => 'public',
    'storage_folder'     => 'pelamar-ai',

    // CV kadang hanya mengembalikan pendidikan terakhir. Saat hasil awal
    // kurang dari 2 baris, ekstraktor dipanggil sekali lagi khusus field
    // riwayat pendidikan. Matikan lewat env bila kuota AI terbatas.
    'education_retry'    => (bool) env('RECRUITMENT_AI_EDUCATION_RETRY', true),

    /*
    |--------------------------------------------------------------------------
    | Session key untuk draft (tidak disimpan ke database)
    |--------------------------------------------------------------------------
    */
    'session_key'        => 'reg_ai_draft',

    /*
    |--------------------------------------------------------------------------
    | Ambang confidence: di bawah ini field ditandai "perlu dicek"
    |--------------------------------------------------------------------------
    */
    'low_confidence'     => (float) env('RECRUITMENT_AI_LOW_CONFIDENCE', 0.75),

    /*
    |--------------------------------------------------------------------------
    | RapidOCR — pre-processing teks dari gambar
    |--------------------------------------------------------------------------
    | Server OCR lokal (RapidOCR) dipakai untuk membaca teks mentah dari
    | gambar SEBELUM dikirim ke model AI. Teks ini ditambahkan ke prompt
    | sebagai konteks tambahan sehingga model vision bisa mencocokkan
    | apa yang terlihat di gambar dengan teks OCR — meningkatkan akurasi
    | terutama untuk scan yang blur / resolusi rendah.
    |
    | Kalau server OCR mati / unreachable, alur tetap jalan tanpanya
    | (graceful degradation).
    */
    'rapidocr' => [
        'enabled'  => (bool) env('RAPIDOCR_ENABLED', true),
        'base_url' => env('RAPIDOCR_URL', 'http://192.168.0.68:9003'),
        'timeout'  => (int) env('RAPIDOCR_TIMEOUT', 30),
    ],

];
