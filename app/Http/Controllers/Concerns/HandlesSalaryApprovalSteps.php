<?php

namespace App\Http\Controllers\Concerns;

use App\Models\SalaryApprove;
use App\Models\User; // TODO: sesuaikan namespace User model kamu kalau berbeda
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Logic approval bertingkat (management_dept -> general_manager) untuk
 * SalaryApprove::progress dipakai bareng oleh RecruitmentController (buat
 * nampilin status gaji di halaman recruitment) dan SalaryApproveController
 * (buat halaman approval terpisah). Disatukan di sini biar gak duplikat.
 */
trait HandlesSalaryApprovalSteps
{
    /**
     * npk disimpan sebagai JSON-encoded string di dalam kolom progress (json),
     * contoh: '["C-00011","C-00001"]'. Kadang Eloquent udah otomatis decode
     * (kalau lagi iterasi ulang dari array yang barusan kita build sendiri),
     * makanya di-cek is_array dulu.
     */
    protected function decodeNpkList($raw)
    {
        $list = is_array($raw) ? $raw : json_decode($raw ?? '[]', true);
        return is_array($list) ? $list : [];
    }

    /**
     * Cari index step yang lagi jalan (belum full approve).
     * false kalau semua step udah approve.
     *
     * status tiap step ada 3 kemungkinan:
     * - 'pending'                    -> belum ada satupun approver yg approve
     * - '["approve","waiting", ...]' -> sebagian approver udah approve (JSON string)
     * - 'approve'                    -> step ini udah selesai/lengkap
     */
    protected function currentStepIndex($progress)
    {
        return collect($progress)->search(function ($item) {
            $status = $item['status'] ?? null;
            return $status === 'pending' || (is_string($status) && str_contains($status, 'waiting'));
        });
    }

    /**
     * Bangun struktur step yang enak dipakai di blade/JS:
     * [ ['label' => 'Management Dept', 'done' => bool,
     *    'approvers' => [ ['npk','name','status'], ... ] ], ... ]
     */
    protected function buildStepsDisplay($progress, Collection $userNameMap, array $stepLabels = ['Management Dept', 'General Manager'])
    {
        return collect($progress)->map(function ($step, $idx) use ($userNameMap, $stepLabels) {
            $npkList = $this->decodeNpkList($step['npk'] ?? null);
            $rawStatus = $step['status'] ?? 'pending';

            if ($rawStatus === 'pending') {
                $statusList = array_fill(0, count($npkList), 'waiting');
            } elseif ($rawStatus === 'approve') {
                $statusList = array_fill(0, count($npkList), 'approve');
            } else {
                $statusList = json_decode($rawStatus, true) ?: [];
            }

            $approvers = [];
            foreach ($npkList as $i => $npk) {
                $approvers[] = [
                    'npk'    => $npk,
                    'name'   => $userNameMap[$npk] ?? $npk,
                    'status' => $statusList[$i] ?? 'waiting',
                ];
            }

            return [
                'label'     => $stepLabels[$idx] ?? ('Step ' . ($idx + 1)),
                'approvers' => $approvers,
                'done'      => $rawStatus === 'approve',
            ];
        })->values();
    }

    /**
     * Kumpulin semua npk approver yang muncul di satu/banyak record SalaryApprove,
     * lalu resolve jadi nama sekali query (hindari N+1).
     */
    protected function resolveApproverNames($salaryCollection)
    {
        $npks = collect();

        foreach ($salaryCollection as $s) {
            foreach (($s->progress ?? []) as $step) {
                $npks = $npks->merge($this->decodeNpkList($step['npk'] ?? null));
            }
        }

        return User::whereIn('npk', $npks->unique()->filter()->values())->pluck('name', 'npk');
    }

