<?php

/**
 * Uji live: generate PDF Surat Ijin Dinas Luar + endpoint manage recap.
 *
 * Jalankan: php8.3 artisan tinker --execute="require 'tests/manual/live_check_wa_pdf.php';"
 */

use App\Models\User;
use App\Models\WorkAssignmentRequest;
use App\Services\WorkAssignment\ApprovalChainBuilder;
use App\Services\WorkAssignment\WorkAssignmentApproval;
use Illuminate\Support\Facades\DB;

$fail = 0;
$npk  = 'C-00825';

$assert = function (string $label, bool $ok) use (&$fail) {
    echo ($ok ? '  OK   ' : '  FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $fail++;
    }
};

WorkAssignmentRequest::where('npk', $npk)->delete();

$chain = ApprovalChainBuilder::build(
    DB::connection('cii')->table('BIODATA')->where('NPK', $npk)->value('ID_DEPT'),
    DB::connection('cii')->table('BIODATA')->where('NPK', $npk)->value('SECTION')
);

$req = WorkAssignmentRequest::create([
    'token' => 'ZWAPDF' . substr(md5(uniqid('', true)), 0, 10),
    'npk' => $npk,
    'duty_dates' => ['2026-12-08', '2026-12-09', '2026-12-10'],
    'start_date' => '2026-12-08', 'end_date' => '2026-12-10', 'total_days' => 3,
    'dinas_ke' => 'Kantor Pusat Jakarta',
    'keperluan' => 'Rapat dengan klien terkait perpanjangan kontrak kerja',
    'jam_keluar' => '08:00', 'rencana_kembali' => '17:00', 'jam_kembali' => null,
    'approval_chain' => $chain, 'answers' => [],
    'status' => 'pending', 'current_level' => 1, 'void' => 'false', 'submitted_by' => $npk,
]);

// Simulasi L1 sudah approve supaya tanda tangan terisi.
WorkAssignmentApproval::approve($req, $chain[0]['npk'], 1, 'Setuju');

echo '═══ 1. Generate PDF ═══' . PHP_EOL;
$user = User::where('npk', $chain[0]['npk'])->first();
\Illuminate\Support\Facades\Auth::login($user);

$pdfController = new \App\Http\Controllers\WorkAssignmentPdfController();
$response = $pdfController->show($req->id);
$content  = $response->getContent();

echo '  status: ' . $response->getStatusCode() . PHP_EOL;
echo '  content-type: ' . $response->headers->get('Content-Type') . PHP_EOL;
echo '  ukuran: ' . strlen($content) . ' bytes' . PHP_EOL;
echo '  signature: ' . substr($content, 0, 8) . PHP_EOL;

$assert('status 200', $response->getStatusCode() === 200);
$assert('content-type application/pdf', $response->headers->get('Content-Type') === 'application/pdf');
$assert('PDF signature valid', substr($content, 0, 4) === '%PDF');
// dompdf meng-subset font, jadi PDF valid bisa cuma ~3-5KB.
$assert('ukuran wajar (>2KB)', strlen($content) > 2000);
$assert('mengandung teks form', str_contains($content, 'SURAT IJIN DINAS LUAR') || str_contains($content, 'IJIN'));

// Simpan ke file supaya bisa dicek visual.
file_put_contents('/tmp/wa_surat_ijin_dinas.pdf', $content);
echo '  disimpan: /tmp/wa_surat_ijin_dinas.pdf' . PHP_EOL;

echo PHP_EOL . '═══ 2. Manage recap — daftar semua pengajuan ═══' . PHP_EOL;
$recap = new \App\Http\Controllers\WorkAssignmentRecapController();
$payload = json_decode($recap->getData(new \Illuminate\Http\Request())->getContent(), true);

$mine = collect($payload['data'] ?? [])->firstWhere('id', $req->id);
echo '  items: ' . count($payload['data'] ?? []) . PHP_EOL;
$assert('pengajuan muncul', (bool) $mine);
$assert('nama terisi', ($mine['nama'] ?? '') !== '' && ($mine['nama'] ?? '') !== $npk);
$assert('3 tanggal dinas', count($mine['duty_dates'] ?? []) === 3);
$assert('progress 1 dari total', ($mine['done'] ?? -1) === 1);
$assert('status pending', ($mine['status'] ?? '') === 'pending');

echo PHP_EOL . '═══ 3. Detail recap ═══' . PHP_EOL;
$detail = json_decode($recap->detail($req->id)->getContent(), true);
$assert('detail sukses', ($detail['success'] ?? false) === true);
$assert('timeline lengkap', count($detail['data']['timeline'] ?? []) === count($chain));
$assert('L1 tercatat approved', ($detail['data']['timeline'][0]['decision'] ?? '') === 'approved');

echo PHP_EOL . '═══ 4. Hapus pengajuan yang belum selesai ═══' . PHP_EOL;
$del = json_decode($recap->destroy(new \Illuminate\Http\Request(), $req->id)->getContent(), true);
echo '  ' . json_encode($del) . PHP_EOL;
$assert('hapus sukses', ($del['success'] ?? false) === true);
$assert('mode deleted', ($del['mode'] ?? '') === 'deleted');
$assert('row hilang', WorkAssignmentRequest::where('id', $req->id)->count() === 0);

\Illuminate\Support\Facades\Auth::logout();

echo PHP_EOL . '═══ CLEANUP ═══' . PHP_EOL;
$del2 = WorkAssignmentRequest::where('npk', $npk)->delete();
echo "  dihapus: {$del2}" . PHP_EOL;
echo '  sisa: ' . WorkAssignmentRequest::where('npk', $npk)->count() . PHP_EOL;

echo PHP_EOL . ($fail === 0 ? '✅ SEMUA SKENARIO LULUS' : "❌ {$fail} ASSERTION GAGAL") . PHP_EOL;

return $fail;
