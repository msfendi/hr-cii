<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\RecruitmentPosition;
use App\Services\RecruitmentAi\ApplicantRegistrationService;
use App\Services\RecruitmentAi\DocumentVisionExtractor;
use App\Services\RecruitmentAi\RecruitmentAiDraftService;
use App\Services\RecruitmentAi\RecruitmentAiFieldRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Form Recruitment versi AI.
 *
 * Alur:
 *  1. Pelamar mengunggah dokumen yang dibutuhkan (KTP, KK, ijazah, dst).
 *  2. Dokumen diekstrak oleh model AI ber-vision (Gemini) → data terisi otomatis.
 *  3. Pelamar cukup memeriksa (cross-check) data yang terisi, lalu mengirim.
 *
 * Sengaja memakai file sendiri (controller + blade + service terpisah) supaya
 * alur rekrutmen yang sudah ada tidak tersentuh sama sekali.
 */
class RecruitmentAiController extends Controller
{
    /** Batas maksimal pengajuan lamaran per NIK (mengikuti alur lama) */
    private const MAX_LAMARAN_PER_NIK = 2;

    /** Token folder upload per sesi */
    private const TOKEN_KEY = 'reg_ai_token';

    /* ---------------------------------------------------------------------
     | GET — halaman form
     |---------------------------------------------------------------------*/
    public function show(RecruitmentAiDraftService $draft)
    {
        $this->resetExpiredDraft($draft);

        // Sinkronkan data keluarga setiap kali form dibuka. Ini juga
        // membersihkan draft lama yang masih menyimpan data anak/saudara
        // dari versi resolver sebelumnya.
        $draft->resolveKkFamily();

        $sections = RecruitmentAiFieldRegistry::sections();
        $fields   = RecruitmentAiFieldRegistry::fields();

        // Department & posisi mengikuti data master posisi rekrutmen yang aktif.
        $positions = RecruitmentPosition::query()
            ->where('is_aktif', 'true')
            ->select('dept', 'position')
            ->distinct()
            ->orderBy('dept')
            ->get()
            ->groupBy('dept');

        $positionMap = $positions->map(
            fn ($rows) => $rows->pluck('position')->unique()->sort()->values()->all()
        );

        $sections['pribadi']['fields']['department']['options'] = $positionMap->keys()->all();
        $sections['pribadi']['fields']['jabatan']['options'] =
            $positionMap->flatten(1)->unique()->sort()->values()->all();

        // Section mana saja yang punya error validasi → dibuka otomatis
        // supaya pesan error langsung terlihat tanpa harus mencari.
        $errorSections = [];
        if ($errors = session('errors')) {
            $errorKeys = array_keys($errors->getBag('default')->getMessages());

            foreach ($sections as $sectionKey => $section) {
                foreach (array_keys($section['fields']) as $fieldKey) {
                    $hit = false;

                    foreach ($errorKeys as $errorKey) {
                        if ($errorKey === $fieldKey || str_starts_with($errorKey, $fieldKey . '.')) {
                            $hit = true;
                            break;
                        }
                    }

                    if ($hit) {
                        $errorSections[] = $sectionKey;
                        break;
                    }
                }
            }
        }

        return view('recruitment_ai.form', [
            'documents'      => RecruitmentAiFieldRegistry::documents(),
            'sections'       => $sections,
            'fields'         => $fields,
            'positionMap'    => $positionMap,
            'fieldIcons'     => RecruitmentAiFieldRegistry::fieldIcons(),
            'draft'          => $draft->draft(),
            'draftFiles'     => $draft->files(),
            'draftMeta'      => $draft->allMeta(),
            'extracted'      => $draft->extractedDocs(),
            'lowConf'        => (float) config('recruitment_ai.low_confidence', 0.75),
            'errorSections'  => $errorSections,
        ]);
    }

