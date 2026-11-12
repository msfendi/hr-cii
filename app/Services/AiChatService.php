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

        // Chatbot HANYA jalan di mode RAG (tanya langsung ke database SQL
        // Server lewat koneksi read-only). Mode global sengaja dihapus.
        return $this->handleRag($session, $providerKey, $userMessage, $attachments);
    }

    /*
    |--------------------------------------------------------------------------
    | Mode GLOBAL (ngobrol bebas tanpa akses database) SUDAH DIHAPUS.
    | Chatbot kini HANYA mode RAG — semua pesan dijawab dari data database
    | SQL Server lewat handleRag().
    |--------------------------------------------------------------------------
    */


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
    | Mode RAG (Read And Generate) — AI menyusun query SELECT read-only,
    | dijalankan lewat koneksi database read-only, lalu hasilnya dipakai
    | AI untuk menyusun jawaban bahasa natural. Semua data yang berhasil
    | diambil juga di-export ke file (default Excel, atau PDF/gambar kalau
    | user memintanya secara eksplisit) supaya bisa diunduh dari chat.
    |--------------------------------------------------------------------------
    */
    protected function handleRag(ChatbotSession $session, string $providerKey, string $userMessage, array $attachments = []): array
    {
        // Mode RAG hanya bekerja dari data database (query read-only), jadi
        // lampiran file (kalau ada) TIDAK dipakai untuk menyusun query SQL
        // maupun dikirim ke AI di mode ini — parameter ini hanya dijaga
        // supaya tanda tangan method konsisten dengan handleGlobal().
        unset($attachments);

        $blockedTables = $this->blockedTables();
        $blockedColumns = $this->blockedColumns();
        $schema = $this->getDatabaseSchema($blockedTables, $blockedColumns);

        if (empty($schema)) {
            return [
                'content' => 'Mode RAG belum bisa dipakai: tidak ada tabel yang bisa dibaca dari koneksi database read-only '
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

        $maxRows = config('chatbot.max_rag_rows', 200);
        $maxAttempts = max(1, (int) config('chatbot.max_rag_query_attempts', 3));
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
                'content' => 'Maaf, pertanyaan itu belum bisa saya jawab dari data yang tersedia untuk mode RAG saat ini '
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
            . 'tidak ditemukan. Jangan menyebutkan detail teknis (nama tabel/kolom SQL) kecuali user menanyakannya secara eksplisit. '
            . 'Jika jawabanmu berupa daftar data dalam bentuk tabel, tulis tabelnya dalam format Markdown '
            . '(| kolom | kolom | dengan baris pemisah |---|---|) supaya bisa dirender sebagai tabel di chat. '
            . 'Jika user meminta melakukan sesuatu di aplikasi HRIS — misalnya "ajukan cuti", "pengajuan cuti", "buka form cuti", '
            . '"lihat slip gaji", "slip payroll saya", "riwayat cuti saya", "cek saldo cuti" — sertakan link aksi yang relevan '
            . 'dalam format Markdown [teks link](URL) dengan URL absolut ke halaman terkait di aplikasi ini, mis.: '
            . 'pengajuan/riwayat cuti -> ' . url('/pengajuan-cuti/form') . ' , approval cuti -> ' . url('/pengajuan-cuti/approval') . ' , '
            . 'riwayat cuti -> ' . url('/pengajuan-cuti/riwayat') . ' , slip gaji/payroll -> ' . url('/employee-payroll') . ' . '
            . 'Jangan mengarang URL lain selain pola di atas; kalau tidak yakin, cukup arahkan user membuka menu yang disebutkan.';

        $answer = $this->callProvider($providerKey, $answerSystemPrompt, [
            ['role' => 'user', 'content' => "Pertanyaan user: {$userMessage}\n\nData JSON (maks {$maxRows} baris):\n{$rowsJson}"],
        ]);

        $format = $this->detectExportFormat($userMessage);
        $export = $this->exportRows($rows, $format, $userMessage);
        $links = $this->detectActionLinks($userMessage);

        return [
            'content' => $answer,
            'meta' => array_filter([
                'sql' => $sql,
                'row_count' => count($rows),
                'export' => $export,
                'links' => $links ?: null,
            ], fn($value) => $value !== null),
        ];
    }

    /**
     * Deteksi permintaan aksi di aplikasi (pengajuan cuti, lihat slip, dsb)
     * dari pesan user, lalu kembalikan daftar link aksi yang relevan untuk
     * ditampilkan sebagai tombol cepat di bawah bubble AI.
     *
     * @return array<int, array{label: string, url: string}>
     */
    protected function detectActionLinks(string $userMessage): array
    {
        $text = mb_strtolower(trim($userMessage));
        $links = [];

        $add = function (string $label, string $url) use (&$links) {
            $links[] = ['label' => $label, 'url' => $url];
        };

        $leaveCuti = preg_match('/\b(cuti|leave|izin|pengajuan|sakit)\b/u', $text);
        $slipGaji  = preg_match('/\b(slip|gaji|payroll|salary|thr)\b/u', $text);
        $saldo     = preg_match('/\b(saldo|balance|jatah|sisa)\b/u', $text);
        $approval  = preg_match('/\b(approval|approve|setuju|tolak|reject)\b/u', $text);
        $riwayat   = preg_match('/\b(riwayat|history|daftar|status)\b/u', $text);

        if ($leaveCuti && ($saldo || $riwayat || preg_match('/\bcek\b/u', $text))) {
            $add('Cek Saldo Cuti', url('/pengajuan-cuti/get-leave-balance'));
        }

        if ($leaveCuti && ! $approval) {
            $add('Ajukan Cuti', url('/pengajuan-cuti/form'));
        }

        if ($leaveCuti && $riwayat) {
            $add('Riwayat Cuti', url('/pengajuan-cuti/riwayat'));
        }

        if ($leaveCuti && $approval) {
            $add('Approval Cuti', url('/pengajuan-cuti/approval'));
        }

        if ($slipGaji) {
            $add('Lihat Slip Gaji', url('/employee-payroll'));
        }

        // Link duplikat dibuang berdasarkan URL.
        $seen = [];
        $unique = [];
        foreach ($links as $link) {
            if (isset($seen[$link['url']])) {
                continue;
            }
            $seen[$link['url']] = true;
            $unique[] = $link;
        }

        return $unique;
    }

    /**
     * Tabel yang benar-benar terlarang total di mode RAG, apa pun isi
     * pertanyaan user. Defaultnya 'users' dan 'payroll_masters' sesuai
     * permintaan, tapi bisa ditambah lewat CHATBOT_RAG_BLOCKED_TABLES di
     * .env (pisahkan dengan koma) tanpa perlu ubah kode.
     */
    protected function blockedTables(): array
    {
        return array_map(
            fn($t) => strtolower(trim($t)),
            config('chatbot.rag_blocked_tables', ['payroll_masters', 'payroll_run_details', 'payroll_run_details_audit'])
        );
    }

    /**
     * Kolom-kolom sensitif yang dibatasi per tabel (berbeda dari
     * blockedTables() yang memblokir SATU TABEL SECARA TOTAL) — tabelnya
     * tetap boleh dibaca, tapi kolom tertentu di dalamnya tidak.
     * Contoh default: employee_contract.salary/allowance/daily_salary/pph_21
     * (data gaji) tidak boleh pernah muncul di skema yang dikirim ke AI
     * maupun di hasil query mode RAG.
     *
     * Formatnya array [nama_tabel_lowercase => [nama_kolom_lowercase, ...]],
     * dikonfigurasi lewat config('chatbot.rag_blocked_columns') supaya bisa
     * ditambah/diubah lewat .env tanpa perlu ubah kode ini.
     */
    protected function blockedColumns(): array
    {
        $result = [];

        foreach (config('chatbot.rag_blocked_columns', []) as $table => $columns) {
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
        $cacheKey = 'chatbot_rag_schema:' . $connection . ':' . md5(json_encode($blockedColumns));
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
        $cacheKey = "chatbot_rag_relations:{$connection}";
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
                     INNER JOIN sys.tables tp ON fk.parent_object_id = tp.object_id
                     INNER JOIN sys.columns cp ON cp.object_id = tp.object_id
                                                AND cp.column_id = (
                                                    SELECT TOP 1 column_id
                                                    FROM sys.foreign_key_columns fkc
                                                    WHERE fkc.constraint_object_id = fk.object_id
                                                      AND fkc.parent_object_id = tp.object_id
                                                    ORDER BY fkc.constraint_column_id
                                                )
                     INNER JOIN sys.tables tr ON fk.referenced_object_id = tr.object_id
                     INNER JOIN sys.columns cr ON cr.object_id = tr.object_id
                                                AND cr.column_id = (
                                                    SELECT TOP 1 column_id
                                                    FROM sys.foreign_key_columns fkc
                                                    WHERE fkc.constraint_object_id = fk.object_id
                                                      AND fkc.referenced_object_id = tr.object_id
                                                    ORDER BY fkc.constraint_column_id
                                                )
                     WHERE tp.schema_id = SCHEMA_ID('dbo')
                       AND tr.schema_id = SCHEMA_ID('dbo')"
                );

                $blockedLower = array_map('strtolower', $blockedTables);

                return array_map(fn($r) => "{$r->parent_table}.{$r->parent_column} -> {$r->referenced_table}.{$r->referenced_column}",
                    array_filter($rows, fn($r) =>
                        ! in_array(strtolower($r->parent_table), $blockedLower, true)
                        && ! in_array(strtolower($r->referenced_table), $blockedLower, true)
                    )
                );
            } catch (\Throwable $e) {
                report($e);

                return [];
            }
        });
    }

    /**
     * Baca file dokumentasi skema tambahan (markdown) yang berisi relasi
     * logis tanpa FK constraint di database (mis. join lewat kolom NPK
     * antar tabel karyawan). File ini dikelola manual oleh tim.
     */
    protected function getSchemaDocumentation(): string
    {
        $path = config('chatbot.schema_docs_path');

        if (! File::exists($path)) {
            return '';
        }

        $content = File::get($path);
        $maxChars = config('chatbot.schema_docs_max_chars', 6000);

        if (strlen($content) <= $maxChars) {
            return $content;
        }

        return Str::limit($content, $maxChars, "\n\n... (dokumentasi dipotong, panjang > {$maxChars} karakter) ...");
    }

    /**
     * Format skema (array [table => [col (type), ...]]) jadi teks rapi
     * untuk prompt AI.
     */
    protected function formatSchemaForPrompt(array $schema): string
    {
        if (empty($schema)) {
            return '(tidak ada tabel yang bisa dibaca)';
        }

        $lines = [];

        foreach ($schema as $table => $columns) {
            $lines[] = "Tabel: {$table}";
            $lines[] = "  " . implode("\n  ", $columns);
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Validasi query read-only:
     * - Harus diawali SELECT (single statement)
     * - Tidak mengandung kata kunci terlarang
     * - Tidak menyebut tabel terlarang
     * - Tidak menyebut kolom terlarang
     * - Tidak SELECT * pada tabel yang punya kolom terlarang
     */
    protected function validateReadonlySql(string $sql, array $blockedTables, array $blockedColumns): array
    {
        $lower = strtolower(trim($sql));

        // Harus diawali SELECT
        if (! preg_match('/^\s*select\b/', $lower)) {
            return ['valid' => false, 'reason' => 'Query tidak diawali SELECT'];
        }

        // Hanya satu statement (tanpa titik koma di tengah, kecuali di dalam string literal)
        if (preg_match('/;.*\S/', $lower)) {
            return ['valid' => false, 'reason' => 'Hanya boleh satu statement SELECT'];
        }

        // Kata kunci terlarang
        foreach (config('chatbot.forbidden_sql_keywords', []) as $keyword) {
            if (strpos($lower, $keyword) !== false) {
                return ['valid' => false, 'reason' => "Mengandung kata kunci terlarang: {$keyword}"];
            }
        }

        // Tabel terlarang (cek sebagai token terpisah, bukan substring)
        foreach ($blockedTables as $table) {
            if (preg_match('/\b' . preg_quote($table, '/') . '\b/i', $lower)) {
                return ['valid' => false, 'reason' => "Menyebut tabel terlarang: {$table}"];
            }
        }

        // Kolom terlarang per tabel
        foreach ($blockedColumns as $table => $columns) {
            if (preg_match('/\b' . preg_quote($table, '/') . '\b/i', $lower)) {
                foreach ($columns as $col) {
                    if (preg_match('/\b' . preg_quote($col, '/') . '\b/i', $lower)) {
                        return ['valid' => false, 'reason' => "Menyebut kolom terlarang: {$table}.{$col}"];
                    }
                }

                // SELECT * pada tabel yang punya kolom terlarang
                if (preg_match('/\bselect\s+\*\b/i', $lower)
                    && preg_match('/\bfrom\s+' . preg_quote($table, '/') . '\b/i', $lower)) {
                    return ['valid' => false, 'reason' => "SELECT * pada tabel {$table} yang punya kolom terlarang tidak diperbolehkan"];
                }
            }
        }

        return ['valid' => true];
    }

    /**
     * Ekstrak SQL dari balasan AI (yang diharapkan di dalam blok kode
     * ```sql ... ```). Kalau tidak ada blok kode, kembalikan mentahnya.
     */
    protected function extractSql(string $response): string
    {
        if (preg_match('/```sql\s*(.+?)\s*```/is', $response, $m)) {
            return trim($m[1]);
        }

        if (preg_match('/```\s*(.+?)\s*```/is', $response, $m)) {
            return trim($m[1]);
        }

        return trim($response);
    }

    /**
     * Normalisasi SQL: hapus komentar, normalisasi whitespace, pastikan
     * ada TOP/LIMIT.
     */
    protected function normalizeSql(string $sql): string
    {
        // Hapus komentar -- dan /* */
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        // Normalisasi whitespace
        $sql = preg_replace('/\s+/', ' ', trim($sql));

        // Pastikan ada TOP (batas baris di sisi DB) — ini defense in depth
        // selain pemotongan di PHP, kalau AI lupa pakai TOP.
        if (! preg_match('/\bselect\s+(?:distinct\s+)?top\s+\d+/i', $sql)) {
            $maxRows = config('chatbot.max_rag_rows', 200);
            $sql = preg_replace('/^\s*select\s+/i', "SELECT TOP {$maxRows} ", $sql, 1);
        }

        return $sql;
    }

    /**
     * Deteksi format export yang diminta user (excel/pdf/image/html_table).
     * Default Excel kalau tidak eksplisit.
     */
    protected function detectExportFormat(string $userMessage): string
    {
        $lower = strtolower($userMessage);

        if (preg_match('/\b(pdf|image|html_table|excel)\b', $lower, $m)) {
            return $m[1];
        }

        return 'excel';
    }

    /**
     * Export hasil query ke file (Excel/PDF/Image/HTML table) di storage
     * disk yang dikonfigurasi (default 'public'), lalu kembalikan array
     * info file untuk disimpan di meta pesan AI.
     */
    protected function exportRows(array $rows, string $format, string $userMessage): ?array
    {
        if (empty($rows)) {
            return null;
        }

        $disk = config('chatbot.export_disk', 'public');
        $timestamp = now()->format('Ymd_His');
        $slug = Str::slug(str($userMessage)->limit(30), '_');

        switch ($format) {
            case 'excel':
                return $this->exportExcel($rows, $disk, $timestamp, $slug);
            case 'pdf':
                return $this->exportPdf($rows, $disk, $timestamp, $slug);
            case 'image':
                return $this->exportImage($rows, $disk, $timestamp, $slug);
            case 'html_table':
                return $this->exportHtmlTable($rows, $disk, $timestamp, $slug);
            default:
                return $this->exportExcel($rows, $disk, $timestamp, $slug);
        }
    }

    protected function exportExcel(array $rows, string $disk, string $timestamp, string $slug): array
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = array_keys((array) $rows[0]);

        foreach ($headers as $i => $header) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$col}1", $header);
        }

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            $data = (array) $row;

            foreach ($headers as $colIndex => $header) {
                $col = Coordinate::stringFromColumnIndex($colIndex + 1);
                $value = $data[$header] ?? '';
                $sheet->setCellValue("{$col}{$excelRow}", $value);
            }
        }

        foreach (range('A', Coordinate::stringFromColumnIndex(count($headers))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $styleArray = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF2563EB'],
            ],
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        ];
        $sheet->getStyle('A1:' . Coordinate::stringFromColumnIndex(count($headers)) . '1')->applyFromArray($styleArray);

        $filename = "chatbot_export_{$timestamp}_{$slug}.xlsx";
        $path = "chatbot_exports/{$filename}";

        Storage::disk($disk)->put($path, (new Xlsx($spreadsheet))->saveToString());

        return [
            'type' => 'excel',
            'filename' => $filename,
            'url' => Storage::disk($disk)->url($path),
            'row_count' => count($rows),
        ];
    }

    protected function exportPdf(array $rows, string $disk, string $timestamp, string $slug): array
    {
        $headers = array_keys((array) $rows[0]);
        $html = view('chatbot.exports.pdf', compact('rows', 'headers'))->render();
        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'landscape');

        $filename = "chatbot_export_{$timestamp}_{$slug}.pdf";
        $path = "chatbot_exports/{$filename}";

        Storage::disk($disk)->put($path, $pdf->output());

        return [
            'type' => 'pdf',
            'filename' => $filename,
            'url' => Storage::disk($disk)->url($path),
            'row_count' => count($rows),
        ];
    }

    protected function exportImage(array $rows, string $disk, string $timestamp, string $slug): array
    {
        $headers = array_keys((array) $rows[0]);
        $html = view('chatbot.exports.image', compact('rows', 'headers'))->render();

        $filename = "chatbot_export_{$timestamp}_{$slug}.png";
        $path = "chatbot_exports/{$filename}";

        // Generate image dari HTML via wkhtmltoimage atau headless chrome
        // Kalau belum tersedia, fallback ke HTML table export
        try {
            // Placeholder — butuh wkhtmltoimage atau puppeteer terinstall di server
            // Storage::disk($disk)->put($path, $imageBytes);
            // Untuk sekarang fallback ke HTML table
            return $this->exportHtmlTable($rows, $disk, $timestamp, $slug);
        } catch (\Throwable $e) {
            report($e);
            return $this->exportHtmlTable($rows, $disk, $timestamp, $slug);
        }
    }

    protected function exportHtmlTable(array $rows, string $disk, string $timestamp, string $slug): array
    {
        $headers = array_keys((array) $rows[0]);
        $html = view('chatbot.exports.html_table', compact('rows', 'headers'))->render();

        $filename = "chatbot_export_{$timestamp}_{$slug}.html";
        $path = "chatbot_exports/{$filename}";

        Storage::disk($disk)->put($path, $html);

        return [
            'type' => 'html_table',
            'filename' => $filename,
            'url' => Storage::disk($disk)->url($path),
            'row_count' => count($rows),
            'html' => $html,
        ];
    }

    /**
     * Panggil provider AI (OpenAI-compatible, Anthropic, Gemini, dll)
     * Mengembalikan string konten jawaban.
     */
    protected function callProvider(string $providerKey, string $systemPrompt, array $messages): string
    {
        $cfg = config("chatbot.providers.$providerKey");
        $driver = $cfg['driver'] ?? 'openai_compatible';

        switch ($driver) {
            case 'openai_compatible':
                return $this->callOpenAiCompatible($cfg, $systemPrompt, $messages);
            case 'anthropic':
                return $this->callAnthropic($cfg, $systemPrompt, $messages);
            case 'gemini':
                return $this->callGemini($cfg, $systemPrompt, $messages);
            default:
                throw new RuntimeException("Driver AI '{$driver}' belum didukung.");
        }
    }

    protected function callOpenAiCompatible(array $cfg, string $systemPrompt, array $messages): string
    {
        $payload = [
            'model' => $cfg['model'],
            'messages' => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                $messages
            ),
            'temperature' => $cfg['temperature'] ?? 0.2,
            'max_tokens' => $cfg['max_tokens'] ?? config('chatbot.max_output_tokens', 4096),
            'max_completion_tokens' => $cfg['max_completion_tokens'] ?? config('chatbot.max_output_tokens', 4096),
        ];

        $response = Http::timeout(config('chatbot.request_timeout', 120))
            ->withHeaders([
                'Authorization' => 'Bearer ' . ($cfg['api_key'] ?? ''),
            ])
            ->post($cfg['base_url'], $payload);

        if (! $response->successful()) {
            throw new RuntimeException("Provider {$cfg['base_url']} error: " . $response->body());
        }

        $data = $response->json();
        if (! is_array($data)) {
            // Body kosong / bukan JSON (mis. HTML proxy). Dulu memakai
            // null-coalescing sehingga tidak error; sekarang resolveProviderMessage
            // juga tetap aman, tapi normalize di sini supaya tidak TypeError.
            $data = [];
        }

        $answer = $this->resolveProviderMessage($data);

        // Guard bubble kosong: model reasoning (mis. Chutex 65536 tokens) bisa
        // menghabiskan budget untuk "thinking" dan mengembalikan content kosong
        // supaya DB menerima '' -> bubble assistant kosong di UI (dulu sempat berhasil
        // muncul, kini wajib jatuh ke return yang tidak kosong).
        if (trim((string) $answer) === '') {
            $answer = 'Maaf, jawaban AI kosong. Coba kirim ulang pertanyaan Anda.';
        }

        return $answer;
    }

    /**
     * Ambil teks jawaban dari respons provider OpenAI-compatible dengan fallback
     * berlapis: urutan ideas: content string -> content array (parts) -> text ->
     * reasoning_content -> reasoning -> reply -> yang lain.
     */
    protected function resolveProviderMessage(array $data): string
    {
        $message = $data['choices'][0]['message'] ?? null;
        if (! is_array($message)) {
            return '';
        }

        $content = $message['content'] ?? null;

        if (is_string($content) && $content !== '') {
            return $content;
        }

        if (is_array($content)) {
            $parts = array_filter(array_map(function ($part) {
                if (is_string($part)) {
                    return $part;
                }
                if (is_array($part)) {
                    return $part['text'] ?? ($part['content'] ?? '');
                }
                return '';
            }, $content));

            if (! empty($parts)) {
                return implode('', $parts);
            }
        }

        foreach (['text', 'reasoning_content', 'reasoning', 'reply', 'output'] as $key) {
            $val = $message[$key] ?? null;
            if (is_string($val) && $val !== '') {
                return $val;
            }
            if (is_array($val)) {
                $joined = implode('', array_filter(array_map(function ($part) {
                    if (is_string($part)) return $part;
                    if (is_array($part)) return $part['text'] ?? ($part['content'] ?? '');
                    return '';
                }, $val)));
                if ($joined !== '') {
                    return $joined;
                }
            }
        }

        // Fallback: reasoning_content bisa juga ada di level data (bukan di message)
        $reasoning = $data['reasoning_content'] ?? $data['reasoning'] ?? null;
        if (is_string($reasoning) && $reasoning !== '') {
            return $reasoning;
        }

        return '';
    }

    protected function callAnthropic(array $cfg, string $systemPrompt, array $messages): string
    {
        // Anthropic expects system prompt separate from messages
        $payload = [
            'model' => $cfg['model'],
            'system' => $systemPrompt,
            'messages' => array_map(function ($m) {
                // Handle content blocks (images + text) for vision
                if (is_array($m['content'])) {
                    return $m;
                }
                return ['role' => $m['role'], 'content' => $m['content']];
            }, $messages),
            'max_tokens' => $cfg['max_tokens'] ?? config('chatbot.max_output_tokens', 4096),
            'temperature' => $cfg['temperature'] ?? 0.2,
        ];

        $response = Http::timeout(config('chatbot.request_timeout', 120))
            ->withHeaders([
                'x-api-key' => $cfg['api_key'],
                'anthropic-version' => '2023-06-01',
            ])
            ->post($cfg['base_url'] ?? 'https://api.anthropic.com/v1/messages', $payload);

        if (! $response->successful()) {
            throw new RuntimeException("Anthropic API error: " . $response->body());
        }

        $data = $response->json();
        $content = $data['content'] ?? [];

        // Handle array content blocks
        if (is_array($content)) {
            $textParts = array_filter($content, fn($c) => ($c['type'] ?? '') === 'text');
            return implode('', array_map(fn($c) => $c['text'] ?? '', $textParts));
        }

        return $content ?? '';
    }

    protected function callGemini(array $cfg, string $systemPrompt, array $messages): string
    {
        // Merge system prompt into first user message
        $contents = array_merge(
            [['role' => 'user', 'parts' => [['text' => $systemPrompt]]]],
            array_map(function ($m) {
                if (is_array($m['content'])) {
                    // For image attachments, keep as-is
                    $parts = [];
                    foreach ($m['content'] as $block) {
                        if ($block['type'] === 'image') {
                            $parts[] = ['inline_data' => [
                                'mime_type' => $block['source']['media_type'],
                                'data' => $block['source']['data'],
                            ]];
                        } else {
                            $parts[] = ['text' => $block['text']];
                        }
                    }
                    return ['role' => $m['role'], 'parts' => $parts];
                }
                return ['role' => $m['role'], 'parts' => [['text' => $m['content']]]];
            }, $messages)
        );

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $cfg['temperature'] ?? 0.2,
                'maxOutputTokens' => $cfg['max_tokens'] ?? config('chatbot.max_output_tokens', 4096),
            ],
        ];

        $response = Http::timeout(config('chatbot.request_timeout', 120))
            ->withHeaders([
                'x-goog-api-key' => $cfg['api_key'],
            ])
            ->post("{$cfg['base_url']}?key={$cfg['api_key']}", $payload);

        if (! $response->successful()) {
            throw new RuntimeException("Gemini API error: " . $response->body());
        }

        $data = $response->json();
        $candidates = $data['candidates'] ?? [];

        if (empty($candidates)) {
            return '';
        }

        $parts = $candidates[0]['content']['parts'] ?? [];
        return implode('', array_map(fn($p) => $p['text'] ?? '', $parts));
    }
}