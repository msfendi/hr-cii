<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use App\Models\CanteenReport;
use App\Models\CanteenTwoReport;
use App\Http\Controllers\CanteenTransactionController;

/**
 * V40 — "Sistem tidak dapat memeriksa transaksi sebelumnya."
 *
 * Gejala di produksi (screenshot 2026-11-10):
 *   - Tantangan terverifikasi (V38 OK)
 *   - GPS "Dalam Area"
 *   - Kotak merah: "Sistem tidak dapat memeriksa transaksi sebelumnya.
 *     Silakan coba lagi beberapa saat lagi."
 *
 * Jalur kode:
 *   CanteenTransactionController::store()
 *     -> findDuplicateAnyCanteen()  (private)
 *        -> CanteenReport::query()->where('npk')->where('date')->where('window_name')
 *        -> CanteenTwoReport::query()->...
 *        -> catch Throwable -> Log::warning -> throw RuntimeException('cek-duplikat-gagal')
 *     -> catch RuntimeException -> response 503 { duplicate_check_failed: true }
 *   Blade: if (data.duplicate_check_failed) setError(data.message)
 *
 * Jadi pesan itu HANYA mungkin muncul bila query ke database 'canteen'
 * melempar error. Test ini membuktikannya secara deterministik memakai
 * SQLite in-memory (tanpa SQL Server remote) dengan sengaja:
 *   Skenario A: tabel 'canteen' TIDAK ada  -> Invalid object name / no such table
 *   Skenario B: kolom 'window_name' hilang  -> no such column
 *   Skenario C: kedua tabel ada & kosong      -> TIDAK 503 (transaksi boleh lanjut)
 */
class V40CanteenDuplicateCheckFailureTest extends TestCase
{
    /** @var \Illuminate\Database\Connection */
    protected $canteen;

