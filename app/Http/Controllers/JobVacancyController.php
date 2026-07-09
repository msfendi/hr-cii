<?php

namespace App\Http\Controllers;

use App\Models\JobVacancy;
use App\Models\RecruitmentPosition;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class JobVacancyController extends Controller
{
    /**
     * Halaman utama Job Vacancy (admin) - filter panel, KPI, grid & tabel.
     */
    public function index()
    {
        $departments = DB::connection('cii')
            ->table('DEPT')
            ->orderBy('DEPARTEMENT')
            ->get();

        return view('job_vacancy.index', compact('departments'));
    }

    /**
     * Endpoint JSON untuk mengisi KPI card, grid card, dan tabel (dipanggil via fetch).
     */
    public function data(Request $request)
    {
        try {
            $vacancies = JobVacancy::filter($request->only(['search', 'department', 'employment_type', 'status']))
                ->orderByDesc('created_at')
                ->get();

            // Department ada di koneksi DB lain ('cii', tabel DEPT), jadi tidak bisa
            // dipakai relasi Eloquent lintas-koneksi. Ambil sekali lalu petakan manual
            // by ID_DEPT -> DEPARTEMENT supaya tidak query berkali-kali per baris.
            $departmentNames = DB::connection('cii')
                ->table('DEPT')
                ->pluck('DEPARTEMENT', 'ID_DEPT');

            // Jumlah pelamar per lowongan, dihitung sekali untuk semua baris (hindari N+1).
            $applicantCounts = $this->countApplicants($vacancies);

            $openVacancies = $vacancies->filter(fn($v) => $v->computed_status === 'open');

            $rows = $vacancies->map(function (JobVacancy $v) use ($departmentNames, $applicantCounts) {
                return [
                    'id' => $v->id,
                    'position' => $v->position,
                    'department' => $departmentNames->get($v->department_id, 'Semua Department'),
                    'department_id' => $v->department_id,
                    'total_needed' => $v->total_needed,
                    'employment_type' => $v->employment_type,
                    'employment_type_label' => $v->employment_type_label,
                    'job_description' => $v->job_description,
                    'criteria' => $v->criteria ?? [],
                    'required_documents' => $v->required_documents ?? [],
                    'open_date' => optional($v->open_date)->format('Y-m-d'),
                    'close_date' => optional($v->close_date)->format('Y-m-d'),
                    'open_date_formatted' => optional($v->open_date)->translatedFormat('d M Y'),
                    'close_date_formatted' => optional($v->close_date)->translatedFormat('d M Y'),
                    'status' => $v->status,
                    'computed_status' => $v->computed_status,
                    'computed_status_label' => $v->computed_status_label,
                    'days_left' => $v->days_left,
                    'applicant_count' => $applicantCounts[$v->id] ?? 0,
                ];
            })->values();

            return response()->json([
                'rows' => $rows,
                'kpi' => [
                    'total_open' => $openVacancies->count(),
                    'total_needed_open' => (int) $openVacancies->sum('total_needed'),
                    'closing_soon' => $openVacancies->filter(
                        fn($v) => $v->days_left !== null && $v->days_left <= 7
                    )->count(),
                    // Total pelamar dari SEMUA lowongan yang sedang tampil (sesuai filter aktif),
                    // bukan cuma yang open, supaya angkanya konsisten dengan yang tampil di grid.
                    'total_applicants' => array_sum($applicantCounts),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Halaman publik untuk pelamar: menampilkan seluruh lowongan (semua status)
     * dengan tombol "Melamar" yang mengarah ke halaman recruitments.index.
     */
    public function publicIndex()
    {
        return view('job_vacancy.public');
    }

    /**
     * Endpoint JSON khusus halaman pelamar. Menampilkan SEMUA baris job_vacancies
     * (tidak difilter status), filter yang tersedia hanya pencarian posisi.
     */
    public function publicData(Request $request)
    {
        try {
            $vacancies = JobVacancy::filter($request->only(['search']))
                ->orderByDesc('created_at')
                ->get();

            $departmentNames = DB::connection('cii')
                ->table('DEPT')
                ->pluck('DEPARTEMENT', 'ID_DEPT');

            $applicantCounts = $this->countApplicants($vacancies);

            $rows = $vacancies->map(function (JobVacancy $v) use ($departmentNames, $applicantCounts) {
                return [
                    'id' => $v->id,
                    'position' => $v->position,
                    'department' => $departmentNames->get($v->department_id, 'Semua Department'),
                    'department_id' => $v->department_id,
                    'total_needed' => $v->total_needed,
                    'employment_type_label' => $v->employment_type_label,
                    'job_description' => $v->job_description,
                    'criteria' => $v->criteria ?? [],
                    'required_documents' => $v->required_documents ?? [],
                    'open_date_formatted' => optional($v->open_date)->translatedFormat('d M Y'),
                    'close_date_formatted' => optional($v->close_date)->translatedFormat('d M Y'),
                    'status' => $v->status,
                    'computed_status' => $v->computed_status,
                    'computed_status_label' => $v->computed_status_label,
                    'days_left' => $v->days_left,
                    'applicant_count' => $applicantCounts[$v->id] ?? 0,
                ];
            })->values();

            return response()->json(['rows' => $rows]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Simpan lowongan pekerjaan baru + buat entri terkait di recruitment_positions.
     */
    public function store(Request $request)
    {
        try {
            $validated = $this->validateData($request);

            $validated['created_by'] = Auth::id();
            $validated['criteria'] = $this->cleanArray($request->input('criteria', []));
            $validated['required_documents'] = $this->cleanArray($request->input('required_documents', []));

            DB::transaction(function () use ($validated) {
                $recruitmentPosition = RecruitmentPosition::create([
                    'position' => $validated['position'],
                    'dept' => $this->resolveDepartmentName($validated['department_id'] ?? null),
                    'is_aktif' => $validated['status'] === 'open' ? 1 : 0,
                ]);

                JobVacancy::create($validated + [
                    'recruitment_position_id' => $recruitmentPosition->id,
                ]);
            });

            return response()->json([
                'message' => 'Lowongan pekerjaan berhasil dibuat.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Ambil detail lowongan (untuk mengisi form edit).
     */
    public function edit(JobVacancy $jobVacancy)
    {
        return response()->json($jobVacancy);
    }

    /**
     * Perbarui lowongan pekerjaan + sinkronkan baris recruitment_positions terkait.
     */
    public function update(Request $request, JobVacancy $jobVacancy)
    {
        try {
            $validated = $this->validateData($request, $jobVacancy->id);
            $validated['criteria'] = $this->cleanArray($request->input('criteria', []));
            $validated['required_documents'] = $this->cleanArray($request->input('required_documents', []));

            DB::transaction(function () use ($validated, $jobVacancy) {
                $departmentName = $this->resolveDepartmentName($validated['department_id'] ?? null);
                $isAktif = $validated['status'] === 'open' ? 1 : 0;

                if ($jobVacancy->recruitment_position_id) {
                    RecruitmentPosition::where('id', $jobVacancy->recruitment_position_id)->update([
                        'position' => $validated['position'],
                        'dept' => $departmentName,
                        'is_aktif' => $isAktif,
                    ]);
                } else {
                    $recruitmentPosition = RecruitmentPosition::create([
                        'position' => $validated['position'],
                        'dept' => $departmentName,
                        'is_aktif' => $isAktif,
                    ]);

                    $validated['recruitment_position_id'] = $recruitmentPosition->id;
                }

                $jobVacancy->update($validated);
            });

            return response()->json([
                'message' => 'Lowongan pekerjaan berhasil diperbarui.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Hapus (soft delete) lowongan pekerjaan + nonaktifkan baris recruitment_positions terkait.
     */
    public function destroy(JobVacancy $jobVacancy)
    {
        try {
            DB::transaction(function () use ($jobVacancy) {
                if ($jobVacancy->recruitment_position_id) {
                    RecruitmentPosition::where('id', $jobVacancy->recruitment_position_id)->update([
                        'is_aktif' => 0,
                    ]);
                }

                $jobVacancy->delete();
            });

            return response()->json([
                'message' => 'Lowongan pekerjaan berhasil dihapus.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Buka / tutup lowongan secara cepat tanpa membuka form edit,
     * sekaligus sinkronkan is_aktif di recruitment_positions.
     */
    public function toggleStatus(JobVacancy $jobVacancy)
    {
        try {
            $jobVacancy->status = $jobVacancy->status === 'closed' ? 'open' : 'closed';
            $jobVacancy->save();

            if ($jobVacancy->recruitment_position_id) {
                RecruitmentPosition::where('id', $jobVacancy->recruitment_position_id)->update([
                    'is_aktif' => $jobVacancy->status === 'open' ? 1 : 0,
                ]);
            }

            return response()->json([
                'message' => $jobVacancy->status === 'closed'
                    ? 'Lowongan pekerjaan telah ditutup.'
                    : 'Lowongan pekerjaan dibuka kembali.',
                'status' => $jobVacancy->status,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    private function validateData(Request $request, $id = null): array
    {
        return $request->validate([
            'position' => 'required|string|max:150',
            'department_id' => 'nullable|exists:cii.DEPT,ID_DEPT',
            'total_needed' => 'required|integer|min:1',
            'employment_type' => 'required|in:full_time,part_time,contract,internship,daily_worker',
            'job_description' => 'nullable|string',
            'open_date' => 'required|date',
            'close_date' => 'required|date|after_or_equal:open_date',
            'status' => 'required|in:draft,open,closed',
        ]);
    }

    private function cleanArray($values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', $values),
            fn($v) => $v !== ''
        ));
    }

    private function resolveDepartmentName($departmentId): ?string
    {
        if (!$departmentId) {
            return null;
        }

        return DB::connection('cii')
            ->table('DEPT')
            ->where('ID_DEPT', $departmentId)
            ->value('DEPARTEMENT');
    }

    /**
     * Hitung jumlah pelamar untuk tiap lowongan berdasarkan:
     * PELAMAR.ID join pelamar_details.id_pelamar, disaring dengan
     * pelamar_details.jabatan yang sama dengan POSISI lowongan (bukan department),
     * dan tanggal lamar berada dalam rentang open_date - close_date lowongan.
     *
     * @param  \Illuminate\Support\Collection<int, JobVacancy>  $vacancies
     * @return array<int, int> Key: job_vacancy id, Value: jumlah pelamar
     */
    private function countApplicants(Collection $vacancies): array
    {
        $positions = $vacancies->pluck('position')->filter()->unique()->values();

        if ($positions->isEmpty()) {
            return [];
        }

        $applicants = DB::connection('cii')
            ->table('PELAMAR')
            ->join('pelamar_details', 'pelamar_details.id_pelamar', '=', 'PELAMAR.ID')
            ->whereIn('pelamar_details.jabatan', $positions)
            ->select('pelamar_details.jabatan as position', 'pelamar_details.created_at as applied_at')
            ->get();

        $counts = [];

        foreach ($vacancies as $vacancy) {
            if (!$vacancy->position || !$vacancy->open_date || !$vacancy->close_date) {
                $counts[$vacancy->id] = 0;
                continue;
            }

            $start = $vacancy->open_date->copy()->startOfDay();
            $end = $vacancy->close_date->copy()->endOfDay();

            $counts[$vacancy->id] = $applicants->filter(function ($applicant) use ($vacancy, $start, $end) {
                if ((string) $applicant->position !== (string) $vacancy->position) {
                    return false;
                }

                if (!$applicant->applied_at) {
                    return false;
                }

                $appliedAt = Carbon::parse($applicant->applied_at);

                return $appliedAt->between($start, $end);
            })->count();
        }

        return $counts;
    }

    public function applicants(JobVacancy $jobVacancy)
    {
        try {
            if (!$jobVacancy->position || !$jobVacancy->open_date || !$jobVacancy->close_date) {
                return response()->json([
                    'summary' => [
                        'total' => 0,
                        'position' => $jobVacancy->position,
                        'open_date' => optional($jobVacancy->open_date)->translatedFormat('d M Y'),
                        'close_date' => optional($jobVacancy->close_date)->translatedFormat('d M Y'),
                    ],
                    'applicants' => [],
                ]);
            }

            $start = $jobVacancy->open_date->copy()->startOfDay();
            $end = $jobVacancy->close_date->copy()->endOfDay();

            $applicants = DB::connection('cii')
                ->table('PELAMAR')
                ->join('pelamar_details', 'pelamar_details.id_pelamar', '=', 'PELAMAR.ID')
                ->where('pelamar_details.jabatan', $jobVacancy->position)
                ->select(
                    'PELAMAR.ID as id',
                    'PELAMAR.NAMA as name',
                    'PELAMAR.HP as phone',
                    'PELAMAR.JENIS_KELAMIN as gender',
                    'PELAMAR.ALAMAT_LENGKAP as address',
                    'pelamar_details.jabatan as position',
                    'PELAMAR.PENDIDIKAN as education',
                    'pelamar_details.created_at as applied_at'
                )
                ->get()
                ->filter(function ($a) use ($start, $end) {
                    if (!$a->applied_at) {
                        return false;
                    }
                    return Carbon::parse($a->applied_at)->between($start, $end);
                })
                ->values();

            $rows = $applicants->map(function ($a, $index) {
                $appliedAt = $a->applied_at ? Carbon::parse($a->applied_at) : null;

                return [
                    'no' => $index + 1,
                    'id' => $a->id,
                    'name' => $a->name,
                    'email' => $a->email,
                    'phone' => $a->phone,
                    'gender' => $a->gender,
                    'address' => $a->address,
                    'position' => $a->position,
                    'education' => $a->education,
                    'applied_at' => optional($appliedAt)->format('Y-m-d H:i'),
                    'applied_at_formatted' => optional($appliedAt)->translatedFormat('d M Y, H:i'),
                ];
            })->values();

            return response()->json([
                'summary' => [
                    'total' => $rows->count(),
                    'position' => $jobVacancy->position,
                    'open_date' => $jobVacancy->open_date->translatedFormat('d M Y'),
                    'close_date' => $jobVacancy->close_date->translatedFormat('d M Y'),
                ],
                'applicants' => $rows,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}