    /* ---------------------------------------------------------------------
     | POST — unggah satu dokumen
     |---------------------------------------------------------------------*/
    public function upload(Request $request, RecruitmentAiDraftService $draft)
    {
        @set_time_limit(300);

        $documents = RecruitmentAiFieldRegistry::documents();

        $request->validate([
            'document' => 'required|string|in:' . implode(',', array_keys($documents)),
            'file'     => 'required|file|mimes:pdf,jpg,jpeg,png|max:' . (int) config('recruitment_ai.max_upload_kb', 5120),
        ], [
            'file.mimes' => 'Format berkas harus PDF, JPG, atau PNG.',
            'file.max'   => 'Ukuran berkas maksimal 5MB.',
        ]);

        $document = $request->input('document');
        $file     = $request->file('file');

        // Berkas 0 byte / placeholder cloud-drive (Google Drive, OneDrive, dst)
        if (! $file->isValid() || ! is_readable($file->getRealPath()) || $file->getSize() === 0) {
            throw ValidationException::withMessages([
                'file' => 'Berkas gagal diproses (kosong atau tidak terbaca). Bila berkas berasal dari Google Drive/OneDrive, '
                    . 'silakan unduh dulu ke penyimpanan lokal lalu unggah ulang.',
            ]);
        }

        $disk   = config('recruitment_ai.disk', 'public');
        $ext    = $file->extension();
        $folder = config('recruitment_ai.storage_folder', 'pelamar-ai') . '/' . $this->token() . '/' . $document;
        $path   = $file->storeAs($folder, Str::random(24) . '.' . $ext, $disk);

        if ($path === false) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan berkas di server.',
            ], 500);
        }

        // Ganti berkas lama (kalau ada) agar tidak menumpuk di disk.
        $old = $draft->filePath($document);
        if ($old) {
            Storage::disk($disk)->delete($old);
        }

        $draft->putFile($document, $path, $file->getClientOriginalName());

        return response()->json([
            'success' => true,
            'message' => $documents[$document]['label'] . ' berhasil diunggah.',
            'file'    => [
                'document' => $document,
                'name'     => $file->getClientOriginalName(),
                'size'     => $file->getSize(),
            ],
        ]);
    }

    /* ---------------------------------------------------------------------
     | POST — hapus dokumen
     |---------------------------------------------------------------------*/
    public function deleteDocument(Request $request, RecruitmentAiDraftService $draft)
    {
        $request->validate([
            'document' => 'required|string',
        ]);

        $document = $request->input('document');
        $path     = $draft->filePath($document);

        if ($path) {
            Storage::disk(config('recruitment_ai.disk', 'public'))->delete($path);
        }

        $draft->removeFile($document);

        return response()->json(['success' => true]);
    }

    /* ---------------------------------------------------------------------
     | POST — ekstraksi AI
     |---------------------------------------------------------------------*/
    public function extract(Request $request, DocumentVisionExtractor $extractor, RecruitmentAiDraftService $draft)
    {
        @set_time_limit(600);

        $documents = RecruitmentAiFieldRegistry::documents();

        // Parameter 'document' WAJIB ada. Kalau body JSON tidak terbaca (mis.
        // karena format rusak), jangan diam-diam memproses semua dokumen —
        // itu boros panggilan AI. Gunakan 'all' bila memang mau semua.
        $target = $request->input('document');

        if (empty($target)) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter "document" wajib diisi (atau "all" untuk semua dokumen).',
            ], 400);
        }

        if ($target !== 'all' && ! isset($documents[$target])) {
            return response()->json(['success' => false, 'message' => 'Jenis dokumen tidak dikenal.'], 400);
        }

        $files = $draft->files();

        // Untuk 'all': semua dokumen yang mendukung ekstraksi & sudah diunggah.
        if ($target === 'all') {
            $targets = [];

            foreach ($documents as $key => $doc) {
                if (! $doc['extract'] || ! isset($files[$key])) {
                    continue;
                }

                $targets[] = $key;
            }
        } else {
            $targets = [$target];
        }

        if (empty($targets)) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada dokumen yang bisa diekstrak. Unggah dokumen terlebih dahulu.',
            ], 400);
        }

        $results = [];
        $errors  = [];

        foreach ($targets as $key) {
            $path = $files[$key]['path'] ?? null;

            if (! $path) {
                continue;
            }

            $absolute = Storage::disk(config('recruitment_ai.disk', 'public'))->path($path);

            if (! is_file($absolute)) {
                $errors[$key] = 'Berkas tidak ditemukan di server.';

                continue;
            }

            try {
                $extracted = $extractor->extract($key, $absolute, $files[$key]['name'] ?? '');

                // CV sering hanya mengembalikan pendidikan terakhir. Ijazah
                // kadang hanya mengisi field "pendidikan" tanpa baris riwayat.
                // Bila hasil awal kurang lengkap, minta sekali lagi khusus
                // riwayat pendidikan (output lebih kecil dan fokus).
                $educationCount = count($extracted['fields']['education']['value'] ?? []);
                $needsEducationRetry = ($key === 'cv' && $educationCount < 2)
                    || ($key === 'ijazah' && $educationCount === 0);

                if (config('recruitment_ai.education_retry', true) && $needsEducationRetry) {
                    try {
                        $education = $extractor->extract($key, $absolute, $files[$key]['name'] ?? '', [
                            'education' => RecruitmentAiFieldRegistry::fields()['education'],
                        ]);

                        $rows = $education['fields']['education']['value'] ?? [];

                        if (count($rows) > count($extracted['fields']['education']['value'] ?? [])) {
                            $extracted['fields']['education'] = $education['fields']['education'];
                        }
                    } catch (\Throwable $e) {
                        // Jangan gagalkan seluruh ekstraksi CV hanya karena
                        // panggilan tambahan ini bermasalah.
                        Log::warning('[RecruitmentAi] Ekstraksi pendidikan tambahan gagal: ' . $e->getMessage());
                    }
                }

                $merged    = $draft->mergeExtraction($key, $extracted);

                // KK + KTP saling melengkapi: siapa pun yang selesai lebih dulu,
                // data keluarga tetap dihitung ulang setelah keduanya ada.
                $draft->resolveKkFamily();

                $hidden = array_keys(RecruitmentAiFieldRegistry::extractionOnly($key));

                $results[$key] = [
                    'count'     => count(array_diff_key($extracted['fields'], array_flip($hidden))),
                    'added'     => $merged['added'],
                    'conflicts' => $merged['conflicts'],
                    'note'      => $extracted['note'],
                ];
            } catch (\Throwable $e) {
                Log::error('[RecruitmentAi] Ekstraksi ' . $key . ' gagal: ' . $e->getMessage());

                $errors[$key] = $e->getMessage();
            }
        }

        $current = $draft->draft();

        return response()->json([
            'success' => empty($errors),
            'results' => $results,
            'errors'  => $errors,
            'draft'   => [
                'fields'  => $current['fields'] ?? [],
                'meta'    => $draft->allMeta(),
                'cleared' => $current['cleared'] ?? [],
            ],
            'message' => empty($errors)
                ? 'Ekstraksi selesai.'
                : 'Sebagian dokumen gagal diekstrak.',
        ]);
    }

    /* ---------------------------------------------------------------------
     | POST — reset semua field
     |---------------------------------------------------------------------*/
    public function resetFields(RecruitmentAiDraftService $draft)
    {
        $draft->resetFields();

        return response()->json([
            'success' => true,
            'message' => 'Semua isian formulir berhasil dikosongkan.',
        ]);
    }

    /* ---------------------------------------------------------------------
     | POST — submit pengajuan
     |---------------------------------------------------------------------*/
    public function submit(
        Request $request,
        RecruitmentAiDraftService $draft,
        ApplicantRegistrationService $registration
    ) {
        @set_time_limit(300);

        $this->normalizePhoneInput($request);
        $this->normalizeEducationInput($request);

        $validated = $request->validate($this->rules(), $this->messages(), $this->attributes());

        // ── Semua dokumen wajib sudah terunggah ─────────────────────────────
        $files = $draft->files();

        foreach (RecruitmentAiFieldRegistry::documents() as $key => $doc) {
            if ($doc['required'] && empty($files[$key])) {
                throw ValidationException::withMessages([
                    'documents' => 'Dokumen "' . $doc['label'] . '" belum diunggah.',
                ]);
            }
        }

        // ── Batas jumlah lamaran per NIK ────────────────────────────────────
        $nik = $validated['nik'] ?? null;

        if ($nik) {
            $jumlah = DB::connection('cii')->table('PELAMAR')->where('NIK', $nik)->count();

            if ($jumlah >= self::MAX_LAMARAN_PER_NIK) {
                return redirect()->route('recruitment-ai.form')
                    ->with('error', 'NIK ini sudah tercatat mengajukan lamaran sebanyak ' . self::MAX_LAMARAN_PER_NIK . ' kali.');
            }
        }

        // ── Simpan ───────────────────────────────────────────────────────────
        try {
            $result = $registration->register($validated, $files);
        } catch (\Throwable $e) {
            Log::error('[RecruitmentAi] Submit gagal: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->with('error', config('app.debug')
                    ? 'Pendaftaran gagal: ' . $e->getMessage()
                    : 'Pendaftaran gagal diproses. Silakan coba lagi beberapa saat lagi.');
        }

        $draft->clear();
        Session::forget(self::TOKEN_KEY);

        Session::put('application_ref', 'RF-' . now()->format('Ymd-His'));
        Session::put('applicant_name', $validated['nama_lengkap'] ?? 'Pelamar');

        return redirect()->route('recruitment-ai.success')
            ->with('success', 'Pendaftaran berhasil dikirim!');
    }

    /* ---------------------------------------------------------------------
     | GET — halaman sukses
     |---------------------------------------------------------------------*/
    public function success()
    {
        return view('recruitment_ai.success', [
            'reference' => session('application_ref'),
            'name'      => session('applicant_name'),
        ]);
    }

    /* ---------------------------------------------------------------------
     | Rules & pesan validasi
     |---------------------------------------------------------------------*/
    /**
     * Rapikan nomor telepon: buang tanda hubung/spasi dan ubah awalan +62
     * menjadi 0. Dipanggil sebelum validasi submit.
     */
    private function normalizePhoneInput(Request $request): void
    {
        $merge = [];

        foreach (['nomor_hp', 'no_telepon', 'no_telepon_darurat'] as $key) {
            $value = $request->input($key);

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $digits = preg_replace('/\D+/', '', $value);

            if ($digits === '') {
                continue;
            }

            if (str_starts_with($digits, '62')) {
                $digits = '0' . substr($digits, 2);
            } elseif (str_starts_with($digits, '8')) {
                $digits = '0' . $digits;
            }

            $merge[$key] = $digits;
        }

        if ($merge) {
            $request->merge($merge);
        }
    }

    /**
     * Normalisasi nilai pendidikan sebelum validasi agar sekolah bebas (mis.
     * "SMK NEGERI 1 SURAKARTA") otomatis dipetakan ke opsi valid (mis. "SMA/SMK").
     */
    private function normalizeEducationInput(Request $request): void
    {
        $mapLevel = function (?string $val): ?string {
            if ($val === null) {
                return null;
            }
            $v = strtoupper(trim($val));
            if ($v === '') {
                return null;
            }
            if (str_contains($v, 'SLTA') || str_contains($v, 'SMU') || str_contains($v, 'SMA') || str_contains($v, 'SMK')) {
                return 'SMA/SMK';
            }
            if (str_contains($v, 'SLTP') || str_contains($v, 'SMP')) {
                return 'SMP';
            }
            if (preg_match('/\bSD\b/', $v)) {
                return 'SD';
            }
            if (str_contains($v, 'S1') || str_contains($v, 'SARJANA') || str_contains($v, 'D4')) {
                return 'S1';
            }
            if (str_contains($v, 'D3') || str_contains($v, 'DIPLOMA')) {
                return 'D3';
            }
            if (str_contains($v, 'S2') || str_contains($v, 'MAGISTER')) {
                return 'S2';
            }
            if (str_contains($v, 'S3') || str_contains($v, 'DOKTOR')) {
                return 'S3';
            }

            return $val;
        };

        $merge = [];

        // Ayah & Ibu
        if ($request->has('ayah.pendidikan')) {
            $ayah = $request->input('ayah', []);
            if (is_array($ayah) && isset($ayah['pendidikan'])) {
                $ayah['pendidikan'] = $mapLevel($ayah['pendidikan']);
                $merge['ayah'] = $ayah;
            }
        }
        if ($request->has('ibu.pendidikan')) {
            $ibu = $request->input('ibu', []);
            if (is_array($ibu) && isset($ibu['pendidikan'])) {
                $ibu['pendidikan'] = $mapLevel($ibu['pendidikan']);
                $merge['ibu'] = $ibu;
            }
        }

        // Saudara
        if ($request->has('saudara')) {
            $saudara = $request->input('saudara', []);
            if (is_array($saudara)) {
                foreach ($saudara as $idx => $s) {
                    if (is_array($s) && isset($s['pendidikan'])) {
                        $saudara[$idx]['pendidikan'] = $mapLevel($s['pendidikan']);
                    }
                }
                $merge['saudara'] = $saudara;
            }
        }

        // Anak
        if ($request->has('anak')) {
            $anak = $request->input('anak', []);
            if (is_array($anak)) {
                foreach ($anak as $idx => $a) {
                    if (is_array($a) && isset($a['pendidikan'])) {
                        $anak[$idx]['pendidikan'] = $mapLevel($a['pendidikan']);
                    }
                }
                $merge['anak'] = $anak;
            }
        }

        if ($merge) {
            $request->merge($merge);
        }
    }

    /**
     * Rules validasi.
     *
     * Disamakan dengan RecruitmentFormController (form rekrutmen lama) supaya
     * behave-nya identik: field wajib, format, dan batasannya sama.
     */
    private function rules(): array
    {
        $r = RecruitmentAiFieldRegistry::PENDIDIKAN;
        $p = RecruitmentAiFieldRegistry::PENDIDIKAN_AYAH;

        return [
            // ── Data pribadi (lama: step 1) ──
            'nama_lengkap'      => ['required', 'string', 'max:255'],
            'nik'               => ['required', 'digits:16'],
            'no_kk'             => ['required', 'digits:16'],
            'sim'               => ['nullable', 'string', 'max:30'],
            'tempat_lahir'      => ['required', 'string', 'max:100'],
            'tanggal_lahir'     => ['required', 'date', 'before_or_equal:' . now()->subYears(18)->toDateString()],
            'warga_negara'      => ['nullable', 'in:WNI,WNA'],
            'golongan_darah'    => ['nullable', 'in:A,B,AB,O'],
            'jenis_kelamin'     => ['required', 'in:L,P'],
            'status_pernikahan' => ['required', 'in:BM,M,CH,CM'],
            'kb'                => ['nullable', 'in:Ya,Tidak'],
            'tanggungan'        => ['nullable', 'integer', 'min:0', 'max:20'],
            'nomor_hp'          => ['required', 'string', 'max:20'],
            'agama'             => ['nullable', 'in:' . implode(',', RecruitmentAiFieldRegistry::AGAMA)],
            'hobby'             => ['nullable', 'string', 'max:255'],
            'transportasi'      => ['nullable', 'in:' . implode(',', RecruitmentAiFieldRegistry::TRANSPORTASI)],
            'pendidikan'        => ['required', 'in:' . implode(',', $r)],
            // Department & posisi berasal dari menu career; boleh kosong di form ini.
            'jabatan'           => ['nullable', 'string', 'max:100'],
            'department'        => ['nullable', 'string', 'max:100'],
            'bpjs_tk'           => ['nullable', 'string', 'max:30'],
            'bpjs_kes'          => ['nullable', 'string', 'max:30'],

            // ── Alamat & kontak (lama: step 2) ──
            'alamat_asal'              => ['required', 'string', 'max:500'],
            'kab_kota_asal'            => ['required', 'string', 'max:100'],
            'status_domisili_asal'     => ['nullable', 'in:' . implode(',', RecruitmentAiFieldRegistry::STATUS_DOMISILI)],
            'alamat_sekarang'          => ['nullable', 'string', 'max:500'],
            'kab_kota_sekarang'        => ['nullable', 'string', 'max:100'],
            'status_domisili_sekarang' => ['nullable', 'in:' . implode(',', RecruitmentAiFieldRegistry::STATUS_DOMISILI)],
            'same_as_asal'             => ['nullable', 'boolean'],
            'no_telepon'               => ['nullable', 'string', 'max:20'],
            'nama_darurat'             => ['required', 'string', 'max:100'],
            'hubungan_darurat'         => ['required', 'string', 'max:100'],
            'no_telepon_darurat'       => ['required', 'string', 'max:20'],

            // ── Pengalaman kerja (lama: step 3) ──
            'experiences'                 => ['nullable', 'array'],
            'experiences.*.perusahaan'    => ['required_with:experiences.*', 'string', 'max:255'],
            'experiences.*.dari'          => ['nullable', 'date_format:Y-m'],
            'experiences.*.sampai'        => ['nullable', 'date_format:Y-m', 'after_or_equal:experiences.*.dari'],
            'experiences.*.masih_bekerja' => ['nullable', 'boolean'],
            'experiences.*.jabatan'       => ['nullable', 'string', 'max:100'],
            'experiences.*.departemen'    => ['nullable', 'string', 'max:100'],
            'experiences.*.alasan'        => ['nullable', 'string', 'max:500'],

            // ── Data keluarga (lama: step 4) ──
            'ayah.nama'            => ['nullable', 'string', 'max:255'],
            'ayah.tgl_lahir'       => ['nullable', 'date'],
            'ayah.pendidikan'      => ['nullable', 'in:' . implode(',', $p)],
            'ayah.pekerjaan'       => ['nullable', 'string', 'max:100'],
            'ibu.nama'             => ['nullable', 'string', 'max:255'],
            'ibu.tgl_lahir'        => ['nullable', 'date'],
            'ibu.pendidikan'       => ['nullable', 'in:' . implode(',', $p)],
            'ibu.pekerjaan'        => ['nullable', 'string', 'max:100'],
            'saudara'              => ['nullable', 'array'],
            'saudara.*.nama'       => ['required_with:saudara.*', 'string', 'max:255'],
            'saudara.*.tgl_lahir'  => ['nullable', 'date'],
            'saudara.*.gender'     => ['nullable', 'in:Laki-laki,Perempuan'],
            'saudara.*.pendidikan' => ['nullable', 'in:' . implode(',', $p)],
            'saudara.*.pekerjaan'  => ['nullable', 'string', 'max:100'],
            'anak'                 => ['nullable', 'array'],
            'anak.*.nama'          => ['required_with:anak.*', 'string', 'max:255'],
            'anak.*.tempat_lahir'  => ['nullable', 'string', 'max:100'],
            'anak.*.tgl_lahir'     => ['nullable', 'date'],
            'anak.*.gender'        => ['nullable', 'in:Laki-laki,Perempuan'],
            'anak.*.pendidikan'    => ['nullable', 'string', 'max:255'],
            'anak.*.status'        => ['nullable', 'string', 'max:100'],

            // ── Riwayat pendidikan (lama: step 5) ──
            'education'             => ['nullable', 'array'],
            'education.*.tingkat'   => ['required_with:education.*', 'in:' . implode(',', $r)],
            'education.*.institusi' => ['required_with:education.*', 'string', 'max:255'],
            'education.*.jurusan'   => ['nullable', 'string', 'max:100'],
            'education.*.dari'      => ['nullable', 'integer', 'min:1950', 'max:' . (date('Y') + 6)],
            'education.*.sampai'    => ['nullable', 'integer', 'min:1950', 'max:' . (date('Y') + 6), $this->afterOrEqualRule('education.*.dari', 'Tahun selesai harus sama atau setelah tahun mulai.')],
            'education.*.lulus'     => ['nullable', 'boolean'],

            // ── Motivasi & kegiatan (lama: step 6) ──
            'motivasi'       => ['nullable', 'string', 'min:50', 'max:1000'],
            'kegiatan_ekstra' => ['nullable', 'string', 'max:800'],

            // ── Data fisik (lama: step 7) ──
            'tinggi_badan' => ['nullable', 'integer', 'min:100', 'max:250'],
            'berat_badan'  => ['nullable', 'integer', 'min:20', 'max:200'],

            // ── Dokumen (lama: step 8) ──
            'deklarasi' => ['required', 'accepted'],
        ];
    }

    /**
     * Rule "tahun selesai >= tahun mulai" untuk field berulang.
     *
     * Kenapa tidak memakai `gte:education.*.dari` seperti form lama? Karena
     * pada array, Laravel membandingkan tiap baris dengan SEMUA baris lain,
     * sehingga baris yang benar pun ikut gagal. Closure ini hanya membandingkan
     * baris yang sama (index yang sama).
     */
    private function afterOrEqualRule(string $otherField, string $message): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) use ($otherField, $message) {
            if ($value === null || $value === '') {
                return;
            }

            $other = $otherField;

            // education.0.sampai + education.*.dari  ->  education.0.dari
            if (str_contains($otherField, '*')) {
                $attributeParts = explode('.', $attribute);
                $wildcardIndex  = substr_count(explode('*', $otherField)[0], '.');

                if (isset($attributeParts[$wildcardIndex])) {
                    $other = str_replace('*', $attributeParts[$wildcardIndex], $otherField);
                }
            }

            $otherValue = data_get(request()->all(), $other);

            if ($otherValue === null || $otherValue === '' || ! is_numeric($otherValue) || ! is_numeric($value)) {
                return;
            }

            if ((int) $value < (int) $otherValue) {
                $fail($message);
            }
        };
    }

    /**
     * Pesan error.
     *
     * Diambil dari RecruitmentFormController (form rekrutmen lama) lalu
     * dilengkapi nama atribut yang enak dibaca.
     */
    private function messages(): array
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

        // Pesan spesifik per field (meniru form rekrutmen lama)
        $specific = [
            // Step 1 — Data pribadi
            'nik.digits'                   => 'NIK harus tepat 16 digit.',
            'no_kk.digits'                 => 'Nomor KK harus tepat 16 digit.',
            'tanggal_lahir.required'       => 'Tanggal lahir wajib diisi.',
            'tanggal_lahir.before_or_equal' => 'Pendaftar harus berusia minimal 18 tahun.',

            // Step 2 — Alamat & kontak
            'nama_darurat.required'       => 'Nama kontak darurat wajib diisi.',
            'no_telepon_darurat.required' => 'Nomor telepon darurat wajib diisi.',

            // Step 3 — Pengalaman kerja
            'experiences.*.perusahaan.required_with' => 'Nama perusahaan wajib diisi.',
            'experiences.*.sampai.after_or_equal'    => 'Tanggal selesai harus setelah tanggal mulai.',
            'experiences.*.dari.date_format'         => 'Format masa kerja dari harus YYYY-MM.',
            'experiences.*.sampai.date_format'       => 'Format masa kerja sampai harus YYYY-MM.',

            // Step 4 — Data keluarga
            'saudara.*.nama.required_with' => 'Nama saudara wajib diisi.',
            'anak.*.nama.required_with'    => 'Nama anak wajib diisi.',

            // Step 5 — Riwayat pendidikan
            'education.*.tingkat.required_with'   => 'Tingkat pendidikan wajib dipilih.',
            'education.*.institusi.required_with' => 'Nama institusi wajib diisi.',
            'education.*.sampai.gte'              => 'Tahun selesai harus sama atau setelah tahun mulai.',

            // Step 6 — Motivasi & kegiatan
            'motivasi.required'        => 'Motivasi melamar wajib diisi.',
            'motivated.min'            => 'Motivasi minimal 50 karakter.',
            'motivasi.min'             => 'Motivasi minimal 50 karakter.',
            'kegiatan_ekstra.required' => 'Kegiatan ekstra wajib diisi.',

            // Step 7 — Data fisik
            'tinggi_badan.required' => 'Tinggi badan wajib diisi.',
            'tinggi_badan.min'      => 'Tinggi badan minimal 100 cm.',
            'tinggi_badan.max'      => 'Tinggi badan maksimal 250 cm.',
            'berat_badan.required'  => 'Berat badan wajib diisi.',
            'berat_badan.min'       => 'Berat badan minimal 20 kg.',
            'berat_badan.max'       => 'Berat badan maksimal 200 kg.',

            // Step 8 — Dokumen
            'deklarasi.required' => 'Anda harus menyetujui pernyataan deklarasi.',
            'deklarasi.accepted' => 'Anda harus menyetujui pernyataan deklarasi.',
            'documents'           => 'Semua dokumen wajib sudah diunggah.',
        ];

        return array_merge($common, $specific, $this->attributes());
    }

    /**
     * Nama field yang enak dibaca pada pesan error.
     * (Tanpa ini Laravel hanya mengubah underscore jadi spasi, mis. "jenis kelamin".)
     */
    private function attributes(): array
    {
        // Nama field yang enak dibaca (Laravel hanya mengubah underscore jadi spasi).
        return [
            'nama_lengkap'           => 'Nama lengkap',
            'nik'                    => 'NIK',
            'no_kk'                  => 'Nomor Kartu Keluarga',
            'sim'                    => 'Nomor SIM',
            'tempat_lahir'           => 'Tempat lahir',
            'tanggal_lahir'          => 'Tanggal lahir',
            'jenis_kelamin'          => 'Jenis kelamin',
            'golongan_darah'         => 'Golongan darah',
            'agama'                  => 'Agama',
            'status_pernikahan'      => 'Status perkawinan',
            'kb'                     => 'Mengikuti KB',
            'tanggungan'             => 'Jumlah tanggungan',
            'warga_negara'           => 'Kewarganegaraan',
            'nomor_hp'               => 'Nomor HP',
            'hobby'                  => 'Bakat/hobby',
            'transportasi'           => 'Mode transportasi',
            'pendidikan'             => 'Pendidikan terakhir',
            'jabatan'                => 'Posisi yang dilamar',
            'department'             => 'Department',
            'bpjs_tk'                => 'No. BPJS Ketenagakerjaan',
            'bpjs_kes'               => 'No. BPJS Kesehatan',
            'alamat_asal'            => 'Alamat asal',
            'kab_kota_asal'          => 'Kabupaten/Kota asal',
            'status_domisili_asal'   => 'Status domisili asal',
            'alamat_sekarang'        => 'Alamat sekarang',
            'kab_kota_sekarang'      => 'Kabupaten/Kota sekarang',
            'status_domisili_sekarang' => 'Status domisili sekarang',
            'no_telepon'             => 'No. telepon',
            'nama_darurat'           => 'Nama kontak darurat',
            'hubungan_darurat'       => 'Hubungan dengan pelamar',
            'no_telepon_darurat'     => 'No. telepon darurat',
            'motivasi'               => 'Motivasi melamar',
            'kegiatan_ekstra'        => 'Kegiatan ekstra/prestasi',
            'tinggi_badan'           => 'Tinggi badan',
            'berat_badan'            => 'Berat badan',
            'education'              => 'Riwayat pendidikan',
            'experiences'            => 'Pengalaman kerja',
            'saudara'                => 'Saudara kandung',
            'anak'                   => 'Data anak',
            'ayah'                   => 'Data ayah',
            'ayah.nama'              => 'Nama ayah',
            'ayah.tgl_lahir'         => 'Tanggal lahir ayah',
            'ayah.pendidikan'        => 'Pendidikan ayah',
            'ayah.pekerjaan'         => 'Pekerjaan ayah',
            'ibu'                    => 'Data ibu',
            'ibu.nama'               => 'Nama ibu',
            'ibu.tgl_lahir'          => 'Tanggal lahir ibu',
            'ibu.pendidikan'         => 'Pendidikan ibu',
            'ibu.pekerjaan'          => 'Pekerjaan ibu',
            'saudara.*.tgl_lahir'    => 'Tanggal lahir saudara',
            'saudara.*.gender'       => 'Jenis kelamin saudara',
            'saudara.*.pendidikan'   => 'Pendidikan saudara',
            'saudara.*.pekerjaan'    => 'Pekerjaan saudara',
            'anak.*.tempat_lahir'    => 'Tempat lahir anak',
            'anak.*.tgl_lahir'       => 'Tanggal lahir anak',
            'anak.*.gender'          => 'Jenis kelamin anak',
            'anak.*.pendidikan'      => 'Pendidikan anak',
            'anak.*.status'          => 'Status anak',
        ];
    }

    /* ---------------------------------------------------------------------
     | Helper
     |---------------------------------------------------------------------*/

    /**
     * Token folder upload (stabil per sesi).
     */
    private function token(): string
    {
        $token = Session::get(self::TOKEN_KEY);

        if (! $token) {
            $token = Str::random(16);
            Session::put(self::TOKEN_KEY, $token);
        }

        return $token;
    }

    /**
     * Hapus berkas dokumen yang sudah tidak ada di draft (mis. session kadaluarsa
     * tapi berkasnya masih ada di disk).
     */
    private function resetExpiredDraft(RecruitmentAiDraftService $draft): void
    {
        $token = Session::get(self::TOKEN_KEY);

        if (! $token) {
            return;
        }

        $folder = config('recruitment_ai.storage_folder', 'pelamar-ai') . '/' . $token;

        if (! Storage::disk(config('recruitment_ai.disk', 'public'))->exists($folder)) {
            return;
        }

        // Berkas yang tidak lagi terdaftar di draft → dibuang agar tidak menumpuk.
        $registered = collect($draft->files())->pluck('path')->all();

        foreach (Storage::disk(config('recruitment_ai.disk', 'public'))->allFiles($folder) as $file) {
            if (! in_array($file, $registered, true)) {
                Storage::disk(config('recruitment_ai.disk', 'public'))->delete($file);
            }
        }
    }
}
