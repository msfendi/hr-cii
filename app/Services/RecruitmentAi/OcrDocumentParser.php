<?php

namespace App\Services\RecruitmentAi;

/**
 * Parser deterministik untuk teks hasil OCR.
 *
 * OCR hanya membaca teks; kelas ini mengubahnya menjadi field form untuk
 * dokumen yang layout-nya relatif tetap (KTP, KK, ijazah, SKCK). Dengan begitu
 * dokumen cetak bisa diproses tanpa memanggil model AI sama sekali.
 *
 * Parser ini sengaja konservatif: kalau pola tidak yakin, field dibiarkan
 * kosong dan extractor akan jatuh ke AI (mode "first") atau ditandai perlu
 * dicek (mode "only").
 */
class OcrDocumentParser
{
    /**
     * @param  array<int, array{text:string}|string>  $lines
     * @return array{fields:array<string,array>, note:string}|null
     */
    public function parse(string $document, array $lines): ?array
    {
        $textLines = $this->textLines($lines);
        $text      = implode("\n", $textLines);

        return match ($document) {
            'scan_ktp'              => $this->parseKtp($textLines, $text),
            'scan_kk'               => $this->parseKk($textLines, $text),
            'ijazah'                => $this->parseIjazah($textLines, $text),
            'scan_skck'             => $this->parseSkck($textLines, $text),
            'scan_akta_kelahiran'   => $this->parseAktaKelahiran($textLines, $text),
            'cv', 'surat_lamaran'   => $this->parseCv($textLines, $text),
            default                 => null,
        };
    }

    /* ---------------------------------------------------------------------
     | KTP
     |---------------------------------------------------------------------*/

    private function parseKtp(array $lines, string $text): ?array
    {
        $fields = [];

        $nik = $this->findNik($lines, $text);

        if ($nik !== null) {
            $fields['nik'] = $this->entry($nik, 0.95);
        }

        $nama = $this->labelValue($lines, ['Nama Lengkap', 'Nama']);

        if ($nama === null && $nik !== null) {
            $nama = $this->lineAfterContains($lines, 'NIK', true);
        }

        if ($nama !== null && $this->isNameLike($nama)) {
            $fields['nama_lengkap'] = $this->entry($this->cleanName($nama), 0.9);
        }

        $ttl = $this->labelValue($lines, [
            'Tempat/Tgl Lahir',
            'Tempat, Tanggal Lahir',
            'Tempat/Tanggal Lahir',
        ]);

        if ($ttl !== null && preg_match('/^(.+?)[,\s.]+(\d{1,2}\s*[-\/.]\s*\d{1,2}\s*[-\/.]\s*\d{4})/u', $ttl, $m) === 1) {
            $fields['tempat_lahir'] = $this->entry($this->clean($m[1]), 0.9);
            $fields['tanggal_lahir'] = $this->entry($m[2], 0.95);
        } else {
            $tempat = $this->labelValue($lines, ['Tempat Lahir']);
            $tanggal = $this->labelValue($lines, ['Tanggal Lahir', 'Tgl Lahir']);

            if ($tempat !== null) {
                $fields['tempat_lahir'] = $this->entry($tempat, 0.85);
            }

            if ($tanggal !== null) {
                $fields['tanggal_lahir'] = $this->entry($tanggal, 0.9);
            }
        }

        $gender = $this->mapGender($this->labelValue($lines, ['Jenis Kelamin']));

        if ($gender === null && $nik !== null && strlen($nik) === 16) {
            // Digit ke-7-8 NIK: tanggal lahir + 40 untuk perempuan.
            $gender = ((int) substr($nik, 6, 2)) > 40 ? 'P' : 'L';
        }

        if ($gender !== null) {
            $fields['jenis_kelamin'] = $this->entry($gender, 0.9);
        }

        $agama = $this->labelValue($lines, ['Agama']);

        if ($agama !== null) {
            $fields['agama'] = $this->entry($agama, 0.9);
        }

        $status = $this->labelValue($lines, ['Status Perkawinan', 'Status Pernikahan']);

        if ($status !== null) {
            $fields['status_pernikahan'] = $this->entry($status, 0.9);
        }

        $pekerjaan = $this->sanitizeOccupation($this->labelValue($lines, ['Pekerjaan']));

        if ($pekerjaan !== null) {
            $fields['pekerjaan'] = $this->entry($pekerjaan, 0.8);
        }

        $wn = $this->labelValue($lines, ['Kewarganegaraan', 'Warga Negara']);

        if ($wn !== null) {
            $fields['warga_negara'] = $this->entry($wn, 0.9);
        }

        $alamat = $this->composeAddress([
            $this->labelValue($lines, ['Alamat']),
            $this->withPrefix('RT/RW', $this->labelValue($lines, ['RT/RW', 'RT / RW'])),
            $this->labelValue($lines, ['Kel/Desa', 'Kelurahan/Desa', 'Desa/Kelurahan', 'Kelurahan', 'Desa']),
            $this->labelValue($lines, ['Kecamatan']),
        ]);

        if ($alamat !== '') {
            $fields['alamat_asal'] = $this->entry($alamat, 0.85);
        }

        $kab = $this->labelValue($lines, ['Kabupaten/Kota', 'Kabupaten', 'Kota']);

        if ($kab === null) {
            foreach ($lines as $line) {
                if (preg_match('/^\s*(KABUPATEN|KOTA)\s*([A-Z .]+)$/iu', $line, $m) === 1) {
                    $candidate = $this->clean($m[2]);

                    if ($candidate !== '' && ! str_contains($candidate, '/')) {
                        $kab = $candidate;
                        break;
                    }
                }
            }
        }

        if ($kab !== null) {
            $fields['kab_kota_asal'] = $this->entry($kab, 0.85);
        }

        $provinsi = $this->labelValue($lines, ['Provinsi']);

        if ($provinsi === null) {
            foreach ($lines as $line) {
                if (preg_match('/^\s*PROVINSI\s*([A-Z .]+)$/iu', $line, $m) === 1) {
                    $provinsi = $this->clean($m[1]);
                    break;
                }
            }
        }

        // Alamat asal dilengkapi dengan kabupaten/kota dan provinsi (tanpa label prefix).
        if (isset($fields['alamat_asal']) && is_array($fields['alamat_asal'])) {
            $address = (string) ($fields['alamat_asal']['value'] ?? '');
            $extras  = [];

            if ($kab !== null && stripos($address, $kab) === false) {
                $extras[] = $kab;
            }

            if ($provinsi !== null && stripos($address, $provinsi) === false) {
                $extras[] = $provinsi;
            }

            if (! empty($extras)) {
                $fields['alamat_asal']['value'] = trim($address . ', ' . implode(', ', $extras), ', ');
            }
        }

        return [
            'fields' => $fields,
            'note'   => 'OCR KTP',
        ];
    }

