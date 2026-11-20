<?php

/**
 * Verifikasi mesin approval bertingkat Work Assignment (JSON based).
 *
 * Menguji:
 *   1. Approver yang salah TIDAK boleh memutuskan.
 *   2. Level N tidak boleh diputuskan sebelum level N-1 approved.
 *   3. Approve berurutan → status naik level, terakhir → approved.
 *   4. Reject → status rejected, level atas jadi skipped.
 *   5. Reject tanpa alasan → ditolak.
 *   6. Update keputusan (ubah) → hanya kalau level bawah belum bertindak.
 *   7. Orang yang SAMA punya 2 level → ganggu? (chain unik, jadi aman)
 *
 * Semua data uji dibersihkan di akhir.
 *
 * Jalankan: php8.3 artisan tinker --execute="require 'tests/manual/work_assignment_approval_check.php';"
 */

use App\Models\ApprovalDept;
use App\Models\ApprovalRule;
use App\Models\WorkAssignmentRequest;
use App\Services\WorkAssignment\WorkAssignmentApproval;
use Illuminate\Support\Facades\DB;

$fail = 0;
$npk  = 'TST-WA-01';

$assert = function (string $label, bool $ok) use (&$fail) {
    echo ($ok ? '  OK   ' : '  FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $fail++;
    }
};

// ─── Bersihkan sisa run sebelumnya ───
WorkAssignmentRequest::where('npk', $npk)->delete();
foreach (ApprovalDept::where('name', 'like', 'ZZ TEST WA%')->pluck('id') as $oldId) {
    ApprovalRule::where('rules_id', $oldId)->delete();
    ApprovalDept::where('id', $oldId)->delete();
}

// ─── Group approval uji: 3 level ───
$g = ApprovalDept::create(['name' => 'ZZ TEST WA', 'dept' => ['99999'], 'section' => null]);

$chain = [
    ['level' => 1, 'role_key' => 'yang_diberi_tugas', 'role_label' => 'Yang diberi Tugas', 'npk' => 'WA-A', 'name' => 'APPROVER A', 'jabatan' => 'LEADER'],
    ['level' => 2, 'role_key' => 'manager_departemen', 'role_label' => 'Manager Departemen', 'npk' => 'WA-B', 'name' => 'APPROVER B', 'jabatan' => 'MANAGER'],
    ['level' => 3, 'role_key' => 'hr_manager', 'role_label' => 'HR Manager', 'npk' => 'WA-C', 'name' => 'APPROVER C', 'jabatan' => 'HR MANAGER'],
];

foreach ($chain as $c) {
    ApprovalRule::create([
        'rules_id' => $g->id, 'name' => $c['jabatan'],
        'approval_id' => $c['npk'], 'level' => $c['level'],
    ]);
}

$makeRequest = function (array $chain) use ($npk) {
    return WorkAssignmentRequest::create([
        'token' => 'ZWA' . substr(md5(uniqid('', true)), 0, 12),
        'npk' => $npk,
        'duty_dates' => ['2026-12-08', '2026-12-09'],
        'start_date' => '2026-12-08', 'end_date' => '2026-12-09', 'total_days' => 2,
        'dinas_ke' => 'Kantor Pusat', 'keperluan' => 'Uji approval bertingkat',
        'jam_keluar' => '08:00', 'rencana_kembali' => '17:00', 'jam_kembali' => null,
        'approval_chain' => $chain, 'answers' => [],
        'status' => 'pending', 'current_level' => 1, 'void' => 'false', 'submitted_by' => $npk,
    ]);
};

echo '═══ SKENARIO 1: approver yang salah TIDAK boleh memutuskan ═══' . PHP_EOL;
$r = $makeRequest($chain);
$res = WorkAssignmentApproval::approve($r, 'WA-B', 1);
echo '  ' . json_encode($res) . PHP_EOL;
$assert('ditolak (ok=false)', ($res['ok'] ?? true) === false);
$assert('pesan otorisasi', str_contains((string) ($res['message'] ?? ''), 'tidak berwenang'));
$assert('status tetap pending', $r->fresh()->status === 'pending');
$assert('answers tetap kosong', count($r->fresh()->answers) === 0);

echo PHP_EOL . '═══ SKENARIO 2: level 2 tidak boleh diputuskan sebelum level 1 ═══' . PHP_EOL;
$res = WorkAssignmentApproval::approve($r, 'WA-B', 2);
echo '  ' . json_encode($res) . PHP_EOL;
$assert('ditolak (ok=false)', ($res['ok'] ?? true) === false);
$assert('pesan "belum giliran"', str_contains((string) ($res['message'] ?? ''), 'belum giliran'));

echo PHP_EOL . '═══ SKENARIO 3: approve berurutan L1 → L2 → L3 ═══' . PHP_EOL;
$res = WorkAssignmentApproval::approve($r, 'WA-A', 1);
echo '  L1: ' . json_encode($res) . PHP_EOL;
$assert('L1 sukses', ($res['ok'] ?? false) === true);
$assert('current_level naik ke 2', (int) $r->fresh()->current_level === 2);
$assert('status masih pending', $r->fresh()->status === 'pending');

$res = WorkAssignmentApproval::approve($r, 'WA-B', 2);
echo '  L2: ' . json_encode($res) . PHP_EOL;
$assert('L2 sukses', ($res['ok'] ?? false) === true);
$assert('current_level naik ke 3', (int) $r->fresh()->current_level === 3);

$res = WorkAssignmentApproval::approve($r, 'WA-C', 3);
echo '  L3: ' . json_encode($res) . PHP_EOL;
$assert('L3 sukses', ($res['ok'] ?? false) === true);
$assert('status jadi approved', $r->fresh()->status === 'approved');

