<?php

namespace App\Services\RecruitmentAi;

use Illuminate\Support\Facades\Session;

/**
 * Penyimpanan draft Form Recruitment (versi AI).
 *
 * Draft disimpan di SESSION — tidak ada tabel baru dan tidak ada query ke
 * database. Isinya:
 *   - files   : dokumen yang sudah diunggah (field => path di disk)
 *   - fields  : nilai hasil ekstraksi AI (sudah digabung antar dokumen)
 *   - meta    : asal dokumen + confidence + penanda konflik per field
 *
 * Whenua beberapa dokumen mengisi field yang sama dengan nilai berbeda,
 * nilainya yang confidence-nya lebih tinggi yang dipakai, sementara nilai
 * lainnya disimpan sebagai "alternatif" supaya pelamar bisa memilih sendiri.
 */
class RecruitmentAiDraftService
{
    /**
     * Urutan sumber data: makin awal makin utama.
     *
     * KTP adalah sumber utama identitas pelamar — nilai dari KTP tidak boleh
     * ditimpa KK/CV/SKCK hanya karena confidence-nya lebih tinggi.
     */
    private const SOURCE_ORDER = [
        'scan_ktp',
        'scan_akta_kelahiran',
        'ijazah',
        'scan_kk',
        'scan_skck',
        'scan_blanko_kesehatan',
        'cv',
        'surat_lamaran',
    ];

    /** Field yang sumber utamanya bukan KTP. */
    private const FIELD_SOURCE_ORDER = [
        'pendidikan' => ['ijazah', 'scan_ktp', 'scan_skck', 'cv'],
        'no_kk'      => ['scan_kk', 'scan_ktp'],
    ];

    /** Field keluarga yang dihitung dari baris KK (bukan hasil AI langsung). */
    private const FAMILY_FIELDS = ['ayah', 'ibu', 'saudara', 'anak'];

    public function draft(): array
    {
        return $this->sanitizeDraft(Session::get($this->key(), [
            'files'          => [],
            'fields'         => [],
            'meta'           => [],
            'raw'            => [],
            'extracted_docs' => [],
        ]));
    }

    /**
     * @return array<string, array{path:string, name:string, uploaded_at:string}>
     */
    public function files(): array
    {
        return $this->draft()['files'] ?? [];
    }

    public function hasFile(string $field): bool
    {
        return ! empty($this->files()[$field]);
    }

    public function filePath(string $field): ?string
    {
        return $this->files()[$field]['path'] ?? null;
    }

    public function putFile(string $field, string $path, string $originalName): void
    {
        $draft = $this->draft();

        $draft['files'][$field] = [
            'path'        => $path,
            'name'        => $originalName,
            'uploaded_at' => now()->toDateTimeString(),
        ];

        $this->save($draft);
    }

    public function removeFile(string $field): void
    {
        $draft = $this->draft();

        unset($draft['files'][$field], $draft['extracted_docs'][$field]);

        // Baris KK mentah ikut dihapus supaya tidak dipakai lagi oleh resolver.
        if ($field === 'scan_kk') {
            unset($draft['raw']['kk_members']);
        }

        $this->save($draft);
    }

    /**
     * Kosongkan semua field data (hasil AI & input) dari draft session,
     * tetapi pertahankan berkas yang sudah diunggah.
     */
    public function resetFields(): void
    {
        $draft = $this->draft();
        $draft['fields']         = [];
        $draft['meta']           = [];
        $draft['raw']            = [];
        $draft['extracted_docs'] = [];
        $draft['cleared']        = [];

        $this->save($draft);
    }