    /* ---------------------------------------------------------------------
     | KK
     |---------------------------------------------------------------------*/

    private function parseKk(array $lines, string $text): ?array
    {
        $fields = [];

        $noKk = $this->findKkNumber($lines, $text);

        if ($noKk !== null) {
            $fields['no_kk'] = $this->entry($noKk, 0.95);
        }

        $members = [];
        $seenNik = [];

        foreach ($lines as $index => $line) {
            if (preg_match('/(?<!\d)(\d{16})(?!\d)/', $line, $match, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }

            $nik = $match[1][0];
            $pos = (int) $match[1][1];

            // Nomor KK bukan anggota keluarga — jangan dibuat baris.
            if ($noKk !== null && $nik === $noKk) {
                continue;
            }

            if (isset($seenNik[$nik])) {
                continue;
            }

            $before = trim(substr($line, 0, $pos));
            $after  = trim(substr($line, $pos + 16));

            $rowNo = null;

            if (preg_match('/^\s*(\d{1,2})[\.\)]?\s+/u', $before, $m) === 1) {
                $rowNo = (int) $m[1];
                $before = trim(substr($before, strlen($m[0])));
            }

            $nama = $this->cleanName($before);

            if (in_array(strtoupper($nama), ['NO', 'NOMOR', 'NO.'], true)) {
                continue;
            }

            if ($nama === '' && count($members) === 0) {
                $nama = $this->cleanName($this->lineAfterContains($lines, 'Nama Lengkap') ?? '');
            }

            if ($nama === '' || ! $this->isNameLike($nama)) {
                // Baris OCR kadang memecah nama dan NIK. Ambil baris sebelumnya.
                $prev = $this->clean($lines[$index - 1] ?? '');

                if ($this->isNameLike($prev) && ! $this->isLabelLine($prev)) {
                    $nama = $this->cleanName($prev);
                }
            }

            if ($nama === '') {
                continue;
            }

            $upper = strtoupper($after);
            $gender = $this->mapGender($upper);

            $dateRaw = null;
            $dateYmd = null;

            if (preg_match('/\b(\d{1,2}\s*[-\/.]\s*\d{1,2}\s*[-\/.]\s*\d{4})\b/u', $after, $dm) === 1) {
                $dateRaw = $dm[1];
                $dateYmd = $this->dateToYmd($dateRaw);
            }

            $place = null;

            if ($dateRaw !== null) {
                $beforeDate = trim(substr($after, 0, (int) strpos($after, $dateRaw)));
                $beforeDate = preg_replace('/\b(LAKI-LAKI|LAKI LAKI|PEREMPUAN|WANITA)\b/iu', ' ', $beforeDate) ?? $beforeDate;
                $place = $this->clean($beforeDate);

                // Buang sisa nomor / tanda baca di awal.
                $place = trim(preg_replace('/^[\d\s\-.,]+/u', '', $place) ?? $place);
            }

            $agama = null;

            if (preg_match('/\b(ISLAM|KRISTEN|KATOLIK|HINDU|BUDDHA|KHONGHUCU)\b/iu', $upper, $rm) === 1) {
                $agama = $this->titleCase($rm[1]);
            }

            $pendidikan = null;

            if (preg_match('/(BELUM\s*TAMAT\s*SD\s*\/\s*SEDERAJAT|TAMAT\s*SD\s*\/\s*SEDERAJAT|SLTP\s*\/\s*SEDERAJAT|SLTA\s*\/\s*SEDERAJAT|TAMAT\s*SMP\s*\/\s*SEDERAJAT|TAMAT\s*SMA\s*\/\s*SEDERAJAT|DIPLOMA\s*[IVX0-9]+|SARJANA|S[123]|D[1234])/iu', $upper, $em) === 1) {
                $pendidikan = $this->normalizeEducation($em[1]);
            }

            $marital = null;

            if (preg_match('/\b(BELUM\s*KAWIN|KAWIN\s*TERCATAT|KAWIN\s*BELUM\s*TERCATAT|CERAI\s*HIDUP|CERAI\s*MATI)\b/iu', $upper, $mm) === 1) {
                $marital = $mm[1];
            }

            $hubungan = null;

            if (preg_match('/\b(KEPALA\s*KELUARGA|ISTRI|SUAMI|ANAK|SAUDARA|FAMILI\s*LAIN|ORANG\s*TUA|MERTUA|MENANTU|CUCU)\b/iu', $upper, $hm) === 1) {
                $hubungan = $this->normalizeRelation($hm[1]);
            }

            $pekerjaan = $this->sanitizeOccupation(
                $this->occupationBetween($after, $pendidikan, $marital)
            );

            [$namaAyah, $namaIbu] = $this->parentNamesFromRow($after);

            $member = [
                'no'           => $rowNo ?? (count($members) + 1),
                'nama'         => $nama,
                'nik'          => $nik,
                'tempat_lahir' => $this->sanitizePlace($place),
                'tgl_lahir'    => $dateYmd,
                'gender'       => $gender,
                'pendidikan'   => $pendidikan,
                'pekerjaan'    => $pekerjaan,
                'hubungan'     => $hubungan,
                'nama_ayah'    => $namaAyah,
                'nama_ibu'     => $namaIbu,
            ];

            // NIK menyimpan tanggal lahir + jenis kelamin. Pakai sebagai
            // koreksi bila OCR salah baris / kolom bergeser.
            $members[] = $this->applyNikIdentity($member);

            $seenNik[$nik] = true;
        }

        // KK fisik sering dibagi dua tabel: tabel atas (nama, NIK, jenis
        // kelamin, TTL, pendidikan, pekerjaan) dan tabel bawah (status kawin,
        // hubungan, kewarganegaraan, nama ayah/ibu). OCR membaca keduanya
        // sebagai blok terpisah, jadi gabungkan berdasarkan nomor baris.
        $bottomRows = $this->parseKkBottomRows($lines);

        foreach ($members as &$member) {
            $no = (int) ($member['no'] ?? 0);

            if (! isset($bottomRows[$no])) {
                continue;
            }

            foreach (['hubungan', 'nama_ayah', 'nama_ibu'] as $key) {
                if (empty($member[$key]) && ! empty($bottomRows[$no][$key])) {
                    $member[$key] = $bottomRows[$no][$key];
                }
            }
        }
        unset($member);

        // Fallback orang tua untuk baris "Anak" yang kolom ayah/ibunya kosong:
        // ayah = kepala keluarga, ibu = istri/suami.
        $head = null;
        $spouse = null;
        foreach ($members as $member) {
            $relation = strtoupper((string) ($member['hubungan'] ?? ''));

            if ($head === null && str_contains($relation, 'KEPALA')) {
                $head = $member;
            }

            if ($spouse === null && (str_contains($relation, 'ISTRI') || str_contains($relation, 'SUAMI'))) {
                $spouse = $member;
            }
        }

        if ($head !== null) {
            foreach ($members as &$member) {
                $relation = strtoupper((string) ($member['hubungan'] ?? ''));

                if (str_contains($relation, 'ANAK')) {
                    if (empty($member['nama_ayah'])) {
                        $member['nama_ayah'] = $head['nama'];
                    }

                    if (empty($member['nama_ibu']) && $spouse !== null) {
                        $member['nama_ibu'] = $spouse['nama'];
                    }
                }
            }
            unset($member);
        }

        if (! empty($members)) {
            $fields['kk_members'] = $this->entry($members, 0.65);
        }

        $alamat = $this->composeAddress([
            $this->labelValue($lines, ['Alamat']),
            $this->withPrefix('RT/RW', $this->labelValue($lines, ['RT/RW', 'RT / RW'])),
            $this->labelValue($lines, ['Desa/Kelurahan', 'Kelurahan/Desa', 'Kel/Desa', 'Desa', 'Kelurahan']),
            $this->labelValue($lines, ['Kecamatan']),
            $this->labelValue($lines, ['Kabupaten/Kota', 'Kabupaten', 'Kota']),
            $this->labelValue($lines, ['Provinsi']),
        ]);

        if ($alamat !== '' && ! $this->isMessyAddress($alamat)) {
            $fields['alamat_sekarang'] = $this->entry($alamat, 0.7);
        }

        $kab = $this->labelValue($lines, ['Kabupaten/Kota', 'Kabupaten', 'Kota']);

        if ($kab !== null) {
            $fields['kab_kota_sekarang'] = $this->entry($kab, 0.8);
        }

        return [
            'fields' => $fields,
            'note'   => 'OCR KK',
        ];
    }

    /**
     * Baris tabel bawah KK (tanpa NIK): status kawin, hubungan, ayah, ibu.
     *
     * @return array<int, array{hubungan:string, nama_ayah:?string, nama_ibu:?string}>
     */
    private function parseKkBottomRows(array $lines): array
    {
        $rows = [];

        foreach ($lines as $line) {
            // Baris tabel atas (ada NIK) sudah diproses di pass pertama.
            if (preg_match('/(?<!\d)\d{16}(?!\d)/', $line) === 1) {
                continue;
            }

            $upper = strtoupper($line);

            // Status kawin & hubungan sering terbaca tanpa spasi
            // (KAWINTERCATAT, KEPALAKELUARGA, BELUMKAWIN).
            if (preg_match('/\b(BELUM\s*KAWIN|KAWIN\s*TERCATAT|KAWIN\s*BELUM\s*TERCATAT|CERAI\s*HIDUP|CERAI\s*MATI)\b/iu', $upper) !== 1) {
                continue;
            }

            if (preg_match('/\b(KEPALA\s*KELUARGA|ISTRI|SUAMI|ANAK|SAUDARA|FAMILI\s*LAIN|ORANG\s*TUA|MERTUA|MENANTU|CUCU)\b/iu', $upper, $hm) !== 1) {
                continue;
            }

            $no = null;

            if (preg_match('/^\s*(\d{1,2})[\.\)]?\s+/u', $line, $nm) === 1) {
                $no = (int) $nm[1];
            }

            if ($no === null || $no < 1 || $no > 30) {
                // Nomor baris kadang terpisah dari teksnya. Kalau belum ada
                // baris, anggap ini baris pertama; selain itu lanjutkan urutan.
                $no = $rows ? (max(array_keys($rows)) + 1) : 1;
            }

            [$ayah, $ibu] = $this->parentNamesFromRow($line);

            $rows[$no] = [
                'hubungan'  => $this->normalizeRelation($hm[1]),
                'nama_ayah' => $ayah,
                'nama_ibu'  => $ibu,
            ];
        }

        return $rows;
    }

    private function normalizeRelation(string $value): string
    {
        $value = strtoupper($this->clean($value));
        $value = str_replace(
            ['KEPALAKELUARGA', 'FAMILILAIN', 'ORANGTUA'],
            ['KEPALA KELUARGA', 'FAMILI LAIN', 'ORANG TUA'],
            $value
        );

        return $value;
    }

    private function normalizeEducation(string $value): string
    {
        $value = strtoupper($this->clean($value));
        $value = preg_replace('/\s*\/\s*/', '/', $value) ?? $value;
        $value = str_replace(
            ['BELUMTAMATSD', 'TAMATSD', 'TAMATSMP', 'TAMATSMA', 'TAMATSMK'],
            ['BELUM TAMAT SD', 'TAMAT SD', 'TAMAT SMP', 'TAMAT SMA', 'TAMAT SMK'],
            $value
        );

        return $value;
    }

    private function sanitizeOccupation(?string $value): ?string
    {
        $value = $this->clean((string) $value);

        if ($value === '') {
            return null;
        }

        $upper = strtoupper($value);

        // Nilai yang tercampur kolom lain (jenis kelamin, tanggal, agama,
        // pendidikan) lebih berbahaya daripada kosong.
        if (preg_match('/\b(LAKI-LAKI|LAKI LAKI|PEREMPUAN|WANITA|ISLAM|KRISTEN|KATOLIK|HINDU|BUDDHA|KHONGHUCU)\b/u', $upper) === 1) {
            return null;
        }

        if (preg_match('/\d{1,2}\s*[-\/.]\s*\d{1,2}\s*[-\/.]\s*\d{4}/u', $upper) === 1) {
            return null;
        }

        if (preg_match('/\b(TAMAT|SLTP|SLTA|SEDERAJAT|TIDAKTAHU|PENDIDIKAN)\b/u', $upper) === 1) {
            return null;
        }

        if (strlen($value) > 60) {
            return null;
        }

        $value = str_replace(
            ['MENGURUSRUMAHTANGGA', 'BELUM/TIDAKBEKERJA', 'BELUMTIDAKBEKERJA'],
            ['MENGURUS RUMAH TANGGA', 'BELUM/TIDAK BEKERJA', 'BELUM/TIDAK BEKERJA'],
            $value
        );

        // Ambil frasa pekerjaan yang dikenal bila OCR menempelkan kolom lain
        // (mis. "PELAJAR/MAHASISWA WONOGIRI").
        $known = [
            'PELAJAR/MAHASISWA', 'BELUM/TIDAK BEKERJA', 'MENGURUS RUMAH TANGGA',
            'IBU RUMAH TANGGA', 'KARYAWAN SWASTA', 'WIRASWASTA', 'PENSIUNAN',
            'BURUH', 'PETANI', 'NELAYAN', 'PEDAGANG', 'SOPIR', 'GURU', 'DOSEN',
            'PNS', 'TNI', 'POLRI', 'SATPAM', 'TUKANG',
        ];

        foreach ($known as $phrase) {
            if (stripos($value, $phrase) !== false) {
                return $phrase;
            }
        }

        return $value;
    }

    private function sanitizePlace(?string $value): ?string
    {
        $value = $this->clean((string) $value);

        if ($value === '' || preg_match('/\d/u', $value) === 1) {
            return null;
        }

        $upper = strtoupper($value);

        if (preg_match('/\b(LAKI|PEREMPUAN|WANITA|ISLAM|KRISTEN|KATOLIK|HINDU|BUDDHA|KHONGHUCU|WNI|WNA)\b/u', $upper) === 1) {
            return null;
        }

        $words = preg_split('/\s+/u', $upper) ?: [];

        if (count($words) !== count(array_unique($words))) {
            return null;
        }

        return strlen($value) <= 40 ? $value : null;
    }

    /**
     * Alamat KK hasil OCR sering mencampur label kolom. Jangan isi form
     * dengan alamat seperti itu.
     */
    private function isMessyAddress(string $value): bool
    {
        if (preg_match('/\.{2,}/u', $value) === 1) {
            return true;
        }

        $upper = strtoupper($value);

        if (preg_match('/\b(AIAMAT|ALAMAL|GOLONGAN|JENIS\s+PEKERJAAN)\b/u', $upper) === 1) {
            return true;
        }

        if (preg_match('/\b(KECAMATAN|KABUPATEN\s*\/\s*KOTA)\s+(KABUPATEN|KOTA|PROVINSI|GOLONGAN|RT\/RW|DESA|KELURAHAN|$)/u', $upper) === 1) {
            return true;
        }

        return substr_count($upper, 'ALAMAT') > 1;
    }

    /**
     * Lengkapi/koreksi tanggal lahir & jenis kelamin dari NIK.
     *
     * NIK: 6 digit wilayah + DDMMYY + 4 digit urut.
     * DD > 40 berarti perempuan (DD-40).
     */
    private function applyNikIdentity(array $member): array
    {
        $nik = preg_replace('/\D/u', '', (string) ($member['nik'] ?? '')) ?? '';

        if (strlen($nik) !== 16) {
            return $member;
        }

        $day    = (int) substr($nik, 6, 2);
        $month  = (int) substr($nik, 8, 2);
        $year2  = (int) substr($nik, 10, 2);
        $gender = $day > 40 ? 'P' : 'L';

        if ($day > 40) {
            $day -= 40;
        }

        $year = $year2 + ($year2 > (int) date('y') ? 1900 : 2000);

        if (! checkdate($month, $day, $year)) {
            return $member;
        }

        $member['tgl_lahir'] = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $member['gender']    = $gender;

        return $member;
    }

    /* ---------------------------------------------------------------------
     | Ijazah
     |---------------------------------------------------------------------*/

    private function parseIjazah(array $lines, string $text): ?array
    {
        $fields = [];
        $upper  = strtoupper($text);

        $level = $this->educationLevelFromText($upper);

        if ($level !== null) {
            $fields['pendidikan'] = $this->entry($level, 0.9);
        }

        $institusi = $this->labelValue($lines, ['Satuan Pendidikan', 'Nama Sekolah']);

        if ($institusi === null) {
            foreach ($lines as $line) {
                if (preg_match('/\b(SMK|SMA|SMP|SD|UNIVERSITAS|INSTITUT|POLITEKNIK|AKADEMI)\b.+/iu', $line, $m) === 1
                    && ! $this->isLabelLine($line)
                    && preg_match('/^SEKOLAH\s+MENENGAH/iu', $line) !== 1) {
                    $institusi = $this->clean($line);
                    break;
                }
            }
        }

        $jurusan = $this->labelValue($lines, ['Konsentrasi Keahlian']);

        if ($jurusan === null) {
            $jurusan = $this->labelValue($lines, ['Program Keahlian', 'Jurusan']);
        }

        $sampai = null;

        if (preg_match('/TAHUN\s+AJARAN\s+(\d{4})\s*\/\s*(\d{4})/iu', $upper, $m) === 1) {
            $sampai = (int) $m[2];
        }

        $lulus = preg_match('/\bLULUS\b/iu', $upper) === 1 ? true : null;

        $nama = $this->labelValue($lines, ['Dengan ini menyatakan bahwa']);

        if ($nama !== null && $this->isNameLike($nama)) {
            $fields['nama_lengkap'] = $this->entry($this->cleanName($nama), 0.8);
        }

        $tanggal = null;

        if (preg_match('/\b(\d{1,2}\s+[A-Z]+\s*\d{4})\b/iu', $text, $tm) === 1) {
            $tanggal = $this->dateToYmd($tm[1]);
        }

        if ($tanggal !== null) {
            $fields['tanggal_lahir'] = $this->entry($tanggal, 0.8);
        }

        if ($level !== null || $institusi !== null || $jurusan !== null) {
            $row = [
                'tingkat'   => $level,
                'institusi' => $institusi,
                'jurusan'   => $jurusan,
                'sampai'    => $sampai,
                'lulus'     => $lulus,
            ];

            $fields['education'] = $this->entry([$this->dropEmpty($row)], 0.8);
        }

        return [
            'fields' => $fields,
            'note'   => 'OCR ijazah',
        ];
    }

    /* ---------------------------------------------------------------------
     | SKCK / CV / surat lamaran
     |---------------------------------------------------------------------*/

    private function parseSkck(array $lines, string $text): ?array
    {
        $ktp = $this->parseKtp($lines, $text);

        if ($ktp === null) {
            return null;
        }

        $fields = $ktp['fields'];

        if (isset($fields['alamat_asal']) && ! isset($fields['alamat_sekarang'])) {
            $fields['alamat_sekarang'] = $fields['alamat_asal'];
        }

        if (isset($fields['kab_kota_asal']) && ! isset($fields['kab_kota_sekarang'])) {
            $fields['kab_kota_sekarang'] = $fields['kab_kota_asal'];
        }

        return [
            'fields' => $fields,
            'note'   => 'OCR SKCK',
        ];
    }

    /* ---------------------------------------------------------------------
     | Akta Kelahiran
     |---------------------------------------------------------------------*/

    private function parseAktaKelahiran(array $lines, string $text): ?array
    {
        $fields = [];
        $upper  = strtoupper($text);

        // Nama anak
        $nama = $this->labelValue($lines, [
            'telah lahir', 'telah lahir seorang anak', 'nama anak',
        ]);

        if ($nama === null) {
            foreach ($lines as $i => $line) {
                if (preg_match('/\b(TELAH\s+LAHIR\s*(?:SEORANG\s*ANAK)?)\s*[:.]?\s*(.*)$/iu', $line, $m) === 1) {
                    $val = trim($m[2]);
                    if ($val !== '' && $this->isNameLike($val)) {
                        $nama = $val;
                        break;
                    } elseif (isset($lines[$i + 1]) && $this->isNameLike($lines[$i + 1])) {
                        $nama = $lines[$i + 1];
                        break;
                    }
                }
            }
        }

        if ($nama !== null && $this->isNameLike($nama)) {
            $fields['nama_lengkap'] = $this->entry($this->cleanName($nama), 0.9);
        }

        // Tempat & Tanggal lahir
        if (preg_match('/\b(?:di|pada)\s+([A-Za-z\s]+?)[,\s]+(?:pada\s+tanggal\s+|tanggal\s+)?(\d{1,2}\s+[A-Za-z]+\s+\d{4})/iu', $text, $m) === 1) {
            $tempat = $this->clean($m[1]);
            if ($tempat !== '' && strlen($tempat) <= 30 && ! preg_match('/\b(BAHWA|AKTA|HARI|PADA|TAHUN)\b/iu', $tempat)) {
                $fields['tempat_lahir'] = $this->entry($tempat, 0.85);
            }
            $tgl = $this->dateToYmd($m[2]);
            if ($tgl !== null) {
                $fields['tanggal_lahir'] = $this->entry($tgl, 0.9);
            }
        }

        // Jenis Kelamin
        if (preg_match('/\b(LAKI-LAKI|PEREMPUAN)\b/iu', $text, $gm) === 1) {
            $fields['jenis_kelamin'] = $this->entry(strtoupper($gm[1]) === 'PEREMPUAN' ? 'P' : 'L', 0.9);
        }

        // Nama Orang Tua (Ayah dan Ibu)
        if (preg_match('/(?:suami\s*istri|suami\s*isteri|pasangan|dari)\s+([A-Za-z\s\'.]+?)\s+dan\s+([A-Za-z\s\'.]+?)(?:\s+(?:pada|nomor|dikeluarkan|yang|\.|$))/iu', $text, $pm) === 1) {
            $ayah = $this->cleanName($pm[1]);
            $ibu  = $this->cleanName($pm[2]);

            if ($this->isNameLike($ayah)) {
                $fields['ayah'] = $this->entry(['nama' => $ayah], 0.85);
            }
            if ($this->isNameLike($ibu)) {
                $fields['ibu'] = $this->entry(['nama' => $ibu], 0.85);
            }
        } else {
            $ayah = $this->labelValue($lines, ['Nama Ayah', 'Ayah']);
            if ($ayah !== null && $this->isNameLike($ayah)) {
                $fields['ayah'] = $this->entry(['nama' => $this->cleanName($ayah)], 0.85);
            }
            $ibu = $this->labelValue($lines, ['Nama Ibu', 'Ibu']);
            if ($ibu !== null && $this->isNameLike($ibu)) {
                $fields['ibu'] = $this->entry(['nama' => $this->cleanName($ibu)], 0.85);
            }
        }

        if (preg_match('/\b(WNI|WARGA\s+NEGARA\s+INDONESIA)\b/iu', $text) === 1 || str_contains($upper, 'REPUBLIK INDONESIA')) {
            $fields['warga_negara'] = $this->entry('WNI', 0.9);
        }

        return ! empty($fields) ? [
            'fields' => $fields,
            'note'   => 'OCR akta kelahiran',
        ] : null;
    }

    private function parseCv(array $lines, string $text): ?array
    {
        $fields = [];
        $upper  = strtoupper($text);

        $nama = $this->labelValue($lines, ['Nama Lengkap', 'Nama']);

        if ($nama !== null && $this->isNameLike($nama)) {
            $fields['nama_lengkap'] = $this->entry($this->cleanName($nama), 0.75);
        }

        $tanggal = $this->labelValue($lines, ['Tanggal Lahir', 'Tgl Lahir']);

        if ($tanggal !== null) {
            $tanggalYmd = $this->dateToYmd($tanggal);

            if ($tanggalYmd !== null) {
                $fields['tanggal_lahir'] = $this->entry($tanggalYmd, 0.75);
            }
        }

        $alamat = $this->labelValue($lines, ['Alamat']);

        if ($alamat !== null && ! $this->isMessyAddress($alamat)) {
            $fields['alamat_sekarang'] = $this->entry($alamat, 0.7);
        }

        if (preg_match('/(?:\+62|62|0)8\d{7,12}/', preg_replace('/[\s\-().]+/', '', $text) ?? '', $pm) === 1) {
            $fields['nomor_hp'] = $this->entry($pm[0], 0.8);
        }

        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $em) === 1) {
            $fields['email'] = $this->entry($em[0], 0.8);
        }

