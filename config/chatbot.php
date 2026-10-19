<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Koneksi Database Read-Only untuk mode RTG
    |--------------------------------------------------------------------------
    |
    | Nama koneksi ini HARUS terdaftar di config/database.php, dan HARUS
    | menunjuk ke akun database yang secara fisik hanya diberi izin SELECT
    | (lihat docs/SSMS_READONLY_SETUP.md untuk cara membuat akun ini di
    | SQL Server Management Studio). Jangan pernah mengarahkan koneksi ini
    | ke akun yang punya hak INSERT/UPDATE/DELETE, karena validasi SQL di
    | AiChatService hanyalah lapisan pertahanan kedua, bukan satu-satunya.
    |
    */
    'readonly_connection' => env('CHATBOT_READONLY_DB_CONNECTION', 'sqlsrv_readonly'),

    // Batas jumlah baris hasil query yang boleh dibawa ke AI (defense in depth,
    // selain TOP/LIMIT yang diminta lewat prompt ke AI).
    'max_rtg_rows' => (int) env('CHATBOT_RTG_MAX_ROWS', 200),

    // Berapa banyak pesan riwayat terakhir yang disertakan sebagai konteks
    // saat mode 'global'. Mode 'rtg' sengaja tidak memakai riwayat panjang
    // supaya generasi query SQL tetap fokus ke pertanyaan terakhir.
    'history_limit' => (int) env('CHATBOT_HISTORY_LIMIT', 20),

    // Batas waktu tunggu (detik) saat memanggil API provider AI.
    // Naikkan kalau sering timeout, terutama untuk model gratis/tier rendah
    // yang bisa lambat merespons saat trafik sedang tinggi.
    'request_timeout' => (int) env('CHATBOT_HTTP_TIMEOUT', 120),

    // Batas token output yang diminta ke provider (dikirim sebagai
    // max_tokens & max_completion_tokens untuk provider openai_compatible).
    // Penting terutama untuk model "reasoning" yang thinking-nya tidak bisa
    // dimatikan (mis. sebagian model di combo 9 Router) — kalau nilainya
    // terlalu kecil, seluruh budget bisa habis untuk proses berpikir dan
    // jawaban akhirnya jadi kosong. Naikkan lewat .env kalau masih terjadi.
    'max_output_tokens' => (int) env('CHATBOT_MAX_OUTPUT_TOKENS', 4096),

    // Berapa lama (menit) hasil introspeksi skema database (daftar tabel &
    // kolom yang dipakai AI di mode RTG) disimpan di cache, supaya tidak
    // query INFORMATION_SCHEMA berulang di setiap pesan chat. Kalau baru
    // menambah/mengubah tabel di database, tunggu cache ini kedaluwarsa
    // atau jalankan `php artisan cache:clear`.
    'schema_cache_minutes' => (int) env('CHATBOT_SCHEMA_CACHE_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Dokumentasi skema tambahan (relasi logis tanpa FK constraint, dsb)
    |--------------------------------------------------------------------------
    |
    | Introspeksi otomatis (INFORMATION_SCHEMA + sys.foreign_keys) hanya bisa
    | menemukan relasi yang benar-benar didaftarkan sebagai FOREIGN KEY di
    | database. Banyak relasi antar tabel di sistem ini sifatnya LOGIS saja
    | (biasa dipakai untuk JOIN manual di report, mis. semua tabel karyawan
    | di-JOIN lewat kolom NPK) tapi tidak pernah didaftarkan sebagai FK
    | constraint, sehingga tidak pernah muncul di hasil introspeksi otomatis.
    |
    | File markdown di path ini (lihat docs/DATABASE_SCHEMA.md untuk contoh
    | & formatnya) dibaca oleh AiChatService::getSchemaDocumentation() dan
    | disisipkan ke prompt AI di mode RTG, BERSAMAAN dengan relasi FK hasil
    | introspeksi — supaya AI bisa memakai keduanya untuk menyusun JOIN yang
    | benar tanpa perlu menebak-nebak nama kolom.
    |
    | Cukup edit file markdown-nya untuk update dokumentasi — tidak perlu
    | ubah kode/deploy ulang. Perubahan terbaca otomatis setelah cache di
    | bawah kedaluwarsa (pakai nilai 'schema_cache_minutes' yang sama di
    | atas), atau langsung setelah `php artisan cache:clear`.
    |
    */
    'schema_docs_path' => env('CHATBOT_SCHEMA_DOCS_PATH', base_path('docs/DATABASE_SCHEMA.md')),

    // Batas panjang (karakter) isi dokumentasi yang dikirim ke prompt AI,
    // supaya dokumentasi yang berkembang jadi sangat panjang tidak
    // membengkakkan ukuran prompt secara tidak terkendali. Kalau isinya
    // melebihi batas ini, akan dipotong dan diberi penanda di prompt.
    'schema_docs_max_chars' => (int) env('CHATBOT_SCHEMA_DOCS_MAX_CHARS', 6000),

    /*
    |--------------------------------------------------------------------------
    | Detail teknis pemanggilan tiap provider
    |--------------------------------------------------------------------------
    |
    | Ini terpisah dari config('services.ai_providers') di services.php.
    | services.ai_providers dipakai partial ai-provider-select untuk UI
    | (label, deskripsi, enabled/disabled). Array di bawah ini dipakai
    | AiChatService untuk tahu HARUS memanggil endpoint yang mana dan
    | dengan format request seperti apa untuk key provider yang sama.
    |
    */
    'providers' => [

        'router9' => [
            // 9 Router — pintu utama. Format OpenAI-compatible (endpoint dasar
            // http://192.168.1.253/v1, tanpa port). AI/model yang sebenarnya
            // dipakai ditentukan oleh router itu sendiri, bukan oleh aplikasi
            // ini — kolom 'model' di bawah hanya dikirim karena field ini
            // wajib ada di payload OpenAI-compatible. Default 'chutex' — combo
            // di 9 Router yang mengurutkan/memilih model sendiri (auto fallback
            // kalau satu model kena limit), jadi aplikasi ini TIDAK menentukan
            // model spesifik. Override lewat ROUTER9_MODEL di .env kalau nama
            // combo-nya beda atau mau paksa ke model tertentu.
            'driver'   => 'openai_compatible',
            'base_url' => env('ROUTER9_BASE_URL', 'http://192.168.1.253/v1/chat/completions'),
            'api_key'  => env('ROUTER9_API_KEY'),
            'model'    => env('ROUTER9_MODEL', 'chutex'),
        ],

        'anthropic' => [
            'driver'   => 'anthropic',
            'base_url' => 'https://api.anthropic.com/v1/messages',
            'api_key'  => env('ANTHROPIC_API_KEY'),
            'model'    => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
        ],

        'deepseek' => [
            'driver'   => 'openai_compatible',
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com/v1/chat/completions'),
            'api_key'  => env('DEEPSEEK_API_KEY'),
            'model'    => env('DEEPSEEK_MODEL', 'deepseek-v4-flash'),
        ],

        'gemini' => [
            'driver'   => 'gemini',
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/models'),
            'api_key'  => env('GEMINI_API_KEY'),
            'model'    => env('GEMINI_MODEL', 'gemini-3.5-flash'),
        ],

        'openrouter' => [
            'driver'   => 'openai_compatible',
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1/chat/completions'),
            'api_key'  => env('OPENROUTER_API_KEY'),
            'model'    => env('OPENROUTER_MODEL', 'minimax/minimax-m3:free'),
        ],

        'mistral' => [
            'driver'   => 'openai_compatible',
            'base_url' => env('MISTRAL_BASE_URL', 'https://api.mistral.ai/v1/chat/completions'),
            'api_key'  => env('MISTRAL_API_KEY'),
            'model'    => env('MISTRAL_MODEL', 'mistral-large-latest'),
        ],

        'groq' => [
            'driver'   => 'openai_compatible',
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1/chat/completions'),
            'api_key'  => env('GROQ_API_KEY'),
            'model'    => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Blocklist tabel untuk mode RTG (Read To Generate)
    |--------------------------------------------------------------------------
    |
    | AI boleh SELECT ke SEMUA tabel di database (skemanya diambil otomatis
    | lewat introspeksi INFORMATION_SCHEMA, bukan didaftarkan manual),
    | KECUALI tabel-tabel yang disebut di sini. Nama tabel tidak case
    | sensitive. Default: 'users' (data akun login) dan 'payroll_masters'
    | (data master payroll) — tambahkan tabel lain lewat .env
    | (CHATBOT_RTG_BLOCKED_TABLES, pisahkan dengan koma) kalau perlu
    | memblokir tabel lain tanpa ubah kode, misalnya tabel sesi login,
    | password reset, token API, dsb.
    |
    | Ini bukan pengganti hak akses database read-only di
    | docs/SSMS_READONLY_SETUP.md — tetap wajib dipasang keduanya.
    |
    */
    'rtg_blocked_tables' => array_filter(array_map(
        'trim',
        explode(',', env('CHATBOT_RTG_BLOCKED_TABLES', 'payroll_masters,payroll_run_details,payroll_run_details_audit'))
    )),

    /*
    |--------------------------------------------------------------------------
    | Blocklist KOLOM per tabel untuk mode RTG (Read To Generate)
    |--------------------------------------------------------------------------
    |
    | Beda dari rtg_blocked_tables di atas (yang memblokir SATU TABEL SECARA
    | TOTAL): ini untuk kasus tabelnya boleh dibaca, tapi ada kolom tertentu
    | di dalamnya yang tidak boleh (mis. kolom gaji). Kolom-kolom ini
    | dihapus dari skema yang dikirim ke AI (AiChatService::getDatabaseSchema)
    | sehingga AI tidak pernah tahu kolom ini ada, dan query yang tetap
    | menyebut nama kolomnya (mis. AI menebak) ditolak juga di lapisan
    | validasi SQL (AiChatService::validateReadonlySql). SELECT * pada tabel
    | yang punya kolom terblokir juga otomatis ditolak.
    |
    | Default: employee_contract.salary, allowance, daily_salary, pph_21
    | (data gaji karyawan). Override lewat CHATBOT_RTG_BLOCKED_COLUMNS di
    | .env dengan format "tabel:kolom1,kolom2;tabel_lain:kolom3,kolom4"
    | (pisahkan tabel dengan titik koma, kolom dengan koma) kalau perlu
    | menambah/mengubah tanpa deploy ulang kode.
    |
    */
    'rtg_blocked_columns' => (function () {
        $raw = env('CHATBOT_RTG_BLOCKED_COLUMNS', 'employee_contract:salary,allowance,daily_salary,pph_21;users:password,email');
        $result = [];

        foreach (array_filter(array_map('trim', explode(';', $raw))) as $group) {
            [$table, $columnsRaw] = array_pad(explode(':', $group, 2), 2, '');
            $table = trim($table);
            $columns = array_values(array_filter(array_map('trim', explode(',', $columnsRaw))));

            if ($table !== '' && ! empty($columns)) {
                $result[$table] = $columns;
            }
        }

        return $result;
    })(),

    // Kata kunci yang membuat query langsung ditolak walaupun lolos
    // pengecekan "harus diawali SELECT". Semua dicek dalam huruf kecil.
    'forbidden_sql_keywords' => [
        'insert',
        'update',
        'delete',
        'drop',
        'alter',
        'truncate',
        'merge',
        'exec ',
        'execute',
        'grant',
        'revoke',
        'create ',
        'sp_',
        'xp_',
        'into outfile',
        'openrowset',
        'opendatasource',
        'backup',
        'restore',
        'shutdown',
        'dbcc',
    ],

];
