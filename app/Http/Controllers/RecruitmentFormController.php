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
use Illuminate\Validation\ValidationException;

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

    /**
     * Field dokumen step 8 + label yang enak dibaca user, dipakai ulang
     * oleh submit() maupun precheckFileUploads(). Sebelumnya field list
     * ini di-hardcode ulang di dalam submit() — sekarang cukup 1 sumber.
     */
    private array $fileFields = [
        'surat_lamaran'         => 'Surat Lamaran',
        'cv'                    => 'CV',
        'scan_ktp'              => 'Scan KTP',
        'scan_kk'               => 'Scan Kartu Keluarga (KK)',
        'pas_foto'              => 'Pas Foto',
        'ijazah'                => 'Scan Ijazah',
        'scan_akta_kelahiran'   => 'Scan Akta Kelahiran',
        'scan_skck'             => 'Scan SKCK',
        'scan_blanko_kesehatan' => 'Scan Blanko Kesehatan',
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

        // Tombol "Sebelumnya" (back) → simpan draft tanpa validasi ketat
        if ($request->input('action') === 'back') {
            return $this->saveDraftAndGoBack($request, $step);
        }

        try {
            // Step 8: cek kesehatan file SEBELUM masuk ke rule Laravel biasa,
            // supaya pesan error untuk kasus "file dari Google Drive / OneDrive
            // belum full sync" bisa spesifik per-field, bukan sekadar
            // ":attribute wajib diisi." padahal user sudah memilih file.
            if ($step === 8) {
                $this->precheckFileUploads($request);
            }

            $validated = $request->validate(
                $this->rules($step),
                $this->messages($step)
            );
        } catch (ValidationException $e) {
            // Diteruskan apa adanya — Laravel otomatis redirect()->back()
            // dengan ->withErrors() & ->withInput(), dan Blade sudah membaca
            // ini lewat @error('field_name') di tiap input. Ditangkap eksplisit
            // di sini hanya supaya jelas bagian mana yang mengurus apa.
            throw $e;
        }

        // Step 8 berisi file — simpan path saja di DB, bukan objek file
        if ($step === 8) {
            return $this->submit($request, $validated);
        }

        Session::put($this->steps[$step]['session'], $validated);

        return redirect()->route('recruitments.step', ['step' => $step + 1]);
    }

    /**
     * Simpan draft saat user klik "Sebelumnya", lalu redirect mundur satu step.
     * Diekstrak dari store() supaya store() lebih pendek & fokus pada alur maju.
     */
    private function saveDraftAndGoBack(Request $request, int $step)
    {
        $draftData = $request->except(['_token', 'action']);

        // Gabungkan draft baru dengan data yang sudah ada di session
        // agar tidak ada yang hilang.
        $existingData = Session::get($this->steps[$step]['session'], []);
        $mergedData   = array_merge($existingData, $draftData);

        Session::put($this->steps[$step]['session'], $mergedData);

        return redirect()->route('recruitments.step', ['step' => $step - 1]);
    }

    /*
    |--------------------------------------------------------------------------
    | Pre-check integritas file upload (step 8)
    |--------------------------------------------------------------------------
    | Tujuan: mendeteksi kasus upload gagal karena file berasal dari folder
    | cloud-sync (Google Drive Desktop, OneDrive, Dropbox) yang belum
    | sepenuhnya terunduh ke disk lokal ("placeholder file" / online-only
    | file). Gejalanya di level PHP biasanya salah satu dari:
    |   - UPLOAD_ERR_PARTIAL   → koneksi/baca file terputus di tengah jalan
    |   - UPLOAD_ERR_CANT_WRITE / NO_TMP_DIR → gagal tulis ke folder temp
    |   - File "berhasil" ter-upload tapi ukurannya 0 byte / tidak terbaca
    |     (isValid() === false) walau user yakin sudah memilih file
    | Semua kasus ini kita ubah jadi pesan per-field yang actionable,
    | dilempar sebagai ValidationException supaya tetap konsisten dengan
    | mekanisme @error() yang sudah dipakai di semua view step 8.
    |--------------------------------------------------------------------------
    */
    private function precheckFileUploads(Request $request): void
    {
        $errors = [];

        foreach ($this->fileFields as $field => $label) {
            // Field belum diisi sama sekali → biarkan rule `required`/`nullable`
            // bawaan yang menangani (mis. scan_blanko_kesehatan memang opsional).
            if (! $request->hasFile($field)) {
                continue;
            }

            $file = $request->file($field);

            // hasFile() bisa true meski array kosong/invalid untuk multi-file,
            // pastikan instance-nya benar sebelum dicek lebih lanjut.
            if (! $file) {
                continue;
            }

            $phpError = $file->getError();

            if ($phpError !== UPLOAD_ERR_OK) {
                $errors["{$field}"] = $this->describeUploadError($phpError, $label);
                continue;
            }

            // File "sukses" menurut PHP tapi ternyata tidak valid / tidak
            // terbaca / 0 byte — ciri khas file placeholder cloud-drive yang
            // belum selesai sinkron saat browser membacanya.
            if (! $file->isValid() || ! is_readable($file->getRealPath()) || $file->getSize() === 0) {
                $errors["{$field}"] =
                    "{$label} gagal diproses (file kosong atau tidak dapat dibaca). " .
                    'Ini sering terjadi bila file masih tersimpan di Google Drive, OneDrive, ' .
                    'atau layanan cloud lain dan belum sepenuhnya terunduh ke perangkat Anda. ' .
                    'Silakan unduh/salin file tersebut ke folder lokal (mis. Documents atau Desktop), ' .
                    'lalu upload ulang dari lokasi tersebut.';
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Terjemahkan kode error upload PHP (UPLOAD_ERR_*) menjadi pesan
     * Bahasa Indonesia yang actionable untuk user awam.
     */
    private function describeUploadError(int $phpError, string $label): string
    {
        return match ($phpError) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
            "{$label} melebihi ukuran maksimal yang diizinkan server. Maksimal 2MB per file.",

            UPLOAD_ERR_PARTIAL =>
            "{$label} hanya terupload sebagian (koneksi terputus di tengah proses). " .
                'Kondisi ini umum terjadi jika file diambil langsung dari folder Google Drive / OneDrive ' .
                'yang belum sepenuhnya tersinkron secara lokal. Silakan simpan file ke penyimpanan ' .
                'lokal perangkat terlebih dahulu, lalu coba upload ulang.',

            UPLOAD_ERR_NO_FILE =>
            "{$label} wajib diupload.",

            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE =>
            "Server gagal menyimpan sementara file {$label}. Silakan coba lagi beberapa saat lagi, " .
                'atau hubungi admin jika masalah berlanjut.',

            UPLOAD_ERR_EXTENSION =>
            "Upload {$label} dihentikan oleh sistem. Silakan coba format file lain (PDF/JPG/PNG).",

            default =>
            "Terjadi kesalahan saat mengupload {$label}. Silakan coba upload ulang.",
        };
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

        // ── Upload file-file dokumen ─────────────────────────────────────────
        $namaPelamar   = Str::slug($step1['nama_lengkap'] ?? 'pelamar', '_');
        $timestamp     = Carbon::now()->format('Ymd-His');
        $uploadedFiles = [];

        try {
            foreach (array_keys($this->fileFields) as $field) {
                if (! $request->hasFile($field)) {
                    continue;
                }

                $file = $request->file($field);

                // Jaring pengaman terakhir: precheckFileUploads() harusnya sudah
                // menyaring file bermasalah sebelum sampai sini, tapi kita tetap
                // validasi ulang karena storeAs() bisa gagal karena isu I/O lain
                // (disk penuh, permission, dsb) yang baru muncul saat proses tulis.
                if (! $file->isValid()) {
                    throw new \RuntimeException(
                        "File {$this->fileFields[$field]} tidak valid saat akan disimpan."
                    );
                }

                $ext      = $file->extension(); // aman: berdasarkan konten file, bukan nama dari client
                $filename = "{$field}_{$namaPelamar}_{$timestamp}.{$ext}";
                $folder   = "pelamar/{$field}";

                $storedPath = $file->storeAs($folder, $filename, 'public');

                if ($storedPath === false) {
                    throw new \RuntimeException(
                        "Gagal menyimpan file {$this->fileFields[$field]} ke server."
                    );
                }

                $uploadedFiles[$field] = $storedPath;
            }
        } catch (\Throwable $e) {
            $this->cleanupUploadedFiles($uploadedFiles);

            $message = $this->buildSubmitErrorMessage(
                $e,
                'Gagal memproses upload file (step 8)',
                'Terjadi kesalahan saat menyimpan dokumen. Pastikan file tidak sedang tersinkron dari ' .
                    'Google Drive/OneDrive dan coba upload ulang dari penyimpanan lokal.'
            );

            return back()->withInput()->with('error', $message);
        }

        // ── Hitung umur dari tanggal lahir ───────────────────────────────────
        $umur = null;
        if (! empty($step1['tanggal_lahir'])) {
            $diff = Carbon::parse($step1['tanggal_lahir'])->diff(Carbon::now());
            $umur = $diff->y . ' Tahun ' . $diff->m . ' Bulan ' . $diff->d . ' Hari';
        }

        // ── Ambil pendidikan terakhir dari step5 (riwayat_pendidikan) ────────
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
            $this->cleanupUploadedFiles($uploadedFiles);

            $message = $this->buildSubmitErrorMessage(
                $e,
                'Gagal insert tabel PELAMAR (data dari step 1, step 2, step 4-ibu, step 5, step 7)',
                'Terjadi kesalahan saat menyimpan data pribadi. Silakan coba lagi.'
            );

            return back()->with('error', $message);
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
            // Rollback: hapus baris PELAMAR yang baru dibuat & file
            try {
                DB::connection('cii')->table('PELAMAR')->where('id', $pelamarId)->delete();
            } catch (\Exception) {
            }

            $this->cleanupUploadedFiles($uploadedFiles);

            $message = $this->buildSubmitErrorMessage(
                $e,
                'Gagal insert pelamar_details (data dari step 1-8, termasuk field JSON experiences/keluarga/education)',
                'Terjadi kesalahan saat menyimpan detail pendaftaran. Silakan coba lagi.'
            );

            return back()->with('error', $message);
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

    /**
     * Hapus file yang sudah terlanjur ter-upload ke disk saat terjadi
     * kegagalan di tahap berikutnya (DB insert gagal, dsb). Diekstrak
     * karena sebelumnya blok yang sama diulang 3x di dalam submit().
     */
    private function cleanupUploadedFiles(array $uploadedFiles): void
    {
        foreach ($uploadedFiles as $path) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Bangun pesan error final submit (step 8) yang tetap membantu untuk
     * debugging, tanpa membocorkan detail internal (nama kolom/tabel DB,
     * struktur query) ke pelamar di lingkungan production.
     *
     * - APP_DEBUG=true (local/staging)  → tampilkan pesan exception asli
     *   apa adanya, supaya langsung kelihatan di layar tanpa buka log.
     * - APP_DEBUG=false (production)    → tampilkan pesan generik + kode
     *   referensi singkat yang bisa dicari di log (grep ref di storage
     *   logs), tanpa expose detail DB ke user.
     *
     * Di kedua kondisi, detail LENGKAP (message + trace + context) selalu
     * ditulis ke log lewat Log::error(), jadi tim tetap bisa telusuri
     * walau APP_DEBUG mati.
     */
    private function buildSubmitErrorMessage(\Throwable $e, string $context, string $genericMessage): string
    {
        $ref = strtoupper(Str::random(8));

        Log::error("[RecruitmentForm] {$context} (ref: {$ref})", [
            'ref'       => $ref,
            'message'   => $e->getMessage(),
            'exception' => get_class($e),
            'file'      => $e->getFile() . ':' . $e->getLine(),
            'trace'     => $e->getTraceAsString(),
        ]);

        if (config('app.debug')) {
            return "{$genericMessage} [DEBUG] {$context}: {$e->getMessage()} (ref: {$ref})";
        }

        return "{$genericMessage} Jika masalah berlanjut, sampaikan kode berikut ke tim IT: {$ref}.";
    }

    /*
    |--------------------------------------------------------------------------
    | Validation rules per step — TIDAK DIUBAH dari versi asli
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
                'tanggal_lahir'     => ['required', 'date', 'before_or_equal:' . now()->subYears(18)->toDateString()],
                'warga_negara'      => ['nullable', 'in:WNI,WNA'],
                'golongan_darah'    => ['nullable', 'in:A,B,AB,O'],
                'jenis_kelamin'     => ['required', 'in:L,P'],
                'status_pernikahan' => ['nullable'],
                'kb'                => ['nullable', 'in:Ya,Tidak'],
                'tanggungan'        => ['nullable', 'integer', 'min:0', 'max:20'],
                'nomor_hp'          => ['required', 'string', 'max:20'],
                'agama'             => ['nullable', 'in:Islam,Kristen,Katolik,Hindu,Buddha,Khonghucu'],
                'hobby'             => ['nullable', 'string', 'max:255'],
                'transportasi'      => ['nullable', 'string', 'max:100'],
                'pendidikan'        => ['nullable', 'in:SD,SMP,SMA/SMK,D3,S1,S2,S3'],
                'jabatan'           => ['required', 'string', 'max:100'],
                'department'        => ['required', 'string', 'max:100'],
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
                'scan_kk'               => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
                'pas_foto'              => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
                'ijazah'                => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
                'scan_akta_kelahiran'   => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
                'scan_skck'             => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
                'scan_blanko_kesehatan' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
                'deklarasi'             => ['required', 'accepted'],
            ],

            default => [],
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Custom validation messages — TIDAK DIUBAH dari versi asli
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
                'nik.digits'                    => 'NIK harus tepat 16 digit.',
                'no_kk.digits'                  => 'Nomor KK harus tepat 16 digit.',
                'tanggal_lahir.required'        => 'Tanggal lahir wajib diisi.',
                'tanggal_lahir.before_or_equal' => 'Pendaftar harus berusia minimal 18 tahun.',
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
                '*.mimes'                => ':attribute harus berformat PDF, JPG, atau PNG. Jika Anda yakin formatnya ' .
                    'benar, kemungkinan file rusak/kosong karena diambil dari Google Drive ' .
                    'atau layanan cloud yang belum sepenuhnya tersinkron — coba salin file ' .
                    'ke penyimpanan lokal lalu upload ulang.',
                'deklarasi.required'     => 'Anda harus menyetujui pernyataan deklarasi.',
                'deklarasi.accepted'     => 'Anda harus mencentang pernyataan deklarasi.',
            ],
            default => [],
        };

        return array_merge($common, $perStep);
    }
}
