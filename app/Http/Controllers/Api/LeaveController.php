<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalDept;
use App\Models\LeaveBalances;
use App\Models\LeaveRequest;
use App\Models\LeaveReason;
use App\Models\LeaveTypes;
use App\Models\Biodata;
use App\Services\LeaveRuleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeaveController extends Controller
{
    // ─────────────────────────────────────────────────────
    //  POST /api/v1/leave/request
    //  Submit pengajuan cuti baru
    // ─────────────────────────────────────────────────────

    /**
     * POST /api/v1/leave/request
     *
     * Submit pengajuan cuti baru. Mendukung upload file lampiran.
     * Requires: Bearer Token di header Authorization.
     *
     * @bodyParam leave_type_id integer required  ID jenis cuti. Example: 1
     * @bodyParam start_date    string  required  Tanggal mulai cuti (Y-m-d). Example: 2026-11-01
     * @bodyParam end_date      string  required  Tanggal selesai cuti inclusive (Y-m-d). Example: 2026-11-05
     * @bodyParam reason        string  required  Alasan / keterangan cuti. Example: Keperluan keluarga
     * @bodyParam attach_files  file[]  optional  File lampiran (pdf, jpg, png, doc, docx). Max 5MB/file.
     */
    public function submitRequest(Request $request, LeaveRuleService $leaveRuleService)
    {
        try {
            // ── Validasi input ──
            $validated = $request->validate([
                'leave_type_id' => 'required|integer|exists:leave_types,id',
                // start/end date wajib untuk cuti berbasis rentang (tahunan, melahirkan, keguguran).
                // Cuti lain boleh memakai custom_dates (pilihan tanggal bebas).
                'start_date'    => 'nullable|date_format:Y-m-d|after_or_equal:today',
                'end_date'      => 'nullable|date_format:Y-m-d',
                'custom_dates'  => 'nullable|array',
                'custom_dates.*' => 'date_format:Y-m-d',
                'reason'        => 'required|string|max:500',
                'attach_files'  => 'nullable|array',
                'attach_files.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120',
            ], [
                'leave_type_id.required'     => 'Jenis cuti wajib dipilih.',
                'leave_type_id.exists'       => 'Jenis cuti tidak valid.',
                'start_date.date_format'     => 'Format tanggal mulai harus Y-m-d (contoh: 2026-11-01).',
                'start_date.after_or_equal'  => 'Tanggal mulai tidak boleh sebelum hari ini.',
                'end_date.date_format'       => 'Format tanggal selesai harus Y-m-d (contoh: 2026-11-05).',
                'custom_dates.*.date_format' => 'Format tanggal harus Y-m-d (contoh: 2026-11-01).',
                'reason.required'            => 'Alasan cuti wajib diisi.',
                'reason.max'                 => 'Alasan cuti maksimal 500 karakter.',
                'attach_files.*.mimes'       => 'Format file harus: pdf, jpg, jpeg, png, doc, atau docx.',
                'attach_files.*.max'         => 'Ukuran file tidak boleh melebihi 5MB.',
            ]);

            $user = $request->user();
            $npk  = $user->npk;

            // ── Cari data karyawan ──
            $employee = Biodata::where('NPK', $npk)->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan untuk NPK ' . $npk . '. Silakan hubungi HR.',
                ], 404);
            }

            // ── Cari approval group untuk departemen karyawan ──
            $deptId = (string) $employee->ID_DEPT;

            $deptGroups = ApprovalDept::with(['rules' => function ($q) {
                $q->orderBy('level', 'asc');
            }])->get()->filter(function ($group) use ($deptId) {
                $depts = is_array($group->dept) ? $group->dept : (json_decode($group->dept, true) ?? []);
                return in_array((string) $deptId, array_map('strval', $depts), true);
            });

            $selectedGroup = null;

            // Prioritas: section karyawan → group umum departemen → fallback group pertama
            if (!empty($employee->SECTION)) {
                $selectedGroup = $deptGroups->first(function ($group) use ($employee) {
                    return (string) $group->section === (string) $employee->SECTION;
                });
            }

            if (!$selectedGroup) {
                $selectedGroup = $deptGroups->first(function ($group) {
                    return empty($group->section);
                });
            }

            if (!$selectedGroup) {
                $selectedGroup = $deptGroups->first();
            }

            $approvalActors = $selectedGroup ? $selectedGroup->rules : collect();

            if ($approvalActors->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jalur approval belum dikonfigurasi untuk departemen Anda. Silakan hubungi HR untuk pengaturan approval.',
                ], 400);
            }

            // ── Validasi aturan pengajuan cuti ──
            // (mode tanggal, jumlah hari per pengajuan, rentang 30 hari dari tanggal
            //  pertama, saldo cuti tahunan, batas jumlah pengajuan seumur hidup)
            $leaveType = LeaveTypes::find($validated['leave_type_id']);

            $result = $leaveRuleService->evaluate(
                $leaveType,
                $npk,
                is_array($request->input('custom_dates')) ? $request->input('custom_dates') : [],
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            if (!$result['ok']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                ], 400);
            }

            $dateMode        = $result['mode'];
            $totalDays       = $result['total_days'];
            $startDate       = $result['start'];
            $endDate         = $result['end'];
            $isNegativeLeave = $result['is_negative_leave'];
            $customDates     = $result['custom_dates'] ?? [];

            // ── Validasi lampiran wajib untuk jenis cuti tertentu ──
            if ($leaveType && $leaveType->requiresAttachment() && !$request->hasFile('attach_files')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lampiran file wajib diupload untuk jenis cuti "' . $leaveType->name . '". Format yang diizinkan: PDF, JPG, PNG, DOC, DOCX (maks 5MB/file).',
                ], 400);
            }

            // ── Upload file lampiran ──
            $attachPaths = [];
            if ($request->hasFile('attach_files')) {
                foreach ($request->file('attach_files') as $file) {
                    if (!$file->isValid()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Upload file "' . $file->getClientOriginalName() . '" gagal. Silakan coba lagi.',
                        ], 400);
                    }
                    $attachPaths[] = $file->store("leave-attachments/{$npk}", 'public');
                }
            }

            // ── Simpan ke database ──
            $reason = $validated['reason'];
            if ($isNegativeLeave) {
                $reason = '[Hutang Cuti] ' . $reason;
            }

            DB::beginTransaction();

            $token = Str::random();

            foreach ($approvalActors as $actor) {
                LeaveRequest::create([
                    'NPK'               => $npk,
                    'leave_type_id'     => $validated['leave_type_id'],
                    'start_date'        => $startDate->format('Y-m-d'),
                    'end_date'          => $endDate->format('Y-m-d'),
                    'total_days'        => $totalDays,
                    'reason'            => $reason,
                    'approval_id'       => $actor->approval_id,
                    'approval_level'    => $actor->level,
                    'approval_progress' => '1',
                    'approval_date'     => null,
                    'status'            => 'pending',
                    'token'             => $token,
                    'void'              => 'false',
                    'attach_files'      => !empty($attachPaths) ? $attachPaths : null,
                    'custom_dates'      => $dateMode === 'custom' ? json_encode($customDates) : null,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan cuti berhasil dikirim dan sedang menunggu approval dari atasan Anda.',
                'data'    => [
                    'token'      => $token,
                    'npk'        => $npk,
                    'leave_type' => $leaveType->name ?? '-',
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date'   => $endDate->format('Y-m-d'),
                    'total_days' => $totalDays,
                    'reason'     => $reason,
                    'status'     => 'pending',
                    'is_negative_leave' => $isNegativeLeave,
                    'custom_dates'     => $dateMode === 'custom' ? $customDates : null,
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data yang dikirim tidak valid. Periksa kembali isian form Anda.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server saat memproses pengajuan cuti. Silakan coba lagi.',
                'error'   => config('app.debug') ? $th->getMessage() : null,
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────
    //  GET /api/v1/leave/history
    //  Riwayat cuti milik user yang sedang login
    // ─────────────────────────────────────────────────────

    /**
     * GET /api/v1/leave/history
     *
     * Mengambil daftar riwayat pengajuan cuti milik user yang login.
     * Requires: Bearer Token di header Authorization.
     *
     * @queryParam year   integer optional  Filter berdasarkan tahun (default: tahun berjalan). Example: 2026
     * @queryParam status string  optional  Filter berdasarkan status: pending, partial, approved, rejected.
     */
    public function getHistory(Request $request)
    {
        try {
            $user = $request->user();
            $npk  = $user->npk;
            $year = $request->input('year', date('Y'));

            // ── Ambil biodata karyawan ──
            $employee = DB::connection('cii')
                ->table('BIODATA')
                ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
                ->where('BIODATA.NPK', $npk)
                ->select('BIODATA.NAMA_KARYAWAN', 'DEPT.DEPARTEMENT')
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data biodata karyawan tidak ditemukan untuk NPK ' . $npk . '.',
                ], 404);
            }

            // ── Ambil pengajuan cuti (row aktif per token) ──
            $activeRequests = LeaveRequest::with('leaveType')
                ->where('NPK', $npk)
                ->whereYear('start_date', $year)
                ->whereColumn('approval_level', 'approval_progress')
                ->orderBy('created_at', 'desc')
                ->get()
                ->unique('token');

            // ── Batch fetch nama approver ──
            $approverNpks = $activeRequests->pluck('approval_id')->unique()->toArray();
            $approvers = DB::connection('cii')
                ->table('BIODATA')
                ->whereIn('NPK', $approverNpks)
                ->pluck('NAMA_KARYAWAN', 'NPK');

            // ── Mapping data ──
            $rows = [];
            foreach ($activeRequests as $activeRow) {
                if ($activeRow->status === 'rejected') {
                    $overallStatus = 'rejected';
                } elseif ($activeRow->status === 'approved') {
                    $overallStatus = 'approved';
                } else {
                    $overallStatus = $activeRow->approval_level > 1 ? 'partial' : 'pending';
                }

                $rows[] = [
                    'token'             => $activeRow->token,
                    'leave_type'        => $activeRow->leaveType->name ?? '-',
                    'start_date'        => $activeRow->start_date ? Carbon::parse($activeRow->start_date)->format('Y-m-d') : null,
                    'end_date'          => $activeRow->end_date ? Carbon::parse($activeRow->end_date)->format('Y-m-d') : null,
                    'total_days'        => $activeRow->total_days,
                    'reason'            => $activeRow->reason,
                    'overall_status'    => $overallStatus,
                    'approver_name'     => $approvers[$activeRow->approval_id] ?? $activeRow->approval_id,
                    'approver_level'    => $activeRow->approval_level,
                    'has_attachment'    => !empty($activeRow->attach_files),
                    'is_negative_leave' => str_contains($activeRow->reason ?? '', '[Hutang Cuti]'),
                    'created_at'        => $activeRow->created_at ? Carbon::parse($activeRow->created_at)->format('Y-m-d H:i:s') : null,
                    'comment'           => $activeRow->comment,
                ];
            }

            // ── Filter berdasarkan status (jika ada) ──
            if ($request->filled('status')) {
                $statusFilter = $request->input('status');
                $validStatuses = ['pending', 'partial', 'approved', 'rejected'];

                if (!in_array($statusFilter, $validStatuses)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Filter status tidak valid. Gunakan salah satu: ' . implode(', ', $validStatuses) . '.',
                    ], 400);
                }

                $rows = array_values(array_filter($rows, function ($row) use ($statusFilter) {
                    return $row['overall_status'] === $statusFilter;
                }));
            }

            return response()->json([
                'success' => true,
                'message' => 'Riwayat cuti berhasil diambil.',
                'data'    => [
                    'npk'   => $npk,
                    'nama'  => $employee->NAMA_KARYAWAN,
                    'year'  => (int) $year,
                    'total' => count($rows),
                    'items' => $rows,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server saat mengambil riwayat cuti.',
                'error'   => config('app.debug') ? $th->getMessage() : null,
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────
    //  GET /api/v1/leave/history/{token}
    //  Detail pengajuan cuti + timeline approval
    // ─────────────────────────────────────────────────────

    /**
     * GET /api/v1/leave/history/{token}
     *
     * Mengambil detail pengajuan cuti berdasarkan token, termasuk timeline approval.
     * Requires: Bearer Token di header Authorization.
     *
     * @urlParam token string required  Token unik pengajuan cuti. Example: aB3cD5eF
     */
    public function getDetail(Request $request, $token)
    {
        try {
            $user = $request->user();

            $requests = LeaveRequest::with('leaveType')
                ->where('token', $token)
                ->where('NPK', $user->npk) // Pastikan hanya bisa lihat milik sendiri
                ->orderBy('approval_level', 'asc')
                ->get();

            if ($requests->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pengajuan cuti dengan token "' . $token . '" tidak ditemukan atau bukan milik Anda.',
                ], 404);
            }

            $firstReq = $requests->first();

            // ── Ambil data karyawan ──
            $employee = DB::connection('cii')
                ->table('BIODATA')
                ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
                ->where('BIODATA.NPK', $firstReq->NPK)
                ->select('BIODATA.NAMA_KARYAWAN', 'DEPT.DEPARTEMENT')
                ->first();

            // ── Ambil nama approver ──
            $approverNpks = $requests->pluck('approval_id')->unique()->toArray();
            $approvers = DB::connection('cii')
                ->table('BIODATA')
                ->whereIn('NPK', $approverNpks)
                ->pluck('NAMA_KARYAWAN', 'NPK');

            // ── Timeline approval ──
            $approvals = $requests->map(function ($req) use ($approvers) {
                return [
                    'level'         => $req->approval_level,
                    'approver_name' => $approvers->get($req->approval_id, $req->approval_id),
                    'status'        => $req->status,
                    'date'          => $req->approval_date ? Carbon::parse($req->approval_date)->format('d M Y') : null,
                    'comment'       => $req->comment,
                ];
            });

            // ── Format file lampiran ──
            $formattedFiles = [];
            if (!empty($firstReq->attach_files)) {
                $files = is_array($firstReq->attach_files) ? $firstReq->attach_files : json_decode($firstReq->attach_files, true);
                if (is_array($files)) {
                    foreach ($files as $filePath) {
                        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                        $formattedFiles[] = [
                            'name'     => basename($filePath),
                            'url'      => asset('storage/' . $filePath),
                            'ext'      => $ext,
                            'is_image' => in_array($ext, ['jpg', 'jpeg', 'png', 'webp']),
                            'is_pdf'   => $ext === 'pdf',
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Detail pengajuan cuti berhasil diambil.',
                'data'    => [
                    'token'             => $firstReq->token,
                    'npk'               => $firstReq->NPK,
                    'nama'              => $employee ? $employee->NAMA_KARYAWAN : $firstReq->NPK,
                    'dept'              => $employee ? $employee->DEPARTEMENT : '-',
                    'leave_type'        => $firstReq->leaveType->name ?? '-',
                    'start_date'        => Carbon::parse($firstReq->start_date)->format('Y-m-d'),
                    'end_date'          => Carbon::parse($firstReq->end_date)->format('Y-m-d'),
                    'total_days'        => $firstReq->total_days,
                    'reason'            => $firstReq->reason,
                    'is_negative_leave' => str_contains($firstReq->reason ?? '', '[Hutang Cuti]'),
                    'approvals'         => $approvals,
                    'attach_files'      => $formattedFiles,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server saat mengambil detail cuti.',
                'error'   => config('app.debug') ? $th->getMessage() : null,
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────
    //  GET /api/v1/leave/balance
    //  Saldo cuti per jenis untuk user yang login
    // ─────────────────────────────────────────────────────

    /**
     * GET /api/v1/leave/balance
     *
     * Mengambil saldo/jatah cuti per jenis untuk user yang login.
     * Requires: Bearer Token di header Authorization.
     *
     * @queryParam year integer optional  Tahun saldo cuti (default: tahun berjalan). Example: 2026
     */
    public function getBalance(Request $request)
    {
        try {
            $user = $request->user();
            $npk  = $user->npk;
            $year = $request->input('year', date('Y'));

            // ── Ambil biodata ──
            $employee = DB::connection('cii')
                ->table('BIODATA')
                ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
                ->where('BIODATA.NPK', $npk)
                ->select('BIODATA.NAMA_KARYAWAN', 'DEPT.DEPARTEMENT')
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data biodata karyawan tidak ditemukan untuk NPK ' . $npk . '.',
                ], 404);
            }

            // ── Ambil daftar jenis cuti (filter gender) ──
            $employeeGender = DB::connection('cii')
                ->table('PKWT')
                ->where('NPK', $npk)
                ->value('JK');

            $jk = $employeeGender ? strtoupper(trim($employeeGender)) : null;

            $leaveTypes = LeaveTypes::where('is_active', true)
                ->get()
                ->filter(function ($type) use ($jk) {
                    $genderType = $type->gender_type ? strtoupper(trim($type->gender_type)) : 'A';
                    return $genderType === 'A' || $jk === null || $genderType === $jk;
                })
                ->values();

            // Saldo tersimpan (hanya relevan untuk cuti tahunan)
            $balances = LeaveBalances::where('NPK', $npk)
                ->where('year', $year)
                ->get()
                ->keyBy('leave_type_id');

            $rows = $leaveTypes->map(function ($type) use ($balances, $npk, $leaveRuleService) {
                $balance      = $balances->get($type->id);
                $usesBalance  = $type->usesBalance();
                $quota        = $leaveRuleService->quota($type, $npk);

                return [
                    'leave_type_id'        => $type->id,
                    'leave_type_name'      => $type->name,
                    'leave_type_code'      => $type->code,
                    'default_days'         => $type->default_days,
                    'uses_balance'         => $usesBalance,
                    'count_calendar_days'  => $type->countsCalendarDays(),
                    'uses_custom_dates'    => $type->usesCustomDates(),
                    'unlimited'            => $type->isUnlimited(),
                    'max_days_per_request' => $type->maxDaysPerRequest(),
                    'required_days'        => $type->requiresExactDays() ? $type->maxDaysPerRequest() : null,
                    'window_days'          => $type->requiresDateWindow() ? $type->dateWindowDays() : null,
                    'max_requests'         => $quota['max'],
                    'used_requests'        => $quota['used'],
                    'remaining_requests'   => $quota['remaining'],
                    'quota_exhausted'      => $quota['exhausted'],
                    'used_days'            => $balance ? (int) $balance->used_days : 0,
                    'remained_days'        => $usesBalance
                        ? ($balance ? (int) $balance->remained_days : 0)
                        : $type->maxDaysPerRequest(),
                    'negative_leave'       => $balance ? (int) ($balance->negative_leave ?? 0) : 0,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Saldo cuti berhasil diambil.',
                'data'    => [
                    'npk'      => $npk,
                    'nama'     => $employee->NAMA_KARYAWAN,
                    'year'     => (int) $year,
                    'balances' => $rows,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server saat mengambil saldo cuti.',
                'error'   => config('app.debug') ? $th->getMessage() : null,
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────
    //  GET /api/v1/leave/types
    //  Daftar jenis cuti aktif (filter gender)
    // ─────────────────────────────────────────────────────

    /**
     * GET /api/v1/leave/types
     *
     * Mengambil daftar jenis cuti yang aktif, difilter berdasarkan gender karyawan.
     * Requires: Bearer Token di header Authorization.
     */
    public function getLeaveTypes(Request $request)
    {
        try {
            $user = $request->user();

            // ── Ambil gender karyawan ──
            $employeeGender = DB::connection('cii')
                ->table('PKWT')
                ->where('NPK', $user->npk)
                ->value('JK');

            $jk = $employeeGender ? strtoupper(trim($employeeGender)) : null;

            // ── Ambil jenis cuti aktif, filter by gender ──
            $leaveTypes = LeaveTypes::where('is_active', true)
                ->get()
                ->filter(function ($type) use ($jk) {
                    $genderType = $type->gender_type ? strtoupper(trim($type->gender_type)) : 'A';
                    return $genderType === 'A' || $jk === null || $genderType === $jk;
                })
                ->values()
                ->map(function ($type) {
                    return [
                        'id'                  => $type->id,
                        'name'                => $type->name,
                        'code'                => $type->code,
                        'default_days'        => $type->default_days,
                        'uses_balance'        => $type->usesBalance(),
                        'count_calendar_days' => $type->countsCalendarDays(),
                        'uses_custom_dates'   => $type->usesCustomDates(),
                        'unlimited'           => $type->isUnlimited(),
                        'max_days_per_request' => $type->maxDaysPerRequest(),
                        'required_days'       => $type->requiresExactDays() ? $type->maxDaysPerRequest() : null,
                        'window_days'         => $type->requiresDateWindow() ? $type->dateWindowDays() : null,
                        'max_requests'        => $type->maxRequests(),
                        'requires_attachment' => $type->requiresAttachment(),
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Daftar jenis cuti berhasil diambil.',
                'data'    => $leaveTypes,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server saat mengambil daftar jenis cuti.',
                'error'   => config('app.debug') ? $th->getMessage() : null,
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────
    //  GET /api/v1/leave/reasons/{leave_type_id}
    //  Daftar alasan untuk jenis cuti tertentu
    // ─────────────────────────────────────────────────────

    /**
     * GET /api/v1/leave/reasons/{leave_type_id}
     *
     * Mengambil daftar alasan/reason untuk jenis cuti tertentu.
     * Requires: Bearer Token di header Authorization.
     *
     * @urlParam leave_type_id integer required  ID jenis cuti. Example: 1
     */
    public function getReasons(Request $request, $leaveTypeId)
    {
        try {
            $leaveType = LeaveTypes::find($leaveTypeId);

            if (!$leaveType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jenis cuti dengan ID ' . $leaveTypeId . ' tidak ditemukan.',
                ], 404);
            }

            $reasons = LeaveReason::where('leave_type_id', $leaveTypeId)
                ->orderBy('id', 'asc')
                ->get()
                ->map(function ($reason) {
                    return [
                        'id'     => $reason->id,
                        'reason' => $reason->reason,
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Daftar alasan cuti untuk "' . $leaveType->name . '" berhasil diambil.',
                'data'    => $reasons,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server saat mengambil daftar alasan cuti.',
                'error'   => config('app.debug') ? $th->getMessage() : null,
            ], 500);
        }
    }
}
