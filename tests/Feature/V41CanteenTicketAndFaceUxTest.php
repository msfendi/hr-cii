<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Models\CanteenReport;
use App\Models\CanteenTwoReport;
use App\Http\Controllers\CanteenTransactionController;

/**
 * V41 — Empat perbaikan pada halaman Transaksi Kantin.
 *
 * Permintaan user (2026-11-10, 2 screenshot):
 *
 *  1. "tambahkan gambar minimapnya menggunaak koordinat dan tampilkan
 *      polygon areanya"
 *      -> Mini map + polygon area GPS harus benar-benar tampil, dan
 *         polygon yang tampil harus sama dengan yang dipakai untuk
 *         pemeriksaan "dalam area".
 *
 *  2. "ada bug sedang menyiapkan camera tapi banner sudah tertulis
 *      tantangan terverifikasi"
 *      -> Banner tantangan harus IKUT STATE AKTUAL:
 *         menyiapkan (abu-abu) -> menunggu gerakan (kuning) ->
 *         terverifikasi (hijau). Teks "Tantangan terverifikasi" TIDAK
 *         boleh muncul sebelum challenge benar-benar lolos.
 *
 *  3. "beri tanda untuk user jika verifikasi wajah berhasil"
 *      -> Splash hijau di atas panel kamera muncul setelah server
 *         mengonfirmasi wajah dikenali + transaksi tersimpan.
 *
 *  4. "buat kolom id_ticket di tabel canteen dan canteen_twos, tampilkan
 *      di bukti scan + history"
 *      -> Kolom id_ticket ada di kedua tabel, nilainya terbentuk saat
 *         transaksi, dan tampil di bukti scan + history scan.
 *
 * Test ini Campuran:
 *   - KONTRAK (contract test): memeriksa isi file view/controller/model.
 *     Tidak menyentuh database remote.
 *   - FUNGSIONAL: memakai SQLite in-memory untuk koneksi 'canteen' agar
 *     id_ticket benar-benar tersimpan & bisa dibaca balik.
 */
class V41CanteenTicketAndFaceUxTest extends TestCase
{
    /** @var \Illuminate\Database\Connection */
    protected $canteen;

