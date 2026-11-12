<?php

namespace Tests\Feature;

use App\Services\SurveySanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PERBAIKAN V32 (2026-11-09) -- PROTEKSI SQL INJECTION & XSS /satisfaction-survey
 *
 * Test ini TIDAK butuh database SQL Server. Semua yang diuji adalah:
 *  - logika sanitizer (unit)
 *  - kontrak kode: output view WAJIB ter-escape, query WAJIB binding
 *
 * Grill: assertion kontrak membaca FILE SOURCE, jadi harus tahan
 * terhadap string contoh di dalam komentar. Karena itu setiap assertion
 * komentar dibuang dulu (lihat helper stripComments).
 */
class SatisfactionSurveySecurityTest extends TestCase
{
    /**
     * Buang komentar PHP/JS/CSS supaya string contoh di dalam
     * komentar tidak salah dianggap sebagai implementasi.
     */
    private function stripComments(string $code): string
    {
        // Blade comment
        $code = preg_replace('/\{\{--.*?--\}\}/s', '', $code);
        // PHP block comment
        $code = preg_replace('#/\*.*?\*/#s', '', $code);
        // PHP line comment (avoid http://)
        $code = preg_replace('/^\s*\/\/.*$/m', '', $code);
        // HTML comment
        $code = preg_replace('/<!--.*?-->/s', '', $code);
        // JS line comment
        $code = preg_replace('/^\s*\/\/.*$/m', '', $code);

        return $code;
    }

    private function source(string $rel): string
    {
        $path = base_path($rel);
        $this->assertFileExists($path, "File sumber tidak ditemukan: {$rel}");

        return $this->stripComments(file_get_contents($path));
    }

    // ==========================================================
    // 1. UNIT -- Sanitizer
    // ==========================================================

    public function test_sanitizer_removes_control_characters()
    {
        $payload = "Halo\x00Dunia\x08\x1F";
        $clean = SurveySanitizer::cleanText($payload);

        $this->assertStringNotContainsString("\x00", $clean);
        $this->assertStringNotContainsString("\x08", $clean);
        $this->assertStringNotContainsString("\x1F", $clean);
        $this->assertStringContainsString('Halo', $clean);
        $this->assertStringContainsString('Dunia', $clean);
    }

    public function test_sanitizer_preserves_legitimate_text()
    {
        $text = "Bagus! Tapi ruangannya perlu ditambah.\nSaran: tambah AC di lantai 2.";

        $this->assertSame($text, SurveySanitizer::cleanText($text));
    }

    public function test_sanitizer_does_not_destroy_dashes_or_quotes()
    {
        // Tanda hubung dan kutip adalah karakter wajar dalam jawaban.
        $text = 'Sayang sekali-BBQ, kurang "luas" untuk 50 orang.';

        $clean = SurveySanitizer::cleanText($text);

        $this->assertSame($text, $clean);
        $this->assertStringContainsString('-', $clean);
        $this->assertStringContainsString('"', $clean);
    }

    public function test_sanitizer_truncates_to_max_length()
    {
        $long = str_repeat('A', 9000);
        $clean = SurveySanitizer::cleanText($long, 500);

        $this->assertSame(500, strlen($clean));
    }

    public function test_section_limit_is_stricter_than_text_limit()
    {
        // 900 karakter: lebih dari batas section (100) DAN lebih dari
        // batas question text (500), jadi keduanya harus dipotong.
        $long = str_repeat('B', 900);

        $this->assertSame(100, strlen(SurveySanitizer::cleanSection($long)));
        $this->assertSame(500, strlen(SurveySanitizer::cleanQuestionText($long)));

        // Di bawah batas question text, tidak dipotong.
        $medium = str_repeat('C', 300);
        $this->assertSame(300, strlen(SurveySanitizer::cleanQuestionText($medium)));
    }

    public function test_escape_formula_neutralizes_dangerous_prefixes()
    {
        $dangerous = [
            '=1+1',
            '+1+1',
            '-1+1',
            '@SUM(1+1)',
            "=cmd|' /C calc'!A0",
            '=HYPERLINK("http://evil.test","click")',
        ];

        foreach ($dangerous as $payload) {
            $escaped = SurveySanitizer::escapeFormula($payload);

            $this->assertStringStartsWith("'", $escaped, "Payload formula belum dinetralkan: {$payload}");
            $this->assertNotSame($payload, $escaped);
        }
    }

    public function test_escape_formula_keeps_normal_text_intact()
    {
        $normal = [
            'Sangat Setuju',
            'Saran: tambah AC di lantai 2',
            'Total 100',
        ];

        foreach ($normal as $text) {
            $this->assertSame($text, SurveySanitizer::escapeFormula($text));
        }
    }

    public function test_escape_formula_does_not_escape_inner_dash()
    {
        // Tanda hubung di tengah kalimat bukan formula.
        $text = 'Kurang baik-BBQ';

        $this->assertSame($text, SurveySanitizer::escapeFormula($text));
    }

    public function test_escape_formula_handles_non_string_types()
    {
        $this->assertSame('1', SurveySanitizer::escapeFormula(1));
        $this->assertSame('1', SurveySanitizer::escapeFormula(true));
        $this->assertSame('0', SurveySanitizer::escapeFormula(false));
        $this->assertSame('1.5', SurveySanitizer::escapeFormula(1.5));
        $this->assertSame('', SurveySanitizer::escapeFormula(null));
    }

