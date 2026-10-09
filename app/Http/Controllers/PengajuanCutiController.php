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

        $password = date('ymd', strtotime($birth));

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

        // Ambil daftar balance cuti karyawan untuk tahun berjalan
        $leaveBalances = DB::table('leave_balances')
            ->join('leave_types', 'leave_balances.leave_type_id', '=', 'leave_types.id')
            ->where('leave_balances.NPK', $npk)
            ->where('leave_balances.year', date('Y'))
            ->select(
                'leave_types.id as leave_type_id',
                'leave_types.name as leave_type_name',
                'leave_types.code as leave_type_code',
                'leave_balances.remained_days',
                'leave_balances.used_days',
                'leave_balances.negative_leave'
            )
            ->get();

        $leaveReasons = DB::table('leave_reasons')
            ->select('id', 'leave_type_id', 'reason')
            ->orderBy('id', 'asc')
            ->get();

        return view('cuti.form', compact('employee', 'masterLeaveType', 'holidays', 'leaveBalances', 'leaveReasons'));
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

            $deptId = (string) $employee->ID_DEPT;

            // Ambil semua approval group yang terkait departemen karyawan (hindari OPENJSON SQL Server)
            $deptGroups = ApprovalDept::with(['rules' => function ($q) {
                $q->orderBy('level', 'asc');
            }])->get()->filter(function ($group) use ($deptId) {
                $depts = is_array($group->dept) ? $group->dept : (json_decode($group->dept, true) ?? []);
                return in_array((string) $deptId, array_map('strval', $depts), true);
            });

            $selectedGroup = null;

            // 1. Jika karyawan memiliki section, prioritaskan approval group khusus section tersebut
            if (!empty($employee->SECTION)) {
                $selectedGroup = $deptGroups->first(function ($group) use ($employee) {
                    return (string) $group->section === (string) $employee->SECTION;
                });
            }

            // 2. Jika tidak ada group khusus section, cari group umum departemen (section null/kosong)
            if (!$selectedGroup) {
                $selectedGroup = $deptGroups->first(function ($group) {
                    return empty($group->section);
                });
            }

            // 3. Fallback: jika tetap tidak ada, ambil group departemen pertama yang cocok
            if (!$selectedGroup) {
                $selectedGroup = $deptGroups->first();
            }

            $approval_actors = $selectedGroup ? $selectedGroup->rules : collect();

            if ($approval_actors->isEmpty()) {
                Alert::error('Error', 'Approval actors not found. Hubungi HR untuk informasi lebih lanjut.');
                return back();
            }

            // ── Tahap 1: Validasi SEMUA baris cuti dulu, sebelum menyimpan apapun ──
            $preparedLeaves = [];

            // Jenis cuti yang WAJIB upload lampiran file
            $attachRequiredCodes = [
                'menikah', 'menikahkan_anak', 'suami_istri_meninggal',
                'keluarga_meninggal', 'anak_meninggal', 'menantu_meninggal',
                'orang_tua_meninggal',
            ];
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

                // Cek apakah jenis cuti ini adalah Cuti Tahunan dan saldo cutinya sudah habis
                $leaveType = LeaveTypes::find($leave['jenis_cuti']);
                $isTahunan = $leaveType && $leaveType->code === 'tahunan';
                $isNegativeLeave = $isTahunan && ($balance->remained_days <= 0);

                // Sisa hari cuti membatasi rentang tanggal selesai yang boleh diajukan,
                // kecuali untuk cuti tahunan dengan saldo habis (menggunakan Hutang Cuti).
                if (!$isNegativeLeave && $balance->remained_days < $total_days) {
                    Alert::error('Error', "$rowLabel: Jumlah Hari Cuti ({$total_days} hari) Melebihi Sisa Jatah Cuti ({$balance->remained_days} hari).");
                    return back();
                }

                // ── Validasi lampiran file untuk jenis cuti tertentu ──
                $requiresAttach = $leaveType && in_array($leaveType->code, $attachRequiredCodes);

                if ($requiresAttach && !$request->hasFile("leaves.{$i}.attach_files")) {
                    Alert::error('Error', "$rowLabel: Lampiran file wajib diupload untuk jenis cuti {$leaveType->name}.");
                    return back();
                }

                // Validasi format & ukuran file (jika ada)
                if ($request->hasFile("leaves.{$i}.attach_files")) {
                    foreach ($request->file("leaves.{$i}.attach_files") as $file) {
                        if (!$file->isValid()) {
                            Alert::error('Error', "$rowLabel: File upload gagal.");
                            return back();
                        }
                        if ($file->getSize() > 5 * 1024 * 1024) {
                            Alert::error('Error', "$rowLabel: Ukuran file {$file->getClientOriginalName()} melebihi 5MB.");
                            return back();
                        }
                        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                        if (!in_array(strtolower($file->getClientOriginalExtension()), $allowedExt)) {
                            Alert::error('Error', "$rowLabel: Format file {$file->getClientOriginalName()} tidak diizinkan. Gunakan PDF, JPG, PNG, atau DOC/DOCX.");
                            return back();
                        }
                    }
                }

                $reason = $leave['keterangan'] ?? '';
                if ($isNegativeLeave) {
                    $reason = '[Hutang Cuti] ' . $reason;
                }

                $preparedLeaves[] = [
                    'leave_type_id'     => $leave['jenis_cuti'],
                    'start_date'        => $leave['tanggal_mulai'],
                    'end_date'          => $leave['tanggal_selesai'],
                    'total_days'        => $total_days,
                    'reason'            => $reason,
                    'is_negative_leave' => $isNegativeLeave,
                    'file_index'        => $i,
                ];
            }

            // ── Upload file lampiran ──
            foreach ($preparedLeaves as &$leaveData) {
                $attachPaths = [];
                $idx = $leaveData['file_index'];
                if ($request->hasFile("leaves.{$idx}.attach_files")) {
                    foreach ($request->file("leaves.{$idx}.attach_files") as $file) {
                        $attachPaths[] = $file->store("leave-attachments/{$request->npk}", 'public');
                    }
                }
                $leaveData['attach_files'] = !empty($attachPaths) ? $attachPaths : null;
                unset($leaveData['file_index']);
            }
            unset($leaveData);

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
                        'attach_files'      => $leaveData['attach_files'],
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

        $leaveType = LeaveTypes::find($leaveTypeId);
        $isTahunan = $leaveType && $leaveType->code === 'tahunan';
        $isNegativeLeave = $isTahunan && $remained <= 0;

        if ($isNegativeLeave) {
            $currentNegative = $balance ? (int)($balance->negative_leave ?? 0) : 0;
            $keterangan = "Saldo cuti tahunan Anda telah habis (0 hari). Pengajuan ini akan dicatat sebagai Hutang Cuti (Total hutang cuti saat ini: {$currentNegative} hari).";
        } elseif ($balance) {
            $keterangan = "Sisa cuti Anda: {$remained} hari, Terpakai: {$used}";
        } else {
            $keterangan = 'Belum ada data jatah cuti untuk jenis ini di tahun berjalan.';
        }

        return response()->json([
            'success'           => true,
            'sisa'              => $remained,
            'keterangan'        => $keterangan,
            'remained_days'     => $remained,
            'used_days'         => $used,
            'is_negative_leave' => $isNegativeLeave,
            'negative_leave'    => $balance ? (int)($balance->negative_leave ?? 0) : 0,
            'max_end_date'      => $isNegativeLeave ? null : $maxEndDate,
        ]);
    }

    /**
     * Riwayat pengajuan cuti karyawan (portal cuti).
     * Setiap token hanya ditampilkan 1 row (row pengajuan unik).
     */
    public function riwayat()
    {
        $npk = session('cuti_employee_npk');
        if (!$npk && auth()->check()) {
            $npk = auth()->user()->npk;
        }
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

        $query = LeaveRequest::where('NPK', $npk);

        // Filter tanggal: tampilkan permohonan yang periode cutinya beririsan
        if ($startDate = request('start_date')) {
            $query->whereDate('end_date', '>=', $startDate);
        }
        if ($endDate = request('end_date')) {
            $query->whereDate('start_date', '<=', $endDate);
        }

        // Ambil semua permohonan cuti (1 row per token)
        $leaveRequests = $query
            ->orderBy('created_at', 'desc')
            ->get()
            ->unique('token');

        // Filter status: default tampilkan yang statusnya pending / waiting
        $filterStatus = request('status', 'pending');
        if (empty($filterStatus)) {
            $filterStatus = 'pending';
        }

        $rows = [];
        foreach ($leaveRequests as $req) {
            $leaveType = LeaveTypes::find($req->leave_type_id);

            // Ambil balance cuti karyawan untuk jenis cuti yang dipilih
            $leaveBalance = DB::table('leave_balances')
                ->where('NPK', $req->NPK)
                ->where('leave_type_id', $req->leave_type_id)
                ->where('year', date('Y'))
                ->first();

            // Ambil daftar semua approver untuk token ini
            $allApprovers = LeaveRequest::where('token', $req->token)
                ->where(function($q) {
                    $q->whereNull('void')->orWhere('void', '!=', 'true');
                })
                ->orderBy('approval_level', 'asc')
                ->get();

            if ($allApprovers->isEmpty()) {
                $allApprovers = LeaveRequest::where('token', $req->token)
                    ->orderBy('approval_level', 'asc')
                    ->get();
            }

            $approversList = [];
            $hasRejected = false;
            $allApproved = ($allApprovers->count() > 0);
            $rejectComment = null;

            foreach ($allApprovers as $approverReq) {
                $approverBio = DB::connection('cii')->table('BIODATA')
                    ->where('NPK', $approverReq->approval_id)
                    ->select('NAMA_KARYAWAN')
                    ->first();

                if ($approverReq->status === 'rejected') {
                    $hasRejected = true;
                    if ($approverReq->comment) {
                        $rejectComment = $approverReq->comment;
                    }
                }
                if ($approverReq->status !== 'approved') {
                    $allApproved = false;
                }

                $approversList[] = [
                    'npk'    => $approverReq->approval_id,
                    'nama'   => $approverBio ? $approverBio->NAMA_KARYAWAN : $approverReq->approval_id,
                    'level'  => $approverReq->approval_level,
                    'status' => $approverReq->status,
                    'comment'=> $approverReq->comment,
                ];
            }

            // Tentukan status utama pengajuan (overall status)
            if ($hasRejected || $req->status === 'rejected') {
                $overallStatus = 'rejected';
            } elseif ($allApproved) {
                $overallStatus = 'approved';
            } else {
                $overallStatus = 'pending';
            }

            // Terapkan filter status jika bukan 'all'
            if ($filterStatus !== 'all' && $overallStatus !== $filterStatus) {
                continue;
            }

            // Format lampiran file
            $formattedFiles = [];
            if (!empty($req->attach_files)) {
                $files = is_array($req->attach_files) ? $req->attach_files : json_decode($req->attach_files, true);
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

            $rows[] = [
                'id'                  => $req->id,
                'token'               => $req->token,
                'npk'                 => $req->NPK,
                'nama'                => $employee->NAMA_KARYAWAN,
                'dept'                => $employee->DEPARTEMENT,
                'leave_type'          => $leaveType ? $leaveType->name : '-',
                'leave_balance'       => $leaveBalance ? $leaveBalance->remained_days : '-',
                'leave_used'          => $leaveBalance ? $leaveBalance->used_days : '-',
                'start_date'          => $req->start_date,
                'end_date'            => $req->end_date,
                'start_date_formatted'=> Carbon::parse($req->start_date)->format('d M Y'),
                'end_date_formatted'  => Carbon::parse($req->end_date)->format('d M Y'),
                'total_days'          => $req->total_days,
                'reason'              => $req->reason,
                'status'              => $overallStatus,
                'comment'             => $rejectComment ?: $req->comment,
                'created_at'          => Carbon::parse($req->created_at)->format('d M Y H:i'),
                'is_negative_leave'   => str_contains($req->reason, '[Hutang Cuti]'),
                'attach_files'        => $formattedFiles,
                'approvers'           => $approversList,
            ];
        }

        if (request()->ajax()) {
            return DataTables::of(collect($rows))
                ->addIndexColumn()
                ->addColumn('karyawan', function($row) {
                    return '<div style="line-height:1.3;">' .
                           '  <strong style="font-size:0.95rem; color:#2e59d9;">'.e($row['nama']).'</strong>' .
                           '  <div class="text-muted" style="font-size:0.85rem; margin-top:2px;">' .
                           '    <span class="font-weight-bold">'.e($row['npk']).'</span> &middot; '.e($row['dept']) .
                           '  </div>' .
                           '</div>';
                })
                ->addColumn('periode', function($row) {
                    $start = $row['start_date_formatted'];
                    $end   = $row['end_date_formatted'];
                    return '<div style="font-size:0.9rem; font-weight:600; white-space:nowrap;">' . $start . '</div>' .
                           '<div class="text-muted" style="font-size:0.8rem; text-align:center;">s/d</div>' .
                           '<div style="font-size:0.9rem; font-weight:600; white-space:nowrap;">' . $end . '</div>';
                })
                ->addColumn('sisa_cuti', function($row) {
                    if ($row['leave_balance'] === '-') {
                        return '<span class="text-muted" style="font-size:0.9rem;">-</span>';
                    }
                    $sisa = (int)$row['leave_balance'];
                    $used = (int)$row['leave_used'];
                    $color = $sisa <= 2 ? 'danger' : ($sisa <= 5 ? 'warning' : 'success');
                    return '<div class="font-weight-bold text-'.$color.'" style="font-size:1.05rem;">'.$sisa.' hari</div>' .
                           '<div class="text-muted font-weight-bold" style="font-size:0.8rem; margin-top:2px;">Terpakai: '.$used.' hr</div>';
                })
                ->addColumn('hari', function($row) {
                    return '<span class="badge badge-light border text-dark font-weight-bold" style="font-size:0.92rem; padding:5px 8px;">'.$row['total_days'].' hari</span>';
                })
                ->addColumn('alasan', function($row) {
                    return '<div style="font-size:0.88rem; line-height:1.4;">' . (e($row['reason']) ?: '-') . '</div>';
                })
                ->addColumn('status_approver', function($row) {
                    // Daftar approver beserta status approval masing-masing
                    if (empty($row['approvers'])) {
                        return '<span class="text-muted">-</span>';
                    }

                    $html = '<div style="font-size:0.86rem; text-align:left;">';
                    foreach ($row['approvers'] as $index => $approver) {
                        $statusBadge = '';
                        if ($approver['status'] === 'approved') {
                            $statusBadge = '<span class="badge badge-success" style="font-size:0.75rem; padding:3px 7px;"><i class="fas fa-check fa-xs mr-1"></i>Approved</span>';
                        } elseif ($approver['status'] === 'rejected') {
                            $statusBadge = '<span class="badge badge-danger" style="font-size:0.75rem; padding:3px 7px;"><i class="fas fa-times fa-xs mr-1"></i>Rejected</span>';
                        } else {
                            $statusBadge = '<span class="badge badge-warning text-white" style="font-size:0.75rem; padding:3px 7px;"><i class="fas fa-clock fa-xs mr-1"></i>Pending</span>';
                        }

                        $borderBottom = ($index < count($row['approvers']) - 1) ? 'border-bottom:1px dashed #e3e6f0;' : '';

                        $html .= '<div class="d-flex align-items-center justify-content-between py-1" style="' . $borderBottom . ' gap:6px;">';
                        $html .= '  <div class="d-flex align-items-center text-truncate" style="flex:1; min-width:0;" title="['.$approver['npk'].'] '.e($approver['nama']).'">';
                        $html .= '    <span class="badge badge-light border text-muted mr-1" style="font-size:0.72rem;">'.$approver['npk'].'</span>';
                        $html .= '    <span class="font-weight-bold text-dark text-truncate" style="font-size:0.85rem;">'.e($approver['nama']).'</span>';
                        $html .= '  </div>';
                        $html .= '  <div style="flex-shrink:0;">'.$statusBadge.'</div>';
                        $html .= '</div>';
                    }
                    $html .= '</div>';

                    return $html;
                })
                ->addColumn('status_utama', function($row) {
                    if ($row['status'] === 'approved') {
                        return '<span class="badge badge-success px-2 py-1" style="font-size:0.85rem;"><i class="fas fa-check mr-1"></i>Disetujui</span>';
                    } elseif ($row['status'] === 'rejected') {
                        return '<span class="badge badge-danger px-2 py-1" style="font-size:0.85rem;"><i class="fas fa-times mr-1"></i>Ditolak</span>';
                    }
                    return '<span class="badge badge-warning text-white px-2 py-1" style="font-size:0.85rem;"><i class="fas fa-hourglass-half mr-1"></i>Menunggu</span>';
                })
                ->addColumn('aksi', function($row) {
                    return '<button type="button" class="btn btn-sm btn-info btn-detail btn-block text-nowrap" style="font-size:0.82rem; padding:4px 6px;" data-id="'.$row['id'].'"><i class="fas fa-eye fa-sm"></i> Detail</button>';
                })
                ->rawColumns(['karyawan', 'periode', 'sisa_cuti', 'hari', 'alasan', 'status_approver', 'status_utama', 'aksi'])
                ->make(true);
        }

        return view('cuti.riwayat', compact('employee'));
    }
}