    /**
     * Gabungkan hasil ekstraksi satu dokumen ke draft.
     *
     * @param  array{fields: array<string, array{value:mixed, confidence:float, note:string|null}>}  $extracted
     * @return array{added:int, updated:int, conflicts:array<int,string>, fields:array<int,string>}
     */
    public function mergeExtraction(string $document, array $extracted): array
    {
        $draft = $this->draft();

        // Penanda "list ini dikosongkan" hanya berlaku untuk respons
        // ekstraksi yang sedang berjalan.
        $draft['cleared'] = [];

        $added      = 0;
        $updated    = 0;
        $conflicts  = [];
        $touched    = [];

        $allowed = array_merge(
            array_keys(RecruitmentAiFieldRegistry::fieldsForDocument($document)),
            array_keys(RecruitmentAiFieldRegistry::extractionOnly($document))
        );

        foreach ($extracted['fields'] ?? [] as $key => $entry) {
            // Dokumen ini tidak berhak mengisi field tersebut (mis. KK tidak
            // boleh mengisi nama/jenis kelamin/status pernikahan pelamar).
            if (! in_array($key, $allowed, true)) {
                continue;
            }

            $value      = $entry['value'];
            $confidence = (float) ($entry['confidence'] ?? 0);
            $note       = $entry['note'] ?? null;

            if ($value === null || $value === '' || $value === [] || $this->isPlaceholder($value)) {
                continue;
            }

            $definition = RecruitmentAiFieldRegistry::fields()[$key] ?? null;

            // Nomor telepon yang isinya bukan nomor (mis. "-") jangan dianggap data.
            if (($definition['format'] ?? null) === 'phone'
                && strlen(preg_replace('/\D+/', '', (string) $value) ?? '') < 8) {
                continue;
            }

            // Field bantu ekstraksi (mis. baris tabel KK) tidak masuk form;
            // disimpan mentah untuk diolah KkFamilyResolver.
            if (isset(RecruitmentAiFieldRegistry::extractionOnly($document)[$key])) {
                $draft['raw'][$key] = $value;

                continue;
            }

            $isList     = ($definition['type'] ?? '') === 'list';

            if ($isList) {
                $before = count($draft['fields'][$key] ?? []);
                $rows   = $draft['fields'][$key] ?? [];
                $existingSource = $draft['meta'][$key]['source'] ?? null;

                foreach ((array) $value as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    // Riwayat pendidikan dari ijazah dan CV bisa berisi jenjang
                    // yang sama dengan ejaan institusi berbeda. Gabungkan baris
                    // yang cocok, jangan tampilkan dua baris SMK.
                    $rows = $key === 'education'
                        ? $this->mergeEducationRow($rows, $row, $document, $existingSource)
                        : $this->appendUniqueRow($rows, $row);
                }

                $draft['fields'][$key] = $rows;

                $after = count($draft['fields'][$key] ?? []);

                if ($after > $before) {
                    $added += $after - $before;
                } else {
                    $updated++;
                }

                $sources = array_values(array_unique(array_merge(
                    $draft['meta'][$key]['sources'] ?? [],
                    [$document]
                )));

                // Simpan sumber paling utama, bukan sekadar dokumen terakhir.
                // Contoh: CV lalu ijazah → sumber list tetap "ijazah" agar
                // re-ekstraksi CV tidak menimpa nama institusi dari ijazah.
                usort(
                    $sources,
                    fn ($a, $b) => $this->sourceRank($key, $b) <=> $this->sourceRank($key, $a)
                );

                $draft['meta'][$key] = [
                    'source'       => $sources[0] ?? $document,
                    'sources'      => $sources,
                    'confidence'   => max($confidence, (float) ($draft['meta'][$key]['confidence'] ?? 0)),
                    'note'         => $note ?: ($draft['meta'][$key]['note'] ?? null),
                    'conflict'     => false,
                    'alternatives' => [],
                ];

                $touched[] = $key;

                continue;
            }

            $existingValue = $draft['fields'][$key] ?? null;
            $existingMeta  = $draft['meta'][$key] ?? null;

            if ($existingValue === null) {
                $draft['fields'][$key] = $value;
                $draft['meta'][$key]   = [
                    'source'       => $document,
                    'sources'      => [$document],
                    'confidence'   => $confidence,
                    'note'         => $note,
                    'conflict'     => false,
                    'alternatives' => [],
                ];

                $added++;
                $touched[] = $key;

                continue;
            }

            if ($this->isSameValue($existingValue, $value)) {
                // Nilai sama → cukup gabungkan sumber & naikkan confidence
                $draft['meta'][$key]['sources'] = array_values(array_unique(array_merge(
                    $draft['meta'][$key]['sources'] ?? [],
                    [$document]
                )));
                $draft['meta'][$key]['confidence'] = max(
                    (float) ($draft['meta'][$key]['confidence'] ?? 0),
                    $confidence
                );

                $updated++;
                $touched[] = $key;

                continue;
            }

            // ── Nilai berbeda ──
            // Sumber yang lebih utama (KTP) menang tanpa memunculkan konflik.
            $newRank = $this->sourceRank($key, $document);
            $oldRank = $this->sourceRank($key, $existingMeta['source'] ?? null);

            if ($newRank !== $oldRank) {
                if ($newRank > $oldRank) {
                    $draft['fields'][$key] = $value;
                    $draft['meta'][$key]   = [
                        'source'       => $document,
                        'sources'      => array_values(array_unique(array_merge(
                            $existingMeta['sources'] ?? [],
                            [$document]
                        ))),
                        'confidence'   => $confidence,
                        'note'         => $note,
                        'conflict'     => false,
                        'alternatives' => [],
                    ];
                    $updated++;
                } else {
                    // Sumber lama lebih utama → nilai baru diabaikan.
                    $draft['meta'][$key]['sources'] = array_values(array_unique(array_merge(
                        $existingMeta['sources'] ?? [],
                        [$document]
                    )));
                    $updated++;
                }

                $touched[] = $key;

                continue;
            }

            // ── Sumber sama-sama kuat → konflik, simpan yang lain sebagai alternatif ──
            $existingConfidence = (float) ($existingMeta['confidence'] ?? 0);

            if ($confidence >= $existingConfidence) {
                $draft['fields'][$key] = $value;
                $draft['meta'][$key]   = [
                    'source'       => $document,
                    'sources'      => array_values(array_unique(array_merge(
                        $existingMeta['sources'] ?? [],
                        [$document]
                    ))),
                    'confidence'   => $confidence,
                    'note'         => $note,
                    'conflict'     => true,
                    'alternatives' => array_merge(
                        $existingMeta['alternatives'] ?? [],
                        [[
                            'value'      => $existingValue,
                            'source'     => $existingMeta['source'] ?? null,
                            'confidence' => $existingConfidence,
                        ]]
                    ),
                ];
            } else {
                $draft['meta'][$key]['alternatives'] = array_merge(
                    $existingMeta['alternatives'] ?? [],
                    [[
                        'value'      => $value,
                        'source'     => $document,
                        'confidence' => $confidence,
                    ]]
                );
                $draft['meta'][$key]['conflict'] = true;
            }

            $conflicts[] = $key;
            $touched[]   = $key;
        }

        $draft['extracted_docs'][$document] = [
            'at'        => now()->toDateTimeString(),
            'count'     => count($extracted['fields'] ?? []),
            'note'      => $extracted['note'] ?? null,
            'added'     => $added,
            'conflicts' => $conflicts,
        ];

        $this->save($draft);

        return [
            'added'     => $added,
            'updated'   => $updated,
            'conflicts' => $conflicts,
            'fields'    => array_values(array_unique($touched)),
        ];
    }

