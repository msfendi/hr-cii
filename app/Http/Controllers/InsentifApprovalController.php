<?php

namespace App\Http\Controllers;

use App\Models\InsentifApproval;
use App\Models\InsentifRoleFormula;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InsentifApprovalController extends Controller
{
    public function index(Request $request)
    {
        // =========================
        // FILTER STATUS PERIOD
        // =========================
        $filter = $request->get('status', 'open');

        // =========================
        // JOIN PERIOD
        // =========================
        $query = InsentifApproval::query()
            ->join('payroll_periods', 'insentif_approvals.period_id', '=', 'payroll_periods.id')
            ->select(
                'insentif_approvals.*',
                'payroll_periods.name as period_name',
                'payroll_periods.id as period_id',
                'payroll_periods.is_closed'
            );

        if ($filter === 'open') {
            $query->where('payroll_periods.is_closed', false);
        }

        if ($filter === 'closed') {
            $query->where('payroll_periods.is_closed', true);
        }

        $data = $query
            ->latest('insentif_approvals.id')
            ->get();

        // =========================
        // EMPLOYEE MASTER
        // =========================
        $employees = collect(DB::select("
        SELECT NPK, NAMA_KARYAWAN FROM BIODATA
        UNION
        SELECT NPK, NAMA_KARYAWAN FROM BIODATA_KELUAR
    "))->keyBy('NPK');

        // =========================
        // FORMAT PROGRESS USER
        // =========================
        $data = $data
            ->sortByDesc('id')
            ->values()
            ->transform(function ($row) use ($employees) {

                $progress = collect($row->progress)->map(function ($p) use ($employees) {

                    $npkList = is_array($p['npk'])
                        ? $p['npk']
                        : json_decode($p['npk'], true);

                    if (!is_array($npkList)) $npkList = [];

                    $p['users'] = collect($npkList)->map(function ($npk) use ($employees) {
                        return [
                            'npk' => $npk,
                            'name' => $employees[$npk]->NAMA_KARYAWAN ?? '-'
                        ];
                    });

                    return $p;
                });

                $row->progress = $progress;

                return $row;
            });

        return view('insentif_approve.index', compact('data', 'filter'));
    }

    public function approve(Request $request, $id)
    {
        $data = InsentifApproval::findOrFail($id);
        $npkLogin = $request->npk;

        $progress = collect($data->progress);
        $approvedAt = collect($data->approved_at ?? []);

        // cari level approval aktif
        $currentIndex = $progress->search(function ($item) {
            return $item['status'] === 'pending'
                || str_contains($item['status'], 'waiting');
        });

        if ($currentIndex === false) {
            return response()->json(['message' => 'Semua sudah approve'], 400);
        }

        $row = $progress[$currentIndex];

        $npkList = is_array($row['npk'])
            ? $row['npk']
            : json_decode($row['npk'], true);

        if (!is_array($npkList)) {
            return response()->json(['message' => 'Format approver invalid'], 500);
        }

        // INIT STATUS
        if ($row['status'] === 'pending') {
            $statusList = array_fill(0, count($npkList), 'waiting');
        } else {
            $statusList = json_decode($row['status'], true);
        }

        $userIndex = array_search($npkLogin, $npkList);

        if ($userIndex === false) {
            return response()->json(['message' => 'Anda bukan approver'], 403);
        }

        if ($statusList[$userIndex] === 'approve') {
            return response()->json(['message' => 'Sudah approve'], 400);
        }

        // approve user
        $statusList[$userIndex] = 'approve';

        $approvedAtArr = $approvedAt->toArray();
        $approvedAtArr[$currentIndex][$userIndex] = now();

        $allApproved = collect($statusList)
            ->every(fn($s) => $s === 'approve');

        $progressArr = $progress->toArray();

        $progressArr[$currentIndex]['status'] =
            $allApproved
            ? 'approve'
            : json_encode($statusList);

        $progress = collect($progressArr);
        $approvedAt = collect($approvedAtArr);

        // FINAL CHECK
        $finalApprove = $progress
            ->every(fn($item) => $item['status'] === 'approve');

        $data->update([
            'progress' => $progress->values(),
            'approved_at' => $approvedAt->values(),
            'status' => $finalApprove ? 'finish' : 'pending'
        ]);

        return response()->json([
            'message' => 'Approval berhasil'
        ]);
    }
}