    /**
     * Cek apakah npkLogin adalah approver di SALAH SATU step (bukan cuma step
     * yang lagi aktif) untuk record SalaryApprove yang sudah dilengkapi
     * attribute 'steps' (lihat buildStepsDisplay()). Dipakai untuk membatasi
     * visibilitas di halaman approval: hanya orang yang di-assign HR sejak
     * awal yang boleh melihat baris pengajuan ini sama sekali.
     */
    protected function isAssignedApprover($item, $npkLogin): bool
    {
        if (!$npkLogin) {
            return false;
        }

        foreach ($item->steps ?? [] as $step) {
            foreach ($step['approvers'] ?? [] as $approver) {
                if (($approver['npk'] ?? null) === $npkLogin) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * PELAMAR.is_staff (koneksi cii) menentukan apakah pelamar ini butuh alur
     * approval gaji sebelum bisa ONBOARDING.
     */
    protected function isPelamarStaff($idPelamar): bool
    {
        $pelamar = DB::connection('cii')->table('PELAMAR')
            ->where('ID', $idPelamar)
            ->first(['is_staff']);

        return $pelamar && (int) ($pelamar->is_staff ?? 0) === 1;
    }

    /**
     * Semua 4 tahap penilaian (interview/kesehatan/teknis/user) sudah lolos
     * (result_* = 'TRUE') di pelamar_details (koneksi cii).
     */
    protected function allPenilaianPassed($idPelamar): bool
    {
        $pd = DB::connection('cii')->table('pelamar_details')
            ->where('id_pelamar', $idPelamar)
            ->first(['result_interview', 'result_kesehatan', 'result_test', 'result_user']);

        if (!$pd) {
            return false;
        }

        return collect([
            $pd->result_interview,
            $pd->result_kesehatan,
            $pd->result_test,
            $pd->result_user,
        ])->every(fn ($r) => $r === 'TRUE');
    }

    /**
     * Gerbang status ONBOARDING khusus staff: baru pindah ke ONBOARDING kalau
     * SEMUA penilaian lolos DAN pengajuan gajinya sudah 'finish' (di-approve
     * sampai tahap General Manager). Dipanggil dari dua tempat — sesudah
     * simpan penilaian (RecruitmentController::updatePenilaian) dan sesudah
     * approval gaji tahap terakhir (SalaryApproveController::approve) —
     * karena kita tidak tahu mana yang akan selesai lebih dulu.
     *
     * Untuk pelamar non-staff, method ini no-op; status ONBOARDING mereka
     * tetap diatur langsung dari updatePenilaian() seperti alur lama.
     */
    protected function maybeFinalizeStaffOnboarding($idPelamar): void
    {
        if (!$this->isPelamarStaff($idPelamar)) {
            return;
        }

        if (!$this->allPenilaianPassed($idPelamar)) {
            return;
        }

        $salaryFinished = SalaryApprove::where('id_pelamar', $idPelamar)
            ->where('status', 'finish')
            ->exists();

        if (!$salaryFinished) {
            return;
        }

        DB::connection('cii')->table('pelamar_details')
            ->where('id_pelamar', $idPelamar)
            ->update([
                'status_apply' => 'ONBOARDING',
                'tgl_diterima' => DB::raw("COALESCE(tgl_diterima, '" . now()->toDateString() . "')"),
            ]);
    }

    /**
     * Kirim email notifikasi pengajuan gaji ke daftar NPK approver pada step aktif.
     */
    protected function notifyApproversByNpk(array $npks, $salaryApprove, string $stepName): void
    {
        try {
            $users = User::whereIn('npk', array_filter($npks))
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->get();

            if ($users->isEmpty()) {
                return;
            }

            $pelamar = DB::connection('cii')->table('PELAMAR')
                ->select('PELAMAR.NAMA', 'pelamar_details.jabatan', 'pelamar_details.department')
                ->leftJoin('pelamar_details', 'PELAMAR.id', '=', 'pelamar_details.id_pelamar')
                ->where('PELAMAR.ID', $salaryApprove->id_pelamar)
                ->first();
            $namaPelamar = $pelamar->NAMA ?? 'Pelamar';
            $jabatan = $pelamar->jabatan ?? 'Jabatan';
            $department = $pelamar->department ?? 'Department';

            $expectedSalaryFmt = 'Rp ' . number_format($salaryApprove->expected_salary ?? 0, 0, ',', '.');
            $approvalUrl = route('salary-approve.index');


            foreach ($users as $user) {
                $viewData = [
                    'approverName'     => $user->name ?? 'Bapak/Ibu',
                    'namaPelamar'      => $namaPelamar,
                    'jabatan'          => $jabatan,
                    'department'       => $department,
                    'expectedSalaryFmt'=> $expectedSalaryFmt,
                    'stepName'         => $stepName,
                    'approvalUrl'      => $approvalUrl,
                ];

                Mail::send('emails.salary_approval_notification', $viewData, function ($message) use ($user, $namaPelamar) {
                    $message->to($user->email)
                        ->subject("Persetujuan Gaji Staff: " . $namaPelamar);
                });
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal mengirim email notification salary approval: ' . $e->getMessage());
        }
    }
}