    /**
     * Hitung ulang data keluarga dari baris KK + identitas KTP.
     *
     * Dipanggil setelah setiap ekstraksi: kalau KK diunggah lebih dulu,
     * data baru bisa ditentukan setelah KTP selesai dibaca (dan sebaliknya).
     */
    public function resolveKkFamily(): void
    {
        $draft = $this->draft();
        $rows  = $draft['raw']['kk_members'] ?? [];

        if (empty($rows)) {
            return;
        }

        $resolved = KkFamilyResolver::resolve(
            (array) $rows,
            $draft['fields']['nik'] ?? null,
            $draft['fields']['nama_lengkap'] ?? null
        );

        if ($resolved === null) {
            return;
        }

        $meta = fn (string $note) => [
            'source'       => 'scan_kk',
            'sources'      => ['scan_kk'],
            'confidence'   => 0.85,
            'note'         => $note,
            'conflict'     => false,
            'alternatives' => [],
        ];

        foreach (['ayah', 'ibu'] as $key) {
            $person = $resolved[$key] ?? null;

            if (! is_array($person) || empty($person['nama'])) {
                continue;
            }

            $draft['fields'][$key] = $person;
            $draft['meta'][$key]   = $meta('Dicocokkan dengan baris KK');
        }

        foreach (['saudara', 'anak'] as $key) {
            $list = $resolved[$key] ?? [];

            if (! empty($list)) {
                $draft['fields'][$key] = array_values($list);
                $draft['meta'][$key]   = $meta(
                    $key === 'saudara'
                        ? 'Saudara kandung dari baris KK'
                        : 'Anak dari baris KK'
                );
            } else {
                // Pelamar bukan kepala keluarga/pasangan → tidak punya data anak
                // di KK ini (atau sebaliknya); jangan sisakan data lama.
                unset($draft['fields'][$key], $draft['meta'][$key]);

                // Beri tahu browser supaya baris lama di form ikut dihapus.
                $draft['cleared'] = array_values(array_unique(array_merge(
                    $draft['cleared'] ?? [],
                    [$key]
                )));
            }
        }

        $this->save($draft);
    }

    /**
     * Nilai hasil ekstraksi untuk satu field.
     */
    public function value(string $key)
    {
        return $this->draft()['fields'][$key] ?? null;
    }

