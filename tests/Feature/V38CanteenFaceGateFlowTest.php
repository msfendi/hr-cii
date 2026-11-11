<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * PERBAIKAN V38 (2026-11-10)
 *
 * Gejala yang dilaporkan user:
 *   "stuck hanya disini saja, wajah tidak terproses secara otomatis,
 *    random challenge tidak tertrigger padahal wajah sudah di posisi"
 *
 * Akar masalah (tiga, saling melengkapi):
 *
 *  1) RACE CONDITION LOADING SCRIPT
 *     Tag <script src ... defer async> TIDAK menjamin urutan eksekusi.
 *     Script inline (yang membuat `new RandomChallenge(...)`) dieksekusi
 *     saat parse -- sebelum random-challenge.js selesai dimuat. Akibatnya
 *     `typeof RandomChallenge === 'undefined'` -> randomChallenge = null
 *     -> blok challenge di loop deteksi dilewati -> randomChallengeVerified
 *     tetap false -> scanReady false -> auto-post TIDAK PERNAH jalan.
 *
 *  2) BANNER INSTRUKSI TANTANGAN DISEMBUNYIKAN
 *     CSS `#challengeAlertBox { display: none !important; }` (dari V37)
 *     membuat user tidak pernah melihat gerakan yang diminta
 *     ("Hadapkan wajah ke KIRI layar" / "BUKA MULUT"), sehingga
 *     challenge mustahil di-trigger.
 *
 *  3) TIDAK ADA FAIL-SAFE
 *     Kalau modul gerbang gagal dimuat, halaman tetap menampilkan teks
 *     generik "Transaksi diproses otomatis saat wajah terdeteksi." tanpa
 *     memberi tahu user apa pun.
 *
 * Test ini bersifat KONTRAK (contract test) -- tidak menyentuh database
 * sehingga tidak terkena timeout SQL Server remote.
 */
class V38CanteenFaceGateFlowTest extends TestCase
{
    private function canteenSource(): string
    {
        $path = resource_path('views/canteen_transaction/index.blade.php');
        $this->assertFileExists($path, 'View canteen_transaction/index.blade.php tidak ditemukan');
        return (string) file_get_contents($path);
    }

    private function serviceSource(): string
    {
        $path = app_path('Services/FaceRecognitionService.php');
        $this->assertFileExists($path, 'FaceRecognitionService.php tidak ditemukan');
        return (string) file_get_contents($path);
    }

    // ---------------------------------------------------------------
    // BAGIAN 1 -- race condition loading script
    // ---------------------------------------------------------------

    public function test_gate_scripts_must_not_use_async_attribute()
    {
        $src = $this->canteenSource();

        // Script yang mendefinisikan global LivenessCheck / RandomChallenge
        // WAJIB punya `defer` TANPA `async`.
        //
        // `async` membuat browser mengeksekusi script kapan saja selesai
        // diunduh, tanpa urutan. Script inline di bawahnya dieksekusi saat
        // parse, sehingga `new RandomChallenge(...)` bisa jalan SEBELUM
        // random-challenge.js terdefinisi -> randomChallenge = null.
        //
        // Catatan: pakai delimiter ~ dan TANPA preg_quote supaya karakter
        // '-' dan '.' di nama file tidak di-escape jadi pola yang tidak
        // cocok di PCRE.
        $scripts = [
            'face-api'    => '~<script[^>]*src=[^>]*vendor-face-api/face-api\.js[^>]*>~i',
            'liveness'    => '~<script[^>]*src=[^>]*js/liveness-check\.js[^>]*>~i',
            'challenge'   => '~<script[^>]*src=[^>]*js/random-challenge\.js[^>]*>~i',
        ];

        foreach ($scripts as $label => $pattern) {
            $this->assertSame(
                1,
                preg_match_all($pattern, $src, $m),
                "Tag script untuk {$label} harus ada tepat satu"
            );

            $tag = $m[0][0];

            $this->assertDoesNotMatchRegularExpression(
                '/\basync\b/',
                $tag,
                "Tag script {$label} TIDAK BOLEH pakai async (merusak urutan eksekusi): {$tag}"
            );

            $this->assertMatchesRegularExpression(
                '/\bdefer\b/',
                $tag,
                "Tag script {$label} harus memakai defer: {$tag}"
            );
        }
    }

