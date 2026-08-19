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
                    'is_staff' => $validated['is_staff'],
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
        $jobVacancy->load('recruitmentPosition');
        $data = $jobVacancy->toArray();
        $data['is_staff'] = optional($jobVacancy->recruitmentPosition)->is_staff ?? 0;

        return response()->json($data);
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
                        'is_staff' => $validated['is_staff'],
                    ]);
                } else {
                    $recruitmentPosition = RecruitmentPosition::create([
                        'position' => $validated['position'],
                        'dept' => $departmentName,
                        'is_aktif' => $isAktif,
                        'is_staff' => $validated['is_staff'],
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
            // 'is_staff' => 'required|boolean',
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
                    // ===== PELAMAR =====
                    'PELAMAR.ID as id',
                    'PELAMAR.NPK as npk',
                    'PELAMAR.NAMA as name',
                    'PELAMAR.JENIS_KELAMIN as gender',
                    'PELAMAR.TMPT_LAHIR as birth_place',
                    'PELAMAR.TGL_LAHIR as birth_date',
                    'PELAMAR.TMK as tmk',
                    'PELAMAR.ALAMAT_LENGKAP as address',
                    'PELAMAR.KABUPATEN as kabupaten',
                    'PELAMAR.ALAMAT_DOMISILI as domisili',
                    'PELAMAR.PENDIDIKAN as education',
                    'PELAMAR.NAMA_SEKOLAH as school_name',
                    'PELAMAR.KABUPATEN_SEKOLAH as school_kabupaten',
                    'PELAMAR.JURUSAN as major',
                    'PELAMAR.TINGGI_BADAN as height',
                    'PELAMAR.BERAT_BADAN as weight',
                    'PELAMAR.HP as phone',
                    'PELAMAR.AGAMA as religion',
                    'PELAMAR.NIK as nik',
                    'PELAMAR.NO_KK as no_kk',
                    'PELAMAR.IBU as mother_name',
                    'PELAMAR.STATUS as marital_status',
                    'PELAMAR.TANGGUNGAN as dependents',
                    'PELAMAR.IS_KONTRAK as is_kontrak',
                    // ===== pelamar_details =====
                    'pelamar_details.jabatan as position',
                    'pelamar_details.department as department',
                    'pelamar_details.nomor_sim as sim_number',
                    'pelamar_details.warga_negara as nationality',
                    'pelamar_details.ikut_kb as ikut_kb',
                    'pelamar_details.bakat_hobby as hobby',
                    'pelamar_details.mode_transportasi as transportation',
                    'pelamar_details.bpjs_tk as bpjs_tk',
                    'pelamar_details.bpjs_kes as bpjs_kes',
                    'pelamar_details.alamat_skrg as current_address',
                    'pelamar_details.kabupaten_kota_skrg as current_kabupaten',
                    'pelamar_details.status_domisili as domisili_status',
                    'pelamar_details.nama_ktk_darurat as emergency_name',
                    'pelamar_details.hubungan as emergency_relation',
                    'pelamar_details.no_telp_darurat as emergency_phone',
                    'pelamar_details.motivasi as motivation',
                    'pelamar_details.kegiatan_ekstra as extracurricular',
                    'pelamar_details.pengalaman_kerja as work_experience',
                    'pelamar_details.data_ayah as father_data',
                    'pelamar_details.data_ibu as mother_data',
                    'pelamar_details.saudara_kandung as siblings_data',
                    'pelamar_details.data_anak as children_data',
                    'pelamar_details.riwayat_pendidikan as education_history',
                    'pelamar_details.file_surat_lamaran as file_cover_letter',
                    'pelamar_details.file_cv as file_cv',
                    'pelamar_details.file_ktp as file_ktp',
                    'pelamar_details.file_kk as file_kk',
                    'pelamar_details.file_ijasah as file_ijazah',
                    'pelamar_details.file_akta_kelahiran as file_akta',
                    'pelamar_details.file_skck as file_skck',
                    'pelamar_details.file_surat_sehat as file_surat_sehat',
                    'pelamar_details.file_pas_foto as file_photo',
                    'pelamar_details.status_apply as status_apply',
                    'pelamar_details.is_test as is_test',
                    'pelamar_details.tgl_test as tgl_test',
                    'pelamar_details.is_kesehatan as is_kesehatan',
                    'pelamar_details.tgl_kesehatan as tgl_kesehatan',
                    'pelamar_details.is_interview as is_interview',
                    'pelamar_details.tgl_interview as tgl_interview',
                    'pelamar_details.tgl_diterima as tgl_diterima',
                    'pelamar_details.result_test as result_test',
                    'pelamar_details.comment_test as comment_test',
                    'pelamar_details.result_kesehatan as result_kesehatan',
                    'pelamar_details.comment_kesehatan as comment_kesehatan',
                    'pelamar_details.result_interview as result_interview',
                    'pelamar_details.comment_interview as comment_interview',
                    'pelamar_details.result_user as result_user',
                    'pelamar_details.comment_user as comment_user',
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
                $birthDate = $a->birth_date ? Carbon::parse($a->birth_date) : null;

                $age = null;
                if ($birthDate) {
                    $diff = $birthDate->diff(now());
                    $age = "{$diff->y} Tahun {$diff->m} Bulan {$diff->d} Hari";
                }

                $decode = fn($v) => $this->tryJsonDecode($v);

                return [
                    'no' => $index + 1,
                    'id' => $a->id,
                    'npk' => $a->npk,
                    'name' => $a->name,
                    'gender' => $a->gender,
                    'birth_place' => $a->birth_place,
                    'birth_date_formatted' => optional($birthDate)->translatedFormat('d M Y'),
                    'age' => $age,
                    'address' => $a->address,
                    'kabupaten' => $a->kabupaten,
                    'domisili' => $a->domisili,
                    'education' => $a->education,
                    'school_name' => $a->school_name,
                    'school_kabupaten' => $a->school_kabupaten,
                    'major' => $a->major,
                    'height' => $a->height,
                    'weight' => $a->weight,
                    'phone' => $a->phone,
                    'religion' => $a->religion,
                    'nik' => $a->nik,
                    'no_kk' => $a->no_kk,
                    'mother_name' => $a->mother_name,
                    'marital_status' => $a->marital_status,
                    'dependents' => $a->dependents,
                    'is_kontrak' => $a->is_kontrak,

                    'position' => $a->position,
                    'department' => $a->department,
                    'sim_number' => $a->sim_number,
                    'nationality' => $a->nationality,
                    'ikut_kb' => (bool) $a->ikut_kb,
                    'hobby' => $a->hobby,
                    'transportation' => $a->transportation,
                    'bpjs_tk' => $a->bpjs_tk,
                    'bpjs_kes' => $a->bpjs_kes,
                    'current_address' => $a->current_address,
                    'current_kabupaten' => $a->current_kabupaten,
                    'domisili_status' => $a->domisili_status,

                    'emergency_name' => $a->emergency_name,
                    'emergency_relation' => $a->emergency_relation,
                    'emergency_phone' => $a->emergency_phone,

                    'motivation' => $a->motivation,
                    'extracurricular' => $a->extracurricular,
                    'work_experience' => $decode($a->work_experience),
                    'father_data' => $decode($a->father_data),
                    'mother_data' => $decode($a->mother_data),
                    'siblings_data' => $decode($a->siblings_data),
                    'children_data' => $decode($a->children_data),
                    'education_history' => $decode($a->education_history),

                    'files' => [
                        'cover_letter' => $a->file_cover_letter,
                        'cv' => $a->file_cv,
                        'ktp' => $a->file_ktp,
                        'kk' => $a->file_kk,
                        'ijazah' => $a->file_ijazah,
                        'akta' => $a->file_akta,
                        'skck' => $a->file_skck,
                        'surat_sehat' => $a->file_surat_sehat,
                        'photo' => $a->file_photo,
                    ],

                    'status_apply' => $a->status_apply,
                    'process' => [
                        'test' => [
                            'status' => $a->is_test,
                            'date' => optional($a->tgl_test ? Carbon::parse($a->tgl_test) : null)->translatedFormat('d M Y'),
                            'result' => $a->result_test,
                            'comment' => $a->comment_test,
                        ],
                        'kesehatan' => [
                            'status' => $a->is_kesehatan,
                            'date' => optional($a->tgl_kesehatan ? Carbon::parse($a->tgl_kesehatan) : null)->translatedFormat('d M Y'),
                            'result' => $a->result_kesehatan,
                            'comment' => $a->comment_kesehatan,
                        ],
                        'interview' => [
                            'status' => $a->is_interview,
                            'date' => optional($a->tgl_interview ? Carbon::parse($a->tgl_interview) : null)->translatedFormat('d M Y'),
                            'result' => $a->result_interview,
                            'comment' => $a->comment_interview,
                        ],
                        'user' => [
                            'result' => $a->result_user,
                            'comment' => $a->comment_user,
                        ],
                        'accepted_at' => optional($a->tgl_diterima ? Carbon::parse($a->tgl_diterima) : null)->translatedFormat('d M Y'),
                    ],

                    'applied_at' => optional($appliedAt)->format('Y-m-d H:i'),
                    'applied_at_formatted' => optional($appliedAt)->translatedFormat('d M Y'),
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

    /**
     * Beberapa kolom nvarchar(max) di pelamar_details (data_ayah, saudara_kandung, dll)
     * kemungkinan menyimpan JSON. Coba decode; kalau bukan JSON valid, kembalikan
     * sebagai teks polos supaya tetap tampil di frontend.
     */
    private function tryJsonDecode(?string $value)
    {
        if (!$value) {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