    /**
     * Meta satu field (sumber, confidence, konflik).
     */
    public function meta(string $key): array
    {
        return $this->draft()['meta'][$key] ?? [
            'source'       => null,
            'sources'      => [],
            'confidence'   => null,
            'note'         => null,
            'conflict'     => false,
            'alternatives' => [],
        ];
    }

    /**
     * @return array<string, array> meta untuk semua field
     */
    public function allMeta(): array
    {
        return $this->draft()['meta'] ?? [];
    }

    public function extractedDocs(): array
    {
        return $this->draft()['extracted_docs'] ?? [];
    }

    /**
     * Hapus seluruh draft (dipakai setelah submit berhasil).
     */
    public function clear(): void
    {
        Session::forget($this->key());
    }

    /* ---------------------------------------------------------------------
     | Helper
     |---------------------------------------------------------------------*/

    /**
     * Bersihkan draft lama: buang nilai/alternatif dari dokumen yang tidak
     * berhak mengisi field tersebut (mis. KK pernah mengisi nama pelamar),
     * placeholder "-", dan motivasi hasil AI (sekarang manual).
     */
    private function sanitizeDraft(array $draft): array
    {
        $fields = RecruitmentAiFieldRegistry::fields();

        foreach ($draft['meta'] ?? [] as $key => $meta) {
            $definition = $fields[$key] ?? null;

            if ($definition === null) {
                unset($draft['fields'][$key], $draft['meta'][$key]);

                continue;
            }

            $allowed = $definition['ai'] ?? [];
            $source  = $meta['source'] ?? null;

            if (! in_array($key, self::FAMILY_FIELDS, true)
                && $source !== null
                && ! in_array($source, $allowed, true)) {
                unset($draft['fields'][$key], $draft['meta'][$key]);

                continue;
            }

            $current = $meta['alternatives'] ?? [];
            $clean   = array_values(array_filter($current, function ($alt) use ($allowed, $key) {
                $altSource = $alt['source'] ?? null;

                if (! in_array($key, self::FAMILY_FIELDS, true)
                    && $altSource !== null
                    && ! in_array($altSource, $allowed, true)) {
                    return false;
                }

                return ! $this->isPlaceholder($alt['value'] ?? null);
            }));

            if (count($clean) !== count($current)) {
                $draft['meta'][$key]['alternatives'] = $clean;
                $draft['meta'][$key]['conflict']     = $clean !== [];
            }

            // Jangan simpan jejak sumber yang tidak berhak (mis. KK untuk nama).
            if (! in_array($key, self::FAMILY_FIELDS, true)) {
                $sources = array_values(array_filter(
                    $meta['sources'] ?? [],
                    fn ($src) => in_array($src, $allowed, true)
                ));

                if ($sources !== ($meta['sources'] ?? [])) {
                    $draft['meta'][$key]['sources'] = $sources;
                }
            }
        }

        foreach ($draft['fields'] ?? [] as $key => $value) {
            $invalidPhone = ($fields[$key]['format'] ?? null) === 'phone'
                && strlen(preg_replace('/\D+/', '', (string) $value) ?? '') < 8;

            if ($this->isPlaceholder($value) || $invalidPhone) {
                unset($draft['fields'][$key], $draft['meta'][$key]);
            }
        }

        // Motivasi sengaja diisi manual.
        unset($draft['fields']['motivasi'], $draft['meta']['motivasi']);

        return $draft;
    }

    /**
     * Nilai placeholder (mis. "-") bukan data yang bisa dikonflikkan.
     */
    private function isPlaceholder($value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return true;
        }

        if (in_array(strtoupper($trimmed), ['-', 'N/A', 'NA', 'N.A.', 'TIDAK ADA', 'TIDAK', 'NULL'], true)) {
            return true;
        }

