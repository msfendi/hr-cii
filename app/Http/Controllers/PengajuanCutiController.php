<?php

namespace App\Http\Controllers;

use App\Models\ApprovalDept;
use App\Models\ApprovalRule;
use App\Models\Biodata;
use App\Models\LeaveBalances;
use App\Models\LeaveRequest;
use App\Models\LeaveTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RealRashid\SweetAlert\Facades\Alert;

class PengajuanCutiController extends Controller
{
    public function login()
    {
        return view('cuti.login');
    }

    public function logout()
    {
        session()->forget('cuti_employee_npk');
        return redirect()->route('pengajuan-cuti.login');
    }

    public function verifyManual(Request $request)
    {
        $request->validate([
            'npk' => 'required',
            'password' => 'required' 
        ]);

        $employee = DB::connection('cii')->table('PKWT')->where('NPK', $request->npk)->first();
        
        if (!$employee) {
            Alert::error('Error', 'NPK tidak ditemukan.');
            return back();
        }

        $birth = DB::connection('cii')->table('PKWT')
            ->where('NPK', $request->npk)
            ->value('TGLLAHIR');

        if (!$birth) {
            Alert::error('Error', 'Data tanggal lahir tidak ditemukan.');
            return back();
        }

        $password = date('dmy', strtotime($birth));

        if ($request->password != $password) {
            Alert::error('Error', 'Password salah.');
            return back();
        }

        // Login employee to session
        session(['cuti_employee_npk' => $employee->NPK]);
        return redirect()->route('pengajuan-cuti.form');
    }

    public function qrLogin(Request $request)
    {
        $npk = $request->npk;
        
        $employee = DB::connection('cii')->table('PKWT')->where('NPK', $npk)->first();
        if (!$employee) {
            Alert::error('Error', 'NPK tidak ditemukan.');
            return redirect()->route('pengajuan-cuti.login');
        }

        // Login employee to session
        session(['cuti_employee_npk' => $employee->NPK]);
        Alert::success('Berhasil', 'Login dengan QR Code berhasil');
        return redirect()->route('pengajuan-cuti.form');
    }

