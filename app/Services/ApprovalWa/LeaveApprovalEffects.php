<?php

namespace App\Services\ApprovalWa;

use App\Models\LeaveRequest;
use App\Models\LeaveTypes;
use App\Models\Overtime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Efek samping approval cuti: advance progress, finalisasi (potong saldo +
 * overtime), dan void sibling rows saat reject.
 *
 * Diekstrak dari LeaveApprovalController supaya logic yang sama dipakai oleh:
 *  - Approve via web portal / admin (LeaveApprovalController::approve)
 *  - Approve via WhatsApp (InboundApprovalProcessor)
 *
 * Penting: finalisasi hanya boleh jalan sekali, di level terakhir.
 */
class LeaveApprovalEffects
{
    /**
     * Setelah satu level approve:
     *  - kalau ada level berikutnya → majukan approval_progress ke level itu
     *  - kalau tidak (level terakhir) → finalisasi (potong saldo + isi overtime)
     */
    public static function advanceOrFinalize(LeaveRequest $leave): void
    {
        // Level berikutnya = baris aktif berikutnya di token ini.
        // Baris void/cancelled (mis. levelnya dihapus dari Manage Approval)
        // TIDAK dihitung — kalau tidak, progress bisa maju ke level hantu yang
        // tidak akan pernah approve, dan finalisasi tidak pernah jalan.
        $nextLevel = LeaveRequest::where('token', $leave->token)
            ->where('approval_level', '>', $leave->approval_level)
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('void')->orWhere('void', 'false')->orWhere('void', '');
            })
            ->orderBy('approval_level', 'asc')
            ->first();

        if ($nextLevel) {
            LeaveRequest::where('token', $leave->token)
                ->update(['approval_progress' => $nextLevel->approval_level]);
            return;
        }

        self::finalizeApproval($leave);
    }

    /**
     * Efek final saat cuti disetujui di level terakhir: potong leave_balances
     * (atau tambah negative_leave untuk hutang cuti), dan isi record Overtime
     * "CT"/"CU" untuk tiap hari kerja di periode cuti.
     */
    public static function finalizeApproval(LeaveRequest $leave): void
    {
        $leaveType = $leave->leaveType ?? LeaveTypes::find($leave->leave_type_id);

        // Hanya cuti tahunan yang memakai saldo; jenis lain dibatasi per pengajuan
        // sehingga tidak perlu memotong saldo.
        if ($leaveType && $leaveType->usesBalance()) {
            $isNegativeLeave = str_contains($leave->reason, '[Hutang Cuti]');

            if ($isNegativeLeave) {
                DB::table('leave_balances')
                    ->where('NPK', $leave->NPK)
                    ->where('leave_type_id', $leave->leave_type_id)
                    ->where('year', date('Y'))
                    ->update([
                        'negative_leave' => DB::raw('COALESCE(negative_leave, 0) + ' . $leave->total_days)
                    ]);
            } else {
                DB::table('leave_balances')
                    ->where('NPK', $leave->NPK)
                    ->where('leave_type_id', $leave->leave_type_id)
                    ->where('year', date('Y'))
                    ->update([
                        'used_days'     => DB::raw('used_days + ' . $leave->total_days),
                        'remained_days' => DB::raw('remained_days - ' . $leave->total_days)
                    ]);
            }
        }

        $karyawan = DB::connection('cii')->table('BIODATA')
            ->join('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->where('BIODATA.NPK', $leave->NPK)
            ->select('BIODATA.NAMA_KARYAWAN', 'DEPT.DEPARTEMENT', 'BIODATA.ID_DEPT', 'BIODATA.IS_STAFF', 'DEPT.IS_SEWING')
            ->first();

        $holidays = [];

        $calendarPath = storage_path('app/calendar.json');

        if (is_file($calendarPath)) {
            $decoded = json_decode((string) file_get_contents($calendarPath), true);

            if (is_array($decoded)) {
                $holidays = array_keys(array_filter($decoded, function ($item) {
                    return isset($item['holiday']) && $item['holiday'] === true;
                }));
            }
        }

        $startDate = Carbon::parse($leave->start_date);
        $endDate   = Carbon::parse($leave->end_date);

        // Tipe cuti 'pribadi' memakai kode jam lembur "CU", selain itu "CT"
        $overtimeCode = ($leaveType && $leaveType->code === 'pribadi') ? 'CU' : 'CT';

        // Pengajuan dengan tanggal bebas hanya memakai tanggal yang benar-benar dipilih
        $leaveDates = $leave->leaveDates();

        foreach ($leaveDates as $date) {
            $dayOfWeek = $date->dayOfWeek; // 0 = Minggu, 6 = Sabtu
            $dateString = $date->format('Y-m-d');

            // Lewati Sabtu, Minggu, atau Hari Libur Nasional
            if ($dayOfWeek == 0 || $dayOfWeek == 6 || in_array($dateString, $holidays)) {
                continue;
            }

            Overtime::updateOrCreate(
                [
                    'NPK'          => $leave->NPK,
                    'OVERTIME_DATE' => $dateString,
                ],
                [
                    'NAMA_KARYAWAN'  => $karyawan ? $karyawan->NAMA_KARYAWAN : $leave->NPK,
                    'BAGIAN'         => $karyawan ? $karyawan->DEPARTEMENT : '-',
                    'DAY'            => $date->translatedFormat('l'),
                    'JUMLAH_JAM_LEMBUR' => $overtimeCode,
                    'DEPT_GROUP'     => '',
                    'is_request'     => 'true',
                ]
            );
        }
    }

    /**
     * Set baris-baris lain (level approval lain) di token yang sama jadi
     * rejected/void, dipakai saat salah satu level menolak permohonan.
     *
     * Baris yang sudah dibatalkan otomatis (cancelled + void, mis. levelnya
     * dihapus dari Manage Approval) tidak diubah lagi.
     */
    public static function voidSiblingRows(LeaveRequest $leave): void
    {
        LeaveRequest::where('token', $leave->token)
            ->where('id', '!=', $leave->id)
            ->where('status', '!=', 'cancelled')
            ->update(['status' => 'rejected', 'void' => 'true']);
    }

    /**
     * Kebalikan dari finalizeApproval() — dipakai saat keputusan "approved" diubah
     * jadi status lain: kembalikan leave_balances / kurangi negative_leave dan hapus
     * record Overtime "CT"/"CU" yang sempat dibuat untuk periode cuti ini.
     */
    public static function reverseFinalize(LeaveRequest $leave): void
    {
        $leaveType = $leave->leaveType ?? LeaveTypes::find($leave->leave_type_id);

        // Hanya cuti tahunan yang memakai saldo; jenis lain tidak menyentuh leave_balances.
        if ($leaveType && $leaveType->usesBalance()) {
            $isNegativeLeave = str_contains($leave->reason, '[Hutang Cuti]');

            if ($isNegativeLeave) {
                DB::table('leave_balances')
                    ->where('NPK', $leave->NPK)
                    ->where('leave_type_id', $leave->leave_type_id)
                    ->where('year', date('Y'))
                    ->update([
                        'negative_leave' => DB::raw('GREATEST(0, COALESCE(negative_leave, 0) - ' . $leave->total_days . ')')
                    ]);
            } else {
                DB::table('leave_balances')
                    ->where('NPK', $leave->NPK)
                    ->where('leave_type_id', $leave->leave_type_id)
                    ->where('year', date('Y'))
                    ->update([
                        'used_days'     => DB::raw('used_days - ' . $leave->total_days),
                        'remained_days' => DB::raw('remained_days + ' . $leave->total_days)
                    ]);
            }
        }

        // Hanya tanggal-tanggal yang benar-benar dipakai pengajuan ini yang dikembalikan
        // (untuk pengajuan tanggal bebas, hanya tanggal yang dipilih).
        $dateStrings = array_map(
            fn ($d) => $d->format('Y-m-d'),
            $leave->leaveDates()
        );

        Overtime::where('NPK', $leave->NPK)
            ->whereIn('OVERTIME_DATE', $dateStrings)
            ->whereIn('JUMLAH_JAM_LEMBUR', ['CT', 'CU'])
            ->update([
                'JUMLAH_JAM_LEMBUR' => null,
                'is_request' => 'false',
            ]);
    }
}