        return preg_match('/^[-\x{2013}\x{2014}_.\s]+$/u', $trimmed) === 1;
    }

    private function appendUniqueRow(array $rows, array $row): array
    {
        $fingerprint = md5(json_encode($row));

        foreach ($rows as $existing) {
            if (md5(json_encode($existing)) === $fingerprint) {
                return $rows;
            }
        }

        $rows[] = $row;

        return $rows;
    }

    /**
     * Gabungkan satu baris pendidikan ke daftar yang sudah ada.
     *
     * Ijazah lebih kuat untuk nama institusi/jurusan, sedangkan CV biasanya
     * memuat riwayat lengkap (SD/SMP/SMK). Baris dengan jenjang & tahun yang
     * beririsan dianggap jenjang yang sama, bukan dua entri berbeda.
     */
    private function mergeEducationRow(array $rows, array $row, string $document, ?string $existingSource): array
    {
        $index = $this->findEducationMatch($rows, $row);

        if ($index === null) {
            return $this->appendUniqueRow($rows, $row);
        }

        $existing = $rows[$index];

        // Ijazah selalu menang untuk identitas pendidikan. CV hanya menang
        // bila data lama bukan dari ijazah (mis. re-ekstraksi CV).
        $incomingWins = $document === 'ijazah' || $existingSource !== 'ijazah';
        $preferred    = $incomingWins ? $row : $existing;
        $fallback     = $incomingWins ? $existing : $row;

        $merged = $existing;

        foreach (['tingkat', 'institusi', 'jurusan', 'dari', 'sampai', 'lulus'] as $field) {
            $value = $preferred[$field] ?? null;

            if ($value === null || $value === '') {
                $value = $fallback[$field] ?? null;
            }

            if ($value !== null && $value !== '') {
                $merged[$field] = $value;
            }
        }

        $rows[$index] = $merged;

        return $rows;
    }

    /**
     * Cari baris pendidikan yang merujuk jenjang yang sama.
     */
    private function findEducationMatch(array $rows, array $row): ?int
    {
        $level       = $this->educationLevelGroup(strtoupper(trim((string) ($row['tingkat'] ?? ''))));
        $institution = $this->normalizeInstitution((string) ($row['institusi'] ?? ''));

        foreach ($rows as $index => $existing) {
            $existingLevel = $this->educationLevelGroup(strtoupper(trim((string) ($existing['tingkat'] ?? ''))));

            if ($level !== '' && $existingLevel !== '' && $level !== $existingLevel) {
                continue;
            }

            $existingInstitution = $this->normalizeInstitution((string) ($existing['institusi'] ?? ''));

            if ($institution !== '' && $existingInstitution !== '' && $institution === $existingInstitution) {
                return $index;
            }

            if ($this->educationYearsOverlap($existing, $row)) {
                return $index;
            }

            // Salah satu sisi tidak mencantumkan tahun: cocokkan hanya bila
            // nama institusi masih berbagi kata yang khas.
            $oneSideHasNoYears = empty($existing['dari']) || empty($existing['sampai'])
                || empty($row['dari']) || empty($row['sampai']);

            if ($level !== '' && $level === $existingLevel
                && $oneSideHasNoYears
                && $this->institutionSimilar($existingInstitution, $institution)) {
                return $index;
            }
        }

        return null;
    }

    private function educationYearsOverlap(array $a, array $b): bool
    {
        $aStart = (int) ($a['dari'] ?? 0);
        $aEnd   = (int) ($a['sampai'] ?? 0);
        $bStart = (int) ($b['dari'] ?? 0);
        $bEnd   = (int) ($b['sampai'] ?? 0);

        if (($aStart === 0 && $aEnd === 0) || ($bStart === 0 && $bEnd === 0)) {
            return false;
        }

        $aStart = $aStart ?: $aEnd;
        $aEnd   = $aEnd ?: $aStart;
        $bStart = $bStart ?: $bEnd;
        $bEnd   = $bEnd ?: $bStart;

        return $aStart <= $bEnd && $bStart <= $aEnd;
    }

    private function normalizeInstitution(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function educationLevelGroup(string $level): string
    {
        return in_array($level, ['SMA', 'SMK', 'SLTA', 'SMU'], true)
            ? 'SMA/SMK'
            : $level;
    }

    private function institutionSimilar(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }

        $tokens = fn (string $value) => array_values(array_filter(
            explode(' ', $value),
            fn (string $token) => strlen($token) > 2
        ));

        return count(array_intersect($tokens($a), $tokens($b))) > 0;
    }

    /**
     * Peringkat sumber untuk sebuah field (makin besar makin utama).
     */
    private function sourceRank(string $field, ?string $source): int
    {
        if ($source === null || $source === '') {
            return -1;
        }

        $order = self::FIELD_SOURCE_ORDER[$field] ?? self::SOURCE_ORDER;
        $index = array_search($source, $order, true);

        return $index === false ? -1 : count($order) - $index;
    }

    /**
     * Bandingkan dua nilai (string tolerant).
     */
    private function isSameValue($a, $b): bool
    {
        if (is_array($a) || is_array($b)) {
            return json_encode($a) === json_encode($b);
        }

        $normalize = fn ($v) => strtolower(preg_replace('/\s+/', ' ', trim((string) $v)));

        return $normalize($a) === $normalize($b);
    }

    private function key(): string
    {
        return config('recruitment_ai.session_key', 'reg_ai_draft');
    }

    private function save(array $draft): void
    {
        Session::put($this->key(), $draft);
    }
}
