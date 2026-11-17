<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Koneksi Database Read-Only untuk mode RAG
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

    /*
    | Provider AI yang dipakai kalau sesi tidak menyimpan ai_provider (sesi
    | lama) dan yang ditampilkan `php artisan chatbot:diagnose`. Nilai ini
    | harus cocok dengan kunci di 'providers' bawah.
    */
    'default_provider' => env('CHATBOT_DEFAULT_PROVIDER', 'router9'),

    // Batas jumlah baris hasil query yang boleh dibawa ke AI (defense in depth,
    // selain TOP/LIMIT yang diminta lewat prompt ke AI).
    'max_rag_rows' => (int) env('CHATBOT_RAG_MAX_ROWS', 200),

    // Berapa banyak pesan riwayat terakhir yang disertakan sebagai konteks
    // saat mode 'global'. Mode 'rag' sengaja tidak memakai riwayat panjang
    // supaya generasi query SQL tetap fokus ke pertanyaan terakhir.
    'history_limit' => (int) env('CHATBOT_HISTORY_LIMIT', 20),

    // Batas waktu tunggu (detik) saat memanggil API provider AI.
    // Naikkan kalau sering timeout, terutama untuk model gratis/tier rendah
    // yang bisa lambat merespons saat trafik sedang tinggi.
    'request_timeout' => (int) env('CHATBOT_HTTP_TIMEOUT', 120),

    // Batas token output yang diminta ke provider (dikirim sebagai
    // max_tokens & max_completion_tokens untuk provider openai_compatible).
    // Penting untuk model "reasoning": lewat uji langsung ke router9, model
    // di balik combo Chutex (gemini-3-8-flash) SELALU mengirim ~2.500
    // karakter proses berpikir walau thinking diminta dimatikan. Kalau nilai
    // ini terlalu kecil, budget habis untuk berpikir dan jawaban berhenti di
    // tengah kalimat (finish_reason=length). Naikkan lewat .env kalau masih
    // terpotong.
    'max_output_tokens' => (int) env('CHATBOT_MAX_OUTPUT_TOKENS', 8192),

    // Minta gateway menonaktifkan thinking (`thinking: {type: disabled}`).
    // CATATAN JUJUR: router9 mengabaikan parameter ini — hasil uji
    // menunjukkan field reasoning tetap terisi meski dikirim. Jadi parameter
    // ini hanya "bonus" untuk gateway yang menghormatinya; pengaman
    // sesungguhnya adalah (a) max_output_tokens yang cukup, (b) deteksi
    // finish_reason=length + percobaan ulang, dan (c) larangan menampilkan
    // teks reasoning sebagai jawaban di AiChatService.
    // Set CHATBOT_DISABLE_THINKING=false untuk tidak mengirim field ini.
    'disable_thinking' => env('CHATBOT_DISABLE_THINKING', true) !== false
        && env('CHATBOT_DISABLE_THINKING', '1') !== '0',

    // Budget token cadangan saat provider berhenti karena kehabisan token
    // (finish_reason = "length") — dipakai untuk satu kali percobaan ulang
    // supaya jawaban tidak terpotong di tengah kalimat.
    'max_output_tokens_retry' => (int) env('CHATBOT_MAX_OUTPUT_TOKENS_RETRY', 16384),

    // Grafik jawaban (PNG, digambar server dengan GD).
    // - chart_auto_min_rows: jumlah baris minimum agar grafik dibuat OTOMATIS
    //   walau user tidak memintanya ("kalau datanya banyak, tampilkan chart").
    // - chart_max_bars: batas jumlah batang supaya gambar tetap terbaca.
    'chart_auto_min_rows' => (int) env('CHATBOT_CHART_AUTO_MIN_ROWS', 8),
    'chart_max_bars' => (int) env('CHATBOT_CHART_MAX_BARS', 12),

    // Berapa lama (menit) hasil introspeksi skema database (daftar tabel &
    // kolom yang dipakai AI di mode RAG) disimpan di cache, supaya tidak
    // query INFORMATION_SCHEMA berulang di setiap pesan chat. Kalau baru
    // menambah/mengubah tabel di database, tunggu cache ini kedaluwarsa
    // atau jalankan `php artisan cache:clear`.
    'schema_cache_minutes' => (int) env('CHATBOT_SCHEMA_CACHE_MINUTES', 30),

    // Cache untuk hasil KOSONG / gagal baca skema. Dulu hasil kosong ikut
    // di-cache selama `schema_cache_minutes` (30 menit), sehingga setelah
    // admin memperbaiki koneksi read-only chatbot tetap melaporkan "tidak
    // ada tabel yang bisa dibaca" sampai cache kedaluwarsa. Sekarang cache
    // kosong hanya bertahan beberapa detik.
    'schema_empty_cache_seconds' => (int) env('CHATBOT_SCHEMA_EMPTY_CACHE_SECONDS', 15),

    // Ulangi sekali saat provider AI timeout (ConnectionException), dengan
    // budget token lebih kecil. Server AI bersama (bottleneck) kadang lambat
    // pada permintaan pertama; tanpa retry, karyawan menunggu lama hanya
    // untuk mendapat pesan error.
    'retry_on_timeout' => (bool) env('CHATBOT_RETRY_ON_TIMEOUT', true),

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
    | disisipkan ke prompt AI di mode RAG, BERSAMAAN dengan relasi FK hasil
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
    | Dokumen aturan RAG (dibaca SEBELUM query disusun)
    |--------------------------------------------------------------------------
    |
    | Berkas markdown berisi aturan resmi penulisan query untuk database CII
    | (R1–R25, peta keputusan pertanyaan→tabel→kolom→join, daftar tabel
    | terbatas, batasan SQL Server 2014, format jawaban).
    |
    | AiChatService::handleRag() membaca berkas ini lebih dulu, lalu
    | menyisipkan bagian intinya ke prompt penyusun SQL. Kalau berkas tidak
    | terbaca, mode RAG berhenti (fail-closed) dan user diberi pesan jelas —
    | supaya chatbot tidak pernah menyusun query tanpa aturan.
    |
    | Cukup edit markdown-nya untuk memperbarui aturan: cache memakai
    | mtime + ukuran berkas, jadi perubahan langsung terbaca tanpa deploy.
    |
    */
    'rag_rules_path' => env('CHATBOT_RAG_RULES_PATH', base_path('docs/RAG_RULES_CII.md')),

    // Batas karakter blok aturan yang dikirim ke prompt (bagian inti +
    // baris peta keputusan yang relevan). Bukan batas panjang berkas.
    'rag_rules_max_chars' => (int) env('CHATBOT_RAG_RULES_MAX_CHARS', 22000),

    // R25 melarang empat tabel audit. Kalau true, daftar itu otomatis
    // ditambahkan ke blocklist runtime (selain CHATBOT_RAG_BLOCKED_TABLES).
    'rag_rules_enforce_r25' => (bool) env('CHATBOT_RAG_RULES_ENFORCE_R25', true),

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

    ],

    /*
    |--------------------------------------------------------------------------
    | Blocklist tabel untuk mode RAG (Read And Generate)
    |--------------------------------------------------------------------------
    |
    | AI boleh SELECT ke SEMUA tabel di database (skemanya diambil otomatis
    | lewat introspeksi INFORMATION_SCHEMA, bukan didaftarkan manual),
    | KECUALI tabel-tabel yang disebut di sini. Nama tabel tidak case
    | sensitive. Default: 'users' (data akun login) dan 'payroll_masters'
    | (data master payroll) — tambahkan tabel lain lewat .env
    | (CHATBOT_RAG_BLOCKED_TABLES, pisahkan dengan koma) kalau perlu
    | memblokir tabel lain tanpa ubah kode, misalnya tabel sesi login,
    | password reset, token API, dsb.
    |
    | Ini bukan pengganti hak akses database read-only di
    | docs/SSMS_READONLY_SETUP.md — tetap wajib dipasang keduanya.
    |
    */
    'rag_blocked_tables' => array_filter(array_map(
        'trim',
        explode(',', env('CHATBOT_RAG_BLOCKED_TABLES', 'payroll_masters,payroll_run_details,payroll_run_details_audit'))
    )),

    /*
    |--------------------------------------------------------------------------
    | Blocklist KOLOM per tabel untuk mode RAG (Read And Generate)
    |--------------------------------------------------------------------------
    |
    | Beda dari rag_blocked_tables di atas (yang memblokir SATU TABEL SECARA
    | TOTAL): ini untuk kasus tabelnya boleh dibaca, tapi ada kolom tertentu
    | di dalamnya yang tidak boleh (mis. kolom gaji). Kolom-kolom ini
    | dihapus dari skema yang dikirim ke AI (AiChatService::getDatabaseSchema)
    | sehingga AI tidak pernah tahu kolom ini ada, dan query yang tetap
    | menyebut nama kolomnya (mis. AI menebak) ditolak juga di lapisan
    | validasi SQL (AiChatService::validateReadonlySql). SELECT * pada tabel
    | yang punya kolom terblokir juga otomatis ditolak.
    |
    | Default: employees_contract.salary, allowance, daily_salary, pph21,
    | file_contract (data gaji karyawan). Nama tabel & kolom di sini HARUS
    | sama persis dengan skema database — kalau salah nama, kolomnya tidak
    | ikut terhapus dari prompt AI (dan tidak ada peringatan apa pun).
    | Override lewat CHATBOT_RAG_BLOCKED_COLUMNS di
    | .env dengan format "tabel:kolom1,kolom2;tabel_lain:kolom3,kolom4"
    | (pisahkan tabel dengan titik koma, kolom dengan koma) kalau perlu
    | menambah/mengubah tanpa deploy ulang kode.
    |
    */
    'rag_blocked_columns' => (function () {
        $raw = env('CHATBOT_RAG_BLOCKED_COLUMNS', 'employees_contract:salary,allowance,daily_salary,pph21,pph_21,file_contract;leave_requests:token;whatsapp_devices:token');
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