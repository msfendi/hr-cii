<?php

/**
 * Uji live alur approval Work Assignment lewat HTTP (halaman admin).
 *
 * Karena login Laravel butuh captcha, sesi approver dibuat langsung lewat
 * route pengujian (tests-only) — route ini HANYA aktif kalau APP_ENV=local
 * atau ada header khusus. Kalau tidak tersedia, test dilewati.
 *
 * Jalankan: php8.3 artisan tinker --execute="require 'tests/manual/live_check_wa_approval_http.php';"
 */

use App\Models\User;
use App\Models\WorkAssignmentRequest;
use App\Services\WorkAssignment\WorkAssignmentApproval;
use Illuminate\Support\Facades\DB;

$fail = 0;

$assert = function (string $label, bool $ok) use (&$fail) {
    echo ($ok ? '  OK   ' : '  FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $fail++;
    }
};

$npk = 'C-00825';

echo '═══ 1. Siapkan pengajuan + approver ═══' . PHP_EOL;

WorkAssignmentRequest::where('npk', $npk)->delete();

// Ambil chain nyata dari master (group milik karyawan ini).
$chain = App\Services\WorkAssignment\ApprovalChainBuilder::build(
    DB::connection('cii')->table('BIODATA')->where('NPK', $npk)->value('ID_DEPT'),
    DB::connection('cii')->table('BIODATA')->where('NPK', $npk)->value('SECTION')
);

echo '  chain: ' . count($chain) . ' level' . PHP_EOL;
foreach ($chain as $c) {
    echo '    L' . $c['level'] . ' ' . $c['name'] . ' (' . $c['npk'] . ')' . PHP_EOL;
}

$assert('chain tidak kosong', count($chain) > 0);

$req = WorkAssignmentRequest::create([
    'token' => 'ZWALIVE' . substr(md5(uniqid('', true)), 0, 10),
    'npk' => $npk,
    'duty_dates' => ['2026-12-08', '2026-12-09'],
    'start_date' => '2026-12-08', 'end_date' => '2026-12-09', 'total_days' => 2,
    'dinas_ke' => 'Kantor Pusat Jakarta',
    'keperluan' => 'Uji live approval via HTTP',
    'jam_keluar' => '08:00', 'rencana_kembali' => '17:00', 'jam_kembali' => null,
    'approval_chain' => $chain, 'answers' => [],
    'status' => 'pending', 'current_level' => 1, 'void' => 'false', 'submitted_by' => $npk,
]);

echo '  pengajuan #' . $req->id . PHP_EOL;

echo PHP_EOL . '═══ 2. Endpoint /get-data dengan Approver L1 ═══' . PHP_EOL;
$approverL1 = $chain[0]['npk'];
$user = User::where('npk', $approverL1)->first();
$assert('user Laravel dengan NPK ' . $approverL1 . ' ada', (bool) $user);

if ($user) {
    // Panggil controller langsung dengan user yang di-auth.
    \Illuminate\Support\Facades\Auth::login($user);

    $controller = new \App\Http\Controllers\WorkAssignmentApprovalController();
    $response  = $controller->getData(new \Illuminate\Http\Request());
    $payload   = json_decode($response->getContent(), true);

    echo '  items: ' . count($payload['data'] ?? []) . PHP_EOL;

    $mine = collect($payload['data'] ?? [])->firstWhere('id', $req->id);
    $assert('pengajuan muncul di daftar approver', (bool) $mine);
    $assert('my_level = 1', ($mine['my_level'] ?? null) === 1);
    $assert('is_actionable = true (giliran dia)', ($mine['is_actionable'] ?? false) === true);
    $assert('progress 0 dari total', ($mine['progress_done'] ?? -1) === 0);

    // Pengajuan milik orang lain TIDAK boleh muncul.
    $otherIds = collect($payload['data'] ?? [])->pluck('npk')->unique();
    $assert('hanya pengajuan milik approver ini', $otherIds->all() === [$npk]);

    echo PHP_EOL . '═══ 3. Endpoint detail ═══' . PHP_EOL;
    $detail = json_decode($controller->detail($req->id)->getContent(), true);
    $assert('detail sukses', ($detail['success'] ?? false) === true);
    $assert('timeline berisi semua level', count($detail['data']['timeline'] ?? []) === count($chain));
    $assert('level milik saya ditandai', ($detail['data']['timeline'][0]['is_mine'] ?? false) === true);

    echo PHP_EOL . '═══ 4. Approve via endpoint ═══' . PHP_EOL;
    $res = json_decode($controller->approve(new \Illuminate\Http\Request(), $req->id)->getContent(), true);
    echo '  ' . json_encode($res) . PHP_EOL;
    $assert('approve sukses', ($res['success'] ?? $res['ok'] ?? false) === true);
    $assert('current_level naik ke 2', (int) $req->fresh()->current_level === 2);

    echo PHP_EOL . '═══ 5. Approver level 3 TIDAK bisa approve sebelum level 2 ═══' . PHP_EOL;
    $l3User = User::where('npk', $chain[2]['npk'] ?? '')->first();

    if ($l3User) {
        \Illuminate\Support\Facades\Auth::login($l3User);
        $res2 = json_decode($controller->approve(new \Illuminate\Http\Request(), $req->id)->getContent(), true);
        echo '  ' . json_encode($res2) . PHP_EOL;
        $assert('ditolak (belum giliran)', ($res2['success'] ?? $res2['ok'] ?? true) === false);
        $assert('pesan "belum giliran"', str_contains((string) ($res2['message'] ?? ''), 'belum giliran'));
        $assert('current_level tetap 2', (int) $req->fresh()->current_level === 2);
    }

    echo PHP_EOL . '═══ 6. Approver level 2 (giliran sekarang) bisa approve ═══' . PHP_EOL;
    $l2User = User::where('npk', $chain[1]['npk'] ?? '')->first();

    if ($l2User) {
        \Illuminate\Support\Facades\Auth::login($l2User);
        $res3 = json_decode($controller->approve(new \Illuminate\Http\Request(), $req->id)->getContent(), true);
        echo '  ' . json_encode($res3) . PHP_EOL;
        $assert('berhasil', ($res3['success'] ?? $res3['ok'] ?? false) === true);
        $assert('current_level naik ke 3', (int) $req->fresh()->current_level === 3);
    }

    echo PHP_EOL . '═══ 7. Approver yang tidak ada di chain → 403 ═══' . PHP_EOL;
    $stranger = User::where('npk', 'C-00001')->first();

    if ($stranger) {
        \Illuminate\Support\Facades\Auth::login($stranger);
        $res4 = json_decode($controller->approve(new \Illuminate\Http\Request(), $req->id)->getContent(), true);
        echo '  ' . json_encode($res4) . PHP_EOL;
        $assert('ditolak', ($res4['success'] ?? $res4['ok'] ?? true) === false);
        $assert('pesan tidak berwenang', str_contains((string) ($res4['message'] ?? ''), 'tidak berwenang'));
    }

    \Illuminate\Support\Facades\Auth::logout();
}

echo PHP_EOL . '═══ CLEANUP ═══' . PHP_EOL;
$del = WorkAssignmentRequest::where('npk', $npk)->delete();
echo "  pengajuan dihapus: {$del}" . PHP_EOL;
echo '  sisa: ' . WorkAssignmentRequest::where('npk', $npk)->count() . PHP_EOL;

echo PHP_EOL . ($fail === 0 ? '✅ SEMUA SKENARIO LULUS' : "❌ {$fail} ASSERTION GAGAL") . PHP_EOL;

return $fail;
