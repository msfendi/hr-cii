<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Blade;

/**
 * V39 — Canteen auto-post tidak pernah terkirim ("stuck tidak terpost").
 *
 * BUG (V32–V38): pemanggil auto-post menyetel `autoPostInFlight = true`
 * SEBELUM memanggil `runAutoScan()`, sementara `runAutoScan()` sendiri punya
 * guard `if (isPosting || autoPostInFlight) return;`. Akibatnya fungsi
 * selalu early-return: tidak ada fetch, tidak ada error, tidak ada exception.
 * UI hanya menampilkan "Tantangan terverifikasi. Memproses transaksi
 * otomatis..." lalu diam. Flag juga tidak pernah di-reset (hanya di
 * stopCamera()/resetFaceGates()), sehingga auto-post tidak pernah retry.
 *
 * Test ini memeriksa STRUKTUR kode pada view Blade (tanpa DB remote) —
 * padanan test fungsional yang tidak bisa dilakukan tanpa kamera + model.
 */
class V39CanteenAutoPostFlowTest extends TestCase
{
    /** @var string */
    private $blade;

    protected function setUp(): void
    {
        parent::setUp();
        $path = resource_path('views/canteen_transaction/index.blade.php');
        $this->assertFileExists($path, 'View canteen tidak ditemukan: ' . $path);
        $this->blade = (string) file_get_contents($path);
    }

    /**
     * Helper: potong blok JS mulai dari marker sampai marker akhir.
     *
     * Memakai kemunculan TERAKHIR dari marker awal. Beberapa marker (mis.
     * trigger auto-post) muncul lebih dari sekali di view: satu di hook
     * debug `window.__canteenAutoPost` dan satu lagi di loop deteksi
     * produksi. Loop produksi adalah yang paling akhir, jadi itu yang diuji.
     */
    private function slice(string $from, string $to): string
    {
        $i = strrpos($this->blade, $from);
        $this->assertNotFalse($i, "Marker awal tidak ditemukan: {$from}");
        $j = strpos($this->blade, $to, $i);
        $this->assertNotFalse($j, "Marker akhir tidak ditemukan: {$to}");
        return substr($this->blade, $i, $j - $i);
    }

    /**
     * REGRESSION UTAMA: guard runAutoScan() tidak boleh mengecek
     * autoPostInFlight, karena flag itu diset oleh pemanggil yang sama.
     */
    public function test_run_auto_scan_guard_does_not_check_auto_post_in_flight(): void
    {
        $body = $this->slice('async function runAutoScan(){', "\n}\n\n// SweetAlert untuk transaksi duplikat");

        $this->assertDoesNotMatchRegularExpression(
            '/if\s*\(\s*isPosting\s*\|\|\s*autoPostInFlight\s*\)\s*return/',
            $body,
            'Guard runAutoScan() masih mengecek autoPostInFlight. Pemanggil '
            . 'menyetel flag itu sebelum memanggil, jadi fungsi selalu early-return '
            . 'dan transaksi tidak pernah terpost.'
        );
    }

