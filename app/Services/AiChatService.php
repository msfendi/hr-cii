<?php

namespace App\Services;

use App\Models\ChatbotMessage;
use App\Models\ChatbotSession;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
     * Kolom komponen gaji pada employees_contract. Diblokir untuk pengguna
     * tanpa role payroll, TETAPI dibuka untuk role payroll terdaftar (R33) —
     * cakupan role adalah soal karyawan mana, bukan soal kolom.
     */
    protected const KOLOM_GAJI = ['salary', 'daily_salary', 'allowance', 'pph21', 'pph_21'];
    /**
     * Pesan error terakhir saat membaca skema read-only (untuk diagnostik
     * pesan kegagalan mode RAG; null kalau tidak ada error).
     */
    protected $lastSchemaError = null;

    /**
     * Pembaca dokumen aturan RAG (docs/RAG_RULES_CII.md). Disimpan per
     * instance supaya berkas hanya diurai sekali per request.
     */
    protected ?RagRules $aturanRag = null;

    /** Guard data payroll per role_payrolls (R33). */
    protected ?PayrollAccessGuard $payrollGuard = null;

    protected function aturanRag(): RagRules
    {
        return $this->aturanRag ??= app(RagRules::class);
    }

    protected function payrollGuard(): PayrollAccessGuard
    {
        return $this->payrollGuard ??= app(PayrollAccessGuard::class);
    }

    /**
     * Guard check yang dijalankan pada SETIAP prompt, sebelum aturan RAG
     * disusun: identitas penanya dan role payroll-nya dibaca ulang dari DB.
     *
     * Dipisah jadi method supaya bisa diuji dan diganti (stub) di unit test.
     */
    protected function guardSesi(ChatbotSession $session): array
    {
        $penggunaAktif = null;

        try {
            $penggunaAktif = auth()->hasUser() ? auth()->id() : null;
        } catch (\Throwable $e) {
            Log::warning('Chatbot: auth() tidak tersedia saat guard check — ' . $e->getMessage());
        }

        return $this->payrollGuard()->periksaSesi($session, $penggunaAktif);
    }

    /**
     * Ringkasan guard untuk meta respons / log. Hanya id, role, dan kode —
     * tidak pernah memuat nama, NPK, atau isi data lain.
     */
    protected function ringkasGuard(array $cek): array
    {
        return [
            'user_id' => $cek['user_id'] ?? null,
            'role' => $cek['role'] ?? null,
            'kode' => $cek['kode'] ?? null,
        ];
    }

    /**
     * Riwayat percakapan sesi ini (lama → baru), siap dikirim ke provider.
     *
     * Pesan user yang BARU dibuat oleh controller sebelum reply() dipanggil
     * tidak ikut dikirim (kalau ikut, pertanyaan yang sama muncul dua kali:
     * sekali sebagai riwayat, sekali sebagai pesan terakhir).
     *
     * @return array<int,array{role:string,content:string}>
     */
    protected function pesanRiwayat(ChatbotSession $session): array
    {
        $pesan = $this->barisRiwayat($session);
        $hasil = [];

        foreach ($pesan as $p) {
            $isi = $this->ringkasPesan((string) $p->content, 1800);

            if ($p->role === ChatbotMessage::ROLE_ASSISTANT) {
                $sql = trim((string) ($p->meta['sql'] ?? ''));

                if ($sql !== '') {
                    $isi .= "\n[query yang dipakai jawaban ini]\n" . $this->ringkasPesan($sql, 1200);
                }

                $jumlah = $p->meta['row_count'] ?? null;

                if ($jumlah !== null) {
                    $isi .= "\n[jumlah baris hasil query: {$jumlah}]";
                }
            }

            if ($isi === '') {
                continue;
            }

            $hasil[] = [
                'role' => $p->role === ChatbotMessage::ROLE_ASSISTANT ? 'assistant' : 'user',
                'content' => $isi,
            ];
        }

        return $hasil;
    }

    /**
     * Blok teks riwayat untuk system prompt generator SQL.
     *
     * Generator SQL butuh ini secara EKSPLISIT: pertanyaan lanjutan seperti
     * "kenapa LINE 66 aktifnya 0?" tidak memuat satupun nama tabel/periode,
     * jadi tanpa riwayat (dan tanpa query terakhir) model hanya bisa menebak
     * atau menolak. Query terakhir ditampilkan utuh supaya bisa dipakai
     * sebagai dasar (diubah filtornya), bukan ditulis dari nol.
     */
    protected function blokRiwayatPercakapan(ChatbotSession $session): string
    {
        $pesan = $this->barisRiwayat($session);

        if ($pesan->isEmpty()) {
            return "\nRIWAYAT PERCAKAPAN SESI INI: (belum ada — ini pertanyaan pertama di sesi ini)\n";
        }

        $baris = [];

        foreach ($pesan as $p) {
            $peran = $p->role === ChatbotMessage::ROLE_ASSISTANT ? 'AI' : 'USER';
            $isi = $this->ringkasPesan((string) $p->content, 900);
            $baris[] = "{$peran}: {$isi}";

            if ($p->role !== ChatbotMessage::ROLE_ASSISTANT) {
                continue;
            }

            $sql = trim((string) ($p->meta['sql'] ?? ''));

            if ($sql !== '') {
                $baris[] = '  QUERY TERAKHIR YANG BERHASIL: ' . $this->ringkasPesan($sql, 1500);
            }
        }

        return "\nRIWAYAT PERCAKAPAN SESI INI (lama → baru; ini konteks resmi, bukan karangan user):\n"
            . implode("\n", $baris)
            . "\n";
    }

    /**
     * @return \Illuminate\Support\Collection<int,ChatbotMessage>
     */
    protected function barisRiwayat(ChatbotSession $session)
    {
        // Mode test / pemanggil yang sudah menyiapkan relasi: pakai apa adanya.
        $pesan = $session->relationLoaded('messages')
            ? $session->messages->sortBy('id')->values()
            : $session->messages()->reorder()->orderBy('id')->get();

        // Pesan user pada giliran SEKARANG sudah tersimpan sebelum reply()
        // dipanggil — keluarkan dari riwayat supaya tidak dobel.
        if ($pesan->isNotEmpty() && $pesan->last()->role === ChatbotMessage::ROLE_USER) {
            $pesan = $pesan->slice(0, $pesan->count() - 1)->values();
        }

        $limit = max(0, (int) config('chatbot.rag_history_limit', 8));

        if ($limit > 0 && $pesan->count() > $limit) {
            $pesan = $pesan->slice($pesan->count() - $limit)->values();
        }

        return $pesan;
    }

    protected function ringkasPesan(string $teks, int $maks): string
    {
        $teks = trim(preg_replace('/\s+/u', ' ', $teks) ?? $teks);

        if (mb_strlen($teks) <= $maks) {
            return $teks;
        }

        return mb_substr($teks, 0, $maks) . ' …';
    }

    /**
     * Titik masuk utama. Mengembalikan array:
     * ['content' => string, 'meta' => array|null]
     */
    public function reply(ChatbotSession $session, string $userMessage, array $attachments = []): array
    {
        // Sesi lama bisa tersimpan tanpa ai_provider; jangan sampai chatbot
        // gagal hanya karena kolom itu kosong.
        $providerKey = $session->ai_provider ?: config('chatbot.default_provider', 'router9');

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
        $adaLampiran = ! empty($attachments);
        unset($attachments);

        // Sapaan / obrolan ringan dijawab langsung TANPA query dan tanpa
        // export. Kalau tidak dipisahkan, model pembuat SQL bisa mengarang
        // query (mis. "SELECT NPK, NAMA_KARYAWAN ... FROM BIODATA LIMIT 10")
        // lalu membalas "halo" dengan tabel karyawan + file Excel yang tidak
        // diminta user.
        if (! $adaLampiran && $this->adalahSapaan($userMessage)) {
            return [
                'content' => $this->jawabTanpaSql($providerKey, $userMessage, $session),
                'meta' => ['sql' => null],
            ];
        }

        // ATURAN DULU, QUERY KEMUDIAN (permintaan 16 Nov 2026): dokumen
        // aturan RAG dibaca sebelum skema diintrospeksi dan sebelum AI
        // menyusun SQL. Kalau berkasnya tidak terbaca, mode RAG berhenti di
        // sini (fail-closed) — aturan memuat larangan tabel/kolom dan
        // batasan data pribadi yang tidak boleh dilewati.
        try {
            $aturanTeks = $this->aturanRag()->ringkasanPrompt($userMessage);
            $sidikAturan = $this->aturanRag()->sidik();
        } catch (\Throwable $e) {
            Log::error('AiChatService: dokumen aturan RAG gagal dibaca — ' . $e->getMessage());

            return [
                'content' => 'Mode RAG belum bisa dijalankan karena dokumen aturan RAG ('
                    . '`docs/RAG_RULES_CII.md`) tidak terbaca. Pertanyaan sengaja tidak diproses '
                    . 'supaya tidak menghasilkan query yang melanggar aturan. Hubungi IT untuk '
                    . 'memulihkan berkas tersebut, lalu coba lagi.',
                'meta' => ['sql' => null, 'rag_rules_error' => $e->getMessage()],
            ];
        }

        // GUARD CHECK WAJIB (permintaan 18 Nov 2026): sebelum aturan RAG
        // dipakai, `users` dan `role_payrolls` dibaca ulang dari database
        // untuk memastikan (a) sesi punya pemilik yang benar-benar ada,
        // (b) sesi tidak dipakai akun lain, (c) role payroll yang dipakai
        // adalah nilai terkini. Gagal verifikasi = permintaan tidak diproses.
        $cekGuard = $this->guardSesi($session);

        if (! $cekGuard['ok']) {
            Log::warning('Chatbot: guard check menolak permintaan', [
                'kode' => $cekGuard['kode'],
                'user_id' => $cekGuard['user_id'],
            ]);

            return [
                'content' => $cekGuard['pesan'],
                'meta' => ['sql' => null, 'guard_check' => $this->ringkasGuard($cekGuard)],
            ];
        }

        $penanya = $cekGuard['user'];
        $rolePayroll = $cekGuard['role'];
        $pertanyaanPayroll = $this->payrollGuard()->sensitif($userMessage);
        $payrollBlok = '';

        // Jejak audit: setiap permintaan yang lolos guard mencatat siapa,
        // role apa, dan apakah pertanyaannya menyentuh payroll. Tanpa angka
        // dan tanpa isi pertanyaan.
        Log::info('Chatbot: guard check lolos', [
            'user_id' => $penanya?->id,
            'role' => $rolePayroll,
            'kode' => $cekGuard['kode'],
            'payroll' => $pertanyaanPayroll,
        ]);

        if ($pertanyaanPayroll) {
            $penolakan = $this->payrollGuard()->pelanggaran($rolePayroll, $userMessage);

            if ($penolakan !== null) {
                Log::info('Chatbot: permintaan payroll di luar cakupan role ditolak', [
                    'role' => $rolePayroll,
                    'user_id' => $penanya?->id,
                ]);

                return [
                    'content' => $penolakan,
                    'meta' => [
                        'sql' => null,
                        'payroll_guard' => $this->payrollGuard()->ringkas($rolePayroll),
                        'guard_check' => $this->ringkasGuard($cekGuard),
                    ],
                ];
            }

            $payrollBlok = $this->payrollGuard()->blokPrompt($rolePayroll);
        }

        // KONTEKS PERCAKAPAN (permintaan 17 Nov 2026): model harus melihat
        // giliran-giliran sebelumnya di sesi ini. Tanpa ini, pertanyaan
        // lanjutan ("kenapa LINE 66 aktifnya 0?") tidak punya rujukan sama
        // sekali dan AI menjawab seolah lupa hasil yang baru saja ia tampilkan.
        $riwayatBlok = $this->blokRiwayatPercakapan($session);
        $riwayatPesan = $this->pesanRiwayat($session);

        $blockedTables = $this->blockedTables();
        // R33: blokir kolom gaji hanya untuk penanya tanpa role payroll.
        $blockedColumns = $this->blockedColumnsUntuk($rolePayroll);
        $schema = $this->getDatabaseSchema($blockedTables, $blockedColumns);

        if (empty($schema)) {
            return [
                'content' => $this->pesanSchemaKosong(),
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

        $driverReadonly = $this->readonlyDriver();
        $dialect = $driverReadonly === 'sqlite' ? 'SQLite' : 'SQL Server (T-SQL)';
        $batasAturan = $driverReadonly === 'sqlite'
            ? "6. Batasi jumlah baris hasil dengan LIMIT di AKHIR query.\n               Contoh BENAR : SELECT DISTINCT kolom FROM tabel LIMIT {$maxRows}"
            : "6. Urutan penulisan klausa di awal query WAJIB: SELECT lalu (kalau perlu) DISTINCT lalu TOP.\n               Contoh BENAR : SELECT DISTINCT TOP {$maxRows} destination FROM ...\n               Contoh SALAH : SELECT TOP {$maxRows} DISTINCT destination FROM ...";

        $sqlSystemPrompt = <<<PROMPT
            Kamu adalah generator query {$dialect} READ-ONLY untuk sebuah sistem aplikasi.

            ATURAN RAG RESMI — dibaca LEBIH DULU sebelum menyusun query, dan wajib dipatuhi.
            Kalau aturan di bawah bertentangan dengan panduan generik mana pun, aturan ini yang menang:

            {$aturanTeks}

            {$payrollBlok}---- akhir aturan RAG ----
            {$riwayatBlok}
            Berikut daftar SEMUA tabel & kolom yang benar-benar ada di database ini (hasil introspeksi
            otomatis langsung dari katalog database — bukan hafalan/perkiraan), kolom yang ditandai (PK)
            adalah primary key:

            $schemaText

            Relasi foreign key antar tabel (pakai ini untuk menentukan JOIN yang benar, JANGAN menebak):
            $relationsText{$schemaDocsBlock}

            Aturan WAJIB:
            1. Hanya boleh menghasilkan SATU statement: SELECT, atau WITH ... SELECT kalau memang perlu CTE. Dilarang keras INSERT/UPDATE/DELETE/DROP/ALTER/EXEC atau statement lain.
            2. Hanya boleh memakai tabel & kolom yang PERSIS tercantum di daftar di atas (nama harus sama persis, termasuk huruf besar/kecil pada penulisan aslinya). Jangan mengarang, menyingkat, atau menebak nama tabel/kolom yang tidak ada di daftar. DILARANG memakai SELECT * — selalu sebutkan nama kolom secara eksplisit.
            3. Kalau mau JOIN antar tabel, cocokkan dulu dengan daftar relasi foreign key ATAU dokumentasi relasi logis di atas. Kalau tidak ada di keduanya, baru cari kolom dengan nama yang paling masuk akal di kedua tabel (mis. id vs sesuatu_id) — jangan asal tebak nama kolom join kalau relasinya sudah terdokumentasi.
            4. Tabel berikut TERLARANG TOTAL dan tidak boleh muncul di query dengan cara apa pun, termasuk lewat JOIN atau subquery: {$blockedList}.{$blockedColumnsRule}
            {$batasAturan}
            7. Jangan tambahkan penjelasan, komentar, atau teks lain di luar query.
            8. Balas HANYA dengan SATU query SQL polos di dalam blok kode ```sql ... ```, tanpa kalimat pengantar, penjelasan, atau teks lain di luar blok kode.
            9. Pertanyaan tentang identitas/kepegawaian yang TIDAK menyentuh tabel maupun kolom terlarang WAJIB dijawab
               dengan query — JANGAN balas NO_QUERY. Contoh kolom yang boleh ditampilkan: BIODATA.NPK,
               BIODATA.NAMA_KARYAWAN, BIODATA.BAG, BIODATA.SECTION, BIODATA.STATUS, DEPT.DEPARTEMENT,
               PKWT.TMK, PKWT.STATUS, PKWT.PDDK. Kalau user meminta data yang SEBAGIAN terlarang
               (mis. "data lengkap karyawan X"), ambil hanya kolom yang diizinkan dan jangan sertakan kolom
               terlarang — bukan membalas NO_QUERY.

            10. KONTEKS PERCAKAPAN: blok "RIWAYAT PERCAKAPAN SESI INI" di atas adalah konteks resmi.
                Kalau pertanyaan user adalah LANJUTAN dari jawaban sebelumnya (memakai kata rujukan seperti
                "itu", "nya", "tersebut", "tadi", "yang tadi", atau hanya menyebut satu nilai dari tabel yang
                barusan ditampilkan, mis. "kenapa LINE 66 aktifnya 0?"), maka:
                - JANGAN menulis query dari nol dan JANGAN menjawab bahwa kamu tidak punya konteks/riwayat.
                - Ambil "QUERY TERAKHIR YANG BERHASIL" sebagai DASAR, lalu pertahankan tabel, JOIN, filter
                  periode, dan pengelompokan yang sama; ubah seminimal mungkin supaya fokus ke objek yang
                  ditanyakan (mis. tambahkan filter LINE 66 atau pecah per LINE/DEPARTEMENT).
                - Kalau pertanyaan lanjutan tidak butuh query baru sama sekali, tetap balas satu query
                  ringkas yang memverifikasi angka yang ditanyakan (bukan NO_QUERY), supaya jawaban punya bukti.

            Jika pertanyaan user hanya bisa dijawab lewat tabel/kolom yang terlarang di atas, atau tidak bisa dijawab
            dari skema yang tersedia sama sekali, balas persis: NO_QUERY
            PROMPT;

        $pesanSql = $riwayatPesan;
        $pesanSql[] = ['role' => 'user', 'content' => $userMessage];

        $rawSqlResponse = $this->callProvider($providerKey, $sqlSystemPrompt, $pesanSql);

        if (trim($rawSqlResponse) === 'NO_QUERY') {
            // Pertanyaan tidak bisa/ tidak perlu dijawab lewat query. Jangan
            // balas dengan satu kalimat penolakan untuk SEMUA kasus: sapaan
            // ("halo") dan obrolan ringan tidak menyentuh data sama sekali,
            // jadi harus dijawab wajar. Permintaan data yang dibatasi pun
            // lebih baik dijelaskan + ditawari alternatif oleh AI.
            return [
                'content' => $this->jawabTanpaSql($providerKey, $userMessage, $session),
                'meta' => ['sql' => null],
            ];
        }

        $sql = $this->normalizeSql($this->extractSql($rawSqlResponse));
        $rows = null;
        $lastError = null;
        $alasanTerakhir = '';

        // Loop percobaan: query yang GAGAL DIVALIDASI (bukan SELECT/WITH,
        // menyentuh tabel terlarang, dsb) ATAU gagal dieksekusi (nama
        // tabel/kolom tidak cocok, salah sintaks) dilempar balik ke AI supaya
        // diperbaiki. Sebelumnya kegagalan validasi langsung menghentikan
        // proses, sehingga user hanya melihat pesan "tidak bisa dijalankan
        // dengan aman" padahal query-nya masih bisa diperbaiki.
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $validation = $this->validateReadonlySql($sql, $blockedTables, $blockedColumns);

            // R33: query payroll WAJIB memuat filter cakupan role penanya.
            // Kalau model lupa menambahkannya, query diperlakukan sebagai
            // tidak valid supaya masuk jalur perbaikan (bukan langsung jalan).
            if ($validation['valid'] && $pertanyaanPayroll
                && ! $this->payrollGuard()->sqlSesuaiCakupan($sql, $rolePayroll)) {
                $validation = ['valid' => false, 'reason' => $this->payrollGuard()->pesanFilterHilang($rolePayroll)];
            }

            if (! $validation['valid']) {
                $alasanTerakhir = $validation['reason'];

                // Balasan AI sama sekali tidak berbentuk query (mis. AI
                // menjawab dengan kalimat biasa). Jangan tampilkan pesan
                // keamanan: sampaikan balasan AI itu apa adanya.
                if (! $this->berbentukQuery($sql)) {
                    // Kalau isinya ternyata monolog proses berpikir model
                    // (bahasa Inggris: "Wait, ...", "Notice: ...") atau
                    // jawaban yang terpotong di tengah, jangan diteruskan ke
                    // user mentah-mentah — minta jawaban wajar lewat jalur
                    // tanya-jawab biasa. Ini yang dulu membuat user melihat
                    // "kenapa responsenya seperti itu dan bukan jawaban
                    // langsung?".
                    if ($this->tampakSepertiPenalaran($rawSqlResponse)) {
                        Log::warning('Chatbot: balasan generator SQL berupa teks penalaran/terpotong — dialihkan ke jawaban biasa.', [
                            'provider' => $providerKey,
                            'cuplikan' => Str::limit(trim($rawSqlResponse), 200),
                        ]);

                        return [
                            'content' => $this->jawabTanpaSql($providerKey, $userMessage, $session),
                            'meta' => ['sql' => null],
                        ];
                    }

                    return [
                        'content' => $this->balasanTanpaQuery($rawSqlResponse),
                        'meta' => ['sql' => null],
                    ];
                }

                if ($attempt >= $maxAttempts) {
                    break;
                }

                $rawFixResponse = $this->mintaPerbaikanQuery(
                    $providerKey,
                    $sqlSystemPrompt,
                    $userMessage,
                    $sql,
                    'Query ditolak validator: ' . $validation['reason']
                );

                if ($rawFixResponse === null) {
                    $alasanTerakhir = 'AI menyerah memperbaiki query setelah ditolak validator.';

                    break;
                }

                $sql = $this->normalizeSql($this->extractSql($rawFixResponse));

                continue;
            }

            try {
                $rows = DB::connection(config('chatbot.readonly_connection'))->select($sql);
                $lastError = null;

                break;
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                $alasanTerakhir = $lastError;

                if ($attempt >= $maxAttempts) {
                    break;
                }

                $rawFixResponse = $this->mintaPerbaikanQuery($providerKey, $sqlSystemPrompt, $userMessage, $sql, $lastError);

                if ($rawFixResponse === null) {
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
                'meta' => ['sql' => $sql, 'error' => $alasanTerakhir ?: $lastError, 'rag_rules' => $sidikAturan],
            ];
        }

        $rows = array_slice($rows, 0, $maxRows);
        $rowsJson = json_encode($rows, JSON_UNESCAPED_UNICODE);

        // Grafik dibuat LEBIH DULU supaya prompt jawaban tahu bahwa grafiknya
        // sudah ada. Tanpa ini model bahasa selalu menolak dengan kalimat
        // "saya belum mendukung visualisasi chart" (persis jawaban yang
        // dikeluhkan user).
        $mintaChart = $this->mintaChart($userMessage);
        $chart = $this->siapkanChart($rows, $userMessage, $mintaChart);

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

        // Bagian 8 dokumen aturan: setiap jawaban berbasis data wajib memuat
        // sumber tabel.kolom, periode, jumlah baris, filter penting, dan
        // status verifikasi.
        $aturanJawaban = '';
        try {
            $aturanJawaban = trim($this->aturanRag()->bagian('8'));
        } catch (\Throwable $e) {
            Log::warning('AiChatService: bagian format jawaban tidak terbaca — ' . $e->getMessage());
        }

        if ($aturanJawaban !== '') {
            $answerSystemPrompt .= "\n\nWAJIB ikuti format jawaban resmi berikut (Bagian 8 dokumen aturan RAG). "
                . 'Ringkas, tapi jangan hilangkan sumber/periode/jumlah baris/status verifikasi:' . "\n" . $aturanJawaban;
        }

        // Model bahasa cenderung menolak permintaan grafik. Karena grafiknya
        // sudah benar-benar dibuat di server, prompt diberi tahu supaya
        // jawaban tidak lagi berbunyi "belum mendukung visualisasi".
        if (! empty($chart)) {
            $answerSystemPrompt .= "\n\nGRAFIK SUDAH DIBUAT DAN DITAMPILKAN OTOMATIS di bawah jawabanmu sebagai gambar PNG "
                . "(grafik batang: {$chart['nilai_kolom']} per {$chart['label_kolom']}"
                . ($chart['satuan'] !== '' ? ", satuan {$chart['satuan']}" : '') . '). '
                . 'JANGAN mengatakan tidak bisa membuat grafik/chart dan JANGAN menyarankan user membuat grafik sendiri. '
                . 'Sebutkan singkat bahwa grafiknya tampil di bawah jawaban, lalu tetap berikan ringkasan angka '
                . 'dan sebutkan nilai tertinggi. Jangan mengarang angka di luar data JSON.';
        } elseif ($mintaChart) {
            $answerSystemPrompt .= "\n\nCATATAN: user meminta grafik, tetapi hasil query ini tidak punya pasangan kolom "
                . 'kategori + angka yang bisa diplot (atau barisnya kurang dari 2). Jelaskan singkat bahwa grafik tidak '
                . 'bisa dibuat untuk data ini, lalu sajikan datanya sebagai tabel. Jangan mengarang grafik.';
        }

        // Riwayat juga dikirim ke penjawab. Tanpa ini, pertanyaan lanjutan
        // seperti "kenapa LINE 66 aktifnya 0?" dijawab dari nol — model tidak
        // tahu baris mana yang dimaksud dan menjawab seolah lupa.
        if ($riwayatPesan !== []) {
            $answerSystemPrompt .= "\n\nKONTEKS PERCAKAPAN SESI INI (lama → baru):" . $riwayatBlok
                . "\nPertanyaan user berikutnya adalah LANJUTAN percakapan di atas. Rujuk hasil yang sudah "
                . 'ditampilkan (mis. baris LINE 66 pada tabel sebelumnya) tanpa meminta user mengulang '
                . 'pertanyaan atau menyebutkan ulang periodenya. DILARANG menjawab bahwa kamu tidak punya '
                . 'riwayat/konteks percakapan.';
        }

        // R33: penjawab juga diberi tahu cakupan payroll penanya supaya tidak
        // menuliskan "seluruh karyawan" untuk angka yang sudah difilter.
        if ($pertanyaanPayroll) {
            $ringkasGuard = $this->payrollGuard()->ringkas($rolePayroll);
            $answerSystemPrompt .= "\n\nBATAS AKSES PAYROLL (R33): penanya berrole `{$ringkasGuard['role']}`"
                . " dengan cakupan {$ringkasGuard['cakupan']}. Data JSON di bawah SUDAH difilter ke cakupan itu."
                . ' Jangan menuliskan "seluruh karyawan"/"semua karyawan" untuk angka yang hanya mencakup cakupan tersebut'
                . ' — sebutkan cakupannya. Kalau user meminta data di luar cakupan, jelaskan bahwa aksesnya tidak'
                . ' diperbolehkan dan sebutkan cakupan yang ia miliki.';
        }

        $answer = $this->callProvider($providerKey, $answerSystemPrompt, array_merge($riwayatPesan, [
            ['role' => 'user', 'content' => "Pertanyaan user: {$userMessage}\n\nData JSON (maks {$maxRows} baris):\n{$rowsJson}"],
        ]));

        $format = $this->detectExportFormat($userMessage);
        $export = $this->exportRows($rows, $format, $userMessage);
        $links = $this->detectActionLinks($userMessage);

        return [
            'content' => $answer,
            'meta' => array_filter([
                'sql' => $sql,
                'row_count' => count($rows),
                'export' => $export,
                'chart' => $chart,
                'links' => $links ?: null,
                'rag_rules' => $sidikAturan,
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

        $leaveCuti = $this->sebutSalahSatu($text, ['cuti', 'leave', 'izin', 'pengajuan', 'sakit']);
        $slipGaji  = $this->sebutSalahSatu($text, ['slip', 'gaji', 'payroll', 'salary', 'thr']);
        $saldo     = $this->sebutSalahSatu($text, ['saldo', 'balance', 'jatah', 'sisa']);
        $approval  = $this->sebutSalahSatu($text, ['approval', 'approve', 'setuju', 'tolak', 'reject']);
        $riwayat   = $this->sebutSalahSatu($text, ['riwayat', 'history', 'daftar', 'status']);

        if ($leaveCuti && ($saldo || $riwayat || $this->sebutKata($text, 'cek'))) {
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
        // Sumber UTAMA = Bagian 6c dokumen aturan (R32). Sebelumnya daftar ini
        // hanya hidup di CHATBOT_RAG_BLOCKED_TABLES (.env), sehingga aturan
        // yang mengikat chatbot tidak ikut terdokumentasi. Config/.env tetap
        // dibaca sebagai lapisan TAMBAHAN kalau masih terisi.
        try {
            $daftar = $this->aturanRag()->blokTeknis()['tabel'];
        } catch (\Throwable $e) {
            Log::warning('AiChatService: blocklist Bagian 6c tidak terbaca — ' . $e->getMessage());
            $daftar = [];
        }

        $daftar = array_merge($daftar, (array) config('chatbot.rag_blocked_tables', []));
        $daftar = array_map(fn($t) => strtolower(trim((string) $t)), $daftar);

        // Jaring pengaman: dokumen dan .env sama-sama tidak terbaca jangan
        // sampai membuat blocklist kosong (fail-open).
        if (empty(array_filter($daftar))) {
            $daftar = ['payroll_masters', 'payroll_run_details', 'payroll_run_details_audit'];
        }

        // R25 dokumen aturan: empat tabel audit dilarang dibaca karena
        // kebijakan data (bukan soal permission). Diambil dari dokumen
        // aturan supaya daftar ini satu sumber dengan yang dikirim ke prompt.
        if (config('chatbot.rag_rules_enforce_r25', true)) {
            try {
                foreach ($this->aturanRag()->tabelTerlarangR25() as $tabel) {
                    $daftar[] = strtolower(trim($tabel));
                }
            } catch (\Throwable $e) {
                Log::warning('AiChatService: blocklist R25 tidak terbaca — ' . $e->getMessage());
            }
        }

        return array_values(array_unique(array_filter($daftar)));
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
        // Sumber UTAMA = Bagian 6c dokumen aturan (R32); config/.env hanya
        // lapisan tambahan (server lama) supaya tidak ada larangan yang hilang.
        $result = [];

        $gabung = function (array $sumber) use (&$result) {
            foreach ($sumber as $table => $columns) {
                $table = strtolower(trim((string) $table));

                if ($table === '') {
                    continue;
                }

                $bersih = array_values(array_filter(array_map(
                    fn($c) => strtolower(trim((string) $c)),
                    (array) $columns
                )));

                if ($bersih === []) {
                    continue;
                }

                $result[$table] = array_values(array_unique(array_merge($result[$table] ?? [], $bersih)));
            }
        };

        try {
            $gabung($this->aturanRag()->blokTeknis()['kolom']);
        } catch (\Throwable $e) {
            Log::warning('AiChatService: kolom terblokir Bagian 6c tidak terbaca — ' . $e->getMessage());
        }

        $gabung((array) config('chatbot.rag_blocked_columns', []));

        // Jaring pengaman: kolom gaji tidak boleh lolos hanya karena dokumen
        // dan .env sama-sama tidak terbaca.
        if ($result === []) {
            $result = ['employees_contract' => ['salary', 'allowance', 'daily_salary', 'pph21', 'pph_21', 'file_contract']];
        }

        return $result;
    }

    /**
     * Kolom terblokir KHUSUS untuk penanya dengan role payroll tertentu (R33).
     *
     * Tanpa ini, blokir kolom gaji dari R32/R19 berlaku untuk semua orang —
     * termasuk Payroll_ALL — sehingga setiap pertanyaan gaji dijawab
     * "kolom terlarang" dan aturan role_payrolls tidak pernah terpakai.
     * Role payroll terdaftar membuka kolom komponen gaji, tetapi:
     *  - kolom identitas (KTP/KK/HP), token, dan berkas tetap diblokir;
     *  - baris tetap wajib difilter ke cakupan role (sqlSesuaiCakupan()).
     * `file_contract` sengaja TIDAK dibuka: itu path berkas, bukan komponen gaji.
     */
    protected function blockedColumnsUntuk(?string $rolePayroll): array
    {
        $kolom = $this->blockedColumns();

        if (! $this->payrollGuard()->bolehLihatGaji($rolePayroll)) {
            return $kolom;
        }

        foreach (['employees_contract', 'employee_contract'] as $tabel) {
            if (! isset($kolom[$tabel])) {
                continue;
            }

            $kolom[$tabel] = array_values(array_diff($kolom[$tabel], self::KOLOM_GAJI));

            // Jangan tinggalkan entri kosong: susunan aturan prompt memakai
            // daftar ini apa adanya.
            if ($kolom[$tabel] === []) {
                unset($kolom[$tabel]);
            }
        }

        return $kolom;
    }

    /**
     * Driver koneksi read-only ('sqlsrv', 'sqlite', ...). Dipakai untuk
     * menyesuaikan introspeksi skema, dialek prompt, dan normalisasi SQL
     * supaya mode RAG tidak hanya jalan di SQL Server: instalasi/UJI COBA
     * yang memakai replika sqlite (mis. struktur CII yang dikirim ke
     * developer) juga jalan. Kalau koneksi gagal dibuka, anggap 'sqlsrv'
     * (perilaku lama) supaya tidak mengubah jalur produksi.
     */
    protected function readonlyDriver(): string
    {
        try {
            return DB::connection(config('chatbot.readonly_connection'))->getDriverName();
        } catch (\Throwable $e) {
            return 'sqlsrv';
        }
    }

    /**
     * Pencocokan kata utuh yang tidak bergantung pada mesin regex. Sebagian
     * lingkungan PCRE bisa gagal mengompilasi pola (preg_* mengembalikan
     * false + "Internal error") sehingga pengecekan blocklist lolos begitu
     * saja. Regex tetap dipakai lebih dulu; kalau mesinnya error, pencocokan
     * jatuh ke strpos yang lebih longgar (= lebih aman).
     */
    protected function sebutKata(string $teks, string $kata): bool
    {
        $hasil = @preg_match('/\b' . preg_quote($kata, '/') . '\b/i', $teks);

        if ($hasil === false) {
            return stripos($teks, $kata) !== false;
        }

        return $hasil === 1;
    }

    /** Salah satu kata muncul di teks? (tanpa bergantung mesin PCRE) */
    protected function sebutSalahSatu(string $teks, array $kata): bool
    {
        foreach ($kata as $satu) {
            if ($this->sebutKata($teks, $satu)) {
                return true;
            }
        }

        return false;
    }

    /**
     * preg_replace yang aman: kalau mesin regex gagal (hasil null), teks asli
     * dikembalikan apa adanya supaya tidak berubah jadi null / TypeError.
     */
    protected function gantiPola(string $pola, string $ganti, string $teks, int $batas = -1): string
    {
        $hasil = @preg_replace($pola, $ganti, $teks, $batas);

        return is_string($hasil) ? $hasil : $teks;
    }

    /**
     * Baca `SELECT [DISTINCT] TOP n ...` dan kembalikan [n, sql tanpa TOP].
     * Dipakai untuk konversi TOP -> LIMIT di sqlite. Regex dicoba dulu; kalau
     * mesin regex error, klausa TOP dibaca manual per token supaya query
     * T-SQL dari AI tetap bisa dijalankan di sqlite.
     */
    protected function ambilTop(string $sql): array
    {
        $m = [];
        $hasil = @preg_match('/^\s*select\s+(distinct\s+)?top\s+(\d+)\s+/i', $sql, $m);

        if ($hasil === 1) {
            $distinct = ($m[1] ?? '') !== '' ? 'DISTINCT ' : '';

            return [(int) $m[2], 'SELECT ' . $distinct . trim(substr($sql, strlen($m[0])))];
        }

        if ($hasil === 0) {
            return [null, $sql];
        }

        // Fallback manual: pecah per token, cari TOP sesudah SELECT/DISTINCT.
        $token = preg_split('/\s+/', trim($sql));

        if (! is_array($token)) {
            // Mesin regex gagal: pecah manual.
            $token = array_values(array_filter(explode(' ', trim($sql)), static function ($b) {
                return $b !== '';
            }));
        }

        if (count($token) < 3 || strtolower($token[0]) !== 'select') {
            return [null, $sql];
        }

        $idx = 1;
        if (isset($token[1]) && strtolower($token[1]) === 'distinct') {
            $idx = 2;
        }

        if (! isset($token[$idx + 1]) || strtolower($token[$idx]) !== 'top') {
            return [null, $sql];
        }

        $n = (int) $token[$idx + 1];
        if ($n <= 0) {
            return [null, $sql];
        }

        $kata = $idx === 2 ? 'DISTINCT ' : '';
        $sisa = array_slice($token, $idx + 2);

        return [$n, 'SELECT ' . $kata . implode(' ', $sisa)];
    }

    /**
     * Query sudah punya batas baris (LIMIT)? Dicek tanpa regex supaya tidak
     * bergantung pada mesin PCRE.
     */
    protected function sudahAdaLimit(string $sql): bool
    {
        return stripos($sql, ' limit') !== false;
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

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        try {
            $schema = $this->bangunSchema($connection, $blockedTables, $blockedColumns);
        } catch (\Throwable $e) {
            // Koneksi gagal / query INFORMATION_SCHEMA ditolak. JANGAN cache
            // kegagalan ini lama-lama: dulu hasil kosong ikut di-cache 30
            // menit, sehingga setelah konfigurasi diperbaiki chatbot tetap
            // melaporkan "tidak ada tabel" sampai cache kedaluwarsa.
            $this->lastSchemaError = $e->getMessage();
            Cache::put($cacheKey, [], now()->addSeconds(15));

            \Illuminate\Support\Facades\Log::warning('Chatbot RAG: gagal membaca skema dari koneksi ' . $connection . ': ' . $e->getMessage());

            return [];
        }

        if (empty($schema)) {
            // Terhubung, tapi tidak ada tabel yang lolos filter. Cache pendek
            // saja supaya perbaikan konfigurasi langsung terasa.
            Cache::put($cacheKey, [], now()->addSeconds((int) config('chatbot.schema_empty_cache_seconds', 15)));

            return [];
        }

        Cache::put($cacheKey, $schema, now()->addMinutes($cacheMinutes));

        return $schema;
    }

    /**
     * Bangun skema (tabel -> daftar kolom) dari koneksi read-only.
     * Dipisah supaya pemanggilnya bisa membedakan "koneksi gagal" dari
     * "koneksi jalan tapi tabel kosong".
     */
    protected function bangunSchema(string $connection, array $blockedTables, array $blockedColumns): array
    {
        $driver = $this->readonlyDriver();
        $primaryKeys = [];

        if ($driver === 'sqlite') {
            // sqlite tidak punya INFORMATION_SCHEMA / sys.*: katalognya dibaca
            // lewat sqlite_master + PRAGMA table_info (kolom pk > 0 = primary key).
            $rows = [];
            $tableRows = DB::connection($connection)->select(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
            );

            foreach ($tableRows as $tableRow) {
                $namaTabel = $tableRow->name;
                $kolomList = DB::connection($connection)->select(
                    'PRAGMA table_info("' . str_replace('"', '""', $namaTabel) . '")'
                );

                foreach ($kolomList as $kolom) {
                    $rows[] = (object) [
                        'TABLE_NAME' => $namaTabel,
                        'COLUMN_NAME' => $kolom->name,
                        'DATA_TYPE' => strtoupper((string) $kolom->type) !== '' ? strtoupper((string) $kolom->type) : 'TEXT',
                    ];

                    if ((int) $kolom->pk > 0) {
                        $primaryKeys[$namaTabel][] = $kolom->name;
                    }
                }
            }
        } else {
            $rows = DB::connection($connection)->select(
                'SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = ?
                 ORDER BY TABLE_NAME, ORDINAL_POSITION',
                ['dbo']
            );

            // Tandai kolom mana yang primary key, supaya AI tahu kolom mana
            // yang cocok dipakai sebagai kunci JOIN/agregasi tanpa menebak.
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
    }

    /**
     * Pesan diagnostik yang bisa ditindaklanjuti saat skema tidak terbaca.
     *
     * Pesan lama hanya bilang "tidak ada tabel" tanpa sebab, sehingga
     * admin tidak tahu harus memperbaiki apa. Sekarang: sebut koneksi,
     * nama database, dan error asli kalau koneksinya gagal.
     */
    protected function pesanSchemaKosong(): string
    {
        $connection = (string) config('chatbot.readonly_connection');
        $database = '-';

        try {
            $database = (string) (config("database.connections.$connection.database") ?? '-');
        } catch (\Throwable $e) {
            // biarkan '-'
        }

        $baris = [
            'Mode RAG belum bisa dipakai: **tidak ada tabel yang bisa dibaca** dari koneksi read-only `' . $connection . '`',
        ];

        if ($this->lastSchemaError) {
            $baris[0] .= ' (database `' . $database . '`).';
            $baris[] = '';
            $baris[] = '**Penyebab terdeteksi:** koneksi/query gagal — `' . $this->lastSchemaError . '`';
        } else {
            $baris[0] .= ' (database `' . $database . '`). Koneksi berhasil, tetapi tidak ada tabel yang lolos filter.';
        }

        $baris[] = '';
        $baris[] = '**Yang perlu dicek:**';
        $baris[] = '1. `DB_READONLY_HOST`, `DB_READONLY_DATABASE`, `DB_READONLY_USERNAME`, `DB_READONLY_PASSWORD` di `.env` server (bukan hanya di mesin dev).';
        $baris[] = '2. Login read-only benar-benar ada dan punya akses `SELECT` ke schema `dbo` database HRIS.';
        $baris[] = '3. Jalankan `php artisan chatbot:diagnose` di server untuk melihat koneksi mana yang dipakai, database apa, dan berapa tabel yang terbaca.';

        return implode("\n", $baris);
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
                if ($this->readonlyDriver() === 'sqlite') {
                    // sqlite: relasi dibaca per tabel lewat PRAGMA
                    // foreign_key_list (tidak ada sys.foreign_keys).
                    $rows = [];
                    $tableRows = DB::connection($connection)->select(
                        "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
                    );

                    foreach ($tableRows as $tableRow) {
                        $fkList = DB::connection($connection)->select(
                            'PRAGMA foreign_key_list("' . str_replace('"', '""', $tableRow->name) . '")'
                        );

                        foreach ($fkList as $fk) {
                            $rows[] = (object) [
                                'parent_table' => $tableRow->name,
                                'parent_column' => $fk->from,
                                'referenced_table' => $fk->table,
                                'referenced_column' => $fk->to,
                            ];
                        }
                    }
                } else {
                    // Query lama memakai subkueri `SELECT TOP 1 column_id` dari
                    // sys.foreign_key_columns. Kolom `column_id` TIDAK ADA di
                    // view itu (yang ada parent_column_id / referenced_column_id),
                    // jadi querynya selalu error dan relasi FK SELALU KOSONG di
                    // prompt AI. Versi ini join langsung lewat constraint_object_id.
                    $rows = DB::connection($connection)->select(
                        "SELECT
                            tp.name AS parent_table,
                            cp.name AS parent_column,
                            tr.name AS referenced_table,
                            cr.name AS referenced_column
                         FROM sys.foreign_keys fk
                         INNER JOIN sys.foreign_key_columns fkc ON fkc.constraint_object_id = fk.object_id
                         INNER JOIN sys.tables tp ON tp.object_id = fkc.parent_object_id
                         INNER JOIN sys.columns cp ON cp.object_id = fkc.parent_object_id AND cp.column_id = fkc.parent_column_id
                         INNER JOIN sys.tables tr ON tr.object_id = fkc.referenced_object_id
                         INNER JOIN sys.columns cr ON cr.object_id = fkc.referenced_object_id AND cr.column_id = fkc.referenced_column_id
                         WHERE tp.schema_id = SCHEMA_ID('dbo')
                           AND tr.schema_id = SCHEMA_ID('dbo')"
                    );
                }

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
     * - Harus satu statement yang diawali SELECT atau WITH (CTE read-only)
     * - Tidak mengandung kata kunci terlarang
     * - Tidak menyebut tabel terlarang
     * - Tidak menyebut kolom terlarang
     * - Tidak SELECT * pada tabel yang punya kolom terlarang
     */
    protected function validateReadonlySql(string $sql, array $blockedTables, array $blockedColumns): array
    {
        $lower = strtolower(trim($sql));

        // Diawali SELECT atau WITH. WITH (CTE) tetap read-only karena kata
        // kunci tulis diperiksa di bawah, dan jumlah baris tetap dipotong di
        // sisi PHP (jadi tidak wajib memakai TOP).
        $diawaliSelect = @preg_match('/^\s*(select|with)\b/', $lower);
        if ($diawaliSelect === false) {
            // Mesin regex gagal: pakai pemeriksaan awalan string biasa.
            $bersih = ltrim($lower);
            $diawaliSelect = (str_starts_with($bersih, 'select') || str_starts_with($bersih, 'with')) ? 1 : 0;
        }
        if ($diawaliSelect !== 1) {
            return ['valid' => false, 'reason' => 'Query tidak diawali SELECT/WITH'];
        }

        // Hanya satu statement (tanpa titik koma di tengah, kecuali di dalam string literal)
        $banyakStatement = @preg_match('/;.*\S/', $lower);
        if ($banyakStatement === false) {
            $pos = strpos($lower, ';');
            $banyakStatement = ($pos !== false && trim(substr($lower, $pos + 1)) !== '') ? 1 : 0;
        }
        if ($banyakStatement === 1) {
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
            if ($this->sebutKata($lower, $table)) {
                return ['valid' => false, 'reason' => "Menyebut tabel terlarang: {$table}"];
            }
        }

        // Kolom terlarang per tabel
        foreach ($blockedColumns as $table => $columns) {
            if ($this->sebutKata($lower, $table)) {
                foreach ($columns as $col) {
                    if ($this->sebutKata($lower, $col)) {
                        return ['valid' => false, 'reason' => "Menyebut kolom terlarang: {$table}.{$col}"];
                    }
                }

                // SELECT * pada tabel yang punya kolom terlarang (dicek
                // tanpa regex, setelah whitespace dinormalkan).
                $rapat = $this->gantiPola('/\s+/', ' ', $lower);
                if (str_contains($rapat, 'select *')
                    && str_contains($rapat, 'from ' . $table)) {
                    return ['valid' => false, 'reason' => "SELECT * pada tabel {$table} yang punya kolom terlarang tidak diperbolehkan"];
                }
            }
        }

        return ['valid' => true];
    }

    /**
     * Balasan AI berbentuk query SQL (bukan kalimat biasa)? Dipakai untuk
     * membedakan "AI menjawab dengan teks" dari "AI mengirim query yang
     * ditolak validator".
     */
    protected function berbentukQuery(string $teks): bool
    {
        $lower = strtolower($teks);

        foreach (['select', 'with', 'insert', 'update', 'delete', 'drop', 'alter', 'truncate', 'exec', 'execute', 'merge', 'create'] as $kata) {
            if ($this->sebutKata($lower, $kata)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Deteksi pesan sapaan / obrolan ringan ("halo", "pagi", "terima kasih",
     * "siapa kamu", ...). Dipakai supaya pesan seperti itu dijawab langsung
     * tanpa query SQL. Pesan yang mengandung kata permintaan data (tampilkan,
     * berapa, jumlah, karyawan, cuti, ...) SELALU dianggap bukan sapaan,
     * sehingga "halo, berapa jumlah karyawan?" tetap masuk jalur query.
     */
    protected function adalahSapaan(string $pesan): bool
    {
        $t = mb_strtolower(trim($pesan), 'UTF-8');
        if ($t === '') {
            return false;
        }

        // Buang tanda baca & emoji sederhana (hindari pola regex rumit —
        // PCRE di sebagian server gagal mengompilasi pola tertentu).
        $t = str_replace(
            [',', '.', '!', '?', ';', ':', '"', "'", '`', '(', ')', '[', ']', '{', '}',
             '-', '_', '/', '\\', '|', '+', '*', '~', '@', '#', '$', '%', '^', '&', '='],
            ' ',
            $t
        );
        $t = trim($this->gantiPola('/[ \t\r\n]+/', ' ', $t));

        // Sapaan biasanya pendek. Pesan panjang = pertanyaan/narasi.
        if ($t === '' || count(explode(' ', $t)) > 6) {
            return false;
        }

        // Ada kata permintaan data → bukan sapaan, harus lewat query.
        $petunjukData = [
            'tampilkan', 'tampil', 'tunjuk', 'berapa', 'jumlah', 'daftar', 'data', 'cari',
            'lihat', 'minta', 'rekap', 'total', 'absen', 'absensi', 'kehadiran', 'cuti',
            'gaji', 'slip', 'payroll', 'lowongan', 'karir', 'karyawan', 'pegawai', 'staf',
            'staff', 'departemen', 'department', 'bagian', 'section', 'jabatan', 'status',
            'masuk', 'keluar', 'resign', 'kontrak', 'pkwt', 'pendidikan', 'lulusan',
            'nomor', 'npk', 'nama',
        ];
        foreach ($petunjukData as $kata) {
            if (mb_stripos($t, $kata) !== false) {
                return false;
            }
        }

        $sapaan = [
            'halo', 'hallo', 'helo', 'hello', 'hai', 'hi', 'hey', 'hei', 'woi',
            'pagi', 'siang', 'sore', 'malam', 'selamat', 'assalamualaikum', 'salam',
            'waalaikumsalam', 'terima kasih', 'makasih', 'thanks', 'thank you', 'thx',
            'siapa kamu', 'kamu siapa', 'apa kabar', 'kabar', 'test', 'tes', 'oke',
            'ok', 'siap', 'bisa apa', 'bantuan', 'help', 'ping',
        ];
        foreach ($sapaan as $kata) {
            if (mb_stripos($t, $kata) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Jawaban untuk pertanyaan yang TIDAK dijawab lewat query SQL: sapaan /
     * obrolan ringan, pertanyaan tentang kemampuan asisten, atau permintaan
     * data yang memang dibatasi. Sebelumnya semua kasus ini dijawab dengan
     * satu kalimat penolakan generik, sehingga "halo" pun terlihat seperti
     * sistem rusak. Di sini AI diminta menjawab sebagai asisten (tanpa akses
     * database) dan menawarkan pertanyaan lain yang diizinkan.
     */
    protected function jawabTanpaSql(string $providerKey, string $userMessage, ?ChatbotSession $session = null): string
    {
        $default = 'Halo! Saya AI Assistant HRIS PT Chutex International Indonesia. '
            . 'Saya bisa membantu menampilkan data karyawan (nama, NPK, departemen, jabatan, status), '
            . 'rekap absensi, sisa cuti, dan lowongan yang dibuka. '
            . 'Data yang dibatasi — gaji, nomor KTP/KK/HP, dan akun pengguna — tidak dapat saya tampilkan. '
            . 'Coba tulis pertanyaan seperti: "jumlah karyawan aktif per departemen".';

        $systemPrompt = <<<PROMPT
            Kamu adalah AI Assistant internal HRIS PT Chutex International Indonesia.
            Pesan user di bawah ini TIDAK dijawab lewat query database, karena berupa sapaan/obrolan
            ringan, pertanyaan tentang kemampuanmu, atau permintaan data yang memang dibatasi.

            Aturan menjawab:
            1. Kalau pesannya sapaan, obrolan ringan, atau ucapan terima kasih: jawab ramah dan singkat
               (1-3 kalimat), lalu sebutkan 3-4 contoh pertanyaan yang bisa kamu jawab, misalnya jumlah
               karyawan aktif per departemen, daftar karyawan per jabatan/section, rekap absensi,
               sisa cuti, dan lowongan yang sedang dibuka.
            2. Kalau pesannya meminta data terbatas: jelaskan dengan sopan bahwa bagian data itu tidak
               dapat ditampilkan di mode ini (mis. data akun/kredensial pengguna, gaji, nomor KTP/KK/HP),
               lalu tawarkan bentuk pertanyaan lain yang diizinkan, misalnya nama, NPK, departemen,
               jabatan, status karyawan, tanggal masuk, atau pendidikan.
            3. Jangan mengarang angka, nama, atau isi data. Jangan mengaku sudah membuka database.
            4. Bahasa Indonesia, ringkas, maksimal 5 kalimat, tanpa tabel dan tanpa blok kode.
            PROMPT;

        $riwayatTanpaSql = $session ? $this->pesanRiwayat($session) : [];

        if ($riwayatTanpaSql !== [] && $session) {
            $systemPrompt .= "\n\n" . trim($this->blokRiwayatPercakapan($session))
                . "\nPesan terakhir user adalah LANJUTAN percakapan di atas. Kalau jawaban sebelumnya sudah "
                . 'menampilkan data (mis. tabel turnover), rujuk data itu — jangan menjawab bahwa kamu tidak '
                . 'punya riwayat/konteks. Jangan mengarang angka baru.';
        }

        try {
            $jawab = trim($this->callProvider($providerKey, $systemPrompt, array_merge($riwayatTanpaSql, [
                ['role' => 'user', 'content' => $userMessage],
            ])));
        } catch (\Throwable $e) {
            $jawab = '';
        }

        // Provider diam / malah membalas query → pakai jawaban bawaan yang aman.
        if ($jawab === '' || $this->berbentukQuery($jawab)) {
            return $default;
        }

        return $jawab;
    }

    /**
     * Apakah teks balasan provider sebenarnya proses berpikir internal
     * (chain-of-thought) atau jawaban yang terpotong di tengah?
     *
     * Model reasoning di balik gateway kadang menulis seluruh proses
     * berpikirnya ke isi jawaban (bahasa Inggris, "Wait, ...", "Notice: ..."),
     * dan kalau budget token habis jawabannya berhenti di tengah kalimat.
     * Teks seperti itu tidak layak ditampilkan sebagai jawaban HRIS.
     */
    protected function tampakSepertiPenalaran(string $teks): bool
    {
        $bersih = trim($teks);

        if ($bersih === '') {
            return false;
        }

        $lower = mb_strtolower($bersih);

        $penanda = [
            'wait,', 'wait.', 'notice:', "i'm now", 'i am now', 'let me think',
            'let me reconsider', 'let me check', 'thinking process',
            'chain of thought', 'the user is asking', 'the user wants',
            'so the ranking', 'i need to', 'i should', 'maybe we can',
            'hmm,', 'step 1:', 'step 2:', 'let me analyze', 'my reasoning',
        ];

        foreach ($penanda as $p) {
            if (str_contains($lower, $p)) {
                return true;
            }
        }

        // Tanpa blok kode dan berakhir tanpa tanda akhir kalimat → hampir pasti
        // jawaban terpotong karena kehabisan token.
        if (mb_strlen($bersih) > 200
            && ! str_contains($bersih, '```')
            && ! preg_match('/[.!?:)\]]\s*$/', $bersih)) {
            return true;
        }

        return false;
    }

    /**
     * Balasan AI yang ternyata bukan query tetap disampaikan ke user apa
     * adanya (tanpa pagar kode), bukan diganti pesan error keamanan.
     */
    protected function balasanTanpaQuery(string $respons): string
    {
        $bersih = trim($this->gantiPola('/```[a-zA-Z]*\s*|\s*```/', '', $respons));

        return $bersih === ''
            ? 'AI tidak menghasilkan query untuk pertanyaan ini. Coba ajukan pertanyaan lain atau perjelas maksudnya.'
            : $bersih;
    }

    /**
     * Minta AI memperbaiki query yang ditolak validator atau gagal
     * dieksekusi. Return null kalau AI menjawab NO_QUERY (menyerah).
     */
    protected function mintaPerbaikanQuery(
        string $providerKey,
        string $sqlSystemPrompt,
        string $userMessage,
        string $sql,
        string $alasan
    ): ?string {
        $fixPrompt = <<<PROMPT
            Query T-SQL berikut tidak lolos pemeriksaan keamanan atau gagal dieksekusi:

            ```sql
            {$sql}
            ```

            Alasan:
            {$alasan}

            Perbaiki query-nya dengan HANYA memakai tabel & kolom yang persis ada di daftar
            skema dan relasi foreign key yang sudah diberikan sebelumnya. Query WAJIB berupa
            satu statement yang diawali SELECT (atau WITH ... SELECT kalau perlu CTE), tanpa
            penjelasan apa pun. Balas HANYA dengan query SQL yang sudah diperbaiki di dalam
            blok kode ```sql ... ```, atau balas persis NO_QUERY kalau memang tidak bisa
            diperbaiki.
            PROMPT;

        $respons = $this->callProvider($providerKey, $sqlSystemPrompt, [
            ['role' => 'user', 'content' => $userMessage],
            ['role' => 'assistant', 'content' => "```sql\n{$sql}\n```"],
            ['role' => 'user', 'content' => $fixPrompt],
        ]);

        return trim($respons) === 'NO_QUERY' ? null : $respons;
    }

    /**
     * Ekstrak SQL dari balasan AI (yang diharapkan di dalam blok kode
     * ```sql ... ```). Kalau tidak ada blok kode, kembalikan mentahnya.
     */
    protected function extractSql(string $response): string
    {
        $blok = @preg_match('/```sql\s*(.+?)\s*```/is', $response, $m);
        if ($blok === 1) {
            return trim($m[1]);
        }
        if ($blok === false) {
            // Mesin regex gagal: buang pagar kode secara manual.
            return trim(str_replace('```sql', '', str_replace('```', '', $response)));
        }

        $blok = @preg_match('/```\s*(.+?)\s*```/is', $response, $m);
        if ($blok === 1) {
            return trim($m[1]);
        }
        if ($blok === false) {
            return trim(str_replace('```', '', $response));
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
        $sql = $this->gantiPola('/--.*$/m', '', $sql);
        $sql = $this->gantiPola('/\/\*.*?\*\//s', '', $sql);

        // Normalisasi whitespace
        $sql = $this->gantiPola('/\s+/', ' ', trim($sql));
        $sql = rtrim($sql, "; \t\n\r");

        $maxRows = config('chatbot.max_rag_rows', 200);

        if ($this->readonlyDriver() === 'sqlite') {
            // sqlite tidak mengenal T-SQL `TOP n`. Kalau AI tetap mengirim
            // `SELECT TOP n ...` (prompt lama / kebiasaan model), ubah jadi
            // `LIMIT n` di akhir query supaya tetap bisa dijalankan —
            // bukan gagal dengan "near TOP: syntax error".
            [$topN, $tanpaTop] = $this->ambilTop($sql);
            if ($topN !== null) {
                $sql = $tanpaTop;

                if (! $this->sudahAdaLimit($sql)) {
                    $sql .= ' LIMIT ' . $topN;
                }
            }

            if (! $this->sudahAdaLimit($sql)) {
                $sql .= ' LIMIT ' . (int) $maxRows;
            }

            return $sql;
        }

        // Pastikan ada TOP (batas baris di sisi DB) — ini defense in depth
        // selain pemotongan di PHP, kalau AI lupa pakai TOP.
        [$topN, ] = $this->ambilTop($sql);
        if ($topN === null) {
            // TOP wajib setelah DISTINCT, jadi sisipkan di posisi yang benar.
            $sql = $this->gantiPola('/^\s*select\s+(distinct\s+)?/i', 'SELECT $1TOP ' . (int) $maxRows . ' ', $sql, 1);
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

        // Dicek tanpa regex: di sebagian lingkungan pola \b...\b gagal
        // dikompilasi PCRE dan dulu membuat seluruh balasan AI jatuh ke
        // pesan "kendala menghubungi AI provider" padahal jawabannya ada.
        foreach (['pdf', 'image', 'html_table', 'excel'] as $format) {
            if (str_contains($lower, $format)) {
                return $format;
            }
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

    /**
     * URL unduhan file export. Diutamakan lewat route aplikasi (selalu bisa
     * diakses karena dilayani controller); kalau route belum terdaftar,
     * jatuh ke URL disk publik seperti sebelumnya.
     */
    protected function urlExport(string $disk, string $path, string $filename): string
    {
        try {
            // URL RELATIF (tanpa host). Kalau memakai URL absolut dari APP_URL,
            // gambar bisa gagal dimuat ketika user membuka aplikasi dari host
            // lain (localhost vs 192.168.x.x) karena cookie sesi tidak ikut
            // terkirim -> permintaan gambar dialihkan ke /login dan grafik
            // tampak "tidak ada" di bubble.
            return route('chatbot.exports.download', ['filename' => $filename], false);
        } catch (\Throwable $e) {
            return Storage::disk($disk)->url($path);
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

        // PhpSpreadsheet tidak punya saveToString(); isi file diambil lewat
        // output buffer (dulu memanggil metode yang tidak ada sehingga export
        // gagal tepat setelah query berhasil).
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $isiExcel = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        Storage::disk($disk)->put($path, $isiExcel);

        return [
            'type' => 'excel',
            'filename' => $filename,
            'url' => $this->urlExport($disk, $path, $filename),
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
            'url' => $this->urlExport($disk, $path, $filename),
            'row_count' => count($rows),
        ];
    }

    protected function exportImage(array $rows, string $disk, string $timestamp, string $slug): array
    {
        $headers = array_keys((array) $rows[0]);

        // Gambar tabel langsung lewat GD. Dulu metode ini hanya placeholder
        // (butuh wkhtmltoimage) sehingga user yang minta format gambar selalu
        // dapat file .html, dan view 'chatbot.exports.image' bahkan tidak ada
        // sehingga permintaannya gagal total.
        $png = $this->renderTabelPng($headers, $rows);

        if ($png === null) {
            // GD/font tidak tersedia: kirim tabel HTML supaya user tetap
            // menerima datanya, bukan pesan error.
            return $this->exportHtmlTable($rows, $disk, $timestamp, $slug);
        }

        $filename = "chatbot_export_{$timestamp}_{$slug}.png";
        $path = "chatbot_exports/{$filename}";

        Storage::disk($disk)->put($path, $png);

        return [
            'type' => 'image',
            'filename' => $filename,
            'url' => $this->urlExport($disk, $path, $filename),
            'row_count' => count($rows),
        ];
    }

    /**
     * Render tabel hasil query jadi PNG memakai GD (tidak butuh wkhtmltoimage
     * / headless chrome). Mengembalikan null kalau GD atau font TTF tidak ada
     * supaya pemanggil bisa fallback ke tabel HTML.
     */
    protected function renderTabelPng(array $headers, array $rows): ?string
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagettftext')) {
            return null;
        }

        $font = $this->fontTabel();
        if ($font === null) {
            return null;
        }

        $pad = 12;
        $fontSize = 12;
        $lineHeight = 24;
        $maksBaris = 60;   // batasi tinggi gambar
        $maksKolom = 360;  // batasi lebar kolom
        $maksTeks = 48;    // batasi panjang teks per sel

        $totalBaris = count($rows);
        $rows = array_slice($rows, 0, $maksBaris);
        $headers = array_values($headers);

        $isi = [];
        foreach ($rows as $row) {
            $data = (array) $row;
            $baris = [];
            foreach ($headers as $header) {
                $teks = $this->teksTabel($data[$header] ?? '');
                if (mb_strlen($teks) > $maksTeks) {
                    $teks = mb_substr($teks, 0, $maksTeks - 1) . '…';
                }
                $baris[] = $teks;
            }
            $isi[] = $baris;
        }

        // Lebar kolom mengikuti teks terpanjang di kolom itu.
        $lebar = [];
        foreach ($headers as $i => $header) {
            $w = $this->lebarTeks($font, $fontSize, (string) $header);
            foreach ($isi as $baris) {
                $w = max($w, $this->lebarTeks($font, $fontSize, $baris[$i] ?? ''));
            }
            $lebar[$i] = min($w + $pad * 2, $maksKolom);
        }

        $sisa = $totalBaris - count($rows);
        $jumlahBarisGambar = count($rows) + 1 + ($sisa > 0 ? 1 : 0);
        $lebarGambar = max(array_sum($lebar), 120);
        $tinggiGambar = $lineHeight * $jumlahBarisGambar + $pad;

        $img = imagecreatetruecolor($lebarGambar, $tinggiGambar);
        if ($img === false) {
            return null;
        }

        $putih = imagecolorallocate($img, 255, 255, 255);
        $biru = imagecolorallocate($img, 29, 63, 134);
        $putihTeks = imagecolorallocate($img, 255, 255, 255);
        $gelapTeks = imagecolorallocate($img, 16, 24, 40);
        $abuTeks = imagecolorallocate($img, 71, 84, 103);
        $garis = imagecolorallocate($img, 226, 232, 240);
        $selang = imagecolorallocate($img, 248, 250, 252);

        imagefilledrectangle($img, 0, 0, $lebarGambar, $tinggiGambar, $putih);
        imageantialias($img, true);

        // Baris header
        imagefilledrectangle($img, 0, 0, $lebarGambar, $lineHeight, $biru);

        $x = 0;
        foreach ($headers as $i => $header) {
            imagettftext($img, $fontSize, 0, $x + $pad, $lineHeight - 8, $putihTeks, $font, (string) $header);
            $x += $lebar[$i];
        }

        // Baris data
        $y = $lineHeight;
        foreach ($isi as $n => $baris) {
            if ($n % 2 === 1) {
                imagefilledrectangle($img, 0, $y, $lebarGambar, $y + $lineHeight, $selang);
            }

            $x = 0;
            foreach ($baris as $i => $teks) {
                if ($teks !== '') {
                    imagettftext($img, $fontSize, 0, $x + $pad, $y + $lineHeight - 8, $gelapTeks, $font, $teks);
                }
                $x += $lebar[$i];
            }

            imageline($img, 0, $y + $lineHeight, $lebarGambar, $y + $lineHeight, $garis);
            $y += $lineHeight;
        }

        if ($sisa > 0) {
            imagettftext($img, $fontSize, 0, $pad, $y + $lineHeight - 8, $abuTeks, $font, "… dan {$sisa} baris lainnya");
        }

        // Garis vertikal antar kolom
        $x = 0;
        foreach ($lebar as $w) {
            $x += $w;
            imageline($img, $x, 0, $x, $tinggiGambar, $garis);
        }

        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return $png !== '' ? $png : null;
    }

    /**
     * Lokasi font TTF untuk render tabel gambar (null kalau tidak ditemukan,
     * mis. di server tanpa paket fonts-dejavu).
     */
    protected function fontTabel(): ?string
    {
        $kandidat = [
            resource_path('fonts/DejaVuSans.ttf'),
            base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
            'C:\Windows\Fonts\arial.ttf',
            'C:\Windows\Fonts\segoeui.ttf',
            '/Library/Fonts/Arial.ttf',
        ];

        foreach ($kandidat as $berkas) {
            if (is_file($berkas)) {
                return $berkas;
            }
        }

        return null;
    }

    /** Lebar teks (piksel) untuk font & ukuran tertentu. */
    protected function lebarTeks(string $font, int $size, string $teks): int
    {
        if ($teks === '') {
            return 0;
        }

        $kotak = @imagettfbbox($size, 0, $font, $teks);
        if (! is_array($kotak)) {
            return 0;
        }

        return (int) abs($kotak[2] - $kotak[0]);
    }

    /** Teks satu sel untuk render gambar. */
    protected function teksTabel($nilai): string
    {
        if ($nilai === null) {
            return '-';
        }

        if (is_bool($nilai)) {
            return $nilai ? 'true' : 'false';
        }

        if (is_scalar($nilai)) {
            $teks = $this->gantiPola('/\s+/', ' ', trim((string) $nilai));
        } else {
            $teks = (string) json_encode($nilai);
        }

        return $teks === '' ? '-' : $teks;
    }

    /* ---------------------------------------------------------------------
     | Chart / grafik jawaban
     |---------------------------------------------------------------------
     | User sering minta data "dalam bentuk chart" dan model bahasa selalu
     | menolak ("belum mendukung visualisasi"). Karena itu grafik dibuat di
     | sisi server dengan GD (tanpa wkhtmltoimage/Chrome/Chart.js), lalu
     | dikirim sebagai gambar PNG yang tampil di dalam bubble jawaban.
     */

    /**
     * Apakah user meminta tampilan grafik/chart?
     */
    protected function mintaChart(string $userMessage): bool
    {
        $teks = mb_strtolower($userMessage);

        $kata = [
            'chart', 'charts', 'grafik', 'grafiknya', 'diagram', 'visualisasi',
            'visualize', 'visualise', 'plot', 'pie', 'donut', 'batang',
            'lingkaran', 'bar chart', 'trend', 'tren',
        ];

        foreach ($kata as $k) {
            if (str_contains($teks, $k)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tentukan kolom label (kategori) dan kolom nilai (angka) yang layak
     * diplot dari hasil query.
     *
     * @return array{label: string, nilai: string, satuan: string}|null
     */
    protected function pilihKolomChart(array $rows, ?string $userMessage = null): ?array
    {
        $sampel = array_slice($rows, 0, 30);

        if (empty($sampel)) {
            return null;
        }

        $headers = array_keys((array) $sampel[0]);
        $kandidatNilai = [];
        $kandidatLabel = [];

        // Kolom identitas, temporal/kalender, dan non-metrik tidak layak
        // dijadikan sumbu nilai grafik batang.
        $bukanNilai = [
            'id', 'npk', 'nik', 'nopeg', 'no', 'nomor', 'urut', 'bulan_ke',
            'tahun', 'year', 'yr', 'thn',
            'bulan', 'month', 'bln',
            'hari', 'day', 'tgl', 'tanggal', 'date', 'scan_date', 'overtime_date',
            'awal_minggu', 'akhir_minggu', 'minggu_ke', 'week',
            'is_holiday', 'status', 'keterangan', 'remark',
            // Penanda teknis kontrak/versi: bukan metrik. `contract_ke` pernah
            // dipilih sebagai sumbu nilai sehingga semua batang bernilai 10
            // ("kontrak ke-10") padahal user minta gaji.
            'contract_ke', 'contract', 'kontrak', 'kontrak_ke', 'contract_no',
            'seq', 'sequence', 'index', 'idx', 'row', 'level',
        ];

        // Petunjuk metrik dari pertanyaan: kolom yang menjawab pertanyaan harus
        // menang atas kolom angka lain yang kebetulan muncul lebih dulu di
        // hasil query (mis. contract_ke sebelum salary).
        $petunjukMetrik = [
            ['kata' => ['payroll', 'gaji', 'salary', 'upah', 'thp', 'take home', 'penghasilan'],
                'kolom' => ['total_payroll', 'payroll', 'salary', 'gaji', 'upah', 'thp', 'take_home']],
            ['kata' => ['karyawan', 'orang', 'pegawai', 'headcount', 'jumlah karyawan'],
                'kolom' => ['employee_count', 'jumlah_karyawan', 'total_karyawan', 'karyawan_count', 'headcount']],
            ['kata' => ['tunjangan', 'allowance'], 'kolom' => ['allowance', 'tunjangan']],
            ['kata' => ['pph', 'pajak', 'potongan'], 'kolom' => ['pph', 'potongan', 'deduction']],
            ['kata' => ['lembur', 'overtime'], 'kolom' => ['jam_lembur', 'lembur', 'overtime', 'total_lembur']],
            ['kata' => ['insentif', 'bonus'], 'kolom' => ['insentif', 'bonus']],
        ];

        // Hitung variasi nilai unik per kolom di sampel untuk mendeteksi
        // kolom angka konstan (misalnya TAHUN=2026 di seluruh baris).
        $nilaiUnikPerKolom = [];
        foreach ($headers as $h) {
            $nilaiUnikPerKolom[$h] = [];
        }

        foreach ($sampel as $row) {
            foreach ($headers as $h) {
                $val = ((array) $row)[$h] ?? null;
                if ($val !== null && $val !== '') {
                    $nilaiUnikPerKolom[$h][(string) $val] = true;
                }
            }
        }

        $pesanLower = mb_strtolower((string) $userMessage);

        // Kata kunci metrik yang disebut user (gaji/tunjangan/lembur/...).
        $prioritasNilai = [];
        foreach ($petunjukMetrik as $p) {
            foreach ($p['kata'] as $kata) {
                if ($pesanLower !== '' && str_contains($pesanLower, $kata)) {
                    $prioritasNilai = array_merge($prioritasNilai, $p['kolom']);
                    break;
                }
            }
        }

        foreach ($headers as $header) {
            $nama = mb_strtolower((string) $header);
            $angka = 0;
            $teks = 0;
            $unik = [];

            foreach ($sampel as $row) {
                $nilai = ((array) $row)[$header] ?? null;

                if ($nilai === null || $nilai === '') {
                    continue;
                }

                if (is_numeric($nilai)) {
                    $angka++;
                } else {
                    $teks++;
                    $unik[(string) $nilai] = true;
                }
            }

            $total = $angka + $teks;
            if ($total === 0) {
                continue;
            }

            if ($angka > $teks) {
                // Lewati kolom non-metrik (identitas/kalender)
                if (in_array($nama, $bukanNilai, true)) {
                    continue;
                }

                // Lewati pola penanda teknis: *_ke, *_id, row, seq, level, dst.
                if ($this->kolomPenandaTeknis($nama)) {
                    continue;
                }

                // Lewati kolom angka yang nilainya konstan / seragam di semua
                // baris sampel bila ada baris >= 2 (misal TAHUN=2026, IS_ACTIVE=1).
                // Kolom konstan tidak informatif untuk dijadikan panjang grafik batang.
                $jumlahVariasi = count($nilaiUnikPerKolom[$header] ?? []);
                if (count($sampel) >= 2 && $jumlahVariasi <= 1) {
                    continue;
                }

                $skor = $angka;

                // Prioritas metrik lembur, jam kerja, dan durasi
                if (str_contains($nama, 'lembur') || str_contains($nama, 'overtime')
                    || str_contains($nama, 'jam') || str_contains($nama, 'hour')
                    || str_contains($nama, 'durasi') || str_contains($nama, 'duration')) {
                    $skor += 80;
                } elseif (str_contains($nama, 'persen') || str_contains($nama, 'percent')
                    || str_contains($nama, 'rate') || str_contains($nama, '%')
                    || str_contains($nama, 'turnover') || str_contains($nama, 'ratio')) {
                    $skor += 60;
                } elseif (str_contains($nama, 'total') || str_contains($nama, 'jumlah')
                    || str_contains($nama, 'jml') || str_contains($nama, 'count')
                    || str_contains($nama, 'qty') || str_contains($nama, 'banyak')) {
                    $skor += 40;
                } elseif (str_contains($nama, 'nilai') || str_contains($nama, 'value')
                    || str_contains($nama, 'amount') || str_contains($nama, 'nominal')) {
                    $skor += 20;
                }

                // Bonus jika nama kolom angka disebut dalam pertanyaan user
                if ($pesanLower !== '' && str_contains($pesanLower, $nama)) {
                    $skor += 35;
                }

                // Bonus besar bila kolom ini metrik yang diminta (gaji →
                // salary). Tanpa ini, kolom seperti contract_ke yang muncul
                // lebih dulu menang seri dan grafik menampilkan angka salah.
                foreach ($prioritasNilai as $kataKolom) {
                    if (str_contains($nama, $kataKolom) || str_contains($kataKolom, $nama)) {
                        $skor += 150;
                        break;
                    }
                }

                $kandidatNilai[$header] = $skor;
                continue;
            }

            // Kolom teks: makin beragam isinya, makin cocok jadi label.
            $skor = count($unik) + $teks;

            // Kolom teks yang nilainya konstan / seragam (hanya 1 nilai unik)
            // dilarang keras menjadi label grafik jika jumlah baris >= 2
            // (misal status="Payroll calculation completed" di semua baris).
            // Memilih kolom konstan akan membuat seluruh baris teragregasi
            // menjadi 1 batang raksasa yang statis dan tidak informatif.
            if (count($sampel) >= 2 && count($unik) <= 1) {
                continue;
            }

            if (str_contains($nama, 'periode') || str_contains($nama, 'period')
                || str_contains($nama, 'bulan') || str_contains($nama, 'month')
                || str_contains($nama, 'tahun') || str_contains($nama, 'year')
                || str_contains($nama, 'tgl') || str_contains($nama, 'tanggal') || str_contains($nama, 'date')) {
                $skor += 80;
            } elseif (str_contains($nama, 'dept') || str_contains($nama, 'departemen')
                || str_contains($nama, 'bagian') || str_contains($nama, 'section')
                || str_contains($nama, 'divisi') || str_contains($nama, 'line')
                || str_contains($nama, 'nama') || str_contains($nama, 'karyawan')
                || str_contains($nama, 'jabatan') || str_contains($nama, 'area')) {
                $skor += 60;
            } elseif (str_contains($nama, 'kategori') || str_contains($nama, 'status')) {
                // Status/kategori hanya mendapat bonus kecil bila nilainya bervariasi (> 1 unik)
                $skor += 20;
            }

            $kandidatLabel[$header] = $skor;
        }

        if (empty($kandidatNilai)) {
            return null;
        }

        arsort($kandidatNilai);
        $kolomNilai = (string) array_key_first($kandidatNilai);

        if (empty($kandidatLabel)) {
            return null;
        }

        arsort($kandidatLabel);
        $kolomLabel = (string) array_key_first($kandidatLabel);

        $namaNilai = mb_strtolower($kolomNilai);
        $satuan = '';

        if (str_contains($namaNilai, 'persen') || str_contains($namaNilai, 'percent')
            || str_contains($namaNilai, 'rate') || str_contains($namaNilai, '%')
            || str_contains($namaNilai, 'turnover') || str_contains($namaNilai, 'ratio')) {
            $satuan = '%';
        } elseif (str_contains($namaNilai, 'jam') || str_contains($namaNilai, 'hour')) {
            $satuan = 'jam';
        } elseif (preg_match('/salary|gaji|upah|allowance|tunjangan|pph|potongan|insentif|bonus|nominal|amount|pay|thp/', $namaNilai)) {
            // Metrik uang: tampilkan dengan pemisah ribuan dan awalan Rp.
            $satuan = 'Rp';
        }

        return ['label' => $kolomLabel, 'nilai' => $kolomNilai, 'satuan' => $satuan, 'waktu' => $this->kolomAdalahWaktu($kolomLabel)];
    }

    /**
     * Apakah kolom label bersifat deret waktu / kronologis?
     * Dipakai untuk menentukan urutan grafik: data waktu harus diurutkan
     * kronologis (naik), bukan di-sort berdasarkan nilai metrik.
     */
    protected function kolomAdalahWaktu(string $namaKolom): bool
    {
        $nama = mb_strtolower(trim($namaKolom));

        return (bool) preg_match(
            '/periode|period|bulan|month|tahun|year|tgl|tanggal|date|minggu|week|hari|day|kuartal|quarter|semester/i',
            $nama
        );
    }

    /**
     * Urutkan dua label waktu secara kronologis (naik).
     * Mengenali format umum: "Juni 2026", "2026-06", "2026-06-01", "06/2026", "Q1 2026".
     */
    protected function urutKronologis(string $a, string $b): int
    {
        return $this->kunciWaktu($a) <=> $this->kunciWaktu($b);
    }

    /**
     * Ubah label waktu menjadi kunci numerik yang bisa dibandingkan.
     */
    protected function kunciWaktu(string $label): float
    {
        $t = mb_strtolower(trim($label));

        $bulanId = [
            'januari' => 1, 'jan' => 1, 'februari' => 2, 'feb' => 2, 'maret' => 3, 'mar' => 3,
            'april' => 4, 'apr' => 4, 'mei' => 5, 'may' => 5, 'juni' => 6, 'jun' => 6,
            'juli' => 7, 'jul' => 7, 'agustus' => 8, 'aug' => 8,
            'september' => 9, 'sept' => 9, 'sep' => 9,
            'oktober' => 10, 'okt' => 10, 'october' => 10, 'oct' => 10,
            'november' => 11, 'nov' => 11,
            'desember' => 12, 'december' => 12, 'des' => 12, 'dec' => 12,
        ];

        // Pola "Bulan Tahun": Juni 2026 / Oct 2026
        foreach ($bulanId as $namaBulan => $nomor) {
            if (preg_match('/\b' . preg_quote($namaBulan, '/') . '\b[^\d]*(\d{4})/i', $t, $m)) {
                return ((float) $m[1]) * 100 + $nomor;
            }
        }

        // Pola YYYY-MM atau YYYY-MM-DD
        if (preg_match('/(\d{4})-(\d{1,2})(?:-(\d{1,2}))?/', $t, $m)) {
            return ((float) $m[1]) * 10000 + ((float) $m[2]) * 100 + (float) ($m[3] ?? 1);
        }

        // Pola MM/YYYY atau MM-YYYY
        if (preg_match('/(\d{1,2})\s*[\/\-]\s*(\d{4})/', $t, $m)) {
            return ((float) $m[2]) * 100 + (float) $m[1];
        }

        // Pola Q1..Q4 YYYY
        if (preg_match('/q([1-4])\s*(\d{4})/i', $t, $m)) {
            return ((float) $m[2]) * 10 + (float) $m[1];
        }

        // Pola tahun penuh saja: 2026
        if (preg_match('/(\d{4})/', $t, $m)) {
            return (float) $m[1] * 100;
        }

        // Fallback: urut alfabetis supaya tetap deterministik.
        return 0;
    }

    /**
     * Kolom penanda teknis (nomor urut, versi kontrak, id, level) tidak layak
     * jadi sumbu nilai grafik: panjang batangnya tidak mewakili besaran apa pun.
     *
     * Contoh nyata: `contract_ke` (kontrak ke-10) diplot sebagai "nilai",
     * sehingga semua batang tampil 10 padahal user bertanya gaji.
     */
    protected function kolomPenandaTeknis(string $nama): bool
    {
        return (bool) preg_match(
            '/(^|_)(ke|id|ids|no|nomor|nomer|urut|seq|sequence|row|rownum|index|idx|level|versi|version)$/',
            $nama
        );
    }

    /**
     * Perlu membuat grafik? Diminta user, atau datanya banyak (melewati
     * ambang `chatbot.chart_auto_min_rows`).
     */
    protected function perluChart(array $rows, bool $diminta, ?string $userMessage = null): bool
    {
        if (count($rows) < 2) {
            return false;
        }

        if ($this->pilihKolomChart($rows, $userMessage) === null) {
            return false;
        }

        if ($diminta) {
            return true;
        }

        return count($rows) >= max(3, (int) config('chatbot.chart_auto_min_rows', 8));
    }

    /**
     * Buat file grafik PNG dari hasil query dan kembalikan info file untuk
     * disimpan di meta pesan (ditampilkan sebagai gambar di bubble).
     *
     * @return array<string, mixed>|null
     */
    protected function siapkanChart(array $rows, string $userMessage, bool $diminta): ?array
    {
        if (! $this->perluChart($rows, $diminta, $userMessage)) {
            return null;
        }

        $kolom = $this->pilihKolomChart($rows, $userMessage);

        if ($kolom === null) {
            return null;
        }

        $judul = $this->judulChart($kolom, $userMessage);
        $png = $this->renderChartPng($rows, $kolom['label'], $kolom['nilai'], $kolom['satuan'], $judul, $kolom['waktu'] ?? false);

        if ($png === null) {
            // GD / font TTF tidak tersedia ATAU hasil agregasi hanya menyisakan
            // 1 kategori (kolom konstan) sehingga grafik statis tidak informatif:
            // jangan gagalkan jawaban, cukup lewati grafik (tabel & export tetap ada).
            Log::warning('AiChatService: grafik tidak dibuat (GD/font tidak tersedia atau hanya 1 kategori).');

            return null;
        }

        $disk = config('chatbot.export_disk', 'public');
        $timestamp = now()->format('Ymd_His');
        $slug = Str::slug(str($userMessage)->limit(30), '_');
        $filename = "chatbot_chart_{$timestamp}_{$slug}.png";
        $path = "chatbot_exports/{$filename}";

        Storage::disk($disk)->put($path, $png);

        $dipakai = array_slice($rows, 0, max(3, (int) config('chatbot.chart_max_bars', 12)));

        return [
            'type' => ($kolom['waktu'] ?? false) ? 'bar_waktu' : 'bar',
            'filename' => $filename,
            'url' => $this->urlExport($disk, $path, $filename),
            'row_count' => count($rows),
            'bar_count' => count($dipakai),
            'label_kolom' => $kolom['label'],
            'nilai_kolom' => $kolom['nilai'],
            'satuan' => $kolom['satuan'],
            'waktu' => (bool) ($kolom['waktu'] ?? false),
            'otomatis' => ! $diminta,
        ];
    }

    /**
     * Judul grafik: pakai nama metrik yang diplot, bukan pertanyaan mentah.
     */
    protected function judulChart(array $kolom, string $userMessage): string
    {
        $rapikan = function (string $nama): string {
            $teks = str_replace(['_', '.'], ' ', $nama);
            $teks = $this->gantiPola('/\s+/', ' ', $teks);

            return trim(Str::title(mb_strtolower($teks)));
        };

        $metrik = $this->labelManusiaKolom($kolom['nilai'], $rapikan);
        $kategori = $rapikan($kolom['label']);

        $judul = $metrik . ' per ' . $kategori;

        // Hindari judul kikuk untuk nama kolom teknis yang panjang.
        if (mb_strlen($judul) > 70) {
            $judul = mb_substr($judul, 0, 67) . '...';
        }

        return $judul;
    }

    /**
     * Nama kolom dalam bahasa manusia untuk judul grafik. Judul harus
     * menyebut metrik ("Gaji per Nama Karyawan"), bukan nama kolom teknis.
     */
    protected function labelManusiaKolom(string $nama, callable $rapikan): string
    {
        $peta = [
            'salary' => 'Gaji', 'gaji' => 'Gaji', 'daily_salary' => 'Gaji Harian',
            'allowance' => 'Tunjangan', 'tunjangan' => 'Tunjangan',
            'pph21' => 'PPh 21', 'pph_21' => 'PPh 21', 'pph' => 'PPh',
            'take_home' => 'Take Home Pay', 'thp' => 'Take Home Pay',
            'contract_ke' => 'Kontrak Ke',
            'jumlah' => 'Jumlah', 'total' => 'Total', 'qty' => 'Jumlah', 'count' => 'Jumlah',
            'persen' => 'Persentase', 'percent' => 'Persentase',
        ];

        $kunci = mb_strtolower(trim($nama));

        if (isset($peta[$kunci])) {
            return $peta[$kunci];
        }

        if (str_contains($kunci, '.')) {
            $ekor = substr($kunci, strrpos($kunci, '.') + 1);

            if (isset($peta[$ekor])) {
                return $peta[$ekor];
            }
        }

        return $rapikan($nama);
    }

    /**
     * Gambar grafik batang horizontal (PNG) memakai GD.
     * Mengembalikan null kalau GD atau font TTF tidak tersedia.
     *
     * @param  array<int, mixed>  $rows
     */
    protected function renderChartPng(array $rows, string $kolomLabel, string $kolomNilai, string $satuan, string $judul, bool $labelWaktu = false): ?string
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagettftext')) {
            return null;
        }

        $font = $this->fontTabel();

        if ($font === null) {
            return null;
        }

        // Kumpulkan pasangan label → nilai (hanya baris yang nilainya angka).
        // Jika ada label yang berulang (misal nama karyawan muncul di beberapa
        // tanggal/kejadian), agregasikan (jumlahkan nilai metriknya) agar
        // setiap batang mewakili 1 entitas unik dan tidak terduplikasi.
        $agregat = [];
        foreach ($rows as $row) {
            $baris = (array) $row;
            $nilaiMentah = $baris[$kolomNilai] ?? null;

            if (! is_numeric($nilaiMentah)) {
                continue;
            }

            $label = $this->teksTabel($baris[$kolomLabel] ?? '-');
            if (mb_strlen($label) > 30) {
                $label = mb_substr($label, 0, 27) . '...';
            }

            $nilai = (float) $nilaiMentah;

            if (! isset($agregat[$label])) {
                $agregat[$label] = [
                    'label' => $label,
                    'nilai' => $nilai,
                    'count' => 1,
                ];
            } else {
                // Untuk persentase / rasio gunakan rata-rata, untuk metrik lain (jam, jumlah) gunakan SUM
                if ($satuan === '%') {
                    $agregat[$label]['nilai'] += $nilai;
                    $agregat[$label]['count']++;
                } else {
                    $agregat[$label]['nilai'] += $nilai;
                    $agregat[$label]['count']++;
                }
            }
        }

        if (empty($agregat)) {
            return null;
        }

        $data = [];
        foreach ($agregat as $item) {
            if ($satuan === '%' && $item['count'] > 1) {
                $item['nilai'] = round($item['nilai'] / $item['count'], 2);
            }
            $data[] = ['label' => $item['label'], 'nilai' => $item['nilai']];
        }

        // R34: grafik batang WAJIB punya minimal 2 kategori pembanding yang
        // nilainya berbeda. Kalau hasil agregasi hanya menyisakan 1 kategori
        // (kolom label konstan, mis. status yang sama di semua baris),
        // JANGAN render grafik statis — kembalikan null supaya jawaban cukup
        // tabel + ringkasan teks.
        if (count($data) < 2) {
            return null;
        }

        // R34: deteksi apakah label bersifat deret waktu / kronologis.
        // Kalau kolom label mengandung periode/bulan/tanggal/tahun, urutkan
        // KRONOLOGIS (naik), jangan di-sort descending berdasarkan nilai.
        $labelWaktu = $labelWaktu || $this->kolomAdalahWaktu($kolomLabel);

        if ($labelWaktu) {
            usort($data, fn ($a, $b) => $this->urutKronologis($a['label'], $b['label']));
        } else {
            usort($data, fn ($a, $b) => $b['nilai'] <=> $a['nilai']);
        }

        $maksBar = max(3, (int) config('chatbot.chart_max_bars', 12));
        $data = array_slice($data, 0, $maksBar);

        // Bila data deret waktu dan semua kategori nilainya IDENTIK (mis. tren
        // datar), tetap tampilkan — tapi urutannya tetap kronologis, bukan
        // acak dari sort nilai.

        $nilaiMaks = 0.0;
        foreach ($data as $d) {
            $nilaiMaks = max($nilaiMaks, $d['nilai']);
        }

        if ($nilaiMaks <= 0) {
            $nilaiMaks = 1.0;
        }

        // Geometri
        $padX = 26;
        $padAtas = 22;
        $judulTinggi = 46;
        $lebarLabel = 250;
        $lebarBar = 470;
        $ruangNilai = 78;
        $tinggiBaris = 34;
        $celahBaris = 8;
        $footer = 34;

        $jumlahBar = count($data);
        $tinggi = $padAtas + $judulTinggi + ($jumlahBar * ($tinggiBaris + $celahBaris)) + $footer;
        $lebar = $padX * 2 + $lebarLabel + 14 + $lebarBar + $ruangNilai;

        $gambar = imagecreatetruecolor($lebar, $tinggi);
        if ($gambar === false) {
            return null;
        }

        imagealphablending($gambar, true);
        imagesavealpha($gambar, true);

        $warna = [
            'bg' => imagecolorallocate($gambar, 255, 255, 255),
            'judul' => imagecolorallocate($gambar, 29, 63, 134),
            'subjudul' => imagecolorallocate($gambar, 100, 116, 139),
            'label' => imagecolorallocate($gambar, 51, 65, 85),
            'bar' => imagecolorallocate($gambar, 39, 75, 143),
            'trek' => imagecolorallocate($gambar, 241, 245, 249),
            'nilai' => imagecolorallocate($gambar, 15, 23, 42),
            'garis' => imagecolorallocate($gambar, 226, 232, 240),
        ];

        imagefilledrectangle($gambar, 0, 0, $lebar, $tinggi, $warna['bg']);

        // Judul + subjudul
        imagettftext($gambar, 14, 0, $padX, $padAtas + 14, $warna['judul'], $font, $judul);

        $subjudul = 'Sumber: hasil query Chatbot HRIS'
            . ' | ' . $jumlahBar . ($labelWaktu ? ' periode (kronologis)' : ' kategori teratas')
            . ' | dibuat ' . now()->format('d/m/Y H:i');

        imagettftext($gambar, 9, 0, $padX, $padAtas + 32, $warna['subjudul'], $font, $subjudul);

        $y = $padAtas + $judulTinggi;

        // Garis bantu vertikal (25/50/75/100% dari nilai tertinggi) digambar
        // lebih dulu supaya berada di belakang batang. Tanpa ini pembaca
        // hanya bisa menebak perbandingan panjang batang.
        $yGarisBantu = $y;
        $yGarisBantuBawah = $y + ($jumlahBar * ($tinggiBaris + $celahBaris)) - $celahBaris;
        $xBarAwal = $padX + $lebarLabel + 14;

        for ($g = 1; $g <= 4; $g++) {
            $xGaris = $xBarAwal + (int) round(($g / 4) * $lebarBar);
            imageline($gambar, $xGaris, $yGarisBantu - 4, $xGaris, $yGarisBantuBawah, $warna['garis']);
        }

        foreach ($data as $d) {
            $panjang = (int) round(($d['nilai'] / $nilaiMaks) * $lebarBar);
            $panjang = max(3, $panjang);

            // Label kategori (rata kanan, dekat bar)
            $lebarTeksLabel = $this->lebarTeks($font, 11, $d['label']);
            $xLabel = $padX + $lebarLabel - min($lebarLabel, $lebarTeksLabel);
            $yLabel = $y + (int) (($tinggiBaris + 11) / 2) - 2;
            imagettftext($gambar, 11, 0, $xLabel, $yLabel, $warna['label'], $font, $d['label']);

            $xBar = $xBarAwal;

            // Jalur bar (track) supaya perbandingan panjang terbaca
            imagefilledrectangle($gambar, $xBar, $y, $xBar + $lebarBar, $y + $tinggiBaris, $warna['trek']);

            // Semua batang memakai warna yang sama: perbedaan warna per
            // peringkat dulu disalahartikan sebagai kategori berbeda.
            imagefilledrectangle($gambar, $xBar, $y, $xBar + $panjang, $y + $tinggiBaris, $warna['bar']);

            // Nilai di ujung bar. Persentase HARUS dua desimal (8,19%),
            // bukan dibulatkan jadi 8% seperti sebelumnya.
            if ($satuan === '%') {
                $teksNilai = number_format($d['nilai'], 2, ',', '.') . '%';
            } elseif ($satuan === 'Rp') {
                $teksNilai = 'Rp ' . number_format($d['nilai'], 0, ',', '.');
            } elseif ($d['nilai'] != floor($d['nilai'])) {
                $teksNilai = number_format($d['nilai'], 2, ',', '.');
            } else {
                $teksNilai = number_format($d['nilai'], 0, ',', '.');
            }

            imagettftext(
                $gambar,
                10,
                0,
                $xBar + $panjang + 8,
                $y + (int) (($tinggiBaris + 10) / 2),
                $warna['nilai'],
                $font,
                $teksNilai
            );

            $y += $tinggiBaris + $celahBaris;
        }

        // Garis penutup + catatan kecil
        $yGaris = $tinggi - $footer;
        imageline($gambar, $padX, $yGaris, $lebar - $padX, $yGaris, $warna['garis']);

        imagettftext(
            $gambar,
            8,
            0,
            $padX,
            $yGaris + 18,
            $warna['subjudul'],
            $font,
            'Grafik dibuat otomatis oleh AI Assistant Chutex HRIS. Angka mengikuti hasil query, bukan perkiraan.'
        );

        ob_start();
        imagepng($gambar);
        $png = (string) ob_get_clean();
        imagedestroy($gambar);

        return $png === '' ? null : $png;
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
            'url' => $this->urlExport($disk, $path, $filename),
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
            // Matikan thinking/reasoning internal model (kalau didukung oleh
            // gateway). Tanpa ini, seluruh proses berpikir model ikut terbaca
            // sebagai jawaban dan budget token habis sebelum jawaban jadi.
            'thinking' => ['type' => 'disabled'],
            // Minta jawaban utuh (bukan potongan SSE) — dulu field ini tidak
            // dikirim, dan sebagian model di balik gateway membalas streaming.
            'stream' => false,
        ];

        if (! config('chatbot.disable_thinking', true)) {
            unset($payload['thinking']);
        }

        $timeout = (int) config('chatbot.request_timeout', 120);

        $response = null;

        // Retry sekali saat timeout: server AI (bottleneck bersama) kadang
        // lambat pada permintaan pertama, dan timeout 300 detik lalu gagal
        // membuat karyawan menunggu 5 menit demi pesan error. Percobaan
        // kedua memakai budget token lebih kecil supaya lebih cepat selesai.
        $percobaan = config('chatbot.retry_on_timeout', true) ? 2 : 1;

        for ($i = 0; $i < $percobaan; $i++) {
            $body = $payload;

            if ($i > 0) {
                $body['max_tokens'] = min((int) ($payload['max_tokens'] ?? 1024), 1024);
                $body['max_completion_tokens'] = min((int) ($payload['max_completion_tokens'] ?? 1024), 1024);
            }

            try {
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . ($cfg['api_key'] ?? ''),
                    ])
                    ->post($cfg['base_url'], $body);
                break;
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                \Illuminate\Support\Facades\Log::warning(
                    'Chatbot: timeout/connection error ke provider (percobaan ' . ($i + 1) . '/' . $percobaan . '): ' . $e->getMessage()
                );
            }
        }

        if (! $response) {
            throw new RuntimeException(
                'Provider AI (model ' . ($cfg['model'] ?? '-') . ') tidak merespons dalam ' . $timeout
                . ' detik. Server AI mungkin sedang sibuk — coba lagi sebentar lagi atau perkecil beban permintaan.'
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException("Provider {$cfg['base_url']} error: " . $response->body());
        }

        $data = $response->json();

        // 9 Router (dan gateway OpenAI-compatible lain) kadang membalas dengan
        // SSE streaming (`text/event-stream`, potongan `delta`) walau request
        // TIDAK meminta streaming — tergantung model yang dipilih di baliknya.
        // Dulu body ini langsung di-json() -> null -> jawaban dianggap kosong,
        // sehingga chatbot cuma membalas "Maaf, jawaban AI kosong." Padahal
        // isinya lengkap, cuma terpecah per potongan. Sekarang potongannya
        // disatukan kembali.
        if (! is_array($data) && $this->responsBerupaStream($response)) {
            $data = ['choices' => [['message' => ['content' => $this->bacaStreamProvider((string) $response->body())]]]];
        }

        if (! is_array($data)) {
            // Body kosong / bukan JSON (mis. halaman HTML dari proxy). Log isinya
            // supaya penyebabnya kelihatan, lalu normalize: resolveProviderMessage
            // tidak boleh pernah menerima null — itu yang dulu memunculkan
            // "Argument #1 ($data) must be of type array, null given".
            Log::warning('Chatbot: respons provider bukan JSON.', [
                'provider' => $cfg['base_url'] ?? ($cfg['name'] ?? $providerKey ?? null),
                'body' => Str::limit((string) $response->body(), 500),
            ]);
            $data = [];
        }

        // Body JSON valid tapi berisi objek error (kuota habis / model tidak
        // dikenal / dsb) tidak punya 'choices' — dulu jatuh ke pesan "jawaban
        // kosong" yang membingungkan. Sekarang errornya disebut apa adanya.
        if (is_array($data) && ! empty($data['error'])) {
            $pesanError = is_array($data['error'])
                ? (string) ($data['error']['message'] ?? json_encode($data['error']))
                : (string) $data['error'];

            throw new RuntimeException('Provider AI menolak permintaan: ' . Str::limit($pesanError, 300));
        }

        // Jawaban terpotong: provider berhenti karena kehabisan budget token
        // (finish_reason = "length"). Dulu teks yang terpotong di tengah
        // kalimat ini tetap dikirim ke user, dan pada model reasoning isinya
        // justru potongan proses berpikir. Sekarang diulang SEKALI dengan
        // budget token lebih besar supaya jawaban akhirnya utuh.
        if (is_array($data) && $this->jawabanTerpotong($data)) {
            Log::warning('Chatbot: jawaban provider terpotong (finish_reason=length), mencoba ulang dengan budget token lebih besar.', [
                'provider' => $cfg['base_url'] ?? ($cfg['name'] ?? $providerKey ?? null),
                'model' => $cfg['model'] ?? null,
            ]);

            $bodyUlang = $payload;
            $budgetUlang = max(
                (int) ($payload['max_completion_tokens'] ?? 0),
                (int) config('chatbot.max_output_tokens_retry', 16384)
            );
            $bodyUlang['max_tokens'] = $budgetUlang;
            $bodyUlang['max_completion_tokens'] = $budgetUlang;
            unset($bodyUlang['thinking']);

            if (config('chatbot.disable_thinking', true)) {
                $bodyUlang['thinking'] = ['type' => 'disabled'];
            }

            try {
                $ulang = Http::timeout($timeout)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . ($cfg['api_key'] ?? ''),
                    ])
                    ->post($cfg['base_url'], $bodyUlang);

                if ($ulang->successful()) {
                    $dataUlang = $ulang->json();

                    if (is_array($dataUlang) && $this->resolveProviderMessage($dataUlang) !== '') {
                        $data = $dataUlang;
                        $response = $ulang;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Chatbot: percobaan ulang setelah jawaban terpotong gagal: ' . $e->getMessage());
            }
        }

        $answer = $this->resolveProviderMessage($data);

        // Guard bubble kosong: model reasoning (mis. Chutex 65536 tokens) bisa
        // menghabiskan budget untuk "thinking" dan mengembalikan content kosong
        // supaya DB menerima '' -> bubble assistant kosong di UI (dulu sempat berhasil
        // muncul, kini wajib jatuh ke return yang tidak kosong).
        if (trim((string) $answer) === '') {
            Log::warning('Chatbot: provider mengembalikan jawaban kosong.', [
                'provider' => $cfg['base_url'] ?? ($cfg['name'] ?? $providerKey ?? null),
                // Cuplikan proses berpikir (kalau ada) untuk diagnosa — model
                // reasoning bisa menghabiskan seluruh budget token untuk ini.
                'reasoning' => Str::limit($this->resolveProviderReasoning($data), 300),
                'body' => Str::limit((string) $response->body(), 500),
            ]);
            $answer = 'Maaf, jawaban AI kosong. Coba kirim ulang pertanyaan Anda.';
        }

        return $answer;
    }

    /**
     * Apakah respons provider berupa SSE streaming, bukan JSON utuh?
     * Dicek dari header Content-Type dan/atau awalan body `data:`.
     */
    protected function responsBerupaStream($response): bool
    {
        $tipe = strtolower((string) $response->header('Content-Type'));

        if (str_contains($tipe, 'text/event-stream')) {
            return true;
        }

        return str_starts_with(ltrim((string) $response->body()), 'data:');
    }

    /**
     * Satukan potongan SSE (`data: {...}` per baris) jadi satu teks jawaban.
     * Dipakai kalau provider membalas streaming tanpa diminta.
     *
     * Potongan "thinking" (reasoning_content) TIDAK dipakai sebagai jawaban
     * walau teks utamanya kosong — isinya proses berpikir internal model, dan
     * dulu inilah yang membuat user melihat monolog bahasa Inggris di bubble
     * chat. Bubble kosong ditangani pemanggil dengan pesan yang jelas.
     */
    protected function bacaStreamProvider(string $body): string
    {
        $teks = '';
        $penalaran = '';

        $barisStream = preg_split('/\r?\n/', $body);

        if (! is_array($barisStream)) {
            // Mesin regex gagal: pecah manual (CRLF/CR/LF).
            $barisStream = explode("\n", str_replace(["\r\n", "\r"], "\n", $body));
        }

        foreach ($barisStream as $baris) {
            $baris = trim($baris);

            if ($baris === '' || ! str_starts_with($baris, 'data:')) {
                continue;
            }

            $isi = trim(substr($baris, 5));

            if ($isi === '' || $isi === '[DONE]') {
                continue;
            }

            $chunk = json_decode($isi, true);

            if (! is_array($chunk)) {
                continue;
            }

            $delta = $chunk['choices'][0]['delta'] ?? ($chunk['choices'][0]['message'] ?? null);

            if (! is_array($delta)) {
                continue;
            }

            if (isset($delta['content'])) {
                if (is_string($delta['content'])) {
                    $teks .= $delta['content'];
                } elseif (is_array($delta['content'])) {
                    foreach ($delta['content'] as $bagian) {
                        if (is_string($bagian)) {
                            $teks .= $bagian;
                        } elseif (is_array($bagian)) {
                            $teks .= (string) ($bagian['text'] ?? ($bagian['content'] ?? ''));
                        }
                    }
                }
            }

            if (isset($delta['reasoning_content']) && is_string($delta['reasoning_content'])) {
                $penalaran .= $delta['reasoning_content'];
            }
        }

        $teks = trim($teks);

        // Jangan pernah kembalikan $penalaran jika $teks kosong.
        // Teks penalaran adalah proses berpikir internal model ("Wait, ...",
        // "Notice: ...") dan tidak boleh sampai bocor ke bubble chat user.
        return $teks;
    }

    /**
     * Ambil teks jawaban dari respons provider OpenAI-compatible dengan
     * fallback berlapis: content (string) -> content (array parts) -> text ->
     * reply -> output.
     *
     * Field reasoning_content/reasoning SENGAJA tidak diikutkan: itu proses
     * berpikir internal model, bukan jawaban untuk user.
     */
    protected function resolveProviderMessage(?array $data): string
    {
        if (! is_array($data)) {
            return '';
        }

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

        // Sisa kandidat isi jawaban. `reasoning_content` dan `reasoning`
        // SENGAJA tidak dipakai di sini: keduanya berisi proses berpikir
        // internal model, dan dulu dipakai sebagai jawaban cadangan sehingga
        // user melihat monolog bahasa Inggris ("Wait, ...", "Notice: ...")
        // alih-alih jawaban. Teks penalaran hanya dipakai untuk log.
        foreach (['text', 'reply', 'output'] as $key) {
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

        // Tidak ada isi jawaban yang layak dipakai. Teks penalaran TIDAK
        // dikembalikan di sini — pemanggil akan menampilkan pesan yang jelas.
        return '';
    }

    /**
     * Ambil teks proses berpikir (reasoning/thinking) kalau provider
     * mengirimnya terpisah. HANYA untuk log internal — jangan pernah
     * ditampilkan ke user sebagai jawaban.
     */
    protected function resolveProviderReasoning(?array $data): string
    {
        if (! is_array($data)) {
            return '';
        }

        $message = $data['choices'][0]['message'] ?? null;
        $kandidat = [];

        if (is_array($message)) {
            $kandidat[] = $message['reasoning_content'] ?? null;
            $kandidat[] = $message['reasoning'] ?? null;
        }

        $kandidat[] = $data['reasoning_content'] ?? null;
        $kandidat[] = $data['reasoning'] ?? null;

        foreach ($kandidat as $val) {
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
            if (is_array($val)) {
                $joined = implode('', array_filter(array_map(function ($part) {
                    if (is_string($part)) return $part;
                    if (is_array($part)) return $part['text'] ?? ($part['content'] ?? '');
                    return '';
                }, $val)));
                if (trim($joined) !== '') {
                    return trim($joined);
                }
            }
        }

        return '';
    }

    /**
     * Apakah provider berhenti karena kehabisan budget token?
     * (finish_reason = "length" → jawaban terpotong, bukan jawaban utuh.)
     */
    protected function jawabanTerpotong(array $data): bool
    {
        foreach ((array) ($data['choices'] ?? []) as $choice) {
            if (is_array($choice) && ($choice['finish_reason'] ?? null) === 'length') {
                return true;
            }
        }

        return false;
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