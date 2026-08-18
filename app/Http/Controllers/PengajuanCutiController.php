<?php

namespace App\Http\Controllers;

use App\Models\ApprovalDept;
use App\Models\ApprovalRule;
use App\Models\Biodata;
use App\Models\Holiday;
use App\Models\LeaveBalances;
use App\Models\LeaveRequest;
use App\Models\LeaveTypes;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RealRashid\SweetAlert\Facades\Alert;
use Yajra\DataTables\Facades\DataTables;

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
                ->join('PKWT','BIODATA.NPK','=','PKWT.NPK')
                ->where('BIODATA.NPK', $npk)
                ->select('BIODATA.*', 'DEPT.DEPARTEMENT', 'DEPT.IS_SEWING', 'PKWT.JK')
                ->first();

        if (!$employee) {
            return redirect()->route('pengajuan-cuti.login');
        }

        // Hanya kirim jenis cuti yang sesuai dengan gender karyawan (gender_type 'A' = semua gender).
        // JK karyawan dinormalisasi agar perbandingan konsisten (mis. "l"/"L").
        $jk = $employee->JK ? strtoupper(trim($employee->JK)) : null;

        $masterLeaveType = LeaveTypes::where('is_active', true)
            ->get()
            ->filter(function ($type) use ($jk) {
                $genderType = $type->gender_type ? strtoupper(trim($type->gender_type)) : 'A';
                return $genderType === 'A' || $jk === null || $genderType === $jk;
            })
            ->values();

        $holidays = Holiday::pluck('holiday_date')->map(function($date) {
            return Carbon::parse($date)->format('Y-m-d');
        })->toArray();

        return view('cuti.form', compact('employee', 'masterLeaveType', 'holidays'));
    }

    /**
     * Submit pengajuan cuti. Mendukung multi-cuti (>=1 baris) dalam satu kali kirim,
     * namun tiap baris tetap diproses sebagai pengajuan approval yang TERPISAH
     * (token & alur approval masing-masing sendiri).
     *
     * Aturan tambahan untuk multi-cuti dalam satu pengajuan:
     * - Satu jenis cuti hanya boleh dipilih di SATU baris (tidak boleh duplikat).
     * - Rentang tanggal antar baris tidak boleh tumpang tindih. tanggal_mulai & tanggal_selesai
     *   dihitung INCLUSIVE (keduanya adalah hari cuti), jadi interval yang dibandingkan
     *   adalah [mulai, selesai] tertutup di kedua ujung.
     *
     * Payload yang diharapkan:
     *   leaves[0][jenis_cuti], leaves[0][tanggal_mulai], leaves[0][tanggal_selesai], leaves[0][keterangan]
     *   leaves[1][...], dst.
     */
    public function submitForm(Request $request)
    {
        try {
            $employee = Biodata::where('NPK', $request->npk)->first();
            if (!$employee) {
                Alert::error('Error', 'Employee not found.');
                return back();
            }

            $leaves = $request->input('leaves', []);

            if (empty($leaves)) {
                Alert::error('Error', 'Minimal 1 pengajuan cuti harus diisi.');
                return back();
            }

            $request->validate([
                'leaves'                     => 'required|array|min:1',
                // distinct: satu jenis cuti hanya boleh dipilih di satu form dalam sekali kirim
                'leaves.*.jenis_cuti'        => 'required|exists:leave_types,id|distinct',
                'leaves.*.tanggal_mulai'     => 'required|date|after_or_equal:today',
                // tanggal_selesai = hari terakhir cuti (inclusive), boleh sama dengan tanggal_mulai untuk cuti 1 hari
                'leaves.*.tanggal_selesai'   => 'required|date|after_or_equal:leaves.*.tanggal_mulai',
                'leaves.*.keterangan'        => 'required|string',
            ], [
                'leaves.*.jenis_cuti.distinct' => 'Jenis cuti yang sama tidak boleh dipilih lebih dari sekali dalam satu pengajuan.',
                'leaves.*.tanggal_mulai.after_or_equal' => 'Tanggal mulai tidak boleh sebelum hari ini.',
                'leaves.*.tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            ]);

            $approval_actors = ApprovalRule::leftJoin('approval_depts', 'approval_rules.rules_id', '=', 'approval_depts.id')
                ->whereJsonContains('approval_depts.dept', (string) $employee->ID_DEPT)
                ->select('approval_rules.*')
                ->orderBy('approval_rules.level', 'asc')
                ->get();

            if ($approval_actors->isEmpty()) {
                Alert::error('Error', 'Approval actors not found. Hubungi HR untuk informasi lebih lanjut.');
                return back();
            }

            // ── Tahap 1: Validasi SEMUA baris cuti dulu, sebelum menyimpan apapun ──
            $preparedLeaves = [];
            foreach ($leaves as $i => $leave) {
                $rowLabel = 'Cuti #' . ((int) $i + 1);

                $startDate = Carbon::parse($leave['tanggal_mulai']);
                $endDate   = Carbon::parse($leave['tanggal_selesai']);

                if ($startDate->lt(Carbon::today())) {
                    Alert::error('Error', "$rowLabel: Tanggal mulai tidak boleh sebelum hari ini.");
                    return back();
                }

                // tanggal_selesai = hari terakhir cuti (inclusive), tidak boleh sebelum tanggal_mulai
                if ($endDate->lt($startDate)) {
                    Alert::error('Error', "$rowLabel: Tanggal selesai tidak boleh sebelum tanggal mulai.");
                    return back();
                }

                // ── Cek tumpang tindih terhadap baris cuti lain dalam pengajuan yang sama ──
                // Interval dianggap tertutup/inclusive [start, end] karena tanggal_selesai adalah
                // hari cuti terakhir (bukan hari kembali kerja).
                foreach ($preparedLeaves as $prevIndex => $prev) {
                    $prevStart = Carbon::parse($prev['start_date']);
                    $prevEnd   = Carbon::parse($prev['end_date']);

                    $overlap = $startDate->lte($prevEnd) && $prevStart->lte($endDate);
                    if ($overlap) {
                        Alert::error('Error', "$rowLabel: Tanggal bertumpang tindih dengan Cuti #" . ($prevIndex + 1) . ".");
                        return back();
                    }
                }

                $holidays = Holiday::whereBetween('holiday_date', [
                    $startDate->format('Y-m-d'),
                    $endDate->format('Y-m-d')
                ])->get()->map(function ($h) {
                    return Carbon::parse($h->holiday_date)->format('Y-m-d');
                })->toArray();

                // Hitung hari kerja dari tanggal_mulai s.d. tanggal_selesai (inclusive, kedua tanggal
                // dihitung sebagai hari cuti), melewati akhir pekan & hari libur.
                $total_days = 0;
                for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                    if ($date->isWeekend() || in_array($date->format('Y-m-d'), $holidays)) {
                        continue;
                    }
                    $total_days++;
                }

                if ($total_days <= 0) {
                    Alert::error('Error', "$rowLabel: Rentang tanggal tidak valid (hanya berisi akhir pekan/hari libur).");
                    return back();
                }

                $balance = LeaveBalances::where('NPK', $request->npk)
                    ->where('leave_type_id', $leave['jenis_cuti'])
                    ->where('year', $startDate->year)
                    ->first();

                if (!$balance) {
                    Alert::error('Error', "$rowLabel: Jatah cuti tidak ditemukan. Hubungi HR untuk informasi lebih lanjut.");
                    return back();
                }

                // Sisa hari cuti membatasi rentang tanggal selesai yang boleh diajukan.
                if ($balance->remained_days < $total_days) {
                    Alert::error('Error', "$rowLabel: Jumlah Hari Cuti ({$total_days} hari) Melebihi Sisa Jatah Cuti ({$balance->remained_days} hari).");
                    return back();
                }

                $preparedLeaves[] = [
                    'leave_type_id' => $leave['jenis_cuti'],
                    'start_date'    => $leave['tanggal_mulai'],
                    'end_date'      => $leave['tanggal_selesai'],
                    'total_days'    => $total_days,
                    'reason'        => $leave['keterangan'] ?? '',
                ];
            }

            // ── Tahap 2: Simpan — setiap baris cuti menjadi pengajuan approval TERPISAH (token beda) ──
            DB::beginTransaction();

            foreach ($preparedLeaves as $leaveData) {
                $token = Str::random();

                foreach ($approval_actors as $approval_actor) {
                    LeaveRequest::create([
                        'NPK'               => $request->npk,
                        'leave_type_id'     => $leaveData['leave_type_id'],
                        'start_date'        => $leaveData['start_date'],
                        'end_date'          => $leaveData['end_date'],
                        'total_days'        => $leaveData['total_days'],
                        'reason'            => $leaveData['reason'],
                        'approval_id'       => $approval_actor->approval_id,
                        'approval_level'    => $approval_actor->level,
                        'approval_progress' => '1',
                        'approval_date'     => null,
                        'status'            => 'pending',
                        'token'             => $token,
                        'void'              => 'false',
                    ]);
                }
            }

            DB::commit();
            $count = count($preparedLeaves);
            Alert::success('Success', "{$count} pengajuan cuti berhasil dikirim. Menunggu approval.");
            return redirect()->route('pengajuan-cuti.form');
        } catch (\Throwable $th) {
            DB::rollBack();
            Alert::error('Error', $th->getMessage());
            return back();
        }
    }

    /**
     * Cek sisa saldo cuti, dan (opsional) hitung batas maksimum tanggal selesai
     * berdasarkan sisa saldo + tanggal mulai yang dipilih (dipakai front-end untuk
     * membatasi date-picker "Tanggal Selesai").
     */
    public function getLeaveBalance(Request $request)
    {
        $npk = $request->npk;
        $leaveTypeId = $request->leave_type_id;
        $startDate = $request->start_date; // optional
        $year = date('Y');

        if (!$npk || !$leaveTypeId) {
            return response()->json(['success' => false, 'error' => 'Invalid parameters'], 400);
        }

        $balance = LeaveBalances::where('NPK', $npk)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();

        $remained = $balance ? $balance->remained_days : 0;
        $used = $balance ? $balance->used_days : 0;

        // Hitung tanggal selesai maksimum yang masih memenuhi sisa saldo cuti (inclusive),
        // dengan melewati akhir pekan & hari libur.
        $maxEndDate = null;
        if ($startDate && $remained > 0) {
            try {
                $holidays = Holiday::pluck('holiday_date')->map(function ($d) {
                    return Carbon::parse($d)->format('Y-m-d');
                })->toArray();

                $count = 0;
                $cursor = Carbon::parse($startDate);
                // Batas pengaman agar tidak infinite loop jika data tidak wajar
                $guard = 0;
                while ($count < $remained && $guard < 3650) {
                    if (!$cursor->isWeekend() && !in_array($cursor->format('Y-m-d'), $holidays)) {
                        $count++;
                    }
                    if ($count >= $remained) {
                        break;
                    }
                    $cursor->addDay();
                    $guard++;
                }
                // $cursor sekarang berada di hari cuti terakhir yang masih tercakup sisa saldo (inclusive)
                $maxEndDate = $cursor->format('Y-m-d');
            } catch (\Exception $e) {
                $maxEndDate = null;
            }
        }

        if ($balance) {
            $keterangan = "Sisa cuti Anda: {$remained} hari, Terpakai: {$used}";
        } else {
            $keterangan = 'Belum ada data jatah cuti untuk jenis ini di tahun berjalan.';
        }

        return response()->json([
            'success'       => true,
            'sisa'          => $remained,
            'keterangan'    => $keterangan,
            'remained_days' => $remained,
            'used_days'     => $used,
            'max_end_date'  => $maxEndDate,
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
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('token');

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
                'comment'        => $activeRow->comment,
            ];
        }

        if (request()->ajax()) {
            return DataTables::of(collect($rows))
                ->addIndexColumn()
                ->addColumn('karyawan', function($row) {
                    return '<strong>'.$row['nama'].'</strong><br><small class="text-muted">'.$row['npk'].' &middot; '.$row['dept'].'</small>';
                })
                ->addColumn('periode', function($row) {
                    $start = Carbon::parse($row['start_date'])->format('d M Y');
                    $end   = Carbon::parse($row['end_date'])->format('d M Y');
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
                    $row['start_date'] = Carbon::parse($row['start_date'])->format('d M Y');
                    $row['end_date'] = Carbon::parse($row['end_date'])->format('d M Y');
                    $row['created_at'] = Carbon::parse($row['created_at'])->format('d M Y');
                    $info = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                    return '<button type="button" class="btn btn-sm btn-info btn-detail" data-info="'.$info.'"><i class="fas fa-eye fa-sm"></i> Detail</button>';
                })
                ->rawColumns(['karyawan', 'status_badge', 'aksi'])
                ->make(true);
        }

        return view('cuti.riwayat', compact('employee'));
    }
}