    /**
     * Guard yang boleh dipakai hanya flag yang di-set DI DALAM fungsi.
     */
    public function test_run_auto_scan_still_guards_against_double_post(): void
    {
        $body = $this->slice('async function runAutoScan(){', "\n}\n\n// SweetAlert untuk transaksi duplikat");

        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*isPosting\s*\)\s*return/',
            $body,
            'Proteksi double-post sisi client (isPosting) harus tetap ada.'
        );
    }

    /**
     * Pemanggil tidak boleh menyetel autoPostInFlight sebelum memanggil.
     */
    public function test_detection_loop_does_not_pre_set_auto_post_in_flight(): void
    {
        $loop = $this->slice(
            'if (!isPosting && !autoPostInFlight && scanReady && stableFrames >= 3) {',
            "\n    } else {\n      // Wajah tidak terdeteksi"
        );

        // Di dalam blok pemicu, tidak boleh ada assignment flag in-flight.
        $this->assertDoesNotMatchRegularExpression(
            '/autoPostInFlight\s*=\s*true/',
            $loop,
            'Loop deteksi masih menyetel autoPostInFlight sebelum memanggil '
            . 'runAutoScan(); itu yang menyebabkan early-return.'
        );

        $this->assertMatchesRegularExpression(
            '/runAutoScan\s*\(\s*\)\s*;/',
            $loop,
            'Loop deteksi harus tetap memanggil runAutoScan().'
        );
    }

    /**
     * Flag in-flight harus di-set DI DALAM fungsi, setelah semua guard.
     */
    public function test_in_flight_flag_is_set_inside_run_auto_scan(): void
    {
        $body = $this->slice('async function runAutoScan(){', "\n}\n\n// SweetAlert untuk transaksi duplikat");

        $this->assertMatchesRegularExpression(
            '/autoPostInFlight\s*=\s*true\s*;/',
            $body,
            'autoPostInFlight harus di-set di dalam runAutoScan().'
        );
    }

    /**
     * Penanda "sedang memproses" harus di-set DI DALAM try, supaya exception
     * di antara set flag dan blok try tidak meninggalkan flag nyangkut true.
     */
    public function test_posting_flags_are_set_inside_try_block(): void
    {
        $body = $this->slice('async function runAutoScan(){', "\n}\n\n// SweetAlert untuk transaksi duplikat");

        $tryPos = strpos($body, 'try {');
        $this->assertNotFalse($tryPos, 'Blok try tidak ditemukan di runAutoScan().');

        $flagPos = strpos($body, 'autoPostInFlight = true;');
        $this->assertNotFalse($flagPos, 'Assignment autoPostInFlight tidak ditemukan.');

        $this->assertGreaterThan(
            $tryPos,
            $flagPos,
            'autoPostInFlight harus di-set setelah blok try dimulai, supaya '
            . 'finally selalu bisa meresetnya.'
        );
    }

    /**
     * Flag harus selalu di-reset di finally, supaya retry bisa terjadi.
     */
    public function test_in_flight_flag_is_reset_in_finally(): void
    {
        $body = $this->slice('async function runAutoScan(){', "\n}\n\n// SweetAlert untuk transaksi duplikat");

        $this->assertMatchesRegularExpression(
            '/finally\s*\{[^}]*autoPostInFlight\s*=\s*false\s*;/',
            $body,
            'autoPostInFlight harus di-reset di finally supaya auto-post '
            . 'bisa mencoba lagi setelah kegagalan.'
        );
    }

    /**
     * Kegagalan jaringan tidak boleh diam — user harus melihat status.
     */
    public function test_catch_block_reports_failure_to_user(): void
    {
        $body = $this->slice('async function runAutoScan(){', "\n}\n\n// SweetAlert untuk transaksi duplikat");

        $this->assertMatchesRegularExpression(
            '/catch\s*\(err\)\s*\{[^}]*setStatus\s*\(/',
            $body,
            'Blok catch harus menampilkan status error, bukan diam.'
        );
    }

    /**
     * POST transaksi harus benar-benar ada di jalur auto-post.
     */
    public function test_auto_post_performs_real_fetch_to_store_route(): void
    {
        $body = $this->slice('async function runAutoScan(){', "\n}\n\n// SweetAlert untuk transaksi duplikat");

        $this->assertMatchesRegularExpression(
            '/fetch\s*\(\s*@json\s*\(\s*route\s*\(\s*[\'"]canteen-transaction\.store[\'"]/',
            $body,
            'runAutoScan() harus melakukan fetch ke route canteen-transaction.store.'
        );

        $this->assertMatchesRegularExpression(
            "/method\s*:\s*['\"]POST['\"]/",
            $body,
            'Permintaan transaksi harus memakai method POST.'
        );
    }

    /**
     * Gate challenge tetap fail-closed di dalam runAutoScan.
     */
    public function test_challenge_gate_is_rechecked_inside_auto_post(): void
    {
        $body = $this->slice('async function runAutoScan(){', "\n}\n\n// SweetAlert untuk transaksi duplikat");

        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*RANDOM_CHALLENGE_REQUIRED\s*&&\s*!randomChallengeVerified\s*\)\s*return/',
            $body,
            'Gerbang random challenge harus dicek ulang di dalam runAutoScan() '
            . '(fail-closed).'
        );
    }

    /**
     * Fail-closed untuk data form: kantin harus dipilih, NPK harus
     * tervalidasi, sebelum request dikirim. Tanpa ini auto-post menembak
     * ke server dan hanya mendapat 422 yang membingungkan user.
     */
    public function test_auto_post_requires_selected_canteen(): void
    {
        $body = $this->slice('async function runAutoScan(){', "\n}\n\n// SweetAlert untuk transaksi duplikat");

        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*!selectedCanteenId\s*\)\s*\{/',
            $body,
            'Auto-post harus menolak jalan bila kantin belum dipilih.'
        );
    }

    public function test_auto_post_requires_validated_npk_when_required(): void
    {
        $body = $this->slice('async function runAutoScan(){', "\n}\n\n// SweetAlert untuk transaksi duplikat");

        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*NPK_REQUIRED\s*&&\s*!validatedNpk\s*\)\s*\{/',
            $body,
            'Auto-post harus menolak jalan bila NPK wajib tapi belum divalidasi.'
        );
    }

    /**
     * Fetch harus punya batas waktu supaya request yang menggantung
     * (mis. SQL Server timeout) tidak membuat UI diam selamanya.
     */
    public function test_fetch_has_timeout_via_abort_controller(): void
    {
        $body = $this->slice('async function runAutoScan(){', "\n}\n\n// SweetAlert untuk transaksi duplikat");

        $this->assertMatchesRegularExpression(
            '/new AbortController\s*\(\s*\)/',
            $body,
            'Fetch transaksi harus memakai AbortController.'
        );

        $this->assertMatchesRegularExpression(
            '/setTimeout\s*\(\s*\(\s*\)\s*=>\s*controller\.abort\s*\(\s*\)\s*,\s*[A-Za-z_$][\w$]*\s*\)/',
            $body,
            'AbortController harus dijadwalkan dengan timeout eksplisit.'
        );

        $this->assertMatchesRegularExpression(
            '/signal\s*:\s*controller\.signal/',
            $body,
            'Fetch harus meneruskan signal dari AbortController.'
        );

        $this->assertMatchesRegularExpression(
            '/clearTimeout\s*\(\s*timeoutId\s*\)/',
            $body,
            'Timeout harus dibersihkan agar tidak membocorkan timer.'
        );
    }

    /**
     * Kegagalan timeout harus punya pesan yang jelas, bukan "kesalahan
     * jaringan" generik.
     */
    public function test_abort_error_has_dedicated_message(): void
    {
        $body = $this->slice('async function runAutoScan(){', "\n}\n\n// SweetAlert untuk transaksi duplikat");

        $this->assertMatchesRegularExpression(
            '/AbortError/',
            $body,
            'Blok catch harus mengenali AbortError.'
        );

        $this->assertMatchesRegularExpression(
            '/tidak merespons/i',
            $body,
            'AbortError harus punya pesan yang menjelaskan server tidak merespons.'
        );
    }

    /**
     * Timeout default harus bernilai wajar (bukan 0 / tak terdefinisi),
     * supaya abort benar-benar terjadi.
     */
    public function test_default_timeout_is_a_positive_number(): void
    {
        $this->assertMatchesRegularExpression(
            '/const\s+AUTO_POST_TIMEOUT_MS\s*=\s*\d+\s*;/',
            $this->blade,
            'AUTO_POST_TIMEOUT_MS harus berisi nilai numerik dalam milidetik.'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/const\s+AUTO_POST_TIMEOUT_MS\s*=\s*0\s*;/',
            $this->blade,
            'Timeout tidak boleh 0 (langsung abort tanpa kesempatan request).'
        );
    }

    /**
     * View harus tetap bisa dikompilasi Blade (sanity check struktur).
     */
    public function test_blade_view_compiles(): void
    {
        $compiled = Blade::compileString($this->blade);
        $this->assertNotEmpty($compiled);
        $this->assertStringContainsString('runAutoScan', $compiled);
    }
}
