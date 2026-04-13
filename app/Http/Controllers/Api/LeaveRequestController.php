<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRule;
use App\Models\Biodata;
use App\Models\Holiday;
use App\Models\LeaveBalances;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeaveRequestController extends Controller
{
    public function submitForm(Request $request)
    {
        try {
            $employee = Biodata::where('NPK', $request->npk)->first();
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found.'
                ], 404);
            }

            $approval_actors = ApprovalRule::leftJoin('approval_depts', 'approval_rules.rules_id', '=', 'approval_depts.id')
                ->whereJsonContains('approval_depts.dept', (string) $employee->ID_DEPT)
                ->select('approval_rules.*')
                ->orderBy('approval_rules.level', 'asc')
                ->get();

            if ($approval_actors->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Approval actors not found. Hubungi HR untuk informasi lebih lanjut.'
                ], 404);
            }

            $startDate = Carbon::parse($request->tanggal_mulai);
            $endDate = Carbon::parse($request->tanggal_selesai);

            if ($startDate->gt($endDate)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tanggal mulai tidak boleh lebih dari tanggal selesai.'
                ], 400);
            }

            $holidays = Holiday::whereBetween('holiday_date', [
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            ])->get()->map(function ($h) {
                return Carbon::parse($h->holiday_date)->format('Y-m-d');
            })->toArray();

            // dari tanggal selesai dikurangi tanggal mulai, dikurangi hari weekdays, dan holidays
            $total_days = 0;
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                if ($date->isWeekend() || in_array($date->format('Y-m-d'), $holidays)) {
                    continue;
                }
                $total_days++;
            }

            if ($total_days <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Total hari cuti tidak valid (0 hari kerja).'
                ], 400);
            }

            $balance = LeaveBalances::where('NPK', $request->npk)
                ->where('leave_type_id', $request->jenis_cuti)
                ->where('year', date('Y'))
                ->first();

            if (!$balance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cuti tidak ditemukan. Hubungi HR untuk informasi lebih lanjut.'
                ], 404);
            }

            if ($balance->remained_days < $total_days) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jumlah Hari Cuti Melebihi Jatah Cuti. (Hitungan sistem: '.$total_days.' hari)'
                ], 400);
            }

            DB::beginTransaction();

            $random = Str::random();
            
            foreach ($approval_actors as $approval_actor) {
                LeaveRequest::create([
                    'NPK' => $request->npk,
                    'leave_type_id' => $request->jenis_cuti,
                    'start_date' => $request->tanggal_mulai,
                    'end_date' => $request->tanggal_selesai,
                    'total_days' => $total_days,
                    'reason' => $request->keterangan ?? '',
                    'approval_id' => $approval_actor->approval_id,
                    'approval_level' => $approval_actor->level,
                    'approval_progress' => '1',
                    'approval_date' => null,
                    'status' => 'pending',
                    'token' => $random,
                    'void' => 'false',
                ]);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Leave request submitted successfully. Waiting for approval.'
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function getHistory(Request $request)
    {
        $request->validate([
            'npk' => 'required',
            'tgl_lahir' => 'required'
        ]);

        $employeePkwt = DB::connection('cii')->table('PKWT')->where('NPK', $request->npk)->first();
        if (!$employeePkwt) {
            return response()->json([
                'success' => false,
                'message' => 'NPK tidak ditemukan.'
            ], 404);
        }

        $birth = $employeePkwt->TGLLAHIR;
        if (!$birth) {
            return response()->json([
                'success' => false,
                'message' => 'Data tanggal lahir tidak ditemukan.'
            ], 404);
        }

        $expectedDmy = date('dmy', strtotime($birth));
        $expectedYmd = date('Y-m-d', strtotime($birth));

        // Accept format dmy (password login) OR Y-m-d (tgl_lahir plain API param)
        $inputTgl = $request->tgl_lahir;
        if ($inputTgl !== $expectedDmy && $inputTgl !== $expectedYmd && date('Y-m-d', strtotime($inputTgl)) !== $expectedYmd) {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal lahir tidak sesuai.'
            ], 401);
        }

        $employee = DB::connection('cii')
            ->table('BIODATA')
            ->join('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->where('BIODATA.NPK', $request->npk)
            ->select('BIODATA.*', 'DEPT.DEPARTEMENT', 'DEPT.IS_SEWING')
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Biodata tidak ditemukan.'
            ], 404);
        }

        // Ambil data pengajuan aktif (dimana level approval sesuai progress) beserta relasinya
        $activeRequests = LeaveRequest::with('leaveType')
            ->where('NPK', $request->npk)
            ->whereColumn('approval_level', 'approval_progress')
            ->distinct('token')
            ->orderBy('created_at', 'desc')
            ->get();

        // Batch fetch nama approver
        $approvers = DB::connection('cii')->table('BIODATA')
            ->whereIn('NPK', $activeRequests->pluck('approval_id'))
            ->pluck('NAMA_KARYAWAN', 'NPK');

        $rows = [];
        foreach ($activeRequests as $activeRow) {
            
            // Penentuan overall status dari state row aktif
            if ($activeRow->status === 'rejected') {
                $overallStatus = 'rejected';
            } elseif ($activeRow->status === 'approved') {
                $overallStatus = 'approved';
            } else { // pending atau waiting
                $overallStatus = $activeRow->approval_level > 1 ? 'partial' : 'pending';
            }

            $rows[] = [
                'token'          => $activeRow->token,
                'npk'            => $activeRow->NPK,
                'nama'           => $employee->NAMA_KARYAWAN,
                'dept'           => $employee->DEPARTEMENT,
                'leave_type'     => $activeRow->leaveType->name ?? '-',
                'start_date'     => $activeRow->start_date ? Carbon::parse($activeRow->start_date)->format('Y-m-d') : null,
                'end_date'       => $activeRow->end_date ? Carbon::parse($activeRow->end_date)->format('Y-m-d') : null,
                'total_days'     => $activeRow->total_days,
                'reason'         => $activeRow->reason,
                'approver_name'  => $approvers[$activeRow->approval_id] ?? $activeRow->approval_id,
                'approver_level' => $activeRow->approval_level,
                'approver_status'=> $activeRow->status,
                'overall_status' => $overallStatus,
                'void'           => $activeRow->void,
                'created_at'     => $activeRow->created_at ? Carbon::parse($activeRow->created_at)->format('Y-m-d H:i:s') : null,
                'comment'        => $activeRow->comment,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => $rows
        ], 200);
    }
}