    public function test_no_script_tag_in_canteen_view_uses_async()
    {
        $src = $this->canteenSource();

        // Tidak boleh ada tag script_gate yang async. Tag lain (sweetalert2)
        // boleh async karena tidak meny.define global yang dipakai inline.
        preg_match_all('/<script[^>]*src=[^>]*liveness-check\.js[^>]*>/i', $src, $m);
        $this->assertNotEmpty($m[0]);
        foreach ($m[0] as $tag) {
            $this->assertDoesNotMatchRegularExpression('/\basync\b/', $tag);
        }

        preg_match_all('/<script[^>]*src=[^>]*random-challenge\.js[^>]*>/i', $src, $m);
        $this->assertNotEmpty($m[0]);
        foreach ($m[0] as $tag) {
            $this->assertDoesNotMatchRegularExpression('/\basync\b/', $tag);
        }
    }

    // ---------------------------------------------------------------
    // BAGIAN 2 -- late binding gerbang (initFaceGates)
    // ---------------------------------------------------------------

    public function test_face_gates_are_initialised_on_dom_content_loaded()
    {
        $src = $this->canteenSource();

        // Fungsi initFaceGates WAJIB ada.
        $this->assertStringContainsString(
            'function initFaceGates()',
            $src,
            'Fungsi initFaceGates() wajib ada'
        );

        // WAJIB dijadwalkan via DOMContentLoaded (dijamin setelah semua
        // script `defer` dieksekusi).
        $this->assertMatchesRegularExpression(
            "/addEventListener\(\s*['\"]DOMContentLoaded['\"]\s*,\s*initFaceGates\s*\)/",
            $src,
            'initFaceGates() harus dijadwalkan via DOMContentLoaded'
        );

        // Variabel gerbang harus `let` (bisa diisi belakangan), BUKAN `const`
        // yang diinialisasi saat parse.
        $this->assertMatchesRegularExpression(
            '/let\s+randomChallenge\s*=\s*null\s*;/',
            $src,
            'randomChallenge harus di-deklarasikan dengan let lalu diisi initFaceGates()'
        );
        $this->assertMatchesRegularExpression(
            '/let\s+liveness\s*=\s*null\s*;/',
            $src,
            'liveness harus di-deklarasikan dengan let lalu diisi initFaceGates()'
        );

        // Pattern lama (const + ternary di parse time) harus hilang.
        //
        // Catatan: baris komentar DIBUANG dulu sebelum dicocokkan, karena
        // komentar dokumentasi sengaja menyebut pattern lama itu sebagai
        // penjelasan "sebelumnya".
        $code = $this->stripBladeComments($src);

        $this->assertDoesNotMatchRegularExpression(
            "/const\s+randomChallenge\s*=\s*\(\s*typeof\s+RandomChallenge\s*!==\s*['\"]undefined['\"]\s*\)/",
            $code,
            'Inisialisasi const randomChallenge saat parse harus dihapus'
        );
        $this->assertDoesNotMatchRegularExpression(
            "/const\s+liveness\s*=\s*\(\s*typeof\s+LivenessCheck\s*!==\s*['\"]undefined['\"]\s*\)/",
            $code,
            'Inisialisasi const liveness saat parse harus dihapus'
        );
    }

    /**
     * Buang baris komentar JS (// ...) dan komentar blok /* ... *\/ dari
     * source Blade, supaya assertion hanya melihat kode yang benar-benar
     * dieksekusi.
     */
    private function stripBladeComments(string $src): string
    {
        $out = preg_replace('#/\*.*?\*/#s', '', $src);
        $lines = explode("\n", (string) $out);
        $kept = [];
        foreach ($lines as $line) {
            // Buang baris yang seluruhnya komentar.
            if (preg_match('#^\s*//#', $line)) {
                continue;
            }
            // Buang sisa baris setelah tanda // (hanya jika tidak di dalam string).
            $pos = strpos($line, '//');
            if ($pos !== false) {
                $before = substr($line, 0, $pos);
                // Heuristik: kalau quote tunggal di bagian sebelum, kemungkinan
                // string yang memuat '//' -- biarkan.
                if (substr_count($before, "'") % 2 === 0 && substr_count($before, '"') % 2 === 0) {
                    $line = $before;
                }
            }
            $kept[] = $line;
        }
        return implode("\n", $kept);
    }

    public function test_init_face_gates_instantiates_random_challenge_with_server_target()
    {
        $src = $this->canteenSource();

        $this->assertStringContainsString(
            'randomChallenge = new RandomChallenge({',
            $src,
            'initFaceGates() harus membuat objek RandomChallenge'
        );
        $this->assertStringContainsString(
            'target: RANDOM_CHALLENGE_TYPE',
            $src,
            'RandomChallenge harus memakai target dari server'
        );
        $this->assertStringContainsString(
            'mirrored: true',
            $src,
            'RandomChallenge harus mirrored=true (preview kamera di-mirror)'
        );
    }