        $level = $this->educationLevelFromText($upper);

        if ($level !== null) {
            $fields['pendidikan'] = $this->entry($level, 0.7);
        }

        $education = [];

        foreach ($lines as $i => $line) {
            if (preg_match('/\b(SD|SMP|SMA|SMK|UNIVERSITAS|INSTITUT|POLITEKNIK|AKADEMI)\b/iu', $line, $m) !== 1) {
                continue;
            }

            if ($this->isLabelLine($line) && ! preg_match('/\b(SDN|SMPN|SMAN|SMKN)\b/iu', $line)) {
                continue;
            }

            $tingkat = $this->educationLevelFromText(strtoupper($m[1]));
            $context = $line . ' ' . ($lines[$i + 1] ?? '') . ' ' . ($lines[$i + 2] ?? '');

            $dari = null;
            $sampai = null;

            if (preg_match('/(\d{4}).{0,40}?(\d{4})/u', $context, $ym) === 1) {
                $dari = (int) $ym[1];
                $sampai = (int) $ym[2];
            }

            $institusi = $this->clean(preg_replace('/^\s*\d+[\.\)]?\s*/u', '', $line) ?? $line);
            $institusi = preg_replace('/\b(SD|SMP|SMA|SMK)\s*\/?\s*Sederajat\b/iu', ' ', $institusi) ?? $institusi;
            $institusi = $this->clean($institusi);

            $row = $this->dropEmpty([
                'tingkat'   => $tingkat,
                'institusi' => $institusi,
                'dari'      => $dari,
                'sampai'    => $sampai,
                'lulus'     => preg_match('/\b(LULUS|BERIJAZAH)\b/iu', $context) === 1 ? true : null,
            ]);

            if (! empty($row)) {
                $education[] = $row;
            }
        }