$answers = $r->fresh()->answered();
$assert('answers berisi 3 entri', count($answers) === 3);
$assert('semua decision=approved', collect($answers)->every(fn ($a) => $a['decision'] === 'approved'));
$assert('answers urut level 1,2,3', array_map('intval', array_column($answers, 'level')) === [1, 2, 3]);
$assert('progress done=3 total=3', $r->fresh()->progress()['done'] === 3);

echo PHP_EOL . '═══ SKENARIO 4: reject → rantai berhenti, level atas skipped ═══' . PHP_EOL;
$r2 = $makeRequest($chain);
WorkAssignmentApproval::approve($r2, 'WA-A', 1);
$res = WorkAssignmentApproval::reject($r2, 'WA-B', 2, 'anggaran tidak tersedia');
echo '  ' . json_encode($res) . PHP_EOL;
$assert('reject sukses', ($res['ok'] ?? false) === true);
$assert('status jadi rejected', $r2->fresh()->status === 'rejected');

$tl = $r2->fresh()->timeline();
$decisions = array_column($tl, 'decision');
echo '  timeline: ' . implode(', ', $decisions) . PHP_EOL;
$assert('L1 approved', $decisions[0] === 'approved');
$assert('L2 rejected', $decisions[1] === 'rejected');
$assert('L3 skipped (rantai berhenti)', $decisions[2] === 'skipped');

$rej = collect($tl)->firstWhere('decision', 'rejected');
$assert('alasan tersimpan', $rej['reason'] === 'anggaran tidak tersedia');

echo PHP_EOL . '═══ SKENARIO 5: reject tanpa alasan → ditolak ═══' . PHP_EOL;
$r3 = $makeRequest($chain);
$res = WorkAssignmentApproval::reject($r3, 'WA-A', 1, '   ');
echo '  ' . json_encode($res) . PHP_EOL;
$assert('ditolak (ok=false)', ($res['ok'] ?? true) === false);
$assert('pesan alasan wajib', str_contains((string) ($res['message'] ?? ''), 'Alasan penolakan wajib'));
$assert('status tetap pending', $r3->fresh()->status === 'pending');

echo PHP_EOL . '═══ SKENARIO 6: update keputusan hanya kalau level bawah belum bertindak ═══' . PHP_EOL;
$r4 = $makeRequest($chain);
WorkAssignmentApproval::approve($r4, 'WA-A', 1);
WorkAssignmentApproval::approve($r4, 'WA-B', 2);
WorkAssignmentApproval::approve($r4, 'WA-C', 3);

// L3 sudah jadi approved dan tidak ada level di atasnya → boleh diubah.
$res = WorkAssignmentApproval::updateDecision($r4, 'WA-C', 'rejected', 'salah input');
echo '  ubah L3: ' . json_encode($res) . PHP_EOL;
$assert('L3 bisa diubah', ($res['ok'] ?? false) === true);
$assert('status jadi rejected', $r4->fresh()->status === 'rejected');

// L2 sudah批准 tapi L3 sudah bertindak → tidak boleh diubah.
$r5 = $makeRequest($chain);
WorkAssignmentApproval::approve($r5, 'WA-A', 1);
WorkAssignmentApproval::approve($r5, 'WA-B', 2);
WorkAssignmentApproval::approve($r5, 'WA-C', 3);
$res = WorkAssignmentApproval::updateDecision($r5, 'WA-B', 'rejected', 'mengubah pikiran');
echo '  ubah L2 (L3 sudah bertindak): ' . json_encode($res) . PHP_EOL;
$assert('L2 TIDAK bisa diubah', ($res['ok'] ?? true) === false);
$assert('pesan level berikutnya sudah memutuskan', str_contains((string) ($res['message'] ?? ''), 'sudah memutuskan'));

echo PHP_EOL . '═══ SKENARIO 7: approver yang tidak ada di chain ═══' . PHP_EOL;
$r6 = $makeRequest($chain);
$res = WorkAssignmentApproval::approve($r6, 'WA-Z', 1);
echo '  ' . json_encode($res) . PHP_EOL;
$assert('ditolak (ok=false)', ($res['ok'] ?? true) === false);
$assert('levelOf null', WorkAssignmentApproval::levelOf($r6, 'WA-Z') === null);
$assert('holdsLevel false', WorkAssignmentApproval::holdsLevel($r6, 'WA-Z', 1) === false);

echo PHP_EOL . '═══ SKENARIO 8: forApprover hanya mengembalikan pengajuan miliknya ═══' . PHP_EOL;
$listA = WorkAssignmentApproval::forApprover('WA-A');
$listZ = WorkAssignmentApproval::forApprover('WA-Z');
echo '  WA-A: ' . $listA->count() . ' pengajuan, WA-Z: ' . $listZ->count() . PHP_EOL;
$assert('WA-A dapat pengajuan', $listA->count() > 0);
$assert('WA-Z tidak dapat apa pun', $listZ->count() === 0);

// ─── CLEANUP ───
echo PHP_EOL . '═══ CLEANUP ═══' . PHP_EOL;
$del = WorkAssignmentRequest::where('npk', $npk)->delete();
$delRule = ApprovalRule::where('rules_id', $g->id)->delete();
$delDept = ApprovalDept::where('id', $g->id)->delete();
echo "  leave_requests: {$del}, rules: {$delRule}, depts: {$delDept}" . PHP_EOL;
echo '  sisa: ' . WorkAssignmentRequest::where('npk', $npk)->count() . PHP_EOL;

echo PHP_EOL . ($fail === 0 ? '✅ SEMUA SKENARIO LULUS' : "❌ {$fail} ASSERTION GAGAL") . PHP_EOL;

return $fail;