    // ---------------------------------------------------------------
    // BAGIAN 3 -- banner instruksi tantangan harus terlihat
    // ---------------------------------------------------------------

    public function test_challenge_instruction_banner_is_not_hidden_by_css()
    {
        $src = $this->canteenSource();

        // Banner instruksi tantangan WAJIB terlihat. V37 sempat
        // menyembunyikannya sehingga user tidak tahu harus bergerak apa.
        $this->assertDoesNotMatchRegularExpression(
            '/#challengeAlertBox\s*,\s*#livenessAlertBox\s*\{[^}]*display\s*:\s*none/i',
            $src,
            'Banner #challengeAlertBox tidak boleh disembunyikan bersama banner liveness'
        );

        // Aturan hide untuk challengeAlertBox TIDAK BOLEH ada sama sekali.
        $this->assertDoesNotMatchRegularExpression(
            '/#challengeAlertBox\s*\{[^}]*display\s*:\s*none/i',
            $src,
            'Banner #challengeAlertBox tidak boleh punya display:none'
        );

        // Badge tetap disembunyikan (permintaan V37: "Liveness dan Tantangan
        // di hide saja cukup tampilkan gps dan mode").
        $this->assertStringContainsString(
            '.d-hide-canteen-verify-badge { display: none !important; }',
            $src,
            'Badge Liveness/Tantangan tetap harus disembunyikan'
        );
    }

    public function test_challenge_hint_is_written_from_random_challenge_hints_map()
    {
        $src = $this->canteenSource();

        // Instruksi spesifik harus diambil dari RandomChallenge.HINTS.
        $this->assertStringContainsString(
            'RandomChallenge.HINTS',
            $src,
            'Instruksi tantangan harus diambil dari RandomChallenge.HINTS'
        );
        $this->assertStringContainsString(
            'challengeInstructionText',
            $src,
            'Instruksi tantangan harus ditulis ke #challengeInstructionText'
        );
        $this->assertStringContainsString(
            'challengeAlertBox',
            $src,
            'Banner tantangan harus dirujuk oleh initFaceGates()'
        );
    }

    // ---------------------------------------------------------------
    // BAGIAN 4 -- fail-safe (jangan diam kalau modul gagal dimuat)
    // ---------------------------------------------------------------

    public function test_fail_safe_message_when_gate_module_fails_to_load()
    {
        $src = $this->canteenSource();

        $this->assertStringContainsString(
            'faceGatesReady',
            $src,
            'Flag faceGatesReady wajib ada untuk fail-safe'
        );
        $this->assertStringContainsString(
            'gagal dimuat',
            $src,
            'Pesan error "gagal dimuat" wajib ada (bukan diam-diam)'
        );
        $this->assertStringContainsString(
            'Ctrl+F5',
            $src,
            'Pesan error harus menyuruh user reload (Ctrl+F5)'
        );

        // Loop deteksi harus mengecek fail-safe sebelum auto-post.
        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*!faceGatesReady\s*&&/',
            $src,
            'Loop deteksi harus mengecek !faceGatesReady sebelum lanjut'
        );
    }

    public function test_server_returns_challenge_hint()
    {
        $src = $this->serviceSource();

        $this->assertStringContainsString(
            'RANDOM_CHALLENGE_HINTS',
            $src,
            'Peta RANDOM_CHALLENGE_HINTS wajib ada di service'
        );
        $this->assertStringContainsString(
            "'hint'",
            $src,
            "issueRandomChallenge() harus mengembalikan key 'hint'"
        );

        // Semua jenis challenge punya hint.
        foreach (['left', 'right', 'mouth'] as $type) {
            $this->assertMatchesRegularExpression(
                "/'" . $type . "'\s*=>/",
                $src,
                "Challenge '{$type}' harus punya hint"
            );
        }
    }

    // ---------------------------------------------------------------
    // BAGIAN 5 -- verifikasi fungsi challenge masih dipakai di loop
    // ---------------------------------------------------------------

    public function test_detection_loop_still_calls_random_challenge_update()
    {
        $src = $this->canteenSource();

        // Loop deteksi WAJIB tetap memanggil randomChallenge.update(),
        // kalau tidak challenge tidak akan pernah dihitung.
        $this->assertStringContainsString(
            'randomChallenge.update(det.landmarks)',
            $src,
            'Loop deteksi harus memanggil randomChallenge.update(det.landmarks)'
        );

        // Dan WAJIB menandai verifikasi berhasil agar scanReady true.
        $this->assertStringContainsString(
            'randomChallengeVerified = true;',
            $src,
            'Loop deteksi harus menandai randomChallengeVerified = true'
        );
    }
}