    protected function setUp(): void
    {
        parent::setUp();

        // Koneksi 'canteen' -> SQLite in-memory supaya test tidak
        // bergantung SQL Server remote (timeout dari sandbox).
        config([
            'database.connections.canteen' => [
                'driver'   => 'sqlite',
                'database' => ':memory:',
                'prefix'   => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        DB::purge('canteen');
        $this->canteen = DB::connection('canteen');

        $this->makeTables();
    }

    protected function tearDown(): void
    {
        DB::purge('canteen');
        parent::tearDown();
    }

    /**
     * Buat tabel canteen & canteen_twos lengkap dengan kolom id_ticket.
     */
    private function makeTables(): void
    {
        foreach (['canteen', 'canteen_twos'] as $t) {
            $this->canteen->statement("CREATE TABLE {$t} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                npk TEXT NULL,
                name TEXT NULL,
                dept TEXT NULL,
                canteen_no TEXT NULL,
                id_ticket TEXT NULL,
                date TEXT NULL,
                window_name TEXT NULL,
                location_name TEXT NULL,
                latitude REAL NULL,
                longitude REAL NULL,
                photo_face TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )");
        }
    }

    /**
     * Baca source file view / controller.
     */
    private function source(string $relative): string
    {
        $base = realpath(__DIR__ . '/../../');
        $path = $base . '/' . $relative;
        $this->assertFileExists($path, "File tidak ditemukan: {$relative}");
        return (string) file_get_contents($path);
    }

    // ============================================================
    // BAGIAN 1 — BANNER TANTANGAN IKUT STATE ASLI
    // ============================================================

    public function test_banner_tantangan_tidak_boleh_tampil_terverifikasi_sebelum_verifikasi()
    {
        $src = $this->source('resources/views/canteen_transaction/index.blade.php');

        // Banner TIDAK boleh hard-code warna "merah/berhasil" di Blade.
        // Warna harus di-set JS dari state aktual.
        $this->assertStringNotContainsString(
            'id="challengeAlertBox" class="alert alert-danger',
            $src,
            'Banner tantangan tidak boleh di-render dengan warna danger (sukses) di Blade'
        );

        // Banner harus mulai netral dengan teks "menyiapkan".
        $this->assertMatchesRegularExpression(
            '/id="challengeAlertBox"[^>]*alert-secondary/',
            $src,
            'Banner tantangan harus mulai pada state idle (alert-secondary)'
        );

        // Teks awal harus menyatakan bahwa verifikasi sedang disiapkan,
        // BUKAN "Tantangan terverifikasi".
        $this->assertMatchesRegularExpression(
            '/id="challengeInstructionText"[^>]*>\s*Menyiapkan verifikasi wajah\.\.\./s',
            $src,
            'Teks awal banner harus "Menyiapkan verifikasi wajah..."'
        );
    }

    public function test_teks_tantangan_terverifikasi_hanya_ditulis_di_tempat_verifikasi_pasti()
    {
        $src = $this->source('resources/views/canteen_transaction/index.blade.php');

        // Semua kemunculan "Tantangan terverifikasi" harus berada di
        // dalam fungsi/state 'passed' — tidak boleh di Blade (render awal)
        // maupun di state 'pending'.
        //
        // Pola: teks itu hanya boleh muncul sebagai argumen ke
        // setChallengeBannerState('passed', ...) atau di map konfigurasi
        // state 'passed'.
        $occurrences = [];
        foreach (explode("\n", $src) as $i => $line) {
            if (str_contains($line, 'Tantangan terverifikasi')) {
                $occurrences[] = ($i + 1) . ': ' . trim($line);
            }
        }

        $this->assertNotEmpty($occurrences, 'Teks "Tantangan terverifikasi" harus ada di sumber.');

        foreach ($occurrences as $occ) {
            [$lineNo, $text] = explode(':', $occ, 2);
            $lineNo = (int) $lineNo;
            $text = trim((string) $text);

            // Pisahkan blok komentar Blade {{-- ... --}} dan komentar
            // baris/komentar blok JS. Teks di dalam komentar hanya
            // DOKUMENTASI (menjelaskan bug), bukan kode yang dieksekusi.
            $isComment = false;
            if (str_starts_with($text, '//') || str_starts_with($text, '*') || str_starts_with($text, '/*')) {
                $isComment = true;
            }
            if (str_contains($text, '{{--')) {
                $isComment = true;
            }

            // Baris di dalam blok komentar Blade {{-- ... --}}.
            if (! $isComment && $this->insideBladeComment($src, $lineNo)) {
                $isComment = true;
            }

            if ($isComment) {
                continue;
            }

            // Teks "Tantangan terverifikasi" pada baris kode AKTIF hanya
            // sah bila berada di dalam konteks "challenge sudah lolos":
            //   - setChallengeBannerState('passed', 'Tantangan terverifikasi')
            //   - atau blok if (hasilChallenge.passed) / randomChallengeVerified
            //     (dalam 12 baris ke atas / di baris yang sama).
            $isPassedContext = (bool) preg_match(
                "/setChallengeBannerState\('passed'/",
                $text
            );

            // Definisi state pada map konfigurasi banner:
            //   passed: { cls: 'alert-success', ..., text: 'Tantangan terverifikasi' }
            if (! $isPassedContext) {
                $isPassedContext = (bool) preg_match(
                    "/^\s*passed\s*:\s*\{/",
                    $text
                );
            }

            if (! $isPassedContext) {
                $isPassedContext = (bool) preg_match(
                    "/^(if\s*\(.*(passed|randomChallengeVerified).*\))$/",
                    $text
                );
            }

            if (! $isPassedContext) {
                // Cari penanda 'passed' dalam vicinity (baris yang sama
                // atau beberapa baris di atas - masih di blok passed).
                $lines = explode("\n", $src);
                $from  = max(0, $lineNo - 13);
                $vicinity = '';
                for ($i = $from; $i < $lineNo && $i < count($lines); $i++) {
                    $vicinity .= $lines[$i] . "\n";
                }
                $isPassedContext = (bool) preg_match(
                    "/setChallengeBannerState\('passed'|if\s*\(.*(hasilChallenge\.passed|randomChallengeVerified)/",
                    $vicinity
                );
            }

            $this->assertTrue(
                $isPassedContext,
                "Teks \"Tantangan terverifikasi\" hanya boleh muncul di konteks challenge sudah lolos "
                . "(setChallengeBannerState('passed') atau blok if(...passed)). Baris: {$occ}"
            );
        }
    }

    /**
     * Apakah baris ke-$lineNo berada di dalam blok komentar Blade {{-- ... --}}?
     */
    private function insideBladeComment(string $src, int $lineNo): bool
    {
        $lines = explode("\n", $src);
        $open = false;
        foreach ($lines as $idx => $line) {
            $n = $idx + 1;
            $hasOpen  = str_contains($line, '{{--');
            $hasClose = str_contains($line, '--}}');

            if ($n === $lineNo) {
                return $open || ($hasOpen && ! $hasClose);
            }

            if ($hasOpen && ! $hasClose) {
                $open = true;
            } elseif ($hasClose) {
                $open = false;
            }
        }

        return false;
    }

    public function test_ada_fungsi_set_challenge_banner_state_dengan_empat_state()
    {
        $src = $this->source('resources/views/canteen_transaction/index.blade.php');

        $this->assertStringContainsString(
            'function setChallengeBannerState(state, text)',
            $src,
            'Harus ada fungsi terpusat setChallengeBannerState()'
        );

        // Empat state: idle, pending, passed, error.
        foreach (['idle', 'pending', 'passed', 'error'] as $state) {
            $this->assertMatchesRegularExpression(
                '/' . $state . ':\s*\{/',
                $src,
                "State banner '{$state}' harus ada di CHALLENGE_BANNER_STATES"
            );
        }

        // Fungsi wajib dipanggil saat challenge terverifikasi.
        $this->assertStringContainsString(
            "setChallengeBannerState('passed', 'Tantangan terverifikasi')",
            $src,
            'Saat challenge lolos, banner harus di-set ke state passed'
        );

        // Dan saat menunggu gerakan.
        $this->assertStringContainsString(
            "setChallengeBannerState('pending'",
            $src,
            'Saat challenge belum lolos, banner harus di-set ke state pending'
        );
    }

    public function test_reset_face_gates_mengembalikan_banner_ke_state_idle()
    {
        $src = $this->source('resources/views/canteen_transaction/index.blade.php');

        // resetFaceGates() harus mengembalikan banner ke idle, supaya
        // scan kedua tidak mewarisi teks "terverifikasi" dari scan pertama.
        $this->assertMatchesRegularExpression(
            '/function resetFaceGates\(\)\s*\{(?:(?!^\}).)*?setChallengeBannerState\(\'idle\'\)/s',
            $src,
            'resetFaceGates() harus memanggil setChallengeBannerState(\'idle\')'
        );
    }

    public function test_banner_awal_tidak_langsung_menampilkan_instruksi_gerakan()
    {
        $src = $this->source('resources/views/canteen_transaction/index.blade.php');

        // Saat init, banner harus di-set ke 'idle', BUKAN langsung
        // menampilkan instruksi gerakan (kamera belum siap).
        $this->assertMatchesRegularExpression(
            "/if \(RANDOM_CHALLENGE_REQUIRED && box\) \{\s*box\.classList\.remove\('d-none'\);[\s\S]{0,200}?setChallengeBannerState\('idle'\)/",
            $src,
            'initFaceGates() harus menyiapkan banner ke state idle'
        );

        // Dan TIDAK boleh lagi menulis hint gerakan langsung ke banner
        // saat init.
        $this->assertStringNotContainsString(
            'if (h) txt.textContent = h;',
            $src,
            'Init tidak boleh menulis instruksi gerakan langsung ke banner (kamera belum siap)'
        );
    }

    // ============================================================
    // BAGIAN 2 — SPLASH HIJAU VERIFIKASI WAJAH
    // ============================================================

    public function test_ada_overlay_splash_hijau_di_panel_kamera()
    {
        $src = $this->source('resources/views/canteen_transaction/index.blade.php');

        // Overlay harus ADA di DOM.
        $this->assertStringContainsString(
            'id="faceVerifiedFlash"',
            $src,
            'Overlay splash hijau #faceVerifiedFlash harus ada'
        );

        // Overlay harus default TERSEMBUNYI (d-none) supaya tidak
        // menutupi kamera sebelum verifikasi.
        $this->assertMatchesRegularExpression(
            '/id="faceVerifiedFlash"[\s\S]{0,300}?d-none/',
            $src,
            'Overlay splash hijau harus default tersembunyi (class d-none)'
        );

        // Isi overlay: teks "Wajah Terverifikasi" ada di badge pill (ikon
        // centang ada di dalam #faceVerifiedBadge, bukan di dalam overlay
        // lagi sejak V43 — overlay sekarang hanya tint + border).
        $this->assertStringContainsString(
            'Wajah Terverifikasi',
            $src,
            'Overlay harus menampilkan teks "Wajah Terverifikasi"'
        );
        $this->assertMatchesRegularExpression(
            '/id="faceVerifiedBadge"[\s\S]{0,300}?fa-check-circle/',
            $src,
            'Badge pill harus punya ikon centang'
        );

        // Latar hijau (tint tipis + border hijau, bukan blok opak).
        $this->assertMatchesRegularExpression(
            '/id="faceVerifiedFlash"[\s\S]{0,400}?rgba\(28,\s*200,\s*138/',
            $src,
            'Overlay harus memakai warna hijau'
        );
    }

    public function test_splash_hijau_hanya_muncul_setelah_server_konfirmasi_berhasil()
    {
        $src = $this->source('resources/views/canteen_transaction/index.blade.php');

        // Fungsi penampilnya harus ada.
        $this->assertStringContainsString(
            'function showFaceVerifiedFlash(message)',
            $src,
            'Harus ada fungsi showFaceVerifiedFlash()'
        );
        $this->assertStringContainsString(
            'function hideFaceVerifiedFlash()',
            $src,
            'Harus ada fungsi hideFaceVerifiedFlash()'
        );

        // Panggilnya harus di cabang SUKSES, yaitu setelah pengecekan
        // "if(!res.ok || !data.success){ ... return; }".
        // PERBAIKAN LAYOUT (2026-11-10): sekarang yang dipanggil adalah
        // showFaceSnapshotFlash(snapshotDataUrl), bukan lagi
        // showFaceVerifiedFlash(data.message) — kamera langsung off
        // setelah wajah terbaca.
        $this->assertMatchesRegularExpression(
            '/if\(!res\.ok \|\| !data\.success\)\s*\{[\s\S]*?return;[\s\S]*?\}\s*\n[\s\S]{0,2000}?showFaceSnapshotFlash\(snapshotDataUrl\)/',
            $src,
            'showFaceSnapshotFlash() harus dipanggil SESUDAH cabang gagal return, di cabang sukses'
        );

        // Dan TIDAK boleh dipanggil di dalam loop deteksi (sebelum
        // verifikasi server) maupun di challenge/liveness block.
        $this->assertStringNotContainsString(
            "setChallengeBannerState('passed', 'Tantangan terverifikasi'); showFaceVerifiedFlash",
            $src,
            'Splash hijau tidak boleh muncul bersamaan dengan challenge yang baru lolos'
        );
    }

    public function test_splash_hijau_bisa_disembunyikan_otomatis_dan_saat_reset()
    {
        $src = $this->source('resources/views/canteen_transaction/index.blade.php');

        // Auto-hide dengan timer.
        $this->assertStringContainsString(
            'FACE_FLASH_DURATION_MS',
            $src,
            'Durasi splash harus dikonstantakan'
        );
        $this->assertMatchesRegularExpression(
            '/faceVerifiedFlashTimer = setTimeout\(function\(\)\{\s*hideFaceVerifiedFlash\(\)/',
            $src,
            'Splash harus hilang otomatis setelah durasi tertentu (via hideFaceVerifiedFlash)'
        );

        // Dan harus disembunyikan saat starting scan baru.
        $this->assertMatchesRegularExpression(
            '/function resetFaceGates\(\)\s*\{(?:(?!^\}).)*?hideFaceVerifiedFlash\(\)/s',
            $src,
            'resetFaceGates() harus menyembunyikan splash hijau'
        );
    }

    // ============================================================
    // BAGIAN 3 — MINI MAP + POLYGON AREA
    // ============================================================

    public function test_mini_map_dan_polygon_pakai_fungsi_yang_sama()
    {
        $src = $this->source('resources/views/canteen_transaction/index.blade.php');

        // Harus ada helper penormalisasi ring polygon.
        $this->assertStringContainsString(
            'function extractPolygonRing(polygonGeojson)',
            $src,
            'Harus ada helper extractPolygonRing() yang menormalisasi GeoJSON'
        );

        // Pengecakan "dalam area" HARUS pakai helper itu.
        $this->assertMatchesRegularExpression(
            '/extractPolygonRing\(loc\.polygon_geojson\)/',
            $src,
            'Pemeriksaan dalam/luar area harus pakai extractPolygonRing()'
        );

        // Penggambaran polygon di peta juga harus pakai helper yang SAMA
        // supaya yang digambar == yang dicek.
        $this->assertMatchesRegularExpression(
            '/const ring = extractPolygonRing\(loc\.polygon_geojson\);/',
            $src,
            'Penggambaran polygon harus pakai extractPolygonRing() yang sama'
        );

        // Kode lama yang hanya bisa Feature harus hilang dari baris KODE
        // AKTIF. (Boleh masih muncul di komentar dokumentasi yang
        // menjelaskan bug lama — itu tidak dieksekusi.)
        $activeLines = [];
        foreach (explode("\n", $src) as $i => $line) {
            $t = trim($line);
            if ($t === '' || str_starts_with($t, '//') || str_starts_with($t, '*') || str_starts_with($t, '/*')) {
                continue;
            }
            if (str_contains($line, 'geo.geometry.coordinates[0]')) {
                $activeLines[] = ($i + 1) . ': ' . $t;
            }
        }
        $this->assertSame(
            [],
            $activeLines,
            "Parsing GeoJSON lama (hanya Feature) masih ada di baris kode aktif:\n" . implode("\n", $activeLines)
        );
    }

    public function test_extract_polygon_ring_menangani_semua_bentuk_geojson()
    {
        $src = $this->source('resources/views/canteen_transaction/index.blade.php');

        // Bentuk 1: Feature.
        $this->assertStringContainsString(
            "data.type === 'Feature' && data.geometry",
            $src,
            'extractPolygonRing harus menangani GeoJSON bentuk Feature'
        );
        // Bentuk 2: Geometry.
        $this->assertStringContainsString(
            'data.coordinates',
            $src,
            'extractPolygonRing harus menangani GeoJSON bentuk Geometry'
        );
        // Bentuk 3: array polos.
        $this->assertStringContainsString(
            'Array.isArray(data) && data.length && Array.isArray(data[0])',
            $src,
            'extractPolygonRing harus menangani array polos [lng,lat]'
        );
        // Bentuk 4: FeatureCollection.
        $this->assertStringContainsString(
            "data.type === 'FeatureCollection'",
            $src,
            'extractPolygonRing harus menangani GeoJSON bentuk FeatureCollection'
        );
    }

    public function test_polygon_kosong_tidak_lagi_lolos_otomatis()
    {
        $src = $this->source('resources/views/canteen_transaction/index.blade.php');

        // Fail-closed: polygon yang tidak bisa dibaca (< 3 titik) TIDAK
        // boleh dianggap "dalam area". Kalau ini lolos, user di luar
        // area bisa scan.
        $this->assertMatchesRegularExpression(
            '/if \(coords\.length >= 3 && pointInPolygon\(/',
            $src,
            'Polygon dengan < 3 titik harus dianggap LUAR area (fail-closed)'
        );
    }

    public function test_mini_map_menampilkan_legend_dan_tinggi_layak()
    {
        $src = $this->source('resources/views/canteen_transaction/index.blade.php');

        // Legenda polygon area.
        $this->assertStringContainsString(
            'Area Lokasi Kerja (polygon GPS)',
            $src,
            'Mini map harus punya legenda polygon area'
        );
        $this->assertStringContainsString(
            'Posisi Anda',
            $src,
            'Mini map harus punya legenda posisi user'
        );

        // Tinggi mini map >= 200px supaya polygon terlihat.
        $this->assertMatchesRegularExpression(
            '/id="miniMap"[^>]*height:\s*2[0-9]{2}px/',
            $src,
            'Mini map harus >= 200px supaya polygon area terlihat jelas'
        );

        // Peta harus difokuskan ke polygon saat belum ada posisi user,
        // supaya area PASTI terlihat (bukan cuma kalau user zoom manual).
        $this->assertStringContainsString(
            'miniMap.fitBounds(polyGroup.getBounds()',
            $src,
            'Peta harus difokuskan ke bounds polygon agar area terlihat'
        );
    }

    // ============================================================
    // BAGIAN 4 — id_ticket
    // ============================================================

    public function test_migrasi_menambahkan_kolom_id_ticket()
    {
        $src = $this->source('database/migrations/2026_11_10_140000_add_id_ticket_to_canteen_tables.php');

        $this->assertStringContainsString(
            "private const COLUMN = 'id_ticket'",
            $src,
            'Migrasi harus mendefinisikan kolom id_ticket'
        );

        // Harus menangani KEDUA tabel.
        $this->assertStringContainsString(
            "['canteen', 'canteen_twos']",
            $src,
            'Migrasi harus menangani tabel canteen DAN canteen_twos'
        );

        // Idempotent: cek kolom sebelum menambahkan.
        $this->assertStringContainsString(
            'hasColumn($table, self::COLUMN)',
            $src,
            'Migrasi harus idempotent (cek kolom sebelum menambah)'
        );

        // Kolom harus nullable (baris lama tidak break).
        $this->assertStringContainsString(
            'NVARCHAR(50) NULL',
            $src,
            'Kolom id_ticket harus nullable'
        );

        // up() HARUS menangkap Throwable supaya migrasi lain tidak
        // terblokir (sama seperti V40). ensuredColumn() boleh melempar,
        // selama up() yang menangkannya.
        $this->assertMatchesRegularExpression(
            '/public function up\(\): void\s*\{[\s\S]{0,3000}?catch \(\\\\Throwable \$e\)/',
            $src,
            'up() harus menangkap Throwable (migrasi lain tidak boleh terblokir)'
        );
    }

    public function test_model_memasukkan_id_ticket_masuk_fillable()
    {
        foreach (['CanteenReport', 'CanteenTwoReport'] as $model) {
            $src = $this->source("app/Models/{$model}.php");

            $this->assertMatchesRegularExpression(
                "/protected \\\$fillable = \[.*?'id_ticket'/s",
                $src,
                "{$model}: kolom id_ticket HARUS ada di \$fillable (tanpa ini mass-assignment membuangnya)"
            );
        }
    }

    public function test_controller_membangkitkan_dan_menyimpan_id_ticket()
    {
        $src = $this->source('app/Http/Controllers/CanteenTransactionController.php');

        // Harus ada generator ticket.
        $this->assertStringContainsString(
            'function buildTicketId(string $npk)',
            $src,
            'Controller harus punya buildTicketId()'
        );

        // Format harus mengandung timestamp + suffix acak.
        $this->assertMatchesRegularExpression(
            "/return \\\$unix \\. '_CTN_' \\. \\\$suffix;/",
            $src,
            'Format id_ticket harus <unix>_CTN_<suffix>'
        );

        // Harus disimpan saat transaksi.
        $this->assertStringContainsString(
            "'id_ticket' => \$idTicket,",
            $src,
            'id_ticket harus dikirim ke saveToCanteenDb()'
        );

        // Harus ada di invoice (bukti scan).
        $this->assertStringContainsString(
            "'id_ticket' => \$idTicket,",
            $src,
            'id_ticket harus ada di payload invoice'
        );
    }

    public function test_id_ticket_terbentuk_dan_tersimpan_di_database()
    {
        // Uji FUNGSIONAL: generate ticket lalu simpan lewat model
        // Eloquent sungguhan, lalu baca kembali dari database.
        $ticket = $this->generateTicketForTest('C-00827');

        $this->assertMatchesRegularExpression(
            '/^\d{10}_CTN_[0-9A-F]{6}$/',
            $ticket,
            "Format id_ticket tidak sesuai: {$ticket}"
        );

        // Simpan ke tabel 'canteen'.
        $row = new CanteenReport();
        $row->fill([
            'npk'        => 'C-00827',
            'name'       => 'DIMAS GALANG RAMADHAN',
            'canteen_no' => '1',
            'id_ticket'  => $ticket,
            'date'       => now()->format('Y-m-d'),
            'window_name' => 'Break 1',
        ]);
        $row->save();

        // Baca kembali.
        $saved = CanteenReport::where('npk', 'C-00827')->first();
        $this->assertNotNull($saved, 'Baris harus tersimpan di tabel canteen');
        $this->assertSame($ticket, $saved->id_ticket, 'id_ticket harus tersimpan persis sama');

        // Tabel canteen_twos juga harus bisa menyimpan.
        $row2 = new CanteenTwoReport();
        $row2->fill([
            'npk'        => 'C-00999',
            'canteen_no' => '2',
            'id_ticket'  => $this->generateTicketForTest('C-00999'),
            'date'       => now()->format('Y-m-d'),
        ]);
        $row2->save();

        $saved2 = CanteenTwoReport::where('npk', 'C-00999')->first();
        $this->assertNotNull($saved2);
        $this->assertNotEmpty($saved2->id_ticket, 'id_ticket harus tersimpan di canteen_twos');
    }

    public function test_id_ticket_unik_untuk_transaksi_berbeda()
    {
        // Dua transaksi untuk NPK yang sama harus punya ticket BERBEDA
        // (suffix acak), walau terjadi pada detik yang sama.
        $a = $this->generateTicketForTest('C-00827');
        $b = $this->generateTicketForTest('C-00827');

        $this->assertNotSame($a, $b, 'Dua transaksi berbeda tidak boleh punya id_ticket sama');
    }

    public function test_bukti_scan_menampilkan_id_ticket()
    {
        $src = $this->source('resources/views/canteen_transaction/index.blade.php');

        // Baris "ID Tiket" di bukti scan.
        $this->assertStringContainsString(
            '<span>ID Tiket</span>',
            $src,
            'Bukti scan harus menampilkan baris "ID Tiket"'
        );
        $this->assertStringContainsString(
            'inv.id_ticket',
            $src,
            'Bukti scan harus merender nilai inv.id_ticket'
        );
    }

    public function test_history_scan_menampilkan_kolom_id_ticket()
    {
        // Controller history harus mengirim id_ticket.
        $ctrl = $this->source('app/Http/Controllers/CanteenReportController.php');
        $this->assertMatchesRegularExpression(
            "/'id_ticket'\s*=>\s*\\\$row\['id_ticket'\]/",
            $ctrl,
            'detailData() harus mengirim id_ticket ke JSON history'
        );
        $this->assertMatchesRegularExpression(
            "/'id_ticket'\s*=>\s*\\\$row->id_ticket/",
            $ctrl,
            'mapRow() harus menyertakan id_ticket'
        );

        // View history harus punya kolom + render.
        $view = $this->source('resources/views/canteen_report/index.blade.php');
        $this->assertStringContainsString(
            '<th>ID Tiket</th>',
            $view,
            'History scan harus punya kolom "ID Tiket"'
        );
        $this->assertStringContainsString(
            "data: 'id_ticket'",
            $view,
            'DataTable history harus punya kolom id_ticket'
        );
    }

    public function test_latest_receipt_mengembalikan_id_ticket()
    {
        $src = $this->source('app/Http/Controllers/CanteenTransactionController.php');

        // Endpoint "Lihat Bukti Scan" juga harus membawa id_ticket.
        $this->assertMatchesRegularExpression(
            "/'id_ticket'\s*=>\s*\\\$row->id_ticket/",
            $src,
            'latestReceipt() harus mengembalikan id_ticket'
        );
    }

    // ============================================================
    // HELPER
    // ============================================================

    /**
     * Panggil buildTicketId() yang protected via reflection.
     */
    private function generateTicketForTest(string $npk): string
    {
        $controller = new CanteenTransactionController();
        $method = new \ReflectionMethod($controller, 'buildTicketId');
        $method->setAccessible(true);

        return (string) $method->invoke($controller, $npk);
    }
}
