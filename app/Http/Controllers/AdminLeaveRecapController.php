<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Services\ApprovalWa\LeaveApprovalEffects;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class AdminLeaveRecapController extends Controller
{
    public function index()
    {
        return view('leave-recap.index');
    }

    public function getData(Request $request)
    {
        $query = LeaveRequest::with('leaveType')
            ->whereColumn('approval_level', 'approval_progress')
            ->select('leave_requests.*');

        if ($request->has('month_year') && $request->month_year != '') {
            $parts = explode('-', $request->month_year);
            if (count($parts) == 2) {
                $query->whereYear('start_date', $parts[0])
                      ->whereMonth('start_date', $parts[1]);
            }
        }

        $activeRequests = $query->get()->unique('token');

        $npkList = $activeRequests->pluck('NPK')->unique()->toArray();
        
        $employees = DB::connection('cii')->table('BIODATA')
            ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->whereIn('BIODATA.NPK', $npkList)
            ->select('BIODATA.NPK', 'BIODATA.NAMA_KARYAWAN', 'DEPT.DEPARTEMENT')
            ->get()
            ->keyBy('NPK');

        $rows = [];
        foreach ($activeRequests as $activeRow) {
            $employee = $employees->get($activeRow->NPK);

            if ($activeRow->status === 'rejected') {
                $overallStatus = 'rejected';
            } elseif ($activeRow->status === 'approved') {
                $overallStatus = 'approved';
            } else {
                $overallStatus = $activeRow->approval_level > 1 ? 'partial' : 'pending';
            }

            $rows[] = [
                'token'          => $activeRow->token,
                'npk'            => $activeRow->NPK,
                'nama'           => $employee ? $employee->NAMA_KARYAWAN : $activeRow->NPK,
                'dept'           => $employee ? $employee->DEPARTEMENT : '-',
                'leave_type'     => $activeRow->leaveType->name ?? '-',
                'start_date'     => $activeRow->start_date,
                'end_date'       => $activeRow->end_date,
                'total_days'     => $activeRow->total_days,
                'reason'         => $activeRow->reason,
                'overall_status' => $overallStatus,
                'has_attach'     => !empty($activeRow->attach_files),
                'is_negative_leave' => str_contains($activeRow->reason, '[Hutang Cuti]'),
                'created_at'     => $activeRow->created_at->format('Y-m-d H:i:s'),
            ];
        }

        $collection = collect($rows);

        if ($request->has('status') && $request->status != '') {
            $collection = $collection->where('overall_status', $request->status);
        }

        return DataTables::of($collection)
            ->addIndexColumn()
            ->addColumn('karyawan', function($row) {
                return '<strong>'.$row['nama'].'</strong><br><small class="text-muted">'.$row['npk'].' &middot; '.$row['dept'].'</small>';
            })
            ->addColumn('periode', function($row) {
                $start = Carbon::parse($row['start_date'])->format('d M y');
                $end   = Carbon::parse($row['end_date'])->format('d M y');
                return $start . ' – ' . $end;
            })
            ->addColumn('hari', function($row) {
                return $row['total_days'] . ' hari';
            })
            ->addColumn('status_badge', function($row) {
                if($row['overall_status'] === 'approved') return '<span class="badge badge-success">Disetujui</span>';
                elseif($row['overall_status'] === 'rejected') return '<span class="badge badge-danger">Ditolak</span>';
                elseif($row['overall_status'] === 'partial') return '<span class="badge badge-warning text-white">Parsial</span>';
                return '<span class="badge badge-secondary">Menunggu</span>';
            })
            ->addColumn('action', function($row) {
                return '<button class="btn btn-sm btn-info btn-detail" data-token="'.$row['token'].'"><i class="fas fa-eye"></i> Detail</button> '
                     . '<button class="btn btn-sm btn-danger btn-delete" data-token="'.$row['token'].'" data-nama="'.e($row['nama']).'" data-status="'.$row['overall_status'].'" data-hari="'.$row['total_days'].'"><i class="fas fa-trash"></i> Hapus</button>';
            })
            ->rawColumns(['karyawan', 'status_badge', 'action'])
            ->make(true);
    }

    public function getDetail($token)
    {
        $requests = LeaveRequest::with('leaveType')
            ->where('token', $token)
            ->where('status', '!=', 'cancelled')
            ->orderBy('approval_level', 'asc')
            ->get();

        if ($requests->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Data not found']);
        }

        $firstReq = $requests->first();
        $employee = DB::connection('cii')->table('BIODATA')
            ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->where('BIODATA.NPK', $firstReq->NPK)
            ->select('BIODATA.NAMA_KARYAWAN', 'DEPT.DEPARTEMENT')
            ->first();

        $approverNpks = $requests->pluck('approval_id')->unique()->toArray();
        $approvers = DB::connection('cii')->table('BIODATA')
            ->whereIn('NPK', $approverNpks)
            ->pluck('NAMA_KARYAWAN', 'NPK');

        $detailPaths = $requests->map(function($req) use ($approvers) {
            return [
                'level' => $req->approval_level,
                'approver_name' => $approvers->get($req->approval_id, $req->approval_id),
                'status' => $req->status,
                'date' => $req->approval_date ? Carbon::parse($req->approval_date)->format('d M Y') : '-',
                'comment' => $req->comment,
            ];
        });

        $formattedFiles = [];
        if (!empty($firstReq->attach_files)) {
            $files = is_array($firstReq->attach_files) ? $firstReq->attach_files : json_decode($firstReq->attach_files, true);
            if (is_array($files)) {
                foreach ($files as $filePath) {
                    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                    $formattedFiles[] = [
                        'path'     => $filePath,
                        'url'      => asset('storage/' . $filePath),
                        'name'     => basename($filePath),
                        'ext'      => $ext,
                        'is_image' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp']),
                        'is_pdf'   => $ext === 'pdf',
                        'is_word'  => in_array($ext, ['doc', 'docx']),
                    ];
                }
            }
        }

        $data = [
            'npk'          => $firstReq->NPK,
            'nama'         => $employee ? $employee->NAMA_KARYAWAN : $firstReq->NPK,
            'dept'         => $employee ? $employee->DEPARTEMENT : '-',
            'leave_type'   => $firstReq->leaveType->name ?? '-',
            'start_date'   => Carbon::parse($firstReq->start_date)->format('d M Y'),
            'end_date'     => Carbon::parse($firstReq->end_date)->format('d M Y'),
            'total_days'   => $firstReq->total_days,
            'reason'       => $firstReq->reason,
            'approvals'    => $detailPaths,
            'attach_files' => $formattedFiles,
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Hapus SATU pengajuan (semua baris dengan token yang sama).
     *
     * Kalau pengajuan sudah selesai (semua level menyetujui), saldo cuti sudah
     * terpotong dan record Overtime "CT"/"CU" sudah dibuat saat finalisasi.
     * Keduanya DIKEMBALIKAN dulu sebelum barisnya dihapus — kalau tidak, saldo
     * karyawan tetap terpotong untuk cuti yang datanya sudah tidak ada.
     *
     * Pengajuan yang belum selesai (pending / parsial / ditolak) tidak menyentuh
     * saldo, jadi cukup dihapus.
     */
    public function destroy($token)
    {
        $rows = LeaveRequest::where('token', $token)->get();

        if ($rows->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan tidak ditemukan (mungkin sudah dihapus).',
            ], 404);
        }

        $active = $rows->whereNotIn('status', ['cancelled']);

        // Pengajuan dianggap sudah final kalau ada baris aktif dan semuanya approved.
        $isFinal = $active->isNotEmpty()
            && $active->every(fn ($r) => strtolower((string) $r->status) === 'approved');

        $restored = false;

        try {
            DB::connection('cii')->transaction(function () use ($token, $active, $isFinal, &$restored) {
                if ($isFinal) {
                    $anchor = $active->sortByDesc('approval_level')->first();
                    LeaveApprovalEffects::reverseFinalize($anchor);
                    $restored = true;
                }

                LeaveRequest::where('token', $token)->delete();
            });
        } catch (\Throwable $e) {
            Log::error("[LeaveRecap] gagal hapus token {$token}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pengajuan: ' . $e->getMessage(),
            ], 500);
        }

        $filesDeleted = $this->deleteAttachments($rows);

        $actor = optional(auth()->user())->name
            ?? optional(auth()->user())->npk
            ?? 'unknown';

        Log::info(sprintf(
            '[LeaveRecap] token %s dihapus oleh %s (%d baris, saldo dikembalikan: %s, file: %d)',
            $token, $actor, $rows->count(), $restored ? 'ya' : 'tidak', $filesDeleted
        ));

        return response()->json([
            'success'          => true,
            'message'          => $restored
                ? 'Pengajuan dihapus. Saldo cuti dikembalikan.'
                : 'Pengajuan dihapus.',
            'deleted_rows'     => $rows->count(),
            'restored_balance' => $restored,
            'deleted_files'    => $filesDeleted,
        ]);
    }

    /**
     * Hapus file lampiran milik baris-baris pengajuan ini.
     *
     * Hanya file di dalam folder `leave-attachments/` yang dihapus — path dari
     * DB tidak dipercaya begitu saja.
     *
     * @return int jumlah file yang terhapus
     */
    private function deleteAttachments($rows): int
    {
        $paths = [];

        foreach ($rows as $row) {
            $files = $row->attach_files;

            if (is_string($files)) {
                $files = json_decode($files, true);
            }

            if (is_array($files)) {
                foreach ($files as $path) {
                    if (is_string($path) && trim($path) !== '') {
                        $paths[] = $path;
                    }
                }
            }
        }

        $deleted = 0;

        foreach (array_unique($paths) as $path) {
            $relative = ltrim(str_replace('\\', '/', $path), '/');

            // Guard: hanya di dalam folder lampiran cuti, tanpa path traversal.
            if (!str_starts_with($relative, 'leave-attachments/') || str_contains($relative, '..')) {
                continue;
            }

            $full = storage_path('app/public/' . $relative);

            if (is_file($full) && @unlink($full)) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