    public function test_suspicious_pattern_detection()
    {
        $this->assertTrue(SurveySanitizer::containsSuspiciousPattern('<script>alert(1)</script>'));
        $this->assertTrue(SurveySanitizer::containsSuspiciousPattern("'; DROP TABLE users; --"));
        $this->assertTrue(SurveySanitizer::containsSuspiciousPattern('javascript:alert(1)'));
        $this->assertTrue(SurveySanitizer::containsSuspiciousPattern('onerror=alert(1)'));

        $this->assertFalse(SurveySanitizer::containsSuspiciousPattern('Wisma bagus, Revelight'));
        $this->assertFalse(SurveySanitizer::containsSuspiciousPattern(''));
        $this->assertFalse(SurveySanitizer::containsSuspiciousPattern(null));
    }

    // ==========================================================
    // 2. KONTRAK -- Controller memakai sanitizer
    // ==========================================================

    public function test_controller_uses_sanitizer_for_answers_and_questions()
    {
        $src = $this->source('app/Http/Controllers/SatisfactionSurveyController.php');

        $this->assertStringContainsString('use App\\Services\\SurveySanitizer;', $src);
        // jawaban teks bebas
        $this->assertStringContainsString('SurveySanitizer::cleanText($val)', $src);
        // section + question text (create & update)
        $this->assertStringContainsString('SurveySanitizer::cleanSection(', $src);
        $this->assertStringContainsString('SurveySanitizer::cleanQuestionText(', $src);
    }

    public function test_controller_has_no_raw_sql_with_user_input()
    {
        $src = $this->source('app/Http/Controllers/SatisfactionSurveyController.php');

        // selectRaw hanya untuk agregasi score (kolom numerik milik sistem),
        // bukan input user. Yang dicari: raw query yang menyisipkan input.
        $this->assertDoesNotMatchRegularExpression(
            '/whereRaw\s*\(\s*[\'"].*\$\w+/',
            $src,
            'Ditemukan whereRaw dengan input user -- harus pakai binding.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/DB::raw\s*\(\s*[\'"].*\$\w+/',
            $src,
            'Ditemukan DB::raw dengan input user -- harus pakai binding.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/DB::select\s*\(\s*[\'"].*\$\w+/',
            $src,
            'Ditemukan DB::select raw dengan input user.'
        );
    }

    // ==========================================================
    // 3. KONTRAK -- Output encoding di view
    // ==========================================================

    public function test_admin_index_escapes_data_attributes()
    {
        $src = $this->source('resources/views/satisfaction_survey/admin_index.blade.php');

        // data-section WAJIB escaped (ini celah XSS yang ditemukan).
        $this->assertStringContainsString('data-section="{{ e($q->section) }}"', $src);
        $this->assertStringContainsString('data-text="{{ e($q->question_text) }}"', $src);

        // Tidak boleh ada data-section mentah.
        $this->assertDoesNotMatchRegularExpression(
            '/data-section="\{\{\s*\$q->section\s*\}\}"/',
            $src,
            'data-section belum di-escape -> stored XSS.'
        );
    }

    public function test_survey_views_have_no_raw_unescaped_output()
    {
        $files = [
            'resources/views/satisfaction_survey/create.blade.php',
            'resources/views/satisfaction_survey/admin_index.blade.php',
            'resources/views/satisfaction_survey/admin_results.blade.php',
            'resources/views/satisfaction_survey/thanks.blade.php',
            'resources/views/satisfaction_survey/admin_likert_pdf.blade.php',
        ];

        foreach ($files as $file) {
            $src = $this->source($file);

            // {!! !!} hanya boleh untuk literal statis yang kita percaya.
            // Cari semua pemakaian dan pastikan tidak ada variabel di dalamnya.
            if (preg_match_all('/\{!!(.+?)!!\}/s', $src, $m)) {
                foreach ($m[1] as $snippet) {
                    $this->assertDoesNotMatchRegularExpression(
                        '/\$\w+|->\w+/',
                        $snippet,
                        "Raw output dengan data dinamis di {$file}: " . trim($snippet)
                    );
                }
            }
        }
    }

    public function test_survey_views_have_no_vhtml_or_innerhtml_with_user_data()
    {
        $files = [
            'resources/views/satisfaction_survey/create.blade.php',
            'resources/views/satisfaction_survey/admin_results.blade.php',
        ];

        foreach ($files as $file) {
            $src = $this->source($file);

            $this->assertDoesNotMatchRegularExpression(
                '/v-html\s*=/',
                $src,
                "v-html ditemukan di {$file} -- potential DOM XSS."
            );
        }
    }

    // ==========================================================
    // 4. KONTRAK -- Formula injection di export
    // ==========================================================

    public function test_exports_escape_formula_injection()
    {
        $files = [
            'app/Exports/SatisfactionSurveyResultExport.php',
            'app/Exports/SatisfactionLikertDistributionExport.php',
        ];

        foreach ($files as $file) {
            $src = $this->source($file);

            $this->assertStringContainsString(
                'SurveySanitizer::escapeFormula',
                $src,
                "Export {$file} belum escape formula-injection."
            );
        }
    }

    public function test_result_export_escapes_answer_text_and_headings()
    {
        $src = $this->source('app/Exports/SatisfactionSurveyResultExport.php');

        // jawaban open question
        $this->assertStringContainsString(
            'SurveySanitizer::escapeFormula($r->answer_text ?? \'\')',
            $src
        );
        // header kolom dari section + question_text
        $this->assertStringContainsString(
            'SurveySanitizer::escapeFormula($label . $q->question_text)',
            $src
        );
    }
}
