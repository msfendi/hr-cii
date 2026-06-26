<?php

namespace App\Http\Controllers;

use App\Models\Pelamar;
use App\Models\PelamarDetails;
use App\Models\RecruitmentPosition;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RecruitmentFormController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Step definitions
    |--------------------------------------------------------------------------
    */
    private array $steps = [
        1 => ['view' => 'recruitments_form.step-1', 'session' => 'reg_step1'],
        2 => ['view' => 'recruitments_form.step-2', 'session' => 'reg_step2'],
        3 => ['view' => 'recruitments_form.step-3', 'session' => 'reg_step3'],
        4 => ['view' => 'recruitments_form.step-4', 'session' => 'reg_step4'],
        5 => ['view' => 'recruitments_form.step-5', 'session' => 'reg_step5'],
        6 => ['view' => 'recruitments_form.step-6', 'session' => 'reg_step6'],
        7 => ['view' => 'recruitments_form.step-7', 'session' => 'reg_step7'],
        8 => ['view' => 'recruitments_form.step-8', 'session' => 'reg_step8'],
    ];

    /*
    |--------------------------------------------------------------------------
    | GET  /recruitments_form/step/{step}
    |--------------------------------------------------------------------------
    */
    public function show(int $step)
    {
        abort_if($step < 1 || $step > 8, 404);

        // Cegah skip step
        if ($step > 1 && ! Session::has('reg_step' . ($step - 1))) {
            return redirect()->route('recruitments.step', ['step' => $step - 1])
                ->with('warning', 'Harap lengkapi step sebelumnya terlebih dahulu.');
        }

        // Step 8 (file upload) — jangan kirim savedData file ke view
        // karena UploadedFile tidak bisa di-serialize
        $savedData = ($step === 8)
            ? []
            : Session::get($this->steps[$step]['session'], []);

        $extraData = [];
        if ($step === 1) {
            $extraData['positions'] = RecruitmentPosition::where('is_aktif', 'true')
                ->select('dept', 'position')
                ->distinct()
                ->get()
                ->groupBy('dept');
        }

        return view($this->steps[$step]['view'], array_merge([
            'currentStep' => $step,
            'savedData'   => $savedData,
        ], $extraData));
    }

    /*
    |--------------------------------------------------------------------------
    | POST  /recruitments_form/step/{step}
    |--------------------------------------------------------------------------
    */
    public function store(Request $request, int $step)
    {
        abort_if($step < 1 || $step > 8, 404);

        // Jika user klik tombol "Sebelumnya" (back), simpan draft tanpa validasi ketat
        if ($request->input('action') === 'back') {
            $draftData = $request->except(['_token', 'action']);

            // Gabungkan draft baru dengan data yang sudah ada di session agar tidak ada yang hilang
            $existingData = Session::get($this->steps[$step]['session'], []);
            $mergedData = array_merge($existingData, $draftData);

            Session::put($this->steps[$step]['session'], $mergedData);
            return redirect()->route('recruitments.step', ['step' => $step - 1]);
        }

        $validated = $request->validate(
            $this->rules($step),
            $this->messages($step)
        );

        // Step 8 berisi file — simpan path saja di session, bukan objek file
        if ($step === 8) {
            return $this->submit($request, $validated);
        }

        Session::put($this->steps[$step]['session'], $validated);

        return redirect()->route('recruitments.step', ['step' => $step + 1]);
    }

    /*
    |--------------------------------------------------------------------------
    | Final submit — insert ke PELAMAR + pelamar_details
    |--------------------------------------------------------------------------
    */
    private function submit(Request $request, array $step8Data)
    {
        // ── Kumpulkan semua data session ────────────────────────────────────
        $step1 = Session::get('reg_step1', []);
        $step2 = Session::get('reg_step2', []);
        $step3 = Session::get('reg_step3', []);  // pengalaman kerja
        $step4 = Session::get('reg_step4', []);  // keluarga
        $step5 = Session::get('reg_step5', []);  // pendidikan
        $step6 = Session::get('reg_step6', []);  // motivasi & kegiatan
        $step7 = Session::get('reg_step7', []);  // fisik

        // dd($step1, $step2, $step3, $step4, $step5, $step6,  $step7);

        // ── Upload file-file dokumen ─────────────────────────────────────────
        $fileFields    = [
            'surat_lamaran',
            'cv',
            'scan_ktp',
            'scan_kk',
            'pas_foto',
            'ijazah',
            'scan_akta_kelahiran',
            'scan_skck',
            'scan_blanko_kesehatan',
        ];
        $namaPelamar   = Str::slug($step1['nama_lengkap'] ?? 'pelamar', '_');
        $timestamp     = Carbon::now()->format('Ymd-His');
        $uploadedFiles = [];

        foreach ($fileFields as $field) {
            if ($request->hasFile($field)) {
                $file      = $request->file($field);
                $ext       = $file->getClientOriginalExtension();
                $filename  = "{$field}_{$namaPelamar}_{$timestamp}.{$ext}";
                $folder    = "pelamar/{$field}";
                $uploadedFiles[$field] = $file->storeAs($folder, $filename, 'public');
            }
        }

        // dd($uploadedFiles);

        // ── Hitung umur dari tanggal lahir ───────────────────────────────────
        $umur = null;
        if (! empty($step1['tanggal_lahir'])) {
            $diff = Carbon::parse($step1['tanggal_lahir'])->diff(Carbon::now());
            $umur = $diff->y . ' Tahun ' . $diff->m . ' Bulan ' . $diff->d . ' Hari';
        }

        // ── Ambil pendidikan terakhir dari step5 (riwayat_pendidikan) ────────
        // Ambil entry pendidikan dengan tingkat tertinggi
        $pendidikanTerakhir = null;
        $namaSekolah        = null;
        $jurusan            = null;
        if (! empty($step5['education'])) {
            $urutan = ['SD', 'SMP', 'SMA/SMK', 'Akademi/D3', 'D3', 'S1', 'S2', 'S3'];
            $sorted = collect($step5['education'])
                ->sortByDesc(fn($e) => array_search($e['tingkat'] ?? '', $urutan))
                ->first();
            if ($sorted) {
                $pendidikanTerakhir = $sorted['tingkat']  ?? null;
                $namaSekolah        = $sorted['institusi'] ?? null;
                $jurusan            = $sorted['jurusan']   ?? null;
            }
        }

        // Fallback: gunakan pendidikan dari step1 jika step5 kosong
        if (! $pendidikanTerakhir) {
            $pendidikanTerakhir = $step1['pendidikan'] ?? null;
        }

        // ── Insert ke tabel PELAMAR (koneksi cii) ───────────────────────────
        try {
            $pelamarId = DB::connection('cii')->table('PELAMAR')->insertGetId([
                'NAMA'             => strtoupper($step1['nama_lengkap'] ?? '-'),
                'NIK'              => $step1['nik']              ?? null,
                'NO_KK'            => $step1['no_kk']            ?? null,
                'JENIS_KELAMIN'    => strtoupper($step1['jenis_kelamin'] ?? '-'),
                'TMPT_LAHIR'       => strtoupper($step1['tempat_lahir']  ?? '-'),
                'TGL_LAHIR'        => $step1['tanggal_lahir']    ?? null,
                'UMUR'             => $umur ?? '-',
                'STATUS'           => $step1['status_pernikahan'] ?? '-',
                'TANGGUNGAN'       => $step1['tanggungan']        ?? 0,
                'AGAMA'            => strtoupper($step1['agama'] ?? '-'),
                // IBU: kolom NOT NULL di SQL Server, fallback ke '-'
                'IBU'              => strtoupper(trim($step4['ibu']['nama'] ?? '') ?: '-'),
                'HP'               => $step1['nomor_hp']          ?? '-',
                // Alamat — dari step2
                'ALAMAT_LENGKAP'   => strtoupper($step2['alamat_asal']      ?? '-'),
                'KABUPATEN'        => strtoupper($step2['kab_kota_asal']     ?? '-'),
                'ALAMAT_DOMISILI'  => strtoupper($step2['status_domisili_asal'] ?? '-'),
                // Pendidikan — dari step5
                'PENDIDIKAN'       => strtoupper($pendidikanTerakhir ?? '-'),
                'JURUSAN'          => strtoupper($jurusan      ?? '-'),
                'NAMA_SEKOLAH'     => strtoupper(trim($namaSekolah ?? '') ?: '-'),
                // KABUPATEN_SEKOLAH: kolom NOT NULL, selalu isi
                'KABUPATEN_SEKOLAH' => '-',
                // Fisik — dari step7
                'TINGGI_BADAN'     => $step7['tinggi_badan'] ?? 0,
                'BERAT_BADAN'      => $step7['berat_badan']  ?? 0,
                // Status default
                'IS_KONTRAK'       => 'FALSE',
            ]);
        } catch (\Exception $e) {
            Log::error('[RecruitmentForm] Gagal insert PELAMAR: ' . $e->getMessage());

            // Hapus file yang sudah terupload
            foreach ($uploadedFiles as $path) {
                Storage::disk('public')->delete($path);
            }

            return back()->with('error', 'Terjadi kesalahan saat menyimpan data pribadi. Silakan coba lagi.');
        }

        // ── Insert ke tabel pelamar_details (koneksi default) ───────────────
        try {
            PelamarDetails::create([
                'id_pelamar'         => $pelamarId,

                // Step 1
                'nomor_sim'          => $step1['sim']          ?? null,
                'warga_negara'       => $step1['warga_negara'] ?? null,
                'ikut_kb'            => ($step1['kb'] ?? 'Tidak') === 'Ya' ? 1 : 0,
                'bakat_hobby'        => $step1['hobby']         ?? null,
                'mode_transportasi'  => $step1['transportasi']  ?? null,
                'jabatan'            => $step1['jabatan']        ?? null,
                'department'         => $step1['department']     ?? null,
                'bpjs_tk'            => $step1['bpjs_tk']        ?? null,
                'bpjs_kes'           => $step1['bpjs_kes']       ?? null,

                // Step 2
                'alamat_skrg'        => $step2['alamat_sekarang']          ?? $step2['alamat_asal']      ?? null,
                'kabupaten_kota_skrg' => $step2['kab_kota_sekarang']        ?? $step2['kab_kota_asal']    ?? null,
                'status_domisili'    => $step2['status_domisili_sekarang']  ?? $step2['status_domisili_asal'] ?? null,
                'nama_ktk_darurat'   => $step2['nama_darurat']     ?? null,
                'hubungan'           => $step2['hubungan_darurat']  ?? null,
                'no_telp_darurat'    => $step2['no_telepon_darurat'] ?? null,

                // Step 3 — pengalaman kerja (JSON)
                'pengalaman_kerja'   => ! empty($step3['experiences'])
                    ? json_encode($step3['experiences'])
                    : null,

                // Step 4 — keluarga (JSON)
                'data_ayah'          => ! empty($step4['ayah'])
                    ? json_encode($step4['ayah'])
                    : null,
                'data_ibu'           => ! empty($step4['ibu'])
                    ? json_encode($step4['ibu'])
                    : null,
                'saudara_kandung'    => ! empty($step4['saudara'])
                    ? json_encode($step4['saudara'])
                    : null,
                'data_anak'          => ! empty($step4['anak'])
                    ? json_encode($step4['anak'])
                    : null,

                // Step 5 — riwayat pendidikan (JSON)
                'riwayat_pendidikan' => ! empty($step5['education'])
                    ? json_encode($step5['education'])
                    : null,

                // Step 6 — Motivasi & Kegiatan
                'motivasi'           => $step6['motivasi'] ?? null,
                'kegiatan_ekstra'    => $step6['kegiatan_ekstra'] ?? null,

                // Fisik dari step7 (tinggi/berat sudah di PELAMAR)

                // Step 8 — file dokumen
                'file_surat_lamaran' => $uploadedFiles['surat_lamaran']         ?? null,
                'file_cv'            => $uploadedFiles['cv']                     ?? null,
                'file_ktp'           => $uploadedFiles['scan_ktp']               ?? null,
                'file_kk'            => $uploadedFiles['scan_kk']                ?? null,
                'file_pas_foto'      => $uploadedFiles['pas_foto']               ?? null,
                'file_ijasah'        => $uploadedFiles['ijazah']                 ?? null,
                'file_akta_kelahiran' => $uploadedFiles['scan_akta_kelahiran']    ?? null,
                'file_skck'          => $uploadedFiles['scan_skck']              ?? null,
                'file_surat_sehat'   => $uploadedFiles['scan_blanko_kesehatan']  ?? null,

                // Status awal
                'status_apply'       => 'APPLIED',
                'is_test'            => 'FALSE',
                'tgl_test'           => null,
                'tgl_interview'      => null,
                'tgl_kesehatan'      => null,
                'tgl_diterima'       => null,
                'is_interview'       => 'FALSE',
                'is_kesehatan'       => 'FALSE',
            ]);
        } catch (\Exception $e) {
            Log::error('[RecruitmentForm] Gagal insert pelamar_details: ' . $e->getMessage());

            // Rollback: hapus baris PELAMAR yang baru dibuat & file
            try {
                DB::connection('cii')->table('PELAMAR')->where('id', $pelamarId)->delete();
            } catch (\Exception) {
            }

            foreach ($uploadedFiles as $path) {
                Storage::disk('public')->delete($path);
            }

            return back()->with('error', 'Terjadi kesalahan saat menyimpan detail pendaftaran. Silakan coba lagi.');
        }

        // ── Bersihkan semua session step ─────────────────────────────────────
        for ($i = 1; $i <= 8; $i++) {
            Session::forget($this->steps[$i]['session']);
        }

        // ── Simpan info ke session untuk halaman sukses ──────────────────────
        Session::put('application_ref',  'RF-' . Carbon::now()->format('Ymd-His'));
        Session::put('applicant_name',   $step1['nama_lengkap'] ?? 'Pelamar');

        return redirect()->route('recruitments.success')
            ->with('success', 'Pendaftaran berhasil dikirim!');
    }

    /*
    |--------------------------------------------------------------------------
    | Validation rules per step
    |--------------------------------------------------------------------------
    */
    private function rules(int $step): array
    {
        return match ($step) {

            // ----------------------------------------------------------------
            // Step 1 — Data Pribadi
            // ----------------------------------------------------------------
            1 => [
                'nama_lengkap'      => ['required', 'string', 'max:255'],
                'nik'               => ['required', 'digits:16'],
                'no_kk'             => ['required', 'digits:16'],
                'sim'               => ['nullable', 'string', 'max:30'],
                'tempat_lahir'      => ['required', 'string', 'max:100'],
                'tanggal_lahir'     => ['nullable', 'date', 'before:today'],
                'warga_negara'      => ['nullable', 'in:WNI,WNA'],
                'golongan_darah'    => ['nullable', 'in:A,B,AB,O'],
                'jenis_kelamin'     => ['required', 'in:L,P'],
                'status_pernikahan' => ['nullable', 'in:Belum Kawin,Kawin,Cerai Hidup,Cerai Mati'],
                'kb'                => ['nullable', 'in:Ya,Tidak'],
                'tanggungan'        => ['nullable', 'integer', 'min:0', 'max:20'],
                'nomor_hp'          => ['required', 'string', 'max:20'],
                'agama'             => ['nullable', 'in:Islam,Kristen,Katolik,Hindu,Buddha,Khonghucu'],
                'hobby'             => ['nullable', 'string', 'max:255'],
                'transportasi'      => ['nullable', 'string', 'max:100'],
                'pendidikan'        => ['nullable', 'in:SMA/SMK,D3,S1,S2,S3'],
                'jabatan'           => ['nullable', 'string', 'max:100'],
                'department'        => ['nullable', 'string', 'max:100'],
                'bpjs_tk'           => ['nullable', 'string', 'max:30'],
                'bpjs_kes'          => ['nullable', 'string', 'max:30'],
            ],

            // ----------------------------------------------------------------
            // Step 2 — Kontak & Alamat
            // ----------------------------------------------------------------
            2 => [
                'alamat_asal'              => ['required', 'string', 'max:500'],
                'kab_kota_asal'            => ['required', 'string', 'max:100'],
                'status_domisili_asal'     => ['nullable', 'in:Milik Sendiri,Sewa/Kontrak,Ikut Orang Tua'],
                'alamat_sekarang'          => ['nullable', 'string', 'max:500'],
                'kab_kota_sekarang'        => ['nullable', 'string', 'max:100'],
                'status_domisili_sekarang' => ['nullable', 'in:Milik Sendiri,Sewa/Kontrak,Ikut Orang Tua'],
                'same_as_asal'             => ['nullable', 'boolean'],
                'no_telepon'               => ['nullable', 'string', 'max:20'],
                'nama_darurat'             => ['required', 'string', 'max:100'],
                'hubungan_darurat'         => ['required', 'string', 'max:100'],
                'no_telepon_darurat'       => ['required', 'string', 'max:20'],
            ],

            // ----------------------------------------------------------------
            // Step 3 — Pengalaman Kerja (multi-entry, opsional)
            // ----------------------------------------------------------------
            3 => [
                'experiences'                 => ['nullable', 'array'],
                'experiences.*.perusahaan'    => ['required_with:experiences.*', 'string', 'max:255'],
                'experiences.*.dari'          => ['nullable', 'date_format:Y-m'],
                'experiences.*.sampai'        => ['nullable', 'date_format:Y-m', 'after_or_equal:experiences.*.dari'],
                'experiences.*.masih_bekerja' => ['nullable', 'boolean'],
                'experiences.*.jabatan'       => ['nullable', 'string', 'max:100'],
                'experiences.*.departemen'    => ['nullable', 'string', 'max:100'],
                'experiences.*.alasan'        => ['nullable', 'string', 'max:500'],
            ],

            // ----------------------------------------------------------------
            // Step 4 — Data Keluarga
            // ----------------------------------------------------------------
            4 => [
                'ayah.nama'            => ['nullable', 'string', 'max:255'],
                'ayah.tgl_lahir'       => ['nullable', 'date'],
                'ayah.pendidikan'      => ['nullable', 'in:SD,SMP,SMA/SMK,D3,S1,S2,S3'],
                'ayah.pekerjaan'       => ['nullable', 'string', 'max:100'],
                'ibu.nama'             => ['nullable', 'string', 'max:255'],
                'ibu.tgl_lahir'        => ['nullable', 'date'],
                'ibu.pendidikan'       => ['nullable', 'in:SD,SMP,SMA/SMK,D3,S1,S2,S3'],
                'ibu.pekerjaan'        => ['nullable', 'string', 'max:100'],
                'saudara'              => ['nullable', 'array'],
                'saudara.*.nama'       => ['required_with:saudara.*', 'string', 'max:255'],
                'saudara.*.tgl_lahir'  => ['nullable', 'date'],
                'saudara.*.gender'     => ['nullable', 'in:Laki-laki,Perempuan'],
                'saudara.*.pendidikan' => ['nullable', 'in:SD,SMP,SMA/SMK,D3,S1,S2,S3'],
                'saudara.*.pekerjaan'  => ['nullable', 'string', 'max:100'],
                'anak'                 => ['nullable', 'array'],
                'anak.*.nama'          => ['required_with:anak.*', 'string', 'max:255'],
                'anak.*.tempat_lahir'  => ['nullable', 'string', 'max:100'],
                'anak.*.tgl_lahir'     => ['nullable', 'date'],
                'anak.*.gender'        => ['nullable', 'in:Laki-laki,Perempuan'],
                'anak.*.pendidikan'    => ['nullable', 'string', 'max:255'],
                'anak.*.status'        => ['nullable', 'string', 'max:100'],
            ],

            // ----------------------------------------------------------------
            // Step 5 — Riwayat Pendidikan Formal
            // ----------------------------------------------------------------
            5 => [
                'education'             => ['nullable', 'array'],
                'education.*.tingkat'   => ['required_with:education.*', 'in:SD,SMP,SMA/SMK,Akademi/D3,S1,S2,S3'],
                'education.*.institusi' => ['required_with:education.*', 'string', 'max:255'],
                'education.*.jurusan'   => ['nullable', 'string', 'max:100'],
                'education.*.dari'      => ['nullable', 'integer', 'min:1950', 'max:' . (date('Y') + 6)],
                'education.*.sampai'    => ['nullable', 'integer', 'min:1950', 'max:' . (date('Y') + 6), 'gte:education.*.dari'],
                'education.*.lulus'     => ['nullable', 'boolean'],
            ],

            // ----------------------------------------------------------------
            // Step 6 — Motivasi & Kegiatan Ekstra
            // ----------------------------------------------------------------
            6 => [
                'motivasi'        => ['nullable', 'string', 'min:50', 'max:1000'],
                'kegiatan_ekstra' => ['nullable', 'string', 'max:800'],
            ],

            // ----------------------------------------------------------------
            // Step 7 — Data Fisik
            // ----------------------------------------------------------------
            7 => [
                'tinggi_badan' => ['nullable', 'integer', 'min:100', 'max:250'],
                'berat_badan'  => ['nullable', 'integer', 'min:20',  'max:200'],
            ],

            // ----------------------------------------------------------------
            // Step 8 — Upload Dokumen
            // ----------------------------------------------------------------
            8 => [
                'surat_lamaran'         => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
                'cv'                    => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
                'scan_ktp'              => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
                'scan_kk'               => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
                'pas_foto'              => ['nullable', 'file', 'mimes:jpg,jpeg,png',      'max:2048'],
                'ijazah'                => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
                'scan_akta_kelahiran'   => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
                'scan_skck'             => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
                'scan_blanko_kesehatan' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
                'deklarasi'             => ['required', 'accepted'],
            ],

            default => [],
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Custom validation messages
    |--------------------------------------------------------------------------
    */
    private function messages(int $step): array
    {
        $common = [
            'required' => ':attribute wajib diisi.',
            'string'   => ':attribute harus berupa teks.',
            'max'      => ':attribute maksimal :max karakter.',
            'digits'   => ':attribute harus :digits digit.',
            'date'     => ':attribute harus berupa tanggal yang valid.',
            'in'       => ':attribute tidak valid.',
            'integer'  => ':attribute harus berupa angka.',
            'min'      => ':attribute minimal :min.',
        ];

        $perStep = match ($step) {
            1 => [
                'nik.digits'           => 'NIK harus tepat 16 digit.',
                'no_kk.digits'         => 'Nomor KK harus tepat 16 digit.',
                'tanggal_lahir.before' => 'Tanggal lahir harus sebelum hari ini.',
            ],
            2 => [
                'nama_darurat.required'       => 'Nama kontak darurat wajib diisi.',
                'no_telepon_darurat.required' => 'Nomor telepon darurat wajib diisi.',
            ],
            3 => [
                'experiences.*.perusahaan.required_with' => 'Nama perusahaan wajib diisi.',
                'experiences.*.sampai.after_or_equal'    => 'Tanggal selesai harus setelah tanggal mulai.',
            ],
            4 => [
                'saudara.*.nama.required_with' => 'Nama saudara wajib diisi.',
                'anak.*.nama.required_with'    => 'Nama anak wajib diisi.',
            ],
            5 => [
                'education.*.tingkat.required_with'   => 'Tingkat pendidikan wajib dipilih.',
                'education.*.institusi.required_with' => 'Nama institusi wajib diisi.',
                'education.*.sampai.gte'              => 'Tahun selesai harus sama atau setelah tahun mulai.',
            ],
            6 => [
                'motivasi.required'        => 'Motivasi melamar wajib diisi.',
                'motivasi.min'             => 'Motivasi minimal 50 karakter.',
                'kegiatan_ekstra.required' => 'Kegiatan ekstra wajib diisi.',
            ],
            7 => [
                'tinggi_badan.required' => 'Tinggi badan wajib diisi.',
                'tinggi_badan.min'      => 'Tinggi badan minimal 100 cm.',
                'tinggi_badan.max'      => 'Tinggi badan maksimal 250 cm.',
                'berat_badan.required'  => 'Berat badan wajib diisi.',
                'berat_badan.min'       => 'Berat badan minimal 20 kg.',
                'berat_badan.max'       => 'Berat badan maksimal 200 kg.',
            ],
            8 => [
                'surat_lamaran.required' => 'Surat lamaran wajib diupload.',
                'surat_lamaran.mimes'    => 'Surat lamaran harus berformat PDF, JPG, atau PNG.',
                'surat_lamaran.max'      => 'Surat lamaran maksimal 2MB.',
                'cv.required'            => 'CV wajib diupload.',
                'cv.mimes'               => 'CV harus berformat PDF, JPG, atau PNG.',
                'cv.max'                 => 'CV maksimal 2MB.',
                'scan_ktp.required'      => 'Scan KTP wajib diupload.',
                'scan_ktp.mimes'         => 'Scan KTP harus berformat PDF, JPG, atau PNG.',
                'scan_ktp.max'           => 'Scan KTP maksimal 2MB.',
                '*.max'                  => ':attribute maksimal 2MB.',
                '*.mimes'                => ':attribute harus berformat PDF, JPG, atau PNG.',
                'deklarasi.required'     => 'Anda harus menyetujui pernyataan deklarasi.',
                'deklarasi.accepted'     => 'Anda harus mencentang pernyataan deklarasi.',
            ],
            default => [],
        };

        return array_merge($common, $perStep);
    }
}
