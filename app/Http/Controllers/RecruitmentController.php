<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSalaryApprovalSteps;
use App\Models\WhatsappDevice;
use App\Models\HealthTest;
use App\Models\RecruitmentPosition;
use App\Models\SalaryApprove;
use App\Models\User; // TODO: sesuaikan namespace User model kamu kalau berbeda
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\FonnteService;
use Carbon\Carbon;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class RecruitmentController extends Controller
{
    use HandlesSalaryApprovalSteps;

    protected $fonnteService;

    public function __construct(FonnteService $fonnteService)
    {
        $this->fonnteService = $fonnteService;
    }

    public function index(Request $request)
    {
        $status = $request->query('status');

        // Daftar approver untuk modal "Pengajuan Gaji" — diambil dinamis dari tabel users
        $approvers = [
            'management' => User::whereNotNull('npk')->where('npk', '!=', '')->get(['npk', 'name']),
            'gm'         => User::where('npk', 'C-00001')->get(['npk', 'name']),
        ];

        return view('recruitment.index', compact('status', 'approvers'));
    }

    /**
     * Server-side DataTables AJAX endpoint for the recruitment table.
     * Handles status & tgl_pendaftaran filters forwarded via DataTables ajax.data().
     */
    public function getData(Request $request)
    {
        $status         = $request->query('status');
        $tglPendaftaran = $request->query('tgl_pendaftaran');

        $query = DB::connection('cii')->table('PELAMAR')
            ->where('PELAMAR.IS_KONTRAK', 'FALSE')
            ->leftJoin('pelamar_details as pd', 'pd.id_pelamar', '=', 'PELAMAR.ID')
            ->select(
                'PELAMAR.ID',
                'PELAMAR.NPK',
                'PELAMAR.NAMA',
                'PELAMAR.NIK',
                'PELAMAR.NO_KK',
                'PELAMAR.JENIS_KELAMIN',
                'PELAMAR.TMPT_LAHIR',
                'PELAMAR.TGL_LAHIR',
                'PELAMAR.UMUR',
                'PELAMAR.STATUS',
                'PELAMAR.TANGGUNGAN',
                'PELAMAR.AGAMA',
                'PELAMAR.HP',
                'PELAMAR.ALAMAT_LENGKAP',
                'PELAMAR.KABUPATEN',
                'PELAMAR.ALAMAT_DOMISILI',
                'PELAMAR.PENDIDIKAN',
                'PELAMAR.JURUSAN',
                'PELAMAR.NAMA_SEKOLAH',
                'PELAMAR.KABUPATEN_SEKOLAH',
                'PELAMAR.TINGGI_BADAN',
                'PELAMAR.BERAT_BADAN',
                'PELAMAR.is_staff',
                'pd.id as detail_id',
                'pd.nomor_sim',
                'pd.warga_negara',
                'pd.ikut_kb',
                'pd.bakat_hobby',
                'pd.mode_transportasi',
                'pd.jabatan',
                'pd.department',
                'pd.bpjs_tk',
                'pd.bpjs_kes',
                'pd.alamat_skrg',
                'pd.kabupaten_kota_skrg',
                'pd.status_domisili',
                'pd.nama_ktk_darurat',
                'pd.hubungan',
                'pd.no_telp_darurat',
                'pd.pengalaman_kerja',
                'pd.data_ayah',
                'pd.data_ibu',
                'pd.saudara_kandung',
                'pd.data_anak',
                'pd.riwayat_pendidikan',
                'pd.motivasi',
                'pd.kegiatan_ekstra',
                'pd.file_surat_lamaran',
                'pd.file_cv',
                'pd.file_ktp',
                'pd.file_kk',
                'pd.file_ijasah',
                'pd.file_akta_kelahiran',
                'pd.file_skck',
                'pd.file_surat_sehat',
                'pd.file_pas_foto',
                'pd.tgl_test',
                'pd.tgl_interview',
                'pd.tgl_kesehatan',
                'pd.tgl_diterima',
                'pd.status_apply',
                'pd.is_test',
                'pd.is_interview',
                'pd.is_kesehatan',
                'pd.result_test',
                'pd.comment_test',
                'pd.result_kesehatan',
                'pd.comment_kesehatan',
                'pd.result_interview',
                'pd.comment_interview',
                'pd.result_user',
                'pd.comment_user',
                'pd.file_test',
                'pd.created_at'
            );

        // ── Status filter ──────────────────────────────────────────────────
        if ($status === 'never_confirm') {
            $query->where('pd.status_apply', 'APPLIED');
        } elseif ($status === 'step_interview') {
            $query->where(function ($q) {
                $q->whereNull('pd.result_interview')->orWhereIn('pd.result_interview', ['', 'FALSE']);
            })->where('pd.status_apply', '!=', 'REJECTED');
        } elseif ($status === 'step_kesehatan') {
            $query->where('pd.result_interview', 'TRUE')
                ->where(function ($q) {
                    $q->whereNull('pd.result_kesehatan')->orWhereIn('pd.result_kesehatan', ['', 'FALSE']);
                })->where('pd.status_apply', '!=', 'REJECTED');
        } elseif ($status === 'step_teknis') {
            $query->where('pd.result_kesehatan', 'TRUE')
                ->where(function ($q) {
                    $q->whereNull('pd.result_test')->orWhereIn('pd.result_test', ['', 'FALSE']);
                })->where('pd.status_apply', '!=', 'REJECTED');
        } elseif ($status === 'step_user') {
            $query->where('pd.result_test', 'TRUE')
                ->where(function ($q) {
                    $q->whereNull('pd.result_user')->orWhereIn('pd.result_user', ['', 'FALSE']);
                })->where('pd.status_apply', '!=', 'REJECTED');
        } elseif ($status === 'ready_test') {
            $query->where('pd.status_apply', 'INVITATION TEST');
        } elseif ($status === 'ready_interview') {
            $query->where('pd.status_apply', 'CALLED TO INTERVIEW');
        } elseif ($status === 'decline') {
            $query->where('pd.status_apply', 'REJECTED');
        } elseif ($status === 'onboarding') {
            $query->where('pd.status_apply', 'ONBOARDING');
        }

        if ($tglPendaftaran) {
            $query->whereDate('pd.created_at', $tglPendaftaran);
        }

        return DataTables::of($query)
            ->orderColumn('PELAMAR.id', 'PELAMAR.id $1')

            // ── Nomor urut ─────────────────────────────────────────────────
            ->addIndexColumn()

            // ── Kolom: Nama Pelamar ────────────────────────────────────────
            ->addColumn('col_nama', function ($row) {
                $isMale  = strtoupper($row->JENIS_KELAMIN ?? '') === 'L';
                $initial = strtoupper(mb_substr($row->NAMA ?? 'X', 0, 1));
                $avClass = $isMale ? 'av-m' : 'av-f';
                $gender  = $isMale ? '♂ Laki-laki' : '♀ Perempuan';

                // Ambil PKWT data untuk baris ini
                $isExPkwt = false;
                if ($row->NIK) {
                    $isExPkwt = DB::connection('cii')->table('PKWT')
                        ->where('KTP', $row->NIK)
                        ->exists();
                }

                $nameStyle = $isExPkwt ? ' style="color: red;" title="Pernah Terdaftar di PKWT"' : '';
                $jabatanHtml = $row->jabatan
                    ? '<div class="name-sub"><i class="fas fa-briefcase mr-1" style="font-size:10px;"></i>' . e($row->jabatan) . '</div>'
                    : '';

                return '<div class="d-flex align-items-center" style="gap:10px;">
                    <div class="av ' . $avClass . '">' . e($initial) . '</div>
                    <div>
                        <div class="name-main"' . $nameStyle . '>' . e($row->NAMA) . '</div>
                        <div class="name-sub">' . $gender . '</div>
                        ' . $jabatanHtml . '
                    </div>
                </div>';
            })

            // ── Kolom: NIK / TTL ───────────────────────────────────────────
            ->addColumn('col_nik', function ($row) {
                $tgl = $row->TGL_LAHIR ? Carbon::parse($row->TGL_LAHIR)->format('d M Y') : '-';
                return '<div style="font-size:12.5px; font-weight:600; font-family:monospace; color:#2d3748; letter-spacing:.5px;">' . e($row->NIK ?? '-') . '</div>
                    <div class="name-sub"><i class="fas fa-map-marker-alt mr-1" style="font-size:10px;"></i>' . e($row->TMPT_LAHIR ?? '-') . '</div>
                    <div class="name-sub"><i class="fas fa-calendar mr-1" style="font-size:10px;"></i>' . $tgl . '</div>
                    <div class="name-sub" style="color:#94a3b8;">' . e($row->UMUR ?? '-') . '</div>';
            })

            // ── Kolom: Pendidikan ──────────────────────────────────────────
            ->addColumn('col_pendidikan', function ($row) {
                $html = '<span class="badge badge-light border" style="font-size:12px; padding:4px 8px; font-weight:700;">' . e($row->PENDIDIKAN ?? '-') . '</span>';
                $html .= '<div class="name-sub mt-1">' . e($row->JURUSAN ?? '-') . '</div>';
                $html .= '<div class="name-sub">' . e($row->NAMA_SEKOLAH ?? '-') . '</div>';
                if ($row->KABUPATEN_SEKOLAH) {
                    $html .= '<div class="name-sub" style="color:#94a3b8;">' . e($row->KABUPATEN_SEKOLAH) . '</div>';
                }
                return $html;
            })

            // ── Kolom: Fisik ───────────────────────────────────────────────
            ->addColumn('col_fisik', function ($row) {
                return '<div style="font-size:13px; font-weight:700; color:#2d3748;">
                        <i class="fas fa-ruler-vertical text-info mr-1" style="font-size:11px;"></i>' . e($row->TINGGI_BADAN ?? '-') . ' cm
                    </div>
                    <div style="font-size:13px; font-weight:700; color:#2d3748;">
                        <i class="fas fa-weight text-warning mr-1" style="font-size:11px;"></i>' . e($row->BERAT_BADAN ?? '-') . ' kg
                    </div>';
            })

            // ── Kolom: Kontak ──────────────────────────────────────────────
            ->addColumn('col_kontak', function ($row) {
                $html = '<div style="font-size:13px; font-weight:700; color:#2d3748;">' . e($row->HP ?? '-') . '</div>';
                $html .= '<div class="name-sub"><i class="fas fa-map-pin mr-1" style="font-size:10px;"></i>' . e($row->KABUPATEN ?? '-') . '</div>';
                if ($row->ALAMAT_DOMISILI) {
                    $html .= '<div class="name-sub" style="color:#94a3b8;">Domisili: ' . e($row->ALAMAT_DOMISILI) . '</div>';
                }
                return $html;
            })

            // ── Kolom: Agama / Status ──────────────────────────────────────
            ->addColumn('col_agama', function ($row) {
                return '<div class="name-main" style="font-size:13px;">' . e($row->AGAMA ?? '-') . '</div>
                    <div class="name-sub">' . e($row->STATUS ?? '-') . '</div>
                    <div class="name-sub">' . e($row->TANGGUNGAN ?? '0') . ' tanggungan</div>';
            })

            // ── Kolom: Departemen / Posisi ─────────────────────────────────
            ->addColumn('col_dept', function ($row) {
                return '<div class="name-main" style="font-size:13px;">' . e($row->department ?? '-') . '</div>
                    <div class="name-sub">' . e($row->jabatan ?? '-') . '</div>';
            })

            // ── Kolom: Tanggal Apply ───────────────────────────────────────
            ->addColumn('col_tgl_apply', function ($row) {
                $ts  = $row->created_at ? Carbon::parse($row->created_at)->timestamp : 0;
                $fmt = $row->created_at ? Carbon::parse($row->created_at)->format('d-m-Y') : '-';
                return '<span data-order="' . $ts . '">' . $fmt . '</span>';
            })

            // ── Kolom: Status Apply ────────────────────────────────────────
            ->addColumn('col_status', function ($row) {
                $sa = $row->status_apply ?? null;
                $sClass = match ($sa) {
                    'APPLIED'            => 's-applied',
                    'INVITATION TEST'    => 's-test',
                    'CALLED TO INTERVIEW'=> 's-interview',
                    'READY FOR SALARY'   => 's-salary',
                    'ONBOARDING'         => 's-onboard',
                    'REJECTED'           => 's-reject',
                    default              => 's-default',
                };
                $sIcon = match ($sa) {
                    'APPLIED'            => 'fa-inbox',
                    'INVITATION TEST'    => 'fa-vial',
                    'CALLED TO INTERVIEW'=> 'fa-comments',
                    'READY FOR SALARY'   => 'fa-money-check-alt',
                    'ONBOARDING'         => 'fa-check-circle',
                    'REJECTED'           => 'fa-times-circle',
                    default              => 'fa-circle',
                };
                $extra = '';
                if ($sa === 'ONBOARDING') {
                    if ($row->tgl_diterima) {
                        $extra = '<br><i class="fas fa-check-circle" style="font-size:13px;">' . Carbon::parse($row->tgl_diterima)->format('d F Y') . '</i>';
                    } else {
                        $extra = '<br><i class="fas fa-exclamation-circle" style="font-size:13px;">Tanggal Diterima Belum Diisi</i>';
                    }
                }
                return '<span class="s-pill ' . $sClass . '"><i class="fas ' . $sIcon . '"></i> ' . e($sa ?? '-') . $extra . '</span>';
            })

            // ── Kolom: Hasil Test ──────────────────────────────────────────
            ->addColumn('col_hasil', function ($row) {
                $stepResults = [
                    'Interview' => $row->result_interview ?? null,
                    'Kesehatan' => $row->result_kesehatan ?? null,
                    'Teknis'    => $row->result_test ?? null,
                    'User'      => $row->result_user ?? null,
                ];
                $hasAny = collect($stepResults)->filter(fn($v) => !is_null($v) && $v !== '')->isNotEmpty();

                // Salary info
                $sal = SalaryApprove::where('id_pelamar', $row->ID)
                    ->orderByDesc('id')->first();
                $salHtml = '';
                if ($sal) {
                    $sal->setAttribute('steps', $this->buildStepsDisplay(
                        $sal->progress ?? [],
                        $this->resolveApproverNames(collect([$row->ID => $sal])->keyBy(fn($s, $k) => $k))
                    ));
                    $gStyle = match ($sal->status) {
                        'finish'   => 'background:#dcfce7; color:#166534; border:1px solid #bbf7d0;',
                        'rejected' => 'background:#fee2e2; color:#991b1b; border:1px solid #fecaca;',
                        default    => 'background:#e0e7ff; color:#4338ca; border:1px solid #c7d2fe;',
                    };
                    $gIcon = match ($sal->status) {
                        'finish'   => 'fa-check-circle',
                        'rejected' => 'fa-times-circle',
                        default    => 'fa-hourglass-half',
                    };
                    $gStepLabel = $sal->current_step === 0 ? 'Management Dept' : ($sal->current_step === 1 ? 'General Manager' : '');
                    $gText = match ($sal->status) {
                        'finish'   => 'Gaji: Rp ' . number_format($sal->approved_salary, 0, ',', '.'),
                        'rejected' => 'Gaji Ditolak',
                        default    => 'Gaji: Menunggu ' . $gStepLabel,
                    };
                    $salHtml = '<div style="display:flex; align-items:center; gap:5px; font-size:11px; font-weight:600; padding:2px 6px; border-radius:4px; ' . $gStyle . '">
                        <i class="fas ' . $gIcon . '" style="font-size:10px;"></i>
                        <span style="flex:1;">' . e($gText) . '</span>
                    </div>';
                }

                if (!$hasAny && !$salHtml) {
                    return '<span class="text-muted" style="font-size:12px;">—</span>';
                }

                $html = '<div style="display:flex; flex-direction:column; gap:3px;">';
                foreach ($stepResults as $label => $val) {
                    if (is_null($val) || $val === '') continue;
                    $rStyle = match (strtoupper($val)) {
                        'TRUE'  => 'background:#dcfce7; color:#166534; border:1px solid #bbf7d0;',
                        'FALSE' => 'background:#fee2e2; color:#991b1b; border:1px solid #fecaca;',
                        'SKIP'  => 'background:#fef9c3; color:#854d0e; border:1px solid #fef08a;',
                        default => 'background:#f1f5f9; color:#475569; border:1px solid #e2e8f0;',
                    };
                    $rIcon = match (strtoupper($val)) {
                        'TRUE'  => 'fa-check-circle',
                        'FALSE' => 'fa-times-circle',
                        'SKIP'  => 'fa-forward',
                        default => 'fa-circle',
                    };
                    $rText = match (strtoupper($val)) {
                        'TRUE'  => 'LOLOS',
                        'FALSE' => 'TIDAK LOLOS',
                        'SKIP'  => 'DILEWATI',
                        default => $val,
                    };
                    $html .= '<div style="display:flex; align-items:center; gap:5px; font-size:11px; font-weight:600; padding:2px 6px; border-radius:4px; ' . $rStyle . '">
                        <i class="fas ' . $rIcon . '" style="font-size:10px;"></i>
                        <span style="flex:1;">' . e($label) . '</span>
                        <span>' . e($rText) . '</span>
                    </div>';
                }
                $html .= $salHtml . '</div>';
                return $html;
            })

            // ── Kolom: Dokumen ─────────────────────────────────────────────
            ->addColumn('col_dokumen', function ($row) {
                $docs = [
                    'Surat Lamaran' => $row->file_surat_lamaran,
                    'CV'            => $row->file_cv,
                    'KTP'           => $row->file_ktp,
                    'KK'            => $row->file_kk,
                    'Pas Foto'      => $row->file_pas_foto,
                    'Ijazah'        => $row->file_ijasah,
                    'Akta Lahir'    => $row->file_akta_kelahiran,
                    'SKCK'          => $row->file_skck,
                    'Surat Sehat'   => $row->file_surat_sehat,
                ];
                $healthId = HealthTest::where('nik', $row->NIK)->value('id');
                $docCount = collect($docs)->filter()->count() + ($healthId ? 1 : 0);

                if ($docCount === 0) {
                    return '<span class="doc-zero"><i class="fas fa-folder"></i></span>';
                }

                $items = '';
                foreach ($docs as $label => $path) {
                    if (!$path) continue;
                    $ext      = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                    $isImg    = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp']);
                    $fileUrl  = asset('storage/' . $path);
                    $imgClass = $isImg ? ' img-viewer-link' : '';
                    $target   = !$isImg ? ' target="_blank"' : '';
                    $icon     = $isImg ? 'fa-image text-purple' : 'fa-file-pdf text-danger';
                    $items   .= '<a class="dropdown-item d-flex align-items-center' . $imgClass . '" style="font-size:12.5px; gap:8px;" href="' . $fileUrl . '" data-url="' . $fileUrl . '" data-label="' . e($label) . '"' . $target . '>
                        <i class="fas ' . $icon . '"></i> ' . e($label) . '
                    </a>';
                }

                return '<div class="dropdown">
                    <button class="doc-fold-btn dropdown-toggle" type="button" data-toggle="dropdown">
                        <i class="fas fa-folder-open text-warning"></i>
                        <span class="badge badge-primary" style="font-size:9px;">' . $docCount . '</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right shadow-sm" style="min-width:160px;">' . $items . '</div>
                </div>';
            })

            // ── Kolom: Aksi ────────────────────────────────────────────────
            ->addColumn('col_aksi', function ($row) {
                // PKWT records untuk data-pkwt
                $pkwtRecords = null;
                if ($row->NIK) {
                    $pkwtCollection = DB::connection('cii')->table('PKWT')
                        ->where('KTP', $row->NIK)
                        ->select('KTP', 'NAMA', 'TMK', 'TKK', 'KETERANGAN', 'leave_reasons')
                        ->get();
                    $pkwtRecords = $pkwtCollection->isNotEmpty() ? $pkwtCollection->values() : null;
                }

                // Salary
                $sal = SalaryApprove::where('id_pelamar', $row->ID)->orderByDesc('id')->first();
                if ($sal) {
                    $sal->setAttribute('steps', $this->buildStepsDisplay(
                        $sal->progress ?? [],
                        $this->resolveApproverNames(collect([$row->ID => $sal]))
                    ));
                }

                $recruitmentJson = json_encode($row);
                $pkwtJson        = json_encode($pkwtRecords);
                $salaryJson      = json_encode($sal);

                // Edit link
                $editRoute  = route('recruitment.edit', $row->ID);
                $editButton = '<a href="' . $editRoute . '" class="act-btn act-edit"><i class="fas fa-edit"></i> Edit</a>';

                // Salary button (staff only)
                $isStaff = (int) ($row->is_staff ?? 0) === 1;
                $salaryButton = '';
                if ($isStaff) {
                    $salEditable  = $sal && $sal->status === 'pending' && (($sal->progress[0]['status'] ?? null) === 'pending');
                    $showSalBtn   = !$sal || $sal->status === 'rejected' || $salEditable;
                    if ($showSalBtn) {
                        $salLabel   = $salEditable ? 'Update Pengajuan Gaji' : 'Ajukan Gaji';
                        $salDataSal = htmlspecialchars(json_encode($salEditable ? $sal : null), ENT_QUOTES, 'UTF-8');
                        $salaryButton = '<button type="button" class="act-btn act-salary btn-salary"
                            data-id="' . $row->ID . '"
                            data-nama="' . e($row->NAMA) . '"
                            data-jabatan="' . e($row->jabatan ?? '-') . '"
                            data-dept="' . e($row->department ?? '-') . '"
                            data-salary="' . $salDataSal . '"
                            data-toggle="modal" data-target="#salaryModal">
                            <i class="fas fa-money-bill-wave"></i> ' . $salLabel . '
                        </button>';
                    } elseif ($sal && $sal->status === 'pending') {
                        $salaryButton = '<span class="salary-badge salary-badge-pending"><i class="fas fa-lock"></i> Sedang diproses approval</span>';
                    }
                }

                $recruitmentAttr = htmlspecialchars($recruitmentJson, ENT_QUOTES, 'UTF-8');
                $pkwtAttr        = htmlspecialchars($pkwtJson, ENT_QUOTES, 'UTF-8');
                $salaryAttr      = htmlspecialchars($salaryJson, ENT_QUOTES, 'UTF-8');

                return '<div style="display:flex; flex-direction:column; gap:5px;">
                    <button type="button" class="act-btn act-wa btn-whatsapp"
                        data-nama="' . e($row->NAMA) . '"
                        data-phone="' . e($row->HP) . '"
                        data-npk="' . e($row->NPK) . '"
                        data-id="' . $row->ID . '"
                        data-jabatan="' . e($row->jabatan ?? '-') . '"
                        data-dept="' . e($row->department ?? '-') . '"
                        data-toggle="modal" data-target="#whatsappModal">
                        <i class="fab fa-whatsapp"></i> WA
                    </button>
                    ' . $editButton . '
                    <button type="button" class="act-btn act-det btn-detail"
                        data-recruitment="' . $recruitmentAttr . '"
                        data-pkwt="' . $pkwtAttr . '"
                        data-salary="' . $salaryAttr . '"
                        data-toggle="modal" data-target="#detailModal">
                        <i class="fas fa-eye"></i> Detail
                    </button>
                    ' . $salaryButton . '
                </div>';
            })

            ->rawColumns([
                'col_nama', 'col_nik', 'col_pendidikan', 'col_fisik',
                'col_kontak', 'col_agama', 'col_dept', 'col_tgl_apply',
                'col_status', 'col_hasil', 'col_dokumen', 'col_aksi',
            ])
            ->make(true);
    }

    public function updatePenilaian(Request $request)
    {
        $request->validate([
            'id' => 'required'
        ]);

        $updates = [
            'result_interview' => $request->result_interview,
            'comment_interview' => $request->comment_interview,
            'result_kesehatan' => $request->result_kesehatan,
            'comment_kesehatan' => $request->comment_kesehatan,
            'result_test' => $request->result_test,
            'comment_test' => $request->comment_test,
            'result_user' => $request->result_user,
            'comment_user' => $request->comment_user,
        ];

                
        $now = date('Y-m-d');

        if ($request->filled('result_interview')) {
            $updates['is_interview'] = 'TRUE';
            $updates['tgl_interview'] = DB::raw("COALESCE(tgl_interview, '$now')");
        }
        if ($request->filled('result_kesehatan')) {
            $updates['is_kesehatan'] = 'TRUE';
            $updates['tgl_kesehatan'] = DB::raw("COALESCE(tgl_kesehatan, '$now')");
        }
        if ($request->filled('result_test')) {
            $updates['is_test'] = 'TRUE';
            $updates['tgl_test'] = DB::raw("COALESCE(tgl_test, '$now')");
        }

        if ($request->hasFile('file_test')) {
            $file = $request->file('file_test');
            $filename = time() . '_teknis_' . $file->getClientOriginalName();
            $path = $file->storeAs('recruitment/teknis', $filename, 'public');
            $updates['file_test'] = $path;
        }

        // Auto status_apply: FALSE di mana saja -> REJECTED, semua TRUE -> ONBOARDING
        // Khusus staff (PELAMAR.is_staff): jangan langsung ONBOARDING walau semua
        // penilaian lolos — masih harus menunggu pengajuan gaji selesai di-approve
        // (lihat HandlesSalaryApprovalSteps::maybeFinalizeStaffOnboarding()).
        $results = array_filter($updates, fn($v, $k) => str_starts_with($k, 'result_'), ARRAY_FILTER_USE_BOTH);
        $isStaff = false;
        if (in_array('FALSE', $results)) {
            $updates['status_apply'] = 'REJECTED';
        } elseif (count($results) === 4 && !in_array(null, $results) && !in_array('', $results) && count(array_unique($results)) === 1 && reset($results) === 'TRUE') {
            $isStaff = $this->isPelamarStaff($request->id);
            $updates['status_apply'] = $isStaff ? 'READY FOR SALARY' : 'ONBOARDING';
        }

        DB::connection('cii')->table('pelamar_details')
            ->where('id_pelamar', $request->id)
            ->update($updates);

        if ($isStaff) {
            // Barangkali pengajuan gajinya sudah lebih dulu selesai di-approve
            // sebelum penilaian ini kelar -> langsung finalize ke ONBOARDING.
            $this->maybeFinalizeStaffOnboarding($request->id);
        }

        Alert::success('Berhasil', 'Penilaian pelamar berhasil diperbarui!');
        return back()->with('success', 'Penilaian pelamar berhasil diperbarui!');
    }

    public function sendWhatsApp(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'type' => 'required',
            'nama' => 'required',
            'nomor_hp' => 'required',
            'message' => 'required',
        ]);

        $sendWa = $request->has('send_wa');
        $waSuccess = true;
        $response = [];

        if ($sendWa) {
            $devices = WhatsappDevice::where('is_active', true)->get();

            if (count($devices) == 0) {
                Alert::warning('Whatsapp Failed!', 'Device Not Linked!');
                return back()->with('error', 'Failed to send WhatsApp message: Device not linked');
            }

            $response = $this->fonnteService->sendMessage($devices[0]->id, $request->nomor_hp, $request->message);
            $waSuccess = $response['status'] ?? false;
        }

        if ($waSuccess) {
            $updates = [
                'status_apply' => strtoupper(str_replace('_', ' ', $request->type)),
            ];

            if ($request->type === 'invitation') {
                $updates['is_test'] = 'TRUE';
                $updates['status_apply'] = 'INVITATION TEST';
                if ($request->filled('tgl_schedule')) {
                    $updates['tgl_test'] = $request->tgl_schedule;
                }
            } elseif ($request->type === 'interview') {
                $updates['is_interview'] = 'TRUE';
                $updates['status_apply'] = 'CALLED TO INTERVIEW';
                if ($request->filled('tgl_schedule')) {
                    $updates['tgl_interview'] = $request->tgl_schedule;
                }
            } elseif ($request->type === 'final') {
                $updates['status_apply'] = 'ONBOARDING';
                if ($request->filled('tgl_schedule')) {
                    $updates['tgl_diterima'] = $request->tgl_schedule;
                }
            } elseif ($request->type === 'rejection') {
                $updates['status_apply'] = 'REJECTED';
            }

            DB::connection('cii')->table('pelamar_details')
                ->where('id_pelamar', $request->id)
                ->update($updates);

            if ($sendWa) {
                Alert::success('Berhasil', 'Status diperbarui dan pesan WhatsApp berhasil dikirim!');
                return back()->with('success', 'Status diperbarui dan pesan WhatsApp berhasil dikirim untuk ' . $request->nama);
            } else {
                Alert::success('Berhasil', 'Status berhasil diperbarui (tanpa mengirim WhatsApp)');
                return back()->with('success', 'Status berhasil diperbarui untuk ' . $request->nama);
            }
        }

        Alert::error('Whatsapp Failed!', 'Gagal mengirim pesan WhatsApp.');
        return back()->with('error', 'Failed to send WhatsApp message: ' . ($response['reason'] ?? 'Unknown error'));
    }

    
    public function edit($id)
    {
        $pelamar = DB::connection('cii')->table('PELAMAR')
            ->leftJoin('pelamar_details as pd', 'pd.id_pelamar', '=', 'PELAMAR.ID')
            ->where('PELAMAR.ID', $id)
            ->select('PELAMAR.*', 'pd.*', 'PELAMAR.ID as id', 'pd.id as detail_id')
            ->first();

        if (!$pelamar) {
            Alert::error('Error', 'Data pelamar tidak ditemukan');
            return redirect()->route('recruitment.index');
        }

        // Decode JSON fields if needed for view
        $pelamar->pengalaman_kerja = $pelamar->pengalaman_kerja ? json_decode($pelamar->pengalaman_kerja, true) : null;
        $pelamar->data_ayah = $pelamar->data_ayah ? json_decode($pelamar->data_ayah, true) : null;
        $pelamar->data_ibu = $pelamar->data_ibu ? json_decode($pelamar->data_ibu, true) : null;
        $pelamar->saudara_kandung = $pelamar->saudara_kandung ? json_decode($pelamar->saudara_kandung, true) : null;
        $pelamar->data_anak = $pelamar->data_anak ? json_decode($pelamar->data_anak, true) : null;
        $pelamar->riwayat_pendidikan = $pelamar->riwayat_pendidikan ? json_decode($pelamar->riwayat_pendidikan, true) : null;

        return view('recruitment.edit', compact('pelamar'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nik' => 'required|string|max:16',
            'no_kk' => 'required|string|max:16',
        ]);

        try {
            DB::connection('cii')->beginTransaction();

            $umur = null;
            if ($request->filled('tanggal_lahir')) {
                $diff = Carbon::parse($request->tanggal_lahir)->diff(Carbon::now());
                $umur = $diff->y . ' Tahun ' . $diff->m . ' Bulan ' . $diff->d . ' Hari';
            }

            $recruitmentPos = RecruitmentPosition::where('position', $request->jabatan)
                ->where('dept', $request->department)
                ->first();
            $isStaff = $recruitmentPos ? ($recruitmentPos->is_staff ?? 0) : 0;

            // Update PELAMAR
            DB::connection('cii')->table('PELAMAR')
                ->where('ID', $id)
                ->update([
                    'is_staff' => $isStaff,
                    'NAMA' => strtoupper($request->nama_lengkap ?? '-'),
                    'NIK' => $request->nik,
                    'NO_KK' => $request->no_kk,
                    'JENIS_KELAMIN' => strtoupper($request->jenis_kelamin ?? '-'),
                    'TMPT_LAHIR' => strtoupper($request->tempat_lahir ?? '-'),
                    'TGL_LAHIR' => $request->tanggal_lahir,
                    'UMUR' => $umur ?? '-',
                    'STATUS' => $request->status_pernikahan ?? '-',
                    'TANGGUNGAN' => $request->tanggungan ?? 0,
                    'AGAMA' => strtoupper($request->agama ?? '-'),
                    'HP' => $request->nomor_hp ?? '-',
                    'ALAMAT_LENGKAP' => strtoupper($request->alamat_asal ?? '-'),
                    'KABUPATEN' => strtoupper($request->kab_kota_asal ?? '-'),
                    'ALAMAT_DOMISILI' => strtoupper($request->status_domisili_asal ?? '-'),
                    'PENDIDIKAN' => strtoupper($request->pendidikan ?? '-'),
                    'JURUSAN' => strtoupper($request->jurusan ?? '-'),
                    'NAMA_SEKOLAH' => strtoupper($request->nama_sekolah ?? '-'),
                    'TINGGI_BADAN' => $request->tinggi_badan ?? 0,
                    'BERAT_BADAN' => $request->berat_badan ?? 0,
                ]);

            // Handle Files
            $fileFields = [
                'surat_lamaran' => 'file_surat_lamaran',
                'cv' => 'file_cv',
                'scan_ktp' => 'file_ktp',
                'scan_kk' => 'file_kk',
                'pas_foto' => 'file_pas_foto',
                'ijazah' => 'file_ijasah',
                'scan_akta_kelahiran' => 'file_akta_kelahiran',
                'scan_skck' => 'file_skck',
                'scan_blanko_kesehatan' => 'file_surat_sehat'
            ];

            $namaPelamar = Str::slug($request->nama_lengkap ?? 'pelamar', '_');
            $timestamp = Carbon::now()->format('Ymd-His');
            $detailUpdates = [
                'nomor_sim' => $request->sim,
                'warga_negara' => $request->warga_negara,
                'ikut_kb' => ($request->kb ?? 'Tidak') === 'Ya' ? 1 : 0,
                'bakat_hobby' => $request->hobby,
                'mode_transportasi' => $request->transportasi,
                'jabatan' => $request->jabatan,
                'department' => $request->department,
                'bpjs_tk' => $request->bpjs_tk,
                'bpjs_kes' => $request->bpjs_kes,
                'alamat_skrg' => $request->alamat_sekarang ?? $request->alamat_asal,
                'kabupaten_kota_skrg' => $request->kab_kota_sekarang ?? $request->kab_kota_asal,
                'status_domisili' => $request->status_domisili_sekarang ?? $request->status_domisili_asal,
                'nama_ktk_darurat' => $request->nama_darurat,
                'hubungan' => $request->hubungan_darurat,
                'no_telp_darurat' => $request->no_telepon_darurat,
                'motivasi' => $request->motivasi,
                'kegiatan_ekstra' => $request->kegiatan_ekstra,
                'data_ayah' => $request->filled('data_ayah') ? json_encode($request->data_ayah) : null,
                'data_ibu' => $request->filled('data_ibu') ? json_encode($request->data_ibu) : null,
                'saudara_kandung' => $request->filled('saudara_kandung') ? json_encode(array_values(array_filter($request->saudara_kandung, fn($v) => !empty(array_filter($v))))) : null,
                'data_anak' => $request->filled('data_anak') ? json_encode(array_values(array_filter($request->data_anak, fn($v) => !empty(array_filter($v))))) : null,
                'riwayat_pendidikan' => $request->filled('riwayat_pendidikan') ? json_encode(array_values(array_filter($request->riwayat_pendidikan, fn($v) => !empty(array_filter($v))))) : null,
                'pengalaman_kerja' => $request->filled('pengalaman_kerja') ? json_encode(array_values(array_filter($request->pengalaman_kerja, fn($v) => !empty(array_filter($v))))) : null,
            ];

            foreach ($fileFields as $inputName => $dbField) {
                if ($request->hasFile($inputName)) {
                    $file = $request->file($inputName);
                    $ext = $file->extension();
                    $filename = "{$inputName}_{$namaPelamar}_{$timestamp}.{$ext}";
                    $folder = "pelamar/{$inputName}";
                    $detailUpdates[$dbField] = $file->storeAs($folder, $filename, 'public');
                }
            }

            DB::connection('cii')->table('pelamar_details')
                ->where('id_pelamar', $id)
                ->update($detailUpdates);

            DB::connection('cii')->commit();

            Alert::success('Berhasil', 'Data pelamar berhasil diperbarui!');
            return redirect()->route('recruitment.index');

        } catch (\Exception $e) {
            DB::connection('cii')->rollBack();
            Alert::error('Gagal', 'Terjadi kesalahan: ' . $e->getMessage());
            return back()->withInput();
        }
    }
}