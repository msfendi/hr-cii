<?php

return [
    'url' => env('HERMES_URL', 'http://127.0.0.1:8642'),
    'api_key' => env('HERMES_API_KEY', ''),
    // Model aktif di gateway (kosong = default profile Hermes; kolom "aktif"
    // di daftar model admin tidak akan menandai satu pun).
    'model' => env('HERMES_MODEL', ''),
    'timeout' => (int) env('HERMES_TIMEOUT', 120),
    'streaming' => filter_var(env('HERMES_STREAMING', true), FILTER_VALIDATE_BOOLEAN),

    // Fallback non-streaming: saat ChatService::respondStreaming mencapai batas
    // idle/total atas stream, coba ulang satu kali via jalur chat() biasa (juga
    // bisa dipakai bila cURL tak mendukung streaming PSR-7 sink seperti Http::fake).
    'streaming_fallback' => filter_var(env('HERMES_STREAMING_FALLBACK', true), FILTER_VALIDATE_BOOLEAN),

    'max_prompt_length' => (int) env('AI_MAX_PROMPT_LENGTH', 10000),
    'max_daily_messages' => (int) env('AI_MAX_DAILY_MESSAGES', 500),
    'rate_limit_per_minute' => (int) env('AI_RATE_LIMIT_PER_MINUTE', 30),

    // Penjaga waktu stream balasan AI. Timeout HTTP saja tidak cukup:
    // koneksi ke gateway bisa tetap hidup tanpa mengirim isi (heartbeat),
    // sehingga request menggantung sangat lama lalu berakhir dengan jawaban
    // setengah jadi. stream_max_duration = batas total, stream_idle_timeout =
    // batas tanpa chunk baru.
    'stream_max_duration' => (int) env('AI_STREAM_MAX_DURATION', 240),
    'stream_idle_timeout' => (int) env('AI_STREAM_IDLE_TIMEOUT', 90),

    // Context yang dikirim ke Hermes sebagai riwayat percakapan.
    'context_messages' => (int) env('HERMES_CONTEXT_MESSAGES', 20),

    // Tanya data perusahaan (Smart IT / CII) via chat — SELECT readonly,
    // whitelist tabel, penolakan tulis ditegakkan level database.
    'data_query' => [
        'enabled' => filter_var(env('DATA_QUERY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'max_rows' => (int) env('DATA_QUERY_MAX_ROWS', 50),
    ],

    // Lampiran chat: ekstraksi teks utk konteks AI (disimpan lokal, privat).
    'attachments' => [
        'max_files' => (int) env('ATTACHMENTS_MAX_FILES', 5),
        'max_kb' => (int) env('ATTACHMENTS_MAX_KB', 5120),
        'mimes' => env('ATTACHMENTS_MIMES', 'pdf,docx,xlsx,pptx,txt,md,csv,json,png,jpg,jpeg,gif,webp'),
        'image_mimes' => env('ATTACHMENTS_IMAGE_MIMES', 'png,jpg,jpeg,gif,webp'),
        'context_chars_per_file' => (int) env('ATTACHMENTS_CONTEXT_CHARS', 6000),
    ],

    // File hasil kerja Hermes (token MEDIA: di balasan AI) yang ditarik ke server chat.
    'media' => [
        'max_kb' => (int) env('AI_MEDIA_MAX_KB', 51200),
    ],
];