    public function form(Request $request)
    {
        $npk = session('cuti_employee_npk');
        if (!$npk) {
            Alert::error('Error', 'Silahkan login terlebih dahulu.');
            return redirect()->route('pengajuan-cuti.login');
        }

        $employee = DB::connection('cii')
                ->table('BIODATA')
                ->join('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
                ->where('NPK', $npk)
                ->select('BIODATA.*', 'DEPT.DEPARTEMENT', 'DEPT.IS_SEWING')
                ->first();

        if (!$employee) {
            return redirect()->route('pengajuan-cuti.login');
        }

        $masterLeaveType = LeaveTypes::all();
        
        $holidays = null;

        return view('cuti.form', compact('employee', 'masterLeaveType', 'holidays'));
    }

    public function submitForm(Request $request)
    {
        try {
            $employee = Biodata::where('NPK', $request->npk)->first();
            if (!$employee) {
                Alert::error('Error', 'Employee not found.');
                return back();
            }

            $approval_actors = ApprovalRule::leftJoin('approval_depts', 'approval_rules.rules_id', '=', 'approval_depts.id')
                ->whereJsonContains('approval_depts.dept', (string) $employee->ID_DEPT)
                ->select('approval_rules.*')
                ->orderBy('approval_rules.level', 'asc')
                ->get();

            if (!$approval_actors) {
                Alert::error('Error', 'Approval actors not found. Hubungi HR untuk informasi lebih lanjut.');
                return back();
            }

            $balance = LeaveBalances::where('NPK', $request->npk)
                ->where('leave_type_id', $request->jenis_cuti)
                ->where('year', date('Y'))
                ->first();

            if (!$balance) {
                Alert::error('Error', 'Cuti tidak ditemukan. Hubungi HR untuk informasi lebih lanjut.');
                return back();
            }

            if ($balance->remained_days < $request->jumlah_hari) {
                Alert::error('Error', 'Jumlah Hari Cuti Melebihi Jatah Cuti.');
                return back();
            }

            DB::beginTransaction();

            $random = Str::random();
            
            foreach ($approval_actors as $approval_actor) {
                LeaveRequest::create([
                    'NPK' => $request->npk,
                    'leave_type_id' => $request->jenis_cuti,
                    'start_date' => $request->tanggal_mulai,
                    'end_date' => $request->tanggal_selesai,
                    'total_days' => $request->jumlah_hari,
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
            Alert::success('Success', 'Leave request submitted successfully. Waiting for approval.');
            return redirect()->route('pengajuan-cuti.form');
        } catch (\Throwable $th) {
            DB::rollBack();
            Alert::error('Error', $th->getMessage());
            return back();
        }
    }

    public function getLeaveBalance(Request $request)
    {
        $npk = $request->npk;
        $leaveTypeId = $request->leave_type_id;
        $year = date('Y');

        if (!$npk || !$leaveTypeId) {
            return response()->json(['success' => false, 'error' => 'Invalid parameters'], 400);
        }

        $balance = LeaveBalances::where('NPK', $npk)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();

        if ($balance) {
            $sisa = $balance->remained_days;
            $keterangan = "Sisa cuti Anda: {$sisa} hari, Terpakai: {$balance->used_days}";
            return response()->json([
                'success' => true,
                'sisa' => $sisa,
                'keterangan' => $keterangan,
                'remained_days' => $balance->remained_days,
                'used_days' => $balance->used_days
            ]);
        }

        // Tampilkan bahwa belum ada data saldo jika record tidak ditemukan
        return response()->json([
            'success' => true,
            'sisa' => 0,
            'keterangan' => 'Belum ada data jatah cuti untuk jenis ini di tahun berjalan.',
            'remained_days' => 0,
            'used_days' => 0
        ]);
    }

    /**
     * Admin: riwayat pengajuan cuti semua karyawan.
     * Setiap token hanya ditampilkan 1 row (row terbaru/per-approval aktif).
     */
    public function riwayat()
    {
        $npk = session('cuti_employee_npk');
        if (!$npk) {
            return redirect()->route('pengajuan-cuti.login');
        }

        $employee = DB::connection('cii')
            ->table('BIODATA')
            ->join('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->where('BIODATA.NPK', $npk)
            ->select('BIODATA.*', 'DEPT.DEPARTEMENT', 'DEPT.IS_SEWING')
            ->first();

        if (!$employee) {
            return redirect()->route('pengajuan-cuti.login');
        }

        // Ambil data pengajuan aktif (dimana level approval sesuai progress) beserta relasinya
        $activeRequests = LeaveRequest::with('leaveType')
            ->where('NPK', $npk)
            ->whereColumn('approval_level', 'approval_progress')
            ->distinct('token')
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
                'start_date'     => $activeRow->start_date,
                'end_date'       => $activeRow->end_date,
                'total_days'     => $activeRow->total_days,
                'reason'         => $activeRow->reason,
                'approver_name'  => $approvers[$activeRow->approval_id] ?? $activeRow->approval_id,
                'approver_level' => $activeRow->approval_level,
                'approver_status'=> $activeRow->status,
                'overall_status' => $overallStatus,
                'void'           => $activeRow->void,
                'created_at'     => $activeRow->created_at,
            ];
        }

        if (request()->ajax()) {
            return \Yajra\DataTables\Facades\DataTables::of(collect($rows))
                ->addIndexColumn()
                ->addColumn('karyawan', function($row) {
                    return '<strong>'.$row['nama'].'</strong><br><small class="text-muted">'.$row['npk'].' &middot; '.$row['dept'].'</small>';
                })
                ->addColumn('periode', function($row) {
                    $start = \Carbon\Carbon::parse($row['start_date'])->format('d M Y');
                    $end   = \Carbon\Carbon::parse($row['end_date'])->format('d M Y');
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
                ->addColumn('aksi', function($row) {
                    $row['start_date'] = \Carbon\Carbon::parse($row['start_date'])->format('d M Y');
                    $row['end_date'] = \Carbon\Carbon::parse($row['end_date'])->format('d M Y');
                    $row['created_at'] = \Carbon\Carbon::parse($row['created_at'])->format('d M Y');
                    $info = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                    return '<button type="button" class="btn btn-sm btn-info btn-detail" data-info="'.$info.'"><i class="fas fa-eye fa-sm"></i> Detail</button>';
                })
                ->rawColumns(['karyawan', 'status_badge', 'aksi'])
                ->make(true);
        }

        return view('cuti.riwayat', compact('employee'));
    }
}