        if (! empty($education)) {
            $education = array_values(array_filter($education, function (array $row): bool {
                $institusi = trim((string) ($row['institusi'] ?? ''));

                if ($institusi === '' || preg_match('/\.{2,}/u', $institusi) === 1) {
                    return false;
                }

                // Baris judul/template, bukan nama sekolah.
                if (preg_match('/^(AKADEMI|UNIVERSITAS|INSTITUT|POLITEKNIK|JURUSAN)\b/iu', $institusi) === 1
                    && preg_match('/\d/u', $institusi) !== 1) {
                    return false;
                }

                return true;
            }));
        }

        if (! empty($education)) {
            $fields['education'] = $this->entry($education, 0.6);
        }

        return [
            'fields' => $fields,
            'note'   => 'OCR CV/surat lamaran; perlu dicek manual',
        ];
    }

    /* ---------------------------------------------------------------------
     | Helper teks
     |---------------------------------------------------------------------*/

    private function textLines(array $lines): array
    {
        $items = [];

        foreach ($lines as $index => $line) {
            if (is_array($line)) {
                $text = (string) ($line['text'] ?? '');
                $x    = (float) ($line['x'] ?? 0);
                $y    = (float) ($line['y'] ?? 0);
            } else {
                $text = (string) $line;
                $x    = 0.0;
                $y    = $index * 30.0;
            }

            $text = $this->clean($text);

            if ($text !== '') {
                $items[] = ['text' => $text, 'x' => $x, 'y' => $y, 'i' => $index];
            }
        }

        if (empty($items)) {
            return [];
        }

        // Kalau server hanya mengirim teks tanpa koordinat, jangan gabungkan
        // semuanya menjadi satu baris.
        $hasY = array_filter($items, fn ($item) => abs($item['y']) > 0.001);

        if (empty($hasY)) {
            foreach ($items as $i => &$item) {
                $item['y'] = $i * 30.0;
            }
            unset($item);
        }

        usort($items, fn ($a, $b) => $a['y'] <=> $b['y'] ?: $a['x'] <=> $b['x'] ?: $a['i'] <=> $b['i']);

        // Gabungkan box yang satu baris. Toleransi 26 px cukup untuk label
        // dan nilai KTP yang baseline-nya sedikit berbeda, tanpa mencampur
        // baris berikutnya (jarak antar baris biasanya 45-60 px).
        $groups  = [];
        $current = [];
        $baseY   = null;

        foreach ($items as $item) {
            if ($baseY === null || abs($item['y'] - $baseY) > 26) {
                if (! empty($current)) {
                    $groups[] = $current;
                }

                $current = [$item];
                $baseY   = $item['y'];
            } else {
                $current[] = $item;
            }
        }

        if (! empty($current)) {
            $groups[] = $current;
        }

        $out = [];

        foreach ($groups as $group) {
            usort($group, fn ($a, $b) => $a['x'] <=> $b['x'] ?: $a['i'] <=> $b['i']);
            $out[] = implode('  ', array_column($group, 'text'));
        }

        return $out;
    }

    private function entry($value, float $confidence = 0.8, ?string $note = null): array
    {
        return [
            'value'      => $value,
            'confidence' => $confidence,
            'note'       => $note,
        ];
    }

    private function clean(?string $value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        $value = preg_replace('/^[\s:：\-–—,;]+/u', '', $value) ?? $value;
        $value = preg_replace('/[\s:：\-–—,;]+$/u', '', $value) ?? $value;

        if ($value === '') {
            return '';
        }

        if (in_array(strtoupper($value), ['-', 'N/A', 'NA', 'N.A.', 'TIDAK ADA', 'TIDAK', 'NULL'], true)) {
            return '';
        }

        if (preg_match('/^[-\x{2013}\x{2014}_.\s]+$/u', $value) === 1) {
            return '';
        }

        return $value;
    }

    private function labelValue(array $lines, array $labels): ?string
    {
        foreach ($lines as $i => $line) {
            $parts = $this->splitLabelValue($line);

            if ($parts === null) {
                continue;
            }

            [$labelText, $value] = $parts;

            foreach ($labels as $label) {
                if (! $this->labelMatches($labelText, $label)) {
                    continue;
                }

                $value = $this->clean($value);

                if ($value !== '') {
                    return $value;
                }

                $next = $this->clean($lines[$i + 1] ?? '');

                if ($next !== '' && ! $this->isLabelLine($next)) {
                    return $next;
                }
            }

            // Fallback untuk label panjang yang dipisahkan satu spasi.
            foreach ($labels as $label) {
                if (mb_strlen($label) < 5 && ! str_contains($label, '/')) {
                    continue;
                }

                if (preg_match($this->labelPrefixPattern($label), $line, $m) === 1) {
                    $value = $this->clean($m[1]);

                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Pisahkan baris menjadi label + nilai berdasarkan ':' atau 2+ spasi.
     *
     * @return array{0:string,1:string}|null
     */
    private function splitLabelValue(string $line): ?array
    {
        $line = trim($line);

        if ($line === '') {
            return null;
        }

        // Titik dua ASCII maupun full-width (sering muncul dari OCR).
        if (preg_match('/^(.{1,50}?)\s*[:：]\s*(.*)$/u', $line, $m) === 1) {
            return [$this->clean($m[1]), $this->clean($m[2])];
        }

        $parts = preg_split('/\s{2,}/u', $line, 2);

        if (count($parts) === 2) {
            return [$this->clean($parts[0]), $this->clean($parts[1])];
        }

        $parts = preg_split('/\t+/u', $line, 2);

        if (count($parts) === 2) {
            return [$this->clean($parts[0]), $this->clean($parts[1])];
        }

        return [$this->clean($line), ''];
    }

    private function labelMatches(string $left, string $right): bool
    {
        $a = $this->normalizeLabel($left);
        $b = $this->normalizeLabel($right);

        if ($a === '' || $b === '') {
            return false;
        }

        if ($a === $b) {
            return true;
        }

        $max = max(strlen($a), strlen($b));

        if ($max === 0) {
            return false;
        }

        $similarity = 1 - (levenshtein($a, $b) / $max);

        // Toleransi salah baca OCR: Mama→Nama, Alamal→Alamat,
        // Kecamalan→Kecamatan.
        return $similarity >= 0.75;
    }

    private function normalizeLabel(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/u', '', strtolower($value)) ?? '';
    }

    private function labelPrefixPattern(string $label): string
    {
        $chars = preg_split('//u', $label, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $body  = implode('\s*', array_map(fn ($char) => preg_quote($char, '/'), $chars));

        return '/^\s*' . $body . '\s*[:\-]?\s*(.+)$/iu';
    }

    private function lineAfterContains(array $lines, string $needle, bool $after = false): ?string
    {
        foreach ($lines as $i => $line) {
            if (stripos($line, $needle) === false) {
                continue;
            }

            $next = $this->clean($lines[$i + ($after ? 1 : -1)] ?? '');

            if ($next !== '' && ! $this->isLabelLine($next)) {
                return $next;
            }
        }

        return null;
    }

    private function findNik(array $lines, string $text): ?string
    {
        foreach ($lines as $line) {
            if (stripos($line, 'NIK') === false) {
                continue;
            }

            $compact = preg_replace('/\s+/', '', $line) ?? '';

            if (preg_match('/(?<!\d)(\d{16})(?!\d)/', $compact, $m) === 1) {
                return $m[1];
            }
        }

        $compact = preg_replace('/\s+/', '', $text) ?? '';

        return preg_match('/(?<!\d)(\d{16})(?!\d)/', $compact, $m) === 1 ? $m[1] : null;
    }

    private function findKkNumber(array $lines, string $text): ?string
    {
        if (preg_match('/(?:KARTU\s*KELUARGA|NO\.?\s*KK|NOMOR\s*KK)[^\d]{0,50}(?<!\d)(\d{16})(?!\d)/iu', $text, $m) === 1) {
            return $m[1];
        }

        foreach (array_slice($lines, 0, 12) as $line) {
            if (preg_match('/^\s*NO\.?\s*[:\-]?\s*(?<!\d)(\d{16})(?!\d)\s*$/iu', $line, $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }

    private function mapGender(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        if ($value === '') {
            return null;
        }

        if (str_contains($value, 'LAKI') || preg_match('/\bL\b/u', $value) === 1) {
            return 'L';
        }

        if (str_contains($value, 'PEREMPUAN') || str_contains($value, 'WANITA') || preg_match('/\bP\b/u', $value) === 1) {
            return 'P';
        }

        return null;
    }

    private function dateToYmd(string $value): ?string
    {
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $value, $m) === 1) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }

        if (preg_match('/(\d{1,2})\s*[-\/.]\s*(\d{1,2})\s*[-\/.]\s*(\d{4})/u', $value, $m) === 1) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        $months = [
            'JANUARI' => 1, 'FEBRUARI' => 2, 'MARET' => 3, 'APRIL' => 4,
            'MEI' => 5, 'JUNI' => 6, 'JULI' => 7, 'AGUSTUS' => 8,
            'SEPTEMBER' => 9, 'OKTOBER' => 10, 'NOVEMBER' => 11, 'DESEMBER' => 12,
        ];

        if (preg_match('/(\d{1,2})\s+([A-Z]+)\s*(\d{4})/iu', $value, $m) === 1) {
            $month = $months[strtoupper($m[2])] ?? null;

            if ($month !== null) {
                return sprintf('%04d-%02d-%02d', $m[3], $month, $m[1]);
            }
        }

        return null;
    }

    private function educationLevelFromText(string $upper): ?string
    {
        if (str_contains($upper, 'SEKOLAH MENENGAH KEJURUAN') || preg_match('/\bSMK\b/u', $upper) === 1) {
            return 'SMK';
        }

        if (str_contains($upper, 'SEKOLAH MENENGAH ATAS') || preg_match('/\bSMA\b/u', $upper) === 1) {
            return 'SMA';
        }

        if (str_contains($upper, 'SEKOLAH MENENGAH PERTAMA') || preg_match('/\bSMP\b/u', $upper) === 1) {
            return 'SMP';
        }

        if (str_contains($upper, 'SEKOLAH DASAR') || preg_match('/\bSD\b/u', $upper) === 1) {
            return 'SD';
        }

        if (preg_match('/\bS3\b/u', $upper) === 1) {
            return 'S3';
        }

        if (preg_match('/\bS2\b/u', $upper) === 1) {
            return 'S2';
        }

        if (preg_match('/\bS1\b/u', $upper) === 1 || str_contains($upper, 'SARJANA')) {
            return 'S1';
        }

        if (preg_match('/\bD4\b/u', $upper) === 1) {
            return 'D4';
        }

        if (preg_match('/\bD3\b/u', $upper) === 1 || str_contains($upper, 'DIPLOMA')) {
            return 'D3';
        }

        return null;
    }

    private function occupationBetween(string $after, ?string $education, ?string $marital): ?string
    {
        $upper = strtoupper($after);
        $start = 0;
        $end   = strlen($after);

        if ($education !== null) {
            $pos = stripos($upper, strtoupper($education));

            if ($pos !== false) {
                $start = $pos + strlen($education);
            }
        }

        if ($marital !== null) {
            $pos = stripos($upper, strtoupper($marital));

            if ($pos !== false && $pos > $start) {
                $end = $pos;
            }
        }

        $value = trim(substr($after, $start, max(0, $end - $start)));
        $value = trim($value, " \t\n\r\0\x0B-–—,;");

        if ($value === '' || preg_match('/^[-\s]+$/u', $value) === 1) {
            return null;
        }

        return $this->clean($value);
    }

    /**
     * Ambil dua nama orang tua dari kolom paling kanan.
     *
     * @return array{0:?string,1:?string}
     */
    private function parentNamesFromRow(string $after): array
    {
        // Kolom ayah/ibu ada di paling kanan, setelah kewarganegaraan.
        // Tanpa penanda WNI/WNA, baris ini bukan baris KK lengkap (bisa jadi
        // tabel atas yang tidak memuat nama orang tua).
        if (preg_match('/\b(?:WNI|WNA)\b(.*)$/iu', $after, $m) !== 1) {
            return [null, null];
        }

        $tail = trim($m[1]);

        $tail = trim($tail, " \t\n\r\0\x0B-–—,;");

        if ($tail === '') {
            return [null, null];
        }

        // Kalau OCR memisahkan kolom dengan 2+ spasi, dua sel terakhir
        // adalah ayah dan ibu.
        $cells = array_values(array_filter(array_map(
            fn ($cell) => $this->clean($cell),
            preg_split('/\s{2,}/u', $tail) ?: []
        ), fn ($cell) => $this->isNameLike($cell)));

        if (count($cells) === 2) {
            return [$cells[0], $cells[1]];
        }

        $tokens = array_values(array_filter(
            preg_split('/\s+/u', $tail) ?: [],
            fn ($token) => $this->isNameLike($token)
                && ! in_array(strtoupper($token), ['WNI', 'WNA', 'ISLAM', 'KRISTEN', 'KATOLIK', 'HINDU', 'BUDDHA', 'KHONGHUCU'], true)
        ));

        if (count($tokens) < 2) {
            return [null, null];
        }

        $mother = array_pop($tokens);

        return [implode(' ', $tokens), $mother];
    }

    private function composeAddress(array $parts): string
    {
        $out = [];

        foreach ($parts as $part) {
            $part = $this->clean($part);

            if ($part === '') {
                continue;
            }

            $joined = strtolower(implode(', ', $out));

            if ($joined !== '' && str_contains($joined, strtolower($part))) {
                continue;
            }

            $out[] = $part;
        }

        return implode(', ', $out);
    }

    private function withPrefix(string $prefix, ?string $value): ?string
    {
        $value = $this->clean($value);

        if ($value === '') {
            return null;
        }

        if (stripos($value, $prefix) !== false) {
            return $value;
        }

        return $prefix . ' ' . $value;
    }

    private function cleanName(?string $value): string
    {
        $value = $this->clean($value);
        $value = preg_replace('/^\s*\d{1,2}[\.\)]?\s*/u', '', $value) ?? $value;
        $value = preg_replace('/\s{2,}/u', ' ', $value) ?? $value;

        return trim($value, " \t\n\r\0\x0B.,;:-");
    }

    private function isNameLike(?string $value): bool
    {
        $value = trim((string) $value);

        if (strlen($value) < 2) {
            return false;
        }

        if (preg_match('/\d{4,}/', $value) === 1) {
            return false;
        }

        return preg_match('/^[A-Za-z .\'\-]+$/u', $value) === 1
            && preg_match('/[A-Za-z]{2,}/', $value) === 1;
    }

    private function isLabelLine(string $value): bool
    {
        $upper = strtoupper($value);

        foreach ([
            'NAMA', 'NIK', 'ALAMAT', 'TEMPAT', 'TANGGAL', 'JENIS', 'AGAMA',
            'STATUS', 'PEKERJAAN', 'KEWARGANEGARAAN', 'PENDIDIKAN', 'NO.',
            'RT/RW', 'KEL/DESA', 'KECAMATAN', 'KABUPATEN', 'PROVINSI',
            'TAHUN', 'PROGRAM', 'KONSENTRASI', 'SATUAN', 'KARTU KELUARGA',
        ] as $label) {
            if (str_contains($upper, $label)) {
                return true;
            }
        }

        return false;
    }

    private function titleCase(string $value): string
    {
        return ucwords(strtolower($value));
    }

    private function dropEmpty(array $row): array
    {
        return array_filter($row, fn ($value) => $value !== null && $value !== '');
    }
}
