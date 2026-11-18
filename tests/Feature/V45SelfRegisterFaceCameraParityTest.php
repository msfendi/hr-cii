<?php

namespace Tests\Feature;

use Tests\TestCase;

class V45SelfRegisterFaceCameraParityTest extends TestCase
{
    private function source(): string
    {
        $path = resource_path('views/face_recognition/self_register.blade.php');
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_step_four_uses_the_same_camera_resolution_as_face_attendance_login(): void
    {
        $source = $this->source();
        $stepFour = substr($source, strpos($source, 'async function startVerification()'));
        $stepFour = substr($stepFour, 0, strpos($stepFour, 'document.getElementById(\'btnRetake\').onclick'));

        $this->assertStringContainsString('width: { ideal: 640 }, height: { ideal: 480 }', $stepFour);
        $this->assertStringContainsString('facingMode: \'user\'', $stepFour);
        $this->assertMatchesRegularExpression('/video:\s*true,\s*audio:\s*false/', $stepFour);
        $this->assertStringNotContainsString('height: { ideal: 640 }', $stepFour);
    }

    public function test_step_four_uses_the_same_default_face_detector_options_as_login(): void
    {
        $source = $this->source();
        $this->assertStringContainsString('new faceapi.TinyFaceDetectorOptions()', $source);
        $this->assertStringNotContainsString('new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 })', $source);
    }

    public function test_step_four_tolerates_temporary_face_detection_misses(): void
    {
        $source = $this->source();
        $stepFour = substr($source, strpos($source, 'async function startVerification()'));
        $stepFour = substr($stepFour, 0, strpos($stepFour, "document.getElementById('btnRetake').onclick"));

        $this->assertStringContainsString('const DETECT_MISS_TOLERANCE = 12', $stepFour);
        $this->assertStringContainsString('randomChallengeStep4.update(null)', $stepFour);
        $this->assertStringContainsString('livenessStep4.update(null)', $stepFour);
        $this->assertStringContainsString('detectMissCount > DETECT_MISS_TOLERANCE', $stepFour);
    }

    public function test_biodata_values_are_readable_in_light_theme(): void
    {
        $path = resource_path('views/hris_v2/employee_biodata.blade.php');
        $this->assertFileExists($path);
        $css = (string) file_get_contents($path);

        // Nilai data harus memakai warna gelap saat mode terang aktif,
        // bukan #e6edf9 (hampir putih) yang menyatu dengan kartu putih.
        foreach (['.bio-val', '.bio-kv dd', '.bio-tel'] as $selector) {
            $this->assertMatchesRegularExpression(
                '/html\[data-v2-theme="light"\]\s+' . preg_quote($selector, '/') . '\s*\{[^}]*color:\s*#0f172a/',
                $css,
                "mode terang harus menetapkan warna gelap untuk {$selector}"
            );
        }

        $this->assertMatchesRegularExpression(
            '/html\[data-v2-theme="light"\]\s+\.bio-stat-lbl\s*,\s*html\[data-v2-theme="light"\]\s+\.bio-lbl\s*,\s*html\[data-v2-theme="light"\]\s+\.bio-kv dt\s*\{[^}]*color:\s*#1d4ed8/',
            $css,
            'label mode terang harus berwarna biru gelap yang kontras'
        );
    }

    public function test_bootstrap_badges_have_light_theme_colours(): void
    {
        $path = resource_path('views/hris_v2/layout.blade.php');
        $this->assertFileExists($path);
        $css = (string) file_get_contents($path);

        // Badge Bootstrap dipakai untuk status kontrak (badge-success /
        // badge-secondary). Tanpa override tema terang, warnanya tetap versi
        // latar gelap (teks mint #b6f3d8, teks #cfe0f5) dan tenggelam di kartu
        // putih: kontras terukur 1.10 - 1.34.
        $expected = [
            'badge-success' => '#065f46',
            'badge-secondary' => '#3f4d63',
        ];

        foreach ($expected as $variant => $color) {
            $this->assertMatchesRegularExpression(
                '/html\[data-v2-theme="light"\]\s+\.v2\s+\.' . $variant . '\s*\{[^}]*color:\s*' . preg_quote($color, '/') . '/',
                $css,
                "tema terang harus menetapkan teks {$color} untuk .v2 .{$variant}"
            );
        }

        // Warna tema gelap tidak boleh ikut diubah oleh perbaikan ini.
        $this->assertStringContainsString('.v2 .badge-success { background-color: rgba(52, 211, 153, .18); color: #b6f3d8; }', $css);
        $this->assertStringContainsString('.v2 .badge-secondary { background-color: rgba(255, 255, 255, .1); color: #cfe0f5; }', $css);
    }
}