    protected function setUp(): void
    {
        parent::setUp();

        // Koneksi 'canteen' diarahkan ke SQLite in-memory supaya test
        // tidak bergantung pada SQL Server remote (yang timeout dari sandbox).
        config([
            'database.connections.canteen' => [
                'driver'   => 'sqlite',
                'database' => ':memory:',
                'prefix'   => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        // Buang koneksi yang mungkin sudah dibuat sebelumnya.
        DB::purge('canteen');
        $this->canteen = DB::connection('canteen');

        // Model kantin HARUS ikut koneksi 'canteen'.
    }

    protected function tearDown(): void
    {
        DB::purge('canteen');
        parent::tearDown();
    }

    /**
     * Buat tabel 'canteen' & 'canteen_twos' yang LENGKAP (tanpa window_name).
     *
     * @param bool $withWindowName true = kolom window_name ada
     */
    private function makeTables(bool $withWindowName = true): void
    {
        foreach (['canteen', 'canteen_twos'] as $t) {
            $this->canteen->statement("CREATE TABLE {$t} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                npk TEXT NULL,
                name TEXT NULL,
                dept TEXT NULL,
                canteen_no TEXT NULL,
                date TEXT NULL" . ($withWindowName ? ", window_name TEXT NULL" : "") . "
            )");
        }
    }

    /**
     * Panggil method private findDuplicateAnyCanteen lewat reflection.
     */
    private function callFindDuplicate(string $npk, string $date)
    {
        $controller = new CanteenTransactionController();
        $ref = new \ReflectionClass($controller);
        $m = $ref->getMethod('findDuplicateAnyCanteen');
        $m->setAccessible(true);
        return $m->invoke($controller, $npk, $date);
    }

    /**
     * REGRESSION UTAMA: kalau tabel 'canteen' tidak ada di database 'canteen',
     * findDuplicateAnyCanteen() harus PULIH SENDIRI (auto-heal) dan TIDAK
     * melempar RuntimeException.
     *
     * Inilah keadaan server saat user melaporkan error. Perilaku yang
     * BENAR setelah V40: tabel dibuat otomatis lalu query diulang, sehingga
     * transaksi BISA diteruskan (bukan 503).
     *
     * Fail-closed tetap berlaku untuk error NON-skema — lihat
     * test_non_schema_error_does_not_trigger_auto_heal().
     */
    public function test_missing_canteen_table_is_auto_healed_not_thrown(): void
    {
        // Sengaja TIDAK membuat tabel -> query akan error.
        $result = $this->callFindDuplicate('C-00827', '2026-11-10');

        $this->assertNull($result, 'Auto-heal berhasil -> tidak ada duplikat -> null.');
        $this->assertTrue(
            $this->canteen->getSchemaBuilder()->hasTable('canteen'),
            'Tabel canteen harus dibuat oleh auto-heal.'
        );
    }

    /**
     * CATATAN PLATFORM — kenapa skenario "kolom window_name hilang" TIDAK
     * diuji di sini sebagai pemicu error:
     *
     *   SQL Server (produksi): grammar mengapit nama kolom dengan [ ... ].
     *   -> "Invalid column name 'window_name'" -> exception -> fail-closed.
     *
     *   SQLite (test lokal): grammar mengapit dengan " ... ".
     *   -> SQLite memperlakukan identifier double-quoted yang tidak dikenal
     *      sebagai STRING LITERAL (legacy quirk), BUKAN error.
     *      Contoh: SELECT "window_name" FROM t -> kolom literal 'window_name'.
     *   -> Query jadi silent no-match, TIDAK melempar.
     *
     * Jadi test "kolom hilang" akan SALAH di SQLite. Yang bisa diuji
     * cross-platform adalah KASUS TABEL HILANG (di bawah), karena
     * "no such table" tetap error di kedua platform.
     *
     * Test ini mengunci knowledge itu supaya tidak ada yang menambahkan
     * test "kolom hilang" yang diam-diam hijau tanpa benar-benar
     * mereproduksi kondisi produksi.
     */
    public function test_sqlite_does_not_error_on_missing_double_quoted_column(): void
    {
        $this->makeTables(false); // tanpa kolom window_name

        // SQLite: TIDAK melempar (unknown identifier = literal).
        $result = $this->callFindDuplicate('C-00827', '2026-11-10');
        $this->assertNull($result, 'SQLite tidak error pada kolom hilang; silently no-match.');
    }

    /**
     * Koneksi database 'canteen' mati total -> juga fail-closed.
     */
    public function test_dead_canteen_connection_throws_runtime_exception(): void
    {
        // Arahkan ke file sqlite yang tidak ada & tidak bisa ditulis.
        config([
            'database.connections.canteen' => [
                'driver'   => 'sqlite',
                'database' => '/proc/self/cannot-create-xyz.sqlite',
                'prefix'   => '',
                'foreign_key_constraints' => false,
            ],
        ]);
        DB::purge('canteen');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cek-duplikat-gagal');
        $this->callFindDuplicate('C-00827', '2026-11-10');
    }

    /**
     * SKENARIO PALING MUNGKIN DI SERVER: tabel 'canteen' (kantin 1) ADA,
     * tapi 'canteen_twos' (kantin 2) TIDAK ada.
     *
     *Migrasi 2026_11_09_130000 membuat kedua tabel dalam satu blok try.
     * Bila koneksi DB 'canteen' terputus TEPAT di antara keduanya (atau
     * hak akses tidak cukup untuk tabel kedua), tabel pertama berhasil
     * dibuat dan tabel kedua gagal. Karena blok catch hanya LOG WARNING
     * tanpa throw, migrasi tetap dianggap "sukses" oleh Laravel.
     *
     * Akibatnya: setiap scan di Kantin 1 (dan Kantin 2) selalu gagal
     * dengan 503 karena findDuplicateAnyCanteen() mencoba membaca kedua
     * tabel dan salah satu lempar exception.
     *
     * Ini persis gejala di screenshot produksi.
     */
    public function test_canteen_two_table_missing_is_auto_healed(): void
    {
        // Hanya tabel 'canteen' yang dibuat; 'canteen_twos' sengaja tidak.
        $this->canteen->statement("CREATE TABLE canteen (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            npk TEXT NULL, name TEXT NULL, dept TEXT NULL,
            canteen_no TEXT NULL, date TEXT NULL, window_name TEXT NULL
        )");

        $result = $this->callFindDuplicate('C-00827', '2026-11-10');

        $this->assertNull($result, 'Auto-heal kantin 2 berhasil -> null.');
        $this->assertTrue(
            $this->canteen->getSchemaBuilder()->hasTable('canteen_twos'),
            'Tabel canteen_twos harus dibuat oleh auto-heal.'
        );
    }

    /**
     * KONTROL: tabel lengkap & kosong -> TIDAK ada exception.
     * Ini membuktikan 503 pada test di atas memang berasal dari error DB,
     * bukan dari logika normal.
     */
    public function test_complete_tables_without_rows_do_not_throw(): void
    {
        $this->makeTables(true);

        $result = $this->callFindDuplicate('C-00827', '2026-11-10');
        $this->assertNull($result, 'Tabel kosong harus menghasilkan null (tidak ada duplikat).');
    }

    /**
     * KONTROL: tabel lengkap dengan baris yang cocok -> detect duplikat
     * (path sukses, TIDAK 503). Membuktikan perbedaan antara "gagal cek"
     * dan "ada duplikat".
     */
    public function test_existing_duplicate_row_is_detected_not_error(): void
    {
        $this->makeTables(true);
        $this->canteen->table('canteen')->insert([
            'npk'         => 'C-00827',
            'name'        => 'Budi Santoso',
            'dept'        => 'Produksi',
            'canteen_no'  => '1',
            'date'        => '2026-11-10',
            'window_name' => 'Break 1',
        ]);

        $result = $this->callFindDuplicate('C-00827', '2026-11-10');
        $this->assertNotNull($result, 'Duplikat harus terdeteksi.');
        $this->assertSame('C-00827', $result->npk);
    }

    /**
     * KONTROL: duplikat di kantin 2 (tabel canteen_twos) juga terdeteksi —
     * cek harus MELINTAS kedua tabel.
     */
    public function test_duplicate_in_canteen_two_table_is_detected(): void
    {
        $this->makeTables(true);
        $this->canteen->table('canteen_twos')->insert([
            'npk'         => 'C-00827',
            'canteen_no'  => '2',
            'date'        => '2026-11-10',
            'window_name' => 'Break 1',
        ]);

        $result = $this->callFindDuplicate('C-00827', '2026-11-10');
        $this->assertNotNull($result, 'Duplikat di canteen_twos harus terdeteksi.');
    }

    /**
     * Pesan 503 di controller harus PERSIS sama dengan yang muncul di
     * kotak merah pada screenshot produksi.
     *
     * Screenshot: "Sistem tidak dapat memeriksa transaksi sebelumnya.
     * Silakan coba lagi beberapa saat lagi."
     *
     * PERBAIKAN V40: awalan pesan tetap sama supaya tidak
     * membingungkan user yang sudah melihat pesan lama; hanya tambahan
     * klarifikasi "Database kantin belum siap. Hubungi admin ...".
     */
    public function test_controller_503_message_matches_production_screenshot_text(): void
    {
        $src = file_get_contents(
            app_path('Http/Controllers/CanteenTransactionController.php')
        );

        $this->assertStringContainsString(
            "'duplicate_check_failed' => true",
            $src,
            'Controller harus menandai response dengan duplicate_check_failed.'
        );

        // Prefix pesan WAJIB sama dengan screenshot produksi.
        $this->assertStringContainsString(
            'Sistem tidak dapat memeriksa transaksi sebelumnya.',
            $src,
            'Awalan pesan 503 harus sama dengan yang tampil di screenshot produksi.'
        );

        // Konfirmasi kalimat kedua dari pesan screenshot tetap ada.
        $this->assertStringContainsString(
            'Silakan coba lagi beberapa saat lagi.',
            $src,
            'Pesan 503 harus memuat kalimat "Silakan coba lagi beberapa saat lagi.".'
        );

        // V40: harus ada reason code supaya front-end bisa membedakan
        // masalah skema vs koneksi mati.
        $this->assertStringContainsString(
            "'reason' => \$reason",
            $src,
            'Response 503 harus menyertakan reason code.'
        );
    }

    /**
     * Blade harusconsumer 'duplicate_check_failed' dan menampilkannya lewat
     * setError(), bukan diam-diam.
     */
    public function test_blade_handles_duplicate_check_failed_flag(): void
    {
        $blade = file_get_contents(
            resource_path('views/canteen_transaction/index.blade.php')
        );

        $this->assertStringContainsString(
            'data.duplicate_check_failed',
            $blade,
            'Blade harus mengecek flag duplicate_check_failed dari server.'
        );
        $this->assertMatchesRegularExpression(
            '/data\.duplicate_check_failed[\s\S]{0,600}setError\(/',
            $blade,
            'Blade harus menampilkan pesan server lewat setError().'
        );
    }

    /**
     * Diagnosa: kode yang melempar RuntimeException('cek-duplikat-gagal')
     * harus menulis detail error aslinya ke Log — tanpa itu, penyebabnya
     * mustahil diketahui dari sisi server.
     */
    public function test_failure_is_logged_with_underlying_error_detail(): void
    {
        $src = file_get_contents(
            app_path('Http/Controllers/CanteenTransactionController.php')
        );

        $this->assertStringContainsString(
            "Log::warning('Canteen duplicate check lintas kantin GAGAL: '",
            $src,
            'Detail error asli harus di-log agar bisa didiagnosis di server.'
        );
        $this->assertStringContainsString(
            '$errors[] = "kantin{$canteenNo}: " . $e->getMessage();',
            $src,
            'Pesan exception asli per kantin harus ikut di-log.'
        );
    }

    /**
     * END-TO-END (HTTP): saat database 'canteen' tidak bisa dibaca,
     * POST /canteen-transaction/store harus membalas 503 dengan flag
     * duplicate_check_failed dan pesan yang PERSIS sama dengan yang
     * muncul di kotak merah pada screenshot produksi.
     *
     * Tahap face-match dilewati dengan men-stub FaceRecognitionService
     * (butuh model face-api yang tidak tersedia di CI).
     */
    public function test_store_returns_503_duplicate_check_failed_when_canteen_db_unreadable(): void
    {
        $this->prepareAppTables();

        // Koneksi database 'canteen' MATI TOTAL (file sqlite tidak bisa
        // dibuat). Ini error NON-skema: tidak ada yang bisa di-heal, jadi
        // controller WAJIB fail-closed dengan 503.
        //
        // Skenario "tabel belum ada" sekarang dipulihkan otomatis oleh
        // auto-heal (lihat test_store_succeeds_after_auto_heal_when_tables_missing).
        config([
            'database.connections.canteen' => [
                'driver'   => 'sqlite',
                'database' => '/proc/self/cannot-create-xyz.sqlite',
                'prefix'   => '',
                'foreign_key_constraints' => false,
            ],
        ]);
        DB::purge('canteen');

        try {
            $response = $this->postJson('/canteen-transaction/store', [
                'descriptor' => json_encode(array_fill(0, 128, 0.1)),
                'npk'        => 'C-00827',
                'canteen_id' => 1,
            ]);
        } catch (\Throwable $e) {
            $this->fail('Exception lolos: ' . get_class($e) . ': ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine());
        }

        $response->assertStatus(503);
        $response->assertJson([
            'success'              => false,
            'duplicate_check_failed' => true,
            'reason'               => 'schema_or_db',
        ]);

        // Awalan pesan harus sama dengan screenshot produksi.
        $message = (string) ($response->json('message') ?? '');
        $this->assertStringStartsWith(
            'Sistem tidak dapat memeriksa transaksi sebelumnya.',
            $message,
            'Awalan pesan 503 harus sama dengan screenshot produksi. Pesan: ' . $message
        );
    }

    /**
     * END-TO-END (HTTP) — SKENARIO PRODUKSI UTAMA:
     * tabel 'canteen' & 'canteen_twos' belum ada -> auto-heal membuat
     * keduanya -> POST harus BERHASIL (bukan 503).
     *
     * Inilah yang menghasilkan pesan di screenshot produksi.
     */
    public function test_store_succeeds_after_auto_heal_when_tables_missing(): void
    {
        $this->prepareAppTables();

        // Sengaja TIDAK membuat tabel kantin.
        $this->assertFalse(
            $this->canteen->getSchemaBuilder()->hasTable('canteen'),
            'Tabel canteen harus belum ada sebelum request.'
        );

        $response = $this->postJson('/canteen-transaction/store', [
            'descriptor' => json_encode(array_fill(0, 128, 0.1)),
            'npk'        => 'C-00827',
            'canteen_id' => 1,
        ]);

        $this->assertNotSame(
            503,
            $response->getStatusCode(),
            'Auto-heal harus membuat transaksi BERHASIL, bukan 503. Body: '
                . $response->getContent()
        );

        // Tabel harus sudah dibuat setelah request.
        $this->assertTrue(
            $this->canteen->getSchemaBuilder()->hasTable('canteen'),
            'Auto-heal harus membuat tabel canteen saat request.'
        );
    }

    /**
     * END-TO-END (HTTP) — KONTROL: ketika database 'canteen' sehat
     * (kedua tabel ada), response TIDAK boleh 503 duplicate_check_failed.
     *
     * Yang boleh muncul di jalur ini: error lain (mis. kolom created_at
     * belum ada di tabel uji, atau NPK/deskriptor tidak valid). Yang
     * TIDAK boleh: flag duplicate_check_failed — karena itu hanya muncul
     * saat query ke database 'canteen' gagal.
     *
     * Ini yang membuktikan pesan di screenshot memang berasal dari error
     * database 'canteen', bukan dari kondisi normal apa pun.
     */
    public function test_store_does_not_return_503_when_canteen_db_is_healthy(): void
    {
        $this->prepareAppTables();
        $this->makeTables(true); // kedua tabel ada

        $response = $this->postJson('/canteen-transaction/store', [
            'descriptor' => json_encode(array_fill(0, 128, 0.1)),
            'npk'        => 'C-00827',
            'canteen_id' => 1,
        ]);

        $body = $response->json() ?: [];
        $this->assertArrayNotHasKey(
            'duplicate_check_failed',
            $body,
            'Dengan database canteen yang sehat, flag duplicate_check_failed '
            . 'TIDAK boleh muncul. Body: ' . json_encode($body)
        );
        $this->assertNotSame(
            503,
            $response->getStatusCode(),
            'Dengan database canteen yang sehat, status tidak boleh 503. '
            . 'Body: ' . json_encode($body)
        );
    }

    /**
     * Siapkan tabel aplikasi yang dibutuhkan store() pada koneksi 'cii'
     * (SQLite in-memory): canteens, break_masters, canteen_settings.
     */
    private function prepareAppTables(): void
    {
        config([
            'database.connections.cii' => [
                'driver'   => 'sqlite',
                'database' => ':memory:',
                'prefix'   => '',
                'foreign_key_constraints' => false,
            ],
        ]);
        DB::purge('cii');
        $cii = DB::connection('cii');

        $cii->statement("CREATE TABLE canteens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            canteen_id INTEGER NULL, name TEXT NULL, location TEXT NULL,
            is_active INTEGER DEFAULT 1, contact_person TEXT NULL,
            contact_phone TEXT NULL, pic_name TEXT NULL, pic_phone TEXT NULL
        )");
        $cii->statement("CREATE TABLE break_masters (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sesi TEXT NULL, time_start TEXT NULL, time_end TEXT NULL
        )");
        $cii->statement("CREATE TABLE canteen_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            require_geomap INTEGER DEFAULT 0,
            is_npk_required_enabled INTEGER DEFAULT 1
        )");
        $cii->statement("CREATE TABLE face_recognition_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            match_threshold REAL DEFAULT 0.55,
            is_npk_required_enabled INTEGER DEFAULT 1
        )");

        // Window Break 1 yang sedang aktif (06:15 - 12:15).
        // CATATAN: tabel break_masters memakai kolom 'sesi' (bukan 'name');
        // CanteenBreakWindow::getNameAttribute() memetakan sesi -> name.
        $cii->table('break_masters')->insert([
            'sesi'       => 'Break 1',
            'time_start' => '00:00:00',
            'time_end'   => '23:59:59',
        ]);

        // Kantin 1 aktif.
        $cii->table('canteens')->insert([
            'canteen_id' => 1,
            'name'       => 'Kantin 1 — Diamond Chickrees',
            'location'   => 'Lantai 1',
            'is_active'  => 1,
        ]);

        $cii->table('canteen_settings')->insert(['require_geomap' => 0]);
        $cii->table('face_recognition_settings')->insert([
            'match_threshold' => 0.55,
            'is_npk_required_enabled' => 1,
        ]);

        // employee_faces: descriptor IDENTIK dengan yang dikirim request,
        // sehingga jarak euclidean = 0 -> match lolos (tidak 401).
        $cii->statement("CREATE TABLE employee_faces (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            npk TEXT NULL, name TEXT NULL, descriptor TEXT NULL,
            is_active INTEGER DEFAULT 1, face_img TEXT NULL
        )");
        $cii->table('employee_faces')->insert([
            'npk'        => 'C-00827',
            'name'       => 'Budi Santoso',
            'descriptor' => json_encode(array_fill(0, 128, 0.1)),
            'is_active'  => 1,
        ]);

        // employee_active_status / BIODATA: tabel opsional. Bila tidak ada,
        // EmployeeActiveStatusService::check() harus mengembalikan found=false
        // (bukan fatal) supaya flow lanjut ke cek duplikat.
        $cii->statement("CREATE TABLE BIODATA (
            NPK TEXT NULL, NAMA_KARYAWAN TEXT NULL, ID_DEPT TEXT NULL, BAG TEXT NULL
        )");
        $cii->table('BIODATA')->insert([
            'NPK'          => 'C-00827',
            'NAMA_KARYAWAN' => 'Budi Santoso',
            'ID_DEPT'      => '2095',
        ]);
    }

    // ================================================================
    // AUTO-HEAL (V40) — skema diperbaiki otomatis bila tabel/kolom hilang
    // ================================================================

    /**
     * Auto-heal harus BENAR-BENAR membuat tabel yang hilang, lalu query
     * diulang dan berhasil (tidak lagi 503).
     *
     * Ini skenario produksi utama: migrasi 2026_11_09_130000 membuat
     * tabel di dalam try/catch yang menelan error, sehingga tabel bisa
     * tidak pernah ada tanpa ada yang melihat.
     */
    public function test_auto_heal_creates_missing_canteen_table(): void
    {
        // Sengaja TIDAK buat tabel 'canteen' / 'canteen_twos'.
        $this->assertFalse(
            $this->canteen->getSchemaBuilder()->hasTable('canteen'),
            'Tabel canteen harus belum ada sebelum auto-heal.'
        );

        // Panggil -> error -> auto-heal -> query ulang -> sukses.
        $result = $this->callFindDuplicate('C-00827', '2026-11-10');

        $this->assertNull($result, 'Setelah auto-heal, tidak ada duplikat jadi null.');

        $schema = $this->canteen->getSchemaBuilder();
        $this->assertTrue($schema->hasTable('canteen'), 'Auto-heal harus membuat tabel canteen.');
        $this->assertTrue($schema->hasTable('canteen_twos'), 'Auto-heal harus membuat tabel canteen_twos.');

        // Kolom yang dipakai query cek-duplikat WAJIB ada.
        foreach (['npk', 'date', 'window_name', 'created_at', 'canteen_no'] as $col) {
            $this->assertTrue(
                $schema->hasColumn('canteen', $col),
                "Auto-heal harus membuat kolom '{$col}' di tabel canteen."
            );
        }
    }

    /**
     * Auto-heal harus IDEMPOTENT: dijalankan pada tabel yang sudah ada
     * tidak boleh menghapus data yang sudah tersimpan.
     */
    public function test_auto_heal_is_idempotent_and_preserves_existing_rows(): void
    {
        $this->makeTables(true);

        // Sisipkan satu baris lama yang harus tetap ada setelah heal.
        $this->canteen->table('canteen')->insert([
            'npk'         => 'C-00001',
            'name'        => 'Lama',
            'dept'        => 'D1',
            'canteen_no'  => '1',
            'date'        => '2026-11-01',
            'window_name' => 'Break 1',
        ]);

        // Panggil pada npk lain -> harus tetap null, tapi heal tetap jalan
        // karena kolom 'created_at' belum ada di tabel buatan test.
        $result = $this->callFindDuplicate('C-00827', '2026-11-10');
        $this->assertNull($result);

        // Data lama MASIH ADA (tidak ada DROP/TRUNCATE).
        $count = $this->canteen->table('canteen')->where('npk', 'C-00001')->count();
        $this->assertSame(1, $count, 'Auto-heal tidak boleh menghapus data yang sudah ada.');
    }

    /**
     * Error BUKAN skema (koneksi mati / timeout) TIDAK boleh memicu
     * auto-heal. Attempt membuat tabel saat DB sedang sakit hanya menambah
     * beban, dan tetap tidak akan memperbaiki masalahnya.
     */
    public function test_non_schema_error_does_not_trigger_auto_heal(): void
    {
        // Arahkan ke path sqlite yang tidak bisa dibuat -> error koneksi.
        config([
            'database.connections.canteen' => [
                'driver'   => 'sqlite',
                'database' => '/proc/self/cannot-create-xyz.sqlite',
                'prefix'   => '',
                'foreign_key_constraints' => false,
            ],
        ]);
        DB::purge('canteen');

        // Tetap fail-closed.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cek-duplikat-gagal');
        $this->callFindDuplicate('C-00827', '2026-11-10');
    }

    /**
     * Klasifikasi error skema: hanya pesan yang benar-benar menunjuk ke
     * objek/kolom yang hilang yang boleh di-heal.
     */
    public function test_is_missing_schema_error_classification(): void
    {
        $controller = new CanteenTransactionController();
        $ref = new \ReflectionClass($controller);
        $m = $ref->getMethod('isMissingSchemaError');
        $m->setAccessible(true);

        // SQL Server
        $this->assertTrue($m->invoke($controller, new \Exception("SQLSTATE[42S02]: [Microsoft][ODBC Driver 17 for SQL Server]Invalid object name 'canteen'.")), 'Invalid object name harus di-heal.');
        $this->assertTrue($m->invoke($controller, new \Exception("Invalid column name 'window_name'.")), 'Invalid column name harus di-heal.');

        // Error lain: TIDAK boleh di-heal.
        $this->assertFalse($m->invoke($controller, new \Exception('Login timeout expired')), 'Timeout tidak boleh di-heal.');
        $this->assertFalse($m->invoke($controller, new \Exception('SQLSTATE[HY000] [2005] Unknown MySQL server host')), 'Koneksi mati tidak boleh di-heal.');
        $this->assertFalse($m->invoke($controller, new \Exception('Permission denied')), 'Hak akses tidak boleh di-heal.');
    }
}
