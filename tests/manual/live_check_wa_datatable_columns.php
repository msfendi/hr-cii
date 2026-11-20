<?php

/**
 * Verifikasi: setiap kolom DataTables punya key yang benar di payload server.
 *
 * Mencegah error "Requested unknown parameter" / data null di DataTables.
 *
 * Jalankan: php8.3 artisan tinker --execute="require 'tests/manual/live_check_wa_datatable_columns.php';"
 */

use App\Models\User;
use App\Models\WorkAssignmentRequest;
use App\Services\WorkAssignment\ApprovalChainBuilder;
use Illuminate\Support\Facades\DB;

$fail = 0;

$assert = function (string $label, bool $ok) use (&$fail) {
    echo ($ok ? '  OK   ' : '  FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $fail++;
    }
};

$npk = 'C-00825';

WorkAssignmentRequest::where('npk', $npk)->delete();

$chain = ApprovalChainBuilder::build(
    DB::connection('cii')->table('BIODATA')->where('NPK', $npk)->value('ID_DEPT'),
    DB::connection('cii')->table('BIODATA')->where('NPK', $npk)->value('SECTION')
);

WorkAssignmentRequest::create([
    'token' => 'ZWADT' . substr(md5(uniqid('', true)), 0, 10),
    'npk' => $npk,
    'duty_dates' => ['2026-12-08'],
    'start_date' => '2026-12-08', 'end_date' => '2026-12-08', 'total_days' => 1,
    'dinas_ke' => 'Kantor Pusat', 'keperluan' => 'Uji kolom datatable',
    'jam_keluar' => '08:00', 'rencana_kembali' => '17:00', 'jam_kembali' => null,
    'approval_chain' => $chain, 'answers' => [],
    'status' => 'pending', 'current_level' => 1, 'void' => 'false', 'submitted_by' => $npk,
]);

$approverUser = User::where('npk', $chain[0]['npk'])->first();
\Illuminate\Support\Facades\Auth::login($approverUser);

echo '═══ 1. Kolom Manage Recap ═══' . PHP_EOL;
$recap = new \App\Http\Controllers\WorkAssignmentRecapController();
$recapPayload = json_decode($recap->getData(new \Illuminate\Http\Request())->getContent(), true);

$recapColumns = ['rowno', 'nama', 'created_at', 'duty_dates', 'dinas_ke', 'total_days',
    'jam_keluar', 'keperluan', 'progress_done', 'status', 'id'];

$recapRow = collect($recapPayload['data'] ?? [])->first();

if (!$recapRow) {
    $assert('payload recap ada isi', false);
} else {
    foreach ($recapColumns as $col) {
        $assert("kolom '{$col}' ada di payload recap", array_key_exists($col, $recapRow));
    }

    // Tidak boleh ada key dengan nilai null yang dipakai kolom.
    foreach (['rowno', 'nama', 'status', 'progress_done', 'id'] as $col) {
        $assert("'{$col}' tidak null", $recapRow[$col] !== null);
    }
}

echo PHP_EOL . '═══ 2. Kolom Approval ═══' . PHP_EOL;
$approval = new \App\Http\Controllers\WorkAssignmentApprovalController();
$approvalPayload = json_decode($approval->getData(new \Illuminate\Http\Request())->getContent(), true);

$approvalColumns = ['rowno', 'nama', 'created_at', 'duty_dates', 'dinas_ke', 'total_days',
    'jam_keluar', 'progress_done', 'my_decision', 'id'];

$approvalRow = collect($approvalPayload['data'] ?? [])->first();

if (!$approvalRow) {
    $assert('payload approval ada isi', false);
} else {
    foreach ($approvalColumns as $col) {
        $assert("kolom '{$col}' ada di payload approval", array_key_exists($col, $approvalRow));
    }

    foreach (['rowno', 'nama', 'progress_done', 'id'] as $col) {
        $assert("'{$col}' tidak null", $approvalRow[$col] !== null);
    }

    // my_decision boleh null kalau approver tak punya level — tapi di sini dia punya.
    $assert('my_level terisi', $approvalRow['my_level'] !== null);
}

\Illuminate\Support\Facades\Auth::logout();

echo PHP_EOL . '═══ CLEANUP ═══' . PHP_EOL;
$del = WorkAssignmentRequest::where('npk', $npk)->delete();
echo "  dihapus: {$del}" . PHP_EOL;

echo PHP_EOL . ($fail === 0 ? '✅ SEMUA KOLOM LULUS' : "❌ {$fail} ASSERTION GAGAL") . PHP_EOL;

return $fail;
