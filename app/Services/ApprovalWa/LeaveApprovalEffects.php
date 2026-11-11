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
        $nextLevel = LeaveRequest::where('token', $leave->token)
            ->where('approval_level', '>', $leave->approval_level)
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

        $holidays = json_decode(file_get_contents(storage_path('app/calendar.json')), true);
        $holidays = array_filter($holidays, function ($item) {
            return isset($item['holiday']) && $item['holiday'] === true;
        });
        $holidays = array_keys($holidays);

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
     */
    public static function voidSiblingRows(LeaveRequest $leave): void
    {
        LeaveRequest::where('token', $leave->token)
            ->where('id', '!=', $leave->id)
            ->update(['status' => 'rejected', 'void' => 'true']);
    }
}
