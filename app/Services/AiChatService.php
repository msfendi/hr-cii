<?php

namespace App\Services;

use App\Models\ChatbotMessage;
use App\Models\ChatbotSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class AiChatService
{
    /**
     * Titik masuk utama. Mengembalikan array:
     * ['content' => string, 'meta' => array|null]
     */
    public function reply(ChatbotSession $session, string $userMessage, array $attachments = []): array
    {
        $providerKey = $session->ai_provider;

        if (! config("chatbot.providers.$providerKey")) {
            throw new RuntimeException("Provider AI '$providerKey' belum dikonfigurasi di config/chatbot.php.");
        }

        return $session->isRtg()
            ? $this->handleRtg($session, $providerKey, $userMessage, $attachments)
            : $this->handleGlobal($session, $providerKey, $userMessage, $attachments);
    }

    /*
    |--------------------------------------------------------------------------
    | Mode GLOBAL — ngobrol bebas, tanpa akses database
    |--------------------------------------------------------------------------
    */
    protected function handleGlobal(ChatbotSession $session, string $providerKey, string $userMessage, array $attachments = []): array
    {
        $system = 'Kamu adalah AI Assistant HRIS Chutex, asisten AI di dalam sistem internal HRIS Chutex. '
            . 'Jawab dengan bahasa Indonesia yang ringkas, jelas, dan sopan. '
            . 'Kamu TIDAK punya akses ke database perusahaan pada mode ini, jadi jangan mengarang data spesifik '
            . '— cukup bantu pertanyaan umum, penjelasan proses, atau bantuan pemakaian sistem. '
            . 'Kalau user melampirkan file: untuk gambar kamu bisa melihat isinya langsung, untuk jenis file lain '
            . '(dokumen, audio, video) kamu hanya diberi tahu nama & jenis filenya saja (tidak bisa membaca isinya), '
            . 'jadi kalau perlu tanyakan isinya ke user.';

        $history = $session->messages()
            ->reorder('created_at', 'desc')
            ->take(config('chatbot.history_limit', 20))
            ->get()
            ->reverse()
            ->values();

        $messages = $history->map(fn(ChatbotMessage $m) => [
            'role' => $m->role === ChatbotMessage::ROLE_ASSISTANT ? 'assistant' : 'user',
            'content' => $m->content,
        ])->push([
            'role' => 'user',
            'content' => $this->buildUserContent($providerKey, $userMessage, $attachments),
        ])->all();

        $content = $this->callProvider($providerKey, $system, $messages);

        return ['content' => $content, 'meta' => null];
    }

    /**
     * Bangun isi pesan user untuk dikirim ke provider AI. Kalau ada
     * lampiran gambar DAN provider yang dipakai mendukung vision (saat ini
     * hanya driver 'anthropic'), isi pesan dibangun sebagai array of
     * content blocks (gambar base64 + teks) supaya AI benar-benar bisa
     * "melihat" gambarnya — bukan cuma tahu nama filenya. Untuk provider
     * lain, atau kalau tidak ada gambar, isi pesan tetap berupa string
     * biasa (ditambah catatan nama file yang dilampirkan) supaya kompatibel
     * dengan semua driver yang ada.
     */
    protected function buildUserContent(string $providerKey, string $userMessage, array $attachments = [])
    {
        if (empty($attachments)) {
            return $userMessage;
        }

        $noteLines = collect($attachments)
            ->map(fn(array $a) => "- {$a['original_name']} ({$a['kind']}, {$a['mime']})")
            ->implode("\n");

        $textContent = trim($userMessage . "\n\n[File terlampir]\n{$noteLines}");

        $cfg = config("chatbot.providers.$providerKey");
        $imageAttachments = array_values(array_filter($attachments, fn(array $a) => ($a['kind'] ?? null) === 'image'));

        if (($cfg['driver'] ?? null) !== 'anthropic' || empty($imageAttachments)) {
            return $textContent;
        }

        $blocks = [];

        foreach ($imageAttachments as $attachment) {
            $bytes = $this->readAttachmentBytes($attachment);

            if ($bytes === null) {
                continue;
            }

            $blocks[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $attachment['mime'],
                    'data' => base64_encode($bytes),
                ],
            ];
        }

        // Kalau ternyata semua gambar gagal dibaca dari disk, jatuhkan
        // balik ke teks biasa saja supaya pesan tetap bisa terkirim.
        if (empty($blocks)) {
            return $textContent;
        }

        $blocks[] = ['type' => 'text', 'text' => $textContent !== '' ? $textContent : '(lihat gambar terlampir)'];

        return $blocks;
    }

    /**
     * Baca isi file lampiran dari disk untuk dikirim sebagai base64 ke
     * provider vision. Dibatasi ukuran supaya tidak melebihi limit payload
     * provider (mis. Anthropic membatasi ~5MB per gambar).
     */
    protected function readAttachmentBytes(array $attachment): ?string
    {
        if (($attachment['size'] ?? 0) > 5 * 1024 * 1024) {
            return null;
        }

        try {
            return Storage::disk(config('chatbot.export_disk', 'public'))->get($attachment['path']);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Mode RTG (Read To Generate) — AI menyusun query SELECT read-only,
    | dijalankan lewat koneksi database read-only, lalu hasilnya dipakai
    | AI untuk menyusun jawaban bahasa natural. Semua data yang berhasil
    | diambil juga di-export ke file (default Excel, atau PDF/gambar kalau
    | user memintanya secara eksplisit) supaya bisa diunduh dari chat.
    |--------------------------------------------------------------------------
    */
    protected function handleRtg(ChatbotSession $session, string $providerKey, string $userMessage, array $attachments = []): array
    {
        // Mode RTG hanya bekerja dari data database (query read-only), jadi
        // lampiran file (kalau ada) TIDAK dipakai untuk menyusun query SQL
        // maupun dikirim ke AI di mode ini — parameter ini hanya dijaga
        // supaya tanda tangan method konsisten dengan handleGlobal().
        unset($attachments);

        $blockedTables = $this->blockedTables();
        $blockedColumns = $this->blockedColumns();
        $schema = $this->getDatabaseSchema($blockedTables, $blockedColumns);

        if (empty($schema)) {
            return [
                'content' => 'Mode RTG belum bisa dipakai: tidak ada tabel yang bisa dibaca dari koneksi database read-only '
                    . '(cek konfigurasi koneksi "' . config('chatbot.readonly_connection') . '" di config/database.php).',
                'meta' => ['sql' => null],
            ];
        }

        // Kirim daftar tabel & kolom SECARA LENGKAP (semua tabel yang tidak
        // diblokir) plus relasi FK antar tabel, supaya AI tidak menebak-nebak
        // nama tabel/kolom atau salah pasang JOIN. Ini sumber utama error
        // "Invalid object/column name" sebelumnya.
        $schemaText = $this->formatSchemaForPrompt($schema);
        $relations = $this->getDatabaseRelations($blockedTables);
        $relationsText = empty($relations)
            ? '(tidak ada informasi foreign key yang terbaca — kalau perlu JOIN, cocokkan lewat penamaan kolom yang masuk akal, mis. tabel_id ke tabel.id)'
            : implode("\n", array_map(fn($r) => "- {$r}", $relations));

        // Relasi LOGIS (tanpa FK constraint di database, mis. join lewat
        // kolom NPK antar tabel karyawan) — tidak bisa ditemukan lewat
        // introspeksi otomatis di atas, jadi didokumentasikan manual oleh
        // tim di docs/DATABASE_SCHEMA.md dan dibaca di sini sebagai
        // pelengkap $relationsText. Kosong kalau file dokumentasinya belum
        // ada/belum diisi — tidak wajib.
        $schemaDocs = $this->getSchemaDocumentation();
        $schemaDocsBlock = $schemaDocs === ''
            ? ''
            : "\n\nDokumentasi tambahan dari tim (relasi logis tanpa FK constraint, konvensi penamaan kolom, dsb — PAKAI ini juga untuk menentukan JOIN yang benar, terutama kalau tabel/kolom yang relevan tidak ada di daftar relasi FK di atas):\n{$schemaDocs}";

        $maxRows = config('chatbot.max_rtg_rows', 4000);
        $maxAttempts = max(1, (int) config('chatbot.max_rtg_query_attempts', 3));
        $blockedList = implode(', ', $blockedTables);

        // Daftar kolom sensitif per tabel (mis. gaji) yang sudah DIHAPUS dari
        // $schemaText di atas, jadi AI seharusnya tidak pernah melihat nama
        // kolom ini sama sekali. Baris ini murni pengingat eksplisit supaya
        // AI tidak mencoba menebak/mengarang nama kolom serupa (mis. "gaji",
        // "salary_amount") kalau user bertanya soal data ini.
        $blockedColumnsList = collect($blockedColumns)
            ->map(fn(array $cols, string $table) => "{$table}.(" . implode(', ', $cols) . ')')
            ->implode(', ');
        $blockedColumnsRule = $blockedColumnsList === ''
            ? ''
            : "\n5. Kolom-kolom berikut TIDAK TERSEDIA sama sekali dan TIDAK BOLEH disebut, diminta, atau ditebak namanya lain: {$blockedColumnsList}. Kolom-kolom ini sudah sengaja dihapus dari daftar skema di atas — kalau pertanyaan user hanya bisa dijawab lewat kolom ini, balas NO_QUERY.";

        $sqlSystemPrompt = <<<PROMPT
            Kamu adalah generator query SQL Server (T-SQL) READ-ONLY untuk sebuah sistem aplikasi.
            Berikut daftar SEMUA tabel & kolom yang benar-benar ada di database ini (hasil introspeksi
            otomatis langsung dari INFORMATION_SCHEMA — bukan hafalan/perkiraan), kolom yang ditandai (PK)
            adalah primary key:

            $schemaText

            Relasi foreign key antar tabel (pakai ini untuk menentukan JOIN yang benar, JANGAN menebak):
            $relationsText{$schemaDocsBlock}

            Aturan WAJIB:
            1. Hanya boleh menghasilkan SATU statement SELECT. Dilarang keras INSERT/UPDATE/DELETE/DROP/ALTER/EXEC atau statement lain.
            2. Hanya boleh memakai tabel & kolom yang PERSIS tercantum di daftar di atas (nama harus sama persis, termasuk huruf besar/kecil pada penulisan aslinya). Jangan mengarang, menyingkat, atau menebak nama tabel/kolom yang tidak ada di daftar. DILARANG memakai SELECT * — selalu sebutkan nama kolom secara eksplisit.
            3. Kalau mau JOIN antar tabel, cocokkan dulu dengan daftar relasi foreign key ATAU dokumentasi relasi logis di atas. Kalau tidak ada di keduanya, baru cari kolom dengan nama yang paling masuk akal di kedua tabel (mis. id vs sesuatu_id) — jangan asal tebak nama kolom join kalau relasinya sudah terdokumentasi.
            4. Tabel berikut TERLARANG TOTAL dan tidak boleh muncul di query dengan cara apa pun, termasuk lewat JOIN atau subquery: {$blockedList}.{$blockedColumnsRule}
            6. Urutan penulisan klausa di awal query WAJIB: SELECT lalu (kalau perlu) DISTINCT lalu TOP.
               Contoh BENAR : SELECT DISTINCT TOP {$maxRows} destination FROM ...
               Contoh SALAH : SELECT TOP {$maxRows} DISTINCT destination FROM ...
            7. Jangan tambahkan penjelasan, komentar, atau teks lain di luar query.
            8. Balas HANYA dengan query SQL polos di dalam blok kode ```sql ... ```.

            Jika pertanyaan user hanya bisa dijawab lewat tabel/kolom yang terlarang di atas, atau tidak bisa dijawab
            dari skema yang tersedia sama sekali, balas persis: NO_QUERY
            PROMPT;

        $rawSqlResponse = $this->callProvider($providerKey, $sqlSystemPrompt, [
            ['role' => 'user', 'content' => $userMessage],
        ]);

        if (trim($rawSqlResponse) === 'NO_QUERY') {
            return [
                'content' => 'Maaf, pertanyaan itu belum bisa saya jawab dari data yang tersedia untuk mode RTG saat ini '
                    . '(atau menyentuh data yang memang tidak boleh diakses, seperti data akun pengguna, dan gaji). '
                    . 'Coba ajukan dengan cara lain.',
                'meta' => ['sql' => null],
            ];
        }

        $sql = $this->normalizeSql($this->extractSql($rawSqlResponse));
        $rows = null;
        $lastError = null;

        // Loop percobaan: kalau query gagal dieksekusi (salah nama tabel/kolom,
        // salah sintaks, dsb), lempar balik pesan error asli dari database ke
        // AI supaya query-nya diperbaiki, bukan langsung menyerah di percobaan
        // pertama seperti sebelumnya.
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $validation = $this->validateReadonlySql($sql, $blockedTables, $blockedColumns);

            if (! $validation['valid']) {
                return [
                    'content' => 'Maaf, saya tidak bisa menjalankan permintaan ini dengan aman (' . $validation['reason'] . '). '
                        . 'Coba ajukan pertanyaan dengan cara lain.',
                    'meta' => ['sql' => $sql, 'error' => $validation['reason']],
                ];
            }

            try {
                $rows = DB::connection(config('chatbot.readonly_connection'))->select($sql);
                $lastError = null;

                break;
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();

                if ($attempt >= $maxAttempts) {
                    break;
                }

                $fixPrompt = <<<PROMPT
                    Query T-SQL berikut gagal dieksekusi ke database:

                    ```sql
                    $sql
                    ```

                    Pesan error dari database:
                    {$lastError}

                    Ini biasanya berarti ada nama tabel/kolom yang tidak cocok dengan skema asli, atau
                    ada JOIN yang salah pasang kolom. Perbaiki query-nya dengan HANYA memakai tabel & kolom
                    yang persis ada di daftar skema dan relasi foreign key yang sudah diberikan sebelumnya.
                    Balas HANYA dengan query SQL yang sudah diperbaiki di dalam blok kode ```sql ... ```,
                    atau balas persis NO_QUERY kalau memang tidak bisa diperbaiki.
                    PROMPT;

                $rawFixResponse = $this->callProvider($providerKey, $sqlSystemPrompt, [
                    ['role' => 'user', 'content' => $userMessage],
                    ['role' => 'assistant', 'content' => "```sql\n{$sql}\n```"],
                    ['role' => 'user', 'content' => $fixPrompt],
                ]);

                if (trim($rawFixResponse) === 'NO_QUERY') {
                    $lastError = 'AI menyerah memperbaiki query setelah melihat error database.';

                    break;
                }

                $sql = $this->normalizeSql($this->extractSql($rawFixResponse));
            }
        }

        if ($rows === null) {
            return [
                'content' => 'Query yang dibuat AI gagal dijalankan ke database setelah ' . $maxAttempts . ' kali percobaan '
                    . '(kemungkinan nama tabel/kolom tidak cocok dengan skema asli, atau ada perbedaan sintaks). '
                    . 'Silakan coba pertanyaan lain atau perjelas maksudnya.',
                'meta' => ['sql' => $sql, 'error' => $lastError],
            ];
        }

        $rows = array_slice($rows, 0, $maxRows);
        $rowsJson = json_encode($rows, JSON_UNESCAPED_UNICODE);

        $answerSystemPrompt = 'Kamu adalah AI Assistant HRIS Chutex, asisten data untuk sistem HRIS Chutex. Berdasarkan data JSON hasil query database '
            . 'berikut, jawab pertanyaan user dalam bahasa Indonesia yang natural dan ringkas. Jika data kosong, katakan datanya '
            . 'tidak ditemukan. Jangan menyebutkan detail teknis (nama tabel/kolom SQL) kecuali user menanyakannya secara eksplisit.';

        $answer = $this->callProvider($providerKey, $answerSystemPrompt, [
            ['role' => 'user', 'content' => "Pertanyaan user: {$userMessage}\n\nData JSON (maks {$maxRows} baris):\n{$rowsJson}"],
        ]);

        $format = $this->detectExportFormat($userMessage);
        $export = $this->exportRows($rows, $format, $userMessage);

        return [
            'content' => $answer,
            'meta' => array_filter([
                'sql' => $sql,
                'row_count' => count($rows),
                'export' => $export,
            ], fn($value) => $value !== null),
        ];
    }

    /**
     * Tabel yang benar-benar terlarang total di mode RTG, apa pun isi
     * pertanyaan user. Defaultnya 'users' dan 'payroll_masters' sesuai
     * permintaan, tapi bisa ditambah lewat CHATBOT_RTG_BLOCKED_TABLES di
     * .env (pisahkan dengan koma) tanpa perlu ubah kode.
     */
    protected function blockedTables(): array
    {
        return array_map(
            fn($t) => strtolower(trim($t)),
            config('chatbot.rtg_blocked_tables', ['users', 'payroll_masters'])
        );
    }

    /**
     * Kolom-kolom sensitif yang dibatasi per tabel (berbeda dari
     * blockedTables() yang memblokir SATU TABEL SECARA TOTAL) — tabelnya
     * tetap boleh dibaca, tapi kolom tertentu di dalamnya tidak.
     * Contoh default: employee_contract.salary/allowance/daily_salary/pph_21
     * (data gaji) tidak boleh pernah muncul di skema yang dikirim ke AI
     * maupun di hasil query mode RTG.
     *
     * Formatnya array [nama_tabel_lowercase => [nama_kolom_lowercase, ...]],
     * dikonfigurasi lewat config('chatbot.rtg_blocked_columns') supaya bisa
     * ditambah/diubah lewat .env tanpa perlu ubah kode ini.
     */
    protected function blockedColumns(): array
    {
        $result = [];

        foreach (config('chatbot.rtg_blocked_columns', []) as $table => $columns) {
            $table = strtolower(trim($table));

            if ($table === '') {
                continue;
            }

            $result[$table] = array_values(array_filter(array_map(
                fn($c) => strtolower(trim($c)),
                (array) $columns
            )));
        }

        return $result;
    }

    /**
     * Introspeksi otomatis semua tabel & kolom yang ada di koneksi
     * database read-only, supaya AI bisa "menganalisis" skema apa adanya
     * tanpa perlu didaftarkan manual satu-satu di config. Tabel yang
     * masuk blocklist (mis. users, payroll_masters) langsung dibuang dari
     * hasil sebelum pernah sampai ke prompt AI, begitu juga kolom sensitif
     * per tabel (mis. employee_contract.salary/allowance/daily_salary/pph_21)
     * walau tabelnya sendiri tetap boleh dibaca kolom lainnya.
     *
     * Hasilnya di-cache sebentar supaya tidak query INFORMATION_SCHEMA
     * berulang-ulang di setiap pesan chat.
     */
    protected function getDatabaseSchema(array $blockedTables, array $blockedColumns = []): array
    {
        $connection = config('chatbot.readonly_connection');
        // Cache key ikut sertakan hash blockedColumns supaya kalau daftar
        // kolom terlarang berubah (mis. tambah kolom baru lewat .env),
        // cache lama otomatis tidak dipakai lagi tanpa perlu cache:clear
        // manual.
        $cacheKey = 'chatbot_rtg_schema:' . $connection . ':' . md5(json_encode($blockedColumns));
        $cacheMinutes = config('chatbot.schema_cache_minutes', 30);

        return Cache::remember($cacheKey, now()->addMinutes($cacheMinutes), function () use ($connection, $blockedTables, $blockedColumns) {
            $rows = DB::connection($connection)->select(
                'SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = ?
                 ORDER BY TABLE_NAME, ORDINAL_POSITION',
                ['dbo']
            );

            // Tandai kolom mana yang primary key, supaya AI tahu kolom mana
            // yang cocok dipakai sebagai kunci JOIN/agregasi tanpa menebak.
            $primaryKeys = [];

            try {
                $pkRows = DB::connection($connection)->select(
                    "SELECT ku.TABLE_NAME, ku.COLUMN_NAME
                     FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
                     INNER JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE ku
                       ON tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
                      AND tc.CONSTRAINT_NAME = ku.CONSTRAINT_NAME
                      AND tc.TABLE_SCHEMA = ku.TABLE_SCHEMA
                     WHERE tc.TABLE_SCHEMA = ?",
                    ['dbo']
                );

                foreach ($pkRows as $pk) {
                    $primaryKeys[$pk->TABLE_NAME][] = $pk->COLUMN_NAME;
                }
            } catch (\Throwable $e) {
                report($e);
            }

            $schema = [];

            foreach ($rows as $row) {
                $table = strtolower($row->TABLE_NAME);

                if (in_array($table, $blockedTables, true)) {
                    continue;
                }

                // Kolom sensitif (mis. gaji di employee_contract) dibuang
                // dari skema di sini, SEBELUM pernah sampai ke prompt AI —
                // ini lapisan pertahanan utama, bukan cuma validasi SQL di
                // validateReadonlySql().
                if (in_array(strtolower($row->COLUMN_NAME), $blockedColumns[$table] ?? [], true)) {
                    continue;
                }

                $isPk = in_array($row->COLUMN_NAME, $primaryKeys[$row->TABLE_NAME] ?? [], true);
                $suffix = $isPk ? ', PK' : '';

                $schema[$row->TABLE_NAME][] = "{$row->COLUMN_NAME} ({$row->DATA_TYPE}{$suffix})";
            }

            return $schema;
        });
    }

    /**
     * Ambil semua relasi foreign key (tabel_anak.kolom -> tabel_induk.kolom)
     * dari database, di luar tabel yang diblokir. Ini yang paling sering
     * hilang di prompt lama sehingga AI asal tebak kolom JOIN dan query-nya
     * gagal karena "Invalid column name".
     */
    protected function getDatabaseRelations(array $blockedTables): array
    {
        $connection = config('chatbot.readonly_connection');
        $cacheKey = "chatbot_rtg_relations:{$connection}";
        $cacheMinutes = config('chatbot.schema_cache_minutes', 30);

        return Cache::remember($cacheKey, now()->addMinutes($cacheMinutes), function () use ($connection, $blockedTables) {
            try {
                $rows = DB::connection($connection)->select(
                    "SELECT
                        tp.name AS parent_table,
                        cp.name AS parent_column,
                        tr.name AS referenced_table,
                        cr.name AS referenced_column
                     FROM sys.foreign_keys fk
                     INNER JOIN sys.foreign_key_columns fkc ON fkc.constraint_object_id = fk.object_id
                     INNER JOIN sys.tables tp ON tp.object_id = fkc.parent_object_id
                     INNER JOIN sys.columns cp ON cp.object_id = tp.object_id AND cp.column_id = fkc.parent_column_id
                     INNER JOIN sys.tables tr ON tr.object_id = fkc.referenced_object_id
                     INNER JOIN sys.columns cr ON cr.object_id = tr.object_id AND cr.column_id = fkc.referenced_column_id"
                );
            } catch (\Throwable $e) {
                // Akun database read-only kadang tidak diberi akses ke sys.*
                // — relasi FK jadi opsional, bukan syarat mutlak mode RTG.
                report($e);

                return [];
            }

            $relations = [];

            foreach ($rows as $row) {
                $parent = strtolower($row->parent_table);
                $referenced = strtolower($row->referenced_table);

                if (in_array($parent, $blockedTables, true) || in_array($referenced, $blockedTables, true)) {
                    continue;
                }

                $relations[] = "{$row->parent_table}.{$row->parent_column} -> {$row->referenced_table}.{$row->referenced_column}";
            }

            return array_values(array_unique($relations));
        });
    }

    /**
     * Baca dokumentasi skema tambahan (relasi LOGIS tanpa FK constraint,
     * konvensi penamaan kolom, dsb) dari file markdown yang dikelola manual
     * oleh tim di config('chatbot.schema_docs_path') (lihat
     * docs/DATABASE_SCHEMA.md untuk contoh & format). Introspeksi otomatis
     * di getDatabaseRelations() di atas HANYA bisa menemukan relasi yang
     * benar-benar didaftarkan sebagai FK constraint di database — banyak
     * relasi di sistem lama/legacy sifatnya cuma konvensi (mis. semua tabel
     * karyawan di-JOIN lewat kolom NPK) tanpa FK constraint sungguhan,
     * sehingga tidak pernah muncul di sana. Dokumen inilah yang
     * melengkapinya.
     *
     * Balik string kosong (bukan error) kalau path belum dikonfigurasi atau
     * file belum ada — dokumentasi ini opsional, bukan syarat mode RTG bisa
     * jalan. Hasil baca di-cache dengan durasi yang sama dengan cache skema
     * supaya tidak baca file dari disk di setiap pesan chat; edit filenya
     * lalu tunggu cache kedaluwarsa atau `php artisan cache:clear`.
     */
    protected function getSchemaDocumentation(): string
    {
        $path = config('chatbot.schema_docs_path');

        if (empty($path)) {
            return '';
        }

        $cacheKey = 'chatbot_schema_docs:' . md5($path);
        $cacheMinutes = config('chatbot.schema_cache_minutes', 30);

        return Cache::remember($cacheKey, now()->addMinutes($cacheMinutes), function () use ($path) {
            if (! File::exists($path) || ! File::isReadable($path)) {
                return '';
            }

            $content = trim(File::get($path));

            if ($content === '') {
                return '';
            }

            $maxChars = (int) config('chatbot.schema_docs_max_chars', 6000);

            if ($maxChars > 0 && mb_strlen($content) > $maxChars) {
                $content = mb_substr($content, 0, $maxChars) . "\n\n[...dokumentasi dipotong karena terlalu panjang, lihat file aslinya untuk selengkapnya...]";
            }

            return $content;
        });
    }

    /**
     * Format array skema [tabel => [kolom, ...]] jadi teks daftar lengkap
     * untuk dikirim ke prompt AI. Semua tabel & kolom yang tidak diblokir
     * disertakan apa adanya (tidak dipotong), supaya AI tidak kehilangan
     * konteks tabel yang jarang dipakai lalu asal menebak.
     */
    protected function formatSchemaForPrompt(array $schema): string
    {
        return collect($schema)
            ->map(fn(array $columns, string $table) => "- {$table} (" . implode(', ', $columns) . ')')
            ->implode("\n");
    }

    protected function extractSql(string $raw): string
    {
        if (preg_match('/```sql\s*(.*?)```/is', $raw, $m)) {
            return trim($m[1]);
        }

        if (preg_match('/```\s*(.*?)```/is', $raw, $m)) {
            return trim($m[1]);
        }

        return trim($raw);
    }

    /**
     * Membetulkan kesalahan sintaks T-SQL yang umum dibuat AI, tanpa perlu
     * meminta AI mengulang. Saat ini menangani urutan "TOP N DISTINCT"
     * (salah) -> "DISTINCT TOP N" (benar), yang sering tertukar karena di
     * database lain (mis. MySQL/Postgres) DISTINCT memang ditulis lebih
     * dulu tapi tanpa konsep TOP seperti ini.
     */
    protected function normalizeSql(string $sql): string
    {
        return preg_replace(
            '/\bselect\s+top\s*\(?\s*(\d+)\s*\)?\s+distinct\b/i',
            'SELECT DISTINCT TOP $1',
            $sql
        );
    }

    /**
     * Lapisan validasi kedua (selain hak akses akun database read-only)
     * supaya query yang dijalankan benar-benar SELECT, satu statement,
     * bebas dari kata kunci tulis, dan tidak menyentuh tabel yang
     * diblokir (mis. 'users') — di luar itu, semua tabel di database
     * boleh diakses.
     */
    protected function validateReadonlySql(string $sql, array $blockedTables, array $blockedColumns = []): array
    {
        $clean = rtrim(trim($sql), "; \t\n\r");

        if ($clean === '') {
            return ['valid' => false, 'reason' => 'AI tidak menghasilkan query.'];
        }

        // Hanya boleh satu statement: tidak boleh ada ';' di tengah query.
        if (str_contains($clean, ';')) {
            return ['valid' => false, 'reason' => 'Terdeteksi lebih dari satu statement.'];
        }

        $lower = strtolower($clean);

        if (! str_starts_with(ltrim($lower), 'select')) {
            return ['valid' => false, 'reason' => 'Query harus diawali dengan SELECT.'];
        }

        // FIX: sebelumnya pakai str_contains biasa, sehingga kolom yang cuma
        // MENGANDUNG kata terlarang sebagai substring (mis. kolom
        // "updated_at" kena blokir gara-gara mengandung kata "update")
        // ikut ditolak walau query-nya SELECT murni dan aman. Sekarang
        // dicek sebagai kata utuh (word boundary) supaya cuma statement
        // tulis yang sungguhan yang diblokir.
        foreach (config('chatbot.forbidden_sql_keywords', []) as $keyword) {
            $pattern = '/\b' . preg_quote(strtolower($keyword), '/') . '\b/';

            if (preg_match($pattern, $lower)) {
                return ['valid' => false, 'reason' => "Mengandung kata kunci terlarang: {$keyword}"];
            }
        }

        // Pastikan tidak ada satu pun tabel yang diblokir (mis. users) yang
        // disebut di query, lewat FROM, JOIN, ATAU subquery mana pun.
        preg_match_all('/\b(?:from|join)\s+([a-zA-Z0-9_\.\[\]]+)/i', $clean, $matches);
        $referencedTables = array_map(
            fn($t) => strtolower(trim($t, '[]')),
            $matches[1] ?? []
        );
        // Buang alias skema (mis. dbo.employee_contract -> employee_contract)
        // supaya bisa dicocokkan langsung ke key blockedTables/blockedColumns.
        $referencedTables = array_map(
            fn($t) => str_contains($t, '.') ? substr($t, strrpos($t, '.') + 1) : $t,
            $referencedTables
        );

        foreach ($referencedTables as $bare) {
            if (in_array($bare, $blockedTables, true)) {
                return ['valid' => false, 'reason' => "Tabel '{$bare}' tidak boleh diakses lewat chatbot."];
            }
        }

        // Lapisan pertahanan kedua untuk kolom sensitif (mis. gaji di
        // employee_contract) — lapisan pertamanya adalah kolom ini memang
        // sudah tidak pernah dikirim ke AI lewat skema (lihat
        // getDatabaseSchema()), jadi baris di bawah ini murni jaga-jaga
        // kalau AI tetap "menebak" nama kolomnya.
        if (! empty($blockedColumns)) {
            $tablesWithBlockedColumns = array_values(array_intersect(
                $referencedTables,
                array_keys($blockedColumns)
            ));

            // SELECT * (atau alias.*) pada tabel yang punya kolom terblokir
            // ditolak total, karena bisa ikut membawa kolom yang seharusnya
            // tidak boleh dibaca sama sekali tanpa AI perlu menyebut namanya.
            if (! empty($tablesWithBlockedColumns) && preg_match('/select\s+(distinct\s+)?(top\s*\(?\s*\d+\s*\)?\s+)?(\*|[a-zA-Z0-9_]+\.\*)/i', $clean)) {
                $table = $tablesWithBlockedColumns[0];

                return ['valid' => false, 'reason' => "SELECT * tidak boleh dipakai untuk tabel '{$table}' karena tabel ini punya kolom yang dibatasi, sebutkan kolom yang dibutuhkan secara eksplisit."];
            }

            foreach ($tablesWithBlockedColumns as $table) {
                foreach ($blockedColumns[$table] as $column) {
                    if (preg_match('/\b' . preg_quote($column, '/') . '\b/i', $clean)) {
                        return ['valid' => false, 'reason' => "Kolom '{$column}' pada tabel '{$table}' tidak boleh diakses lewat chatbot."];
                    }
                }
            }
        }

        return ['valid' => true, 'reason' => null];
    }

    /*
    |--------------------------------------------------------------------------
    | Export hasil query — default Excel, atau PDF/gambar kalau user minta
    |--------------------------------------------------------------------------
    */

    /**
     * Membaca pesan user untuk menentukan format file yang diminta.
     * Defaultnya selalu 'xlsx' kecuali user secara eksplisit menyebut
     * pdf, atau menyebut gambar/image/grafik/dsb.
     */
    protected function detectExportFormat(string $userMessage): string
    {
        $text = strtolower($userMessage);

        if (preg_match('/\bpdf\b/', $text)) {
            return 'pdf';
        }

        $imageHints = ['gambar', 'image', 'foto', 'png', 'jpg', 'jpeg', 'grafik', 'chart', 'visual'];

        foreach ($imageHints as $hint) {
            if (str_contains($text, $hint)) {
                return 'image';
            }
        }

        return 'xlsx';
    }

    /**
     * Meng-generate file export sesuai format. Balik null kalau datanya
     * kosong atau proses generate gagal (mis. package belum lengkap) —
     * kegagalan export TIDAK BOLEH menggagalkan jawaban chat itu sendiri.
     */
    protected function exportRows(array $rows, string $format, string $userMessage): ?array
    {
        if (empty($rows)) {
            return null;
        }

        try {
            if ($format === 'pdf') {
                return $this->exportRowsToPdf($rows, $userMessage);
            }

            if ($format === 'image') {
                return $this->exportRowsToImage($rows, $userMessage);
            }

            // Format default ('xlsx'): kalau user tidak minta pdf/gambar
            // secara eksplisit, tidak semua hasil perlu dibuatkan file.
            // Datanya sedikit -> cukup tabel HTML rapi di dalam chat.
            // Datanya banyak -> baru dibuatkan file Excel supaya enak diunduh.
            $htmlThreshold = (int) config('chatbot.html_table_threshold', 20);

            if (count($rows) <= $htmlThreshold) {
                return $this->exportRowsToHtmlTable($rows);
            }

            return $this->exportRowsToExcel($rows, $userMessage);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Render hasil query sebagai tabel HTML rapi untuk ditampilkan langsung
     * di dalam chat (tanpa file terpisah) — dipakai kalau jumlah baris masih
     * di bawah ambang batas 'chatbot.html_table_threshold'.
     */
    protected function exportRowsToHtmlTable(array $rows): array
    {
        $headers = array_keys((array) $rows[0]);

        $formatCell = function ($value): string {
            if ($value === null) {
                return '';
            }

            if (is_scalar($value)) {
                return e((string) $value);
            }

            return e(json_encode($value, JSON_UNESCAPED_UNICODE));
        };

        // Sengaja tidak pakai inline style di sini — markup dibiarkan bersih
        // (cuma class) supaya tampilannya diatur lewat CSS di sisi chatbox
        // (lihat .cw-data-table di _message_styles.blade.php), bukan
        // "dibakar" dari backend.
        $theadCells = implode('', array_map(
            fn($header) => '<th>' . e($header) . '</th>',
            $headers
        ));

        $tbodyRows = '';

        foreach ($rows as $row) {
            $values = array_values((array) $row);

            $cells = implode('', array_map(
                fn($value) => '<td>' . $formatCell($value) . '</td>',
                $values
            ));

            $tbodyRows .= "<tr>{$cells}</tr>";
        }

        $html = '<div class="cw-data-table-wrap">'
            . '<table class="cw-data-table">'
            . "<thead><tr>{$theadCells}</tr></thead>"
            . "<tbody>{$tbodyRows}</tbody>"
            . '</table></div>';

        return [
            'type' => 'html_table',
            'html' => $html,
            'row_count' => count($rows),
        ];
    }

    protected function exportRowsToExcel(array $rows, string $userMessage): array
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Hasil Chatbot');

        $headers = array_keys((array) $rows[0]);
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));

        foreach ($headers as $colIndex => $header) {
            $col = Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet->setCellValue("{$col}1", $header);
        }

        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E2E8F0');

        foreach ($rows as $rowIndex => $row) {
            $values = array_values((array) $row);

            foreach ($values as $colIndex => $value) {
                $col = Coordinate::stringFromColumnIndex($colIndex + 1);
                $sheet->setCellValue("{$col}" . ($rowIndex + 2), is_scalar($value) || $value === null ? $value : (string) $value);
            }
        }

        foreach (range(1, count($headers)) as $colIndex) {
            $col = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $sheet->freezePane('A2');

        $filename = $this->exportFilename($userMessage, 'xlsx');
        $fullPath = $this->exportFullPath($filename);

        (new Xlsx($spreadsheet))->save($fullPath);

        return $this->exportResult('xlsx', $filename, count($rows));
    }

    protected function exportRowsToPdf(array $rows, string $userMessage): array
    {
        $headers = array_keys((array) $rows[0]);
        $tableRows = array_map(fn($row) => array_values((array) $row), $rows);

        $pdf = Pdf::loadView('chatbot.exports.pdf_table', [
            'title' => 'Hasil Chatbot',
            'headers' => $headers,
            'rows' => $tableRows,
        ])->setPaper('a4', 'landscape');

        $filename = $this->exportFilename($userMessage, 'pdf');
        $fullPath = $this->exportFullPath($filename);

        $pdf->save($fullPath);

        return $this->exportResult('pdf', $filename, count($rows));
    }

    /**
     * Export sederhana ke gambar PNG (tabel hasil query dirender langsung
     * pakai GD, tanpa dependency font eksternal). Dibatasi jumlah baris &
     * kolom supaya gambarnya tetap terbaca — kalau butuh data lengkap,
     * arahkan user ke export Excel.
     */
    protected function exportRowsToImage(array $rows, string $userMessage): ?array
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $maxImageRows = (int) config('chatbot.max_image_rows', 40);
        $maxImageCols = 8;

        $allHeaders = array_keys((array) $rows[0]);
        $headers = array_slice($allHeaders, 0, $maxImageCols);
        $displayRows = array_slice($rows, 0, $maxImageRows);

        $font = 4;
        $charWidth = imagefontwidth($font);
        $charHeight = imagefontheight($font);
        $padding = 10;
        $rowHeight = $charHeight + 12;

        $truncate = fn($value) => mb_strlen((string) $value) > 24
            ? mb_substr((string) $value, 0, 21) . '...'
            : (string) $value;

        $colWidths = [];

        foreach ($headers as $colIndex => $header) {
            $maxLen = strlen($truncate($header));

            foreach ($displayRows as $row) {
                $values = array_values((array) $row);
                $maxLen = max($maxLen, strlen($truncate($values[$colIndex] ?? '')));
            }

            $colWidths[$colIndex] = min(240, ($maxLen * $charWidth) + ($padding * 2));
        }

        $isTruncated = count($rows) > count($displayRows) || count($allHeaders) > count($headers);
        $footerHeight = $isTruncated ? 24 : 0;

        $width = max(array_sum($colWidths), 200) + 2;
        $height = $rowHeight * (count($displayRows) + 1) + $footerHeight + 4;

        $im = imagecreatetruecolor((int) $width, (int) $height);
        $white = imagecolorallocate($im, 255, 255, 255);
        $headerBg = imagecolorallocate($im, 30, 58, 138);
        $headerText = imagecolorallocate($im, 255, 255, 255);
        $rowBgAlt = imagecolorallocate($im, 241, 245, 249);
        $border = imagecolorallocate($im, 203, 213, 225);
        $text = imagecolorallocate($im, 30, 41, 59);
        $muted = imagecolorallocate($im, 100, 116, 139);

        imagefill($im, 0, 0, $white);
        imagefilledrectangle($im, 0, 0, (int) $width, $rowHeight, $headerBg);

        $x = 0;

        foreach ($headers as $colIndex => $header) {
            imagestring($im, $font, $x + $padding, 6, $truncate($header), $headerText);
            $x += $colWidths[$colIndex];
        }

        $y = $rowHeight;

        foreach ($displayRows as $rowIndex => $row) {
            if ($rowIndex % 2 === 1) {
                imagefilledrectangle($im, 0, $y, (int) $width, $y + $rowHeight, $rowBgAlt);
            }

            $values = array_values((array) $row);
            $x = 0;

            foreach ($headers as $colIndex => $header) {
                imagestring($im, $font, $x + $padding, $y + 6, $truncate($values[$colIndex] ?? ''), $text);
                $x += $colWidths[$colIndex];
            }

            imageline($im, 0, $y, (int) $width, $y, $border);
            $y += $rowHeight;
        }

        if ($isTruncated) {
            imagestring($im, 2, 6, $y + 4, 'Data dipotong untuk tampilan gambar — minta versi Excel/PDF untuk data lengkap.', $muted);
        }

        $filename = $this->exportFilename($userMessage, 'png');
        $fullPath = $this->exportFullPath($filename);

        imagepng($im, $fullPath);
        imagedestroy($im);

        return $this->exportResult('image', $filename, count($displayRows));
    }

    protected function exportFilename(string $userMessage, string $extension): string
    {
        $slug = Str::slug(Str::limit(trim($userMessage), 50, ''), '-');
        $base = $slug !== '' ? $slug : 'hasil-chatbot';

        return "{$base}-" . now()->format('Ymd-His') . '-' . Str::random(4) . ".{$extension}";
    }

    protected function exportDisk(): string
    {
        return config('chatbot.export_disk', 'public');
    }

    protected function exportDirectory(): string
    {
        return trim(config('chatbot.export_directory', 'chatbot-exports'), '/');
    }

    protected function exportFullPath(string $filename): string
    {
        $disk = Storage::disk($this->exportDisk());
        $disk->makeDirectory($this->exportDirectory());

        return $disk->path($this->exportDirectory() . '/' . $filename);
    }

    protected function exportResult(string $type, string $filename, int $rowCount): array
    {
        $url = Storage::disk($this->exportDisk())->url($this->exportDirectory() . '/' . $filename);

        return [
            'type' => $type,
            'filename' => $filename,
            'url' => $url,
            'row_count' => $rowCount,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Dispatcher pemanggilan provider
    |--------------------------------------------------------------------------
    */
    protected function callProvider(string $providerKey, string $system, array $messages): string
    {
        $cfg = config("chatbot.providers.$providerKey");

        if (empty($cfg['api_key'])) {
            throw new RuntimeException("API key untuk provider '$providerKey' belum diisi di .env.");
        }

        return match ($cfg['driver']) {
            'anthropic' => $this->callAnthropic($cfg, $system, $messages),
            'gemini' => $this->callGemini($cfg, $system, $messages),
            'openai_compatible' => $this->callOpenAiCompatible($cfg, $system, $messages),
            default => throw new RuntimeException("Driver '{$cfg['driver']}' tidak dikenal."),
        };
    }

    protected function callAnthropic(array $cfg, string $system, array $messages): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $cfg['api_key'],
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(config('chatbot.request_timeout', 120))->post($cfg['base_url'], [
            'model' => $cfg['model'],
            'max_tokens' => 1024,
            'system' => $system,
            'messages' => $messages,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Anthropic API error: ' . $response->body());
        }

        $blocks = $response->json('content', []);

        return collect($blocks)
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");
    }

    protected function callOpenAiCompatible(array $cfg, string $system, array $messages): string
    {
        $response = Http::withToken($cfg['api_key'])
            ->timeout(config('chatbot.request_timeout', 120))
            ->connectTimeout(15)
            ->retry(2, 3000, function ($exception) {
                // Coba ulang khusus untuk timeout/koneksi putus, bukan untuk
                // error validasi (4xx) yang memang pasti gagal lagi kalau diulang.
                return $exception instanceof \Illuminate\Http\Client\ConnectionException;
            })
            ->post($cfg['base_url'], [
                'model' => $cfg['model'],
                'temperature' => 0.3,
                // Sebagian model di balik router (terutama yang "reasoning"-nya
                // tidak bisa dimatikan, mis. beberapa model Gemini di combo
                // 9 Router) akan menghabiskan seluruh token budget untuk proses
                // thinking kalau tidak dikasih batas eksplisit, sehingga jawaban
                // akhirnya (message.content) kosong walau HTTP-nya sukses.
                // Kirim dua-duanya (gaya lama & baru) supaya kompatibel dengan
                // provider apa pun di baliknya.
                'max_tokens' => (int) config('chatbot.max_output_tokens', 4096),
                'max_completion_tokens' => (int) config('chatbot.max_output_tokens', 4096),
                // Minta respons utuh (bukan potongan SSE) — beberapa router
                // (termasuk 9 Router) tetap mengirim format streaming kalau
                // field ini tidak disebutkan secara eksplisit.
                'stream' => false,
                'messages' => array_merge(
                    [['role' => 'system', 'content' => $system]],
                    $messages
                ),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('AI provider error: ' . $response->body());
        }

        $rawBody = $response->body();

        // Jaga-jaga: kalau router tetap membalas dalam format SSE
        // ("data: {...}" per baris) walaupun sudah diminta stream=false,
        // gabungkan semua potongan delta.content jadi satu jawaban utuh.
        if (str_starts_with(ltrim($rawBody), 'data:')) {
            $content = $this->extractContentFromSse($rawBody);

            if ($content !== '') {
                return $content;
            }
        }

        $message = $response->json('choices.0.message', []);

        // Jalur normal: field 'content' berupa string biasa.
        $content = (string) ($message['content'] ?? '');

        // Beberapa router mengembalikan 'content' sebagai array of parts
        // (mirip format Anthropic), bukan string langsung.
        if ($content === '' && is_array($message['content'] ?? null)) {
            $content = collect($message['content'])
                ->map(fn($part) => is_array($part) ? ($part['text'] ?? '') : (string) $part)
                ->implode('');
        }

        // Fallback lain yang dipakai sebagian router/model reasoning saat
        // 'content' kosong karena token habis untuk thinking, atau saat
        // jawaban akhir ditaruh di field terpisah dari proses berpikirnya.
        if ($content === '') {
            $content = (string) ($message['reasoning_content'] ?? '');
        }

        if ($content === '') {
            $content = (string) $response->json('choices.0.text', '');
        }

        if ($content === '') {
            $finishReason = $response->json('choices.0.finish_reason', 'unknown');
            $wasSse = str_starts_with(ltrim($rawBody), 'data:') ? ' (format SSE terdeteksi tapi gagal digabung)' : '';

            throw new RuntimeException(
                "AI provider mengembalikan respons kosong (finish_reason: {$finishReason}){$wasSse}. "
                    . 'Kemungkinan model kehabisan token untuk proses reasoning sebelum sempat menjawab, '
                    . 'atau model/combo yang dipilih router sedang bermasalah. Respons mentah: '
                    . substr($rawBody, 0, 500)
            );
        }

        return $content;
    }

    /**
     * Gabungkan potongan-potongan delta.content dari respons berformat
     * Server-Sent Events (baris "data: {...}", diakhiri "data: [DONE]")
     * jadi satu string jawaban utuh.
     */
    protected function extractContentFromSse(string $rawBody): string
    {
        $content = '';

        foreach (preg_split('/\r?\n/', $rawBody) as $line) {
            $line = trim($line);

            if ($line === '' || ! str_starts_with($line, 'data:')) {
                continue;
            }

            $json = trim(substr($line, 5));

            if ($json === '' || $json === '[DONE]') {
                continue;
            }

            $chunk = json_decode($json, true);

            if (! is_array($chunk)) {
                continue;
            }

            $delta = $chunk['choices'][0]['delta'] ?? [];

            if (isset($delta['content']) && is_string($delta['content'])) {
                $content .= $delta['content'];
            }
        }

        return $content;
    }

    protected function callGemini(array $cfg, string $system, array $messages): string
    {
        $contents = collect($messages)->map(fn($m) => [
            'role' => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ])->values()->all();

        $url = rtrim($cfg['base_url'], '/') . "/{$cfg['model']}:generateContent?key={$cfg['api_key']}";

        $response = Http::timeout(config('chatbot.request_timeout', 120))->post($url, [
            'system_instruction' => ['parts' => [['text' => $system]]],
            'contents' => $contents,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini API error: ' . $response->body());
        }

        return (string) $response->json('candidates.0.content.parts.0.text', '');
    }
}
