<?php

namespace App\Services\RecruitmentAi;

/**
 * Menyusun data keluarga (ayah, ibu, saudara, anak) dari baris tabel KK.
 *
 * Acuan utama adalah orang yang ada di KTP:
 *  1. Cari baris KK yang NIK/nama-nya sama dengan data KTP.
 *  2. Nama ayah/ibu diambil dari kolom "Nama Ayah"/"Nama Ibu" pada baris itu.
 *     Jika nama tersebut juga muncul sebagai anggota di tabel KK, tanggal
 *     lahir/pendidikan/pekerjaannya ikut diambil. Jika tidak (KK berbeda),
 *     hanya namanya yang dipakai.
 *  3. Peran pelamar ditentukan dari kolom "Status Hubungan Dalam Keluarga"
 *     (Kepala Keluarga/Istri/Anak). Bila kolom itu kosong, dipakai kombinasi
 *     nomor baris dan nama ayah/ibu:
 *       - pelamar "Anak"  → baris sesama "Anak" = SAUDARA, data anak kosong.
 *       - pelamar Kepala/Istri → baris "Anak" = ANAK pelamar.
 *     Ini mencegah pelamar yang masih anak-anak dianggap punya anak.
 */
class KkFamilyResolver
{
    /** Pendidikan KK → pilihan form (PENDIDIKAN_AYAH). */
    private const EDUCATION_MAP = [
        'S3' => 'S3', 'S2' => 'S2', 'S1' => 'S1',
        'D4' => 'S1',
        'D3' => 'D3', 'D2' => 'D3', 'D1' => 'D3',
    ];

    /**
     * @param  array<int, array>  $rows   baris mentah kk_members hasil AI
     * @param  string|null        $nik    NIK pelamar (dari KTP)
     * @param  string|null        $nama   nama pelamar (dari KTP)
     * @return array{ayah:array|null, ibu:array|null, saudara:array, anak:array}|null
     */
    public static function resolve(array $rows, ?string $nik, ?string $nama): ?array
    {
        $members = self::normalizeRows($rows);

        if (empty($members)) {
            return null;
        }

        $target = self::findTarget($members, $nik, $nama);

        if ($target === null) {
            return null;
        }

        $targetNo = $target['__no'];

        // Status hubungan di KK lebih akurat daripada nomor baris: pelamar
        // yang tertulis "Anak" tidak boleh dianggap punya anak.
        $relation = strtoupper((string) ($target['hubungan'] ?? ''));
        $head     = self::firstByRelation($members, ['KEPALA']);
        $spouse   = self::firstByRelation($members, ['ISTRI', 'SUAMI', 'PASANGAN']);

        // Kalau kolom hubungan kosong, cocokkan nama ayah/ibu pelamar dengan
        // kepala keluarga/pasangan. Ini menghindari pelamar yang sebenarnya
        // anak (baris KK-nya salah nomor) dianggap punya anak.
        $parentNames = array_values(array_filter([
            self::cleanName($target['nama_ayah'] ?? null),
            self::cleanName($target['nama_ibu'] ?? null),
        ]));

        $looksLikeChild = false;

        foreach (array_filter([$head['nama'] ?? null, $spouse['nama'] ?? null]) as $parentName) {
            if (in_array(self::cleanName($parentName), $parentNames, true)) {
                $looksLikeChild = true;

                break;
            }
        }

        // Tanpa kolom hubungan, orang tua pelamar biasanya juga tercatat
        // sebagai anggota KK (ayah & ibu). Kalau keduanya ada di tabel,
        // pelamar hampir pasti baris "Anak" — meskipun nomor barisnya salah.
        $parentsInMembers = 0;

        foreach ($parentNames as $parentName) {
            if ($parentName !== '' && self::findByName($members, $parentName) !== null) {
                $parentsInMembers++;
            }
        }

        if ($parentsInMembers >= 2) {
            $looksLikeChild = true;
        }

        $isChild  = str_contains($relation, 'ANAK') || ($relation === '' && $looksLikeChild);
        $isParent = ! $isChild && (
            str_contains($relation, 'KEPALA')
            || str_contains($relation, 'ISTRI')
            || str_contains($relation, 'SUAMI')
            || str_contains($relation, 'PASANGAN')
            || $targetNo <= 2
        );

        $fatherName = $target['nama_ayah'] ?? null;
        $motherName = $target['nama_ibu'] ?? null;

        // Kolom "Nama Ayah"/"Nama Ibu" kadang kosong. Kalau pelamar adalah
        // anak, ambil kepala keluarga/pasangan sebagai cadangan.
        if ($isChild && (self::cleanName($fatherName) === '' || self::cleanName($motherName) === '')) {
            $male = $female = null;

            foreach ([$head, $spouse] as $person) {
                if (! $person) {
                    continue;
                }

                if ($person['gender'] === 'Laki-laki' && $male === null) {
                    $male = $person;
                }

                if ($person['gender'] === 'Perempuan' && $female === null) {
                    $female = $person;
                }
            }

            $father = $male ?: ($head && $head['gender'] !== 'Perempuan' ? $head : $spouse);
            $mother = $female ?: ($spouse && $spouse['gender'] !== 'Laki-laki' ? $spouse : $head);

            if (self::cleanName($fatherName) === '') {
                $fatherName = $father['nama'] ?? null;
            }

            if (self::cleanName($motherName) === '') {
                $motherName = $mother['nama'] ?? null;
            }
        }

        $ayah = self::parent($members, $fatherName);
        $ibu  = self::parent($members, $motherName);

        $hasRelations = count(array_filter(
            $members,
            fn (array $m) => ($m['hubungan'] ?? '') !== ''
        )) > 0;

        $afterSecond = array_values(array_filter(
            $members,
            fn (array $m) => $m['__no'] > 2 && $m['__no'] !== $targetNo
        ));

        if ($isParent) {
            $children = array_values(array_filter(
                $members,
                fn (array $m) => $m['__no'] !== $targetNo && self::isChildRelation($m)
            ));

            if (empty($children) && ! $hasRelations) {
                // Nomor baris/hubungan tidak terbaca: kenali anak dari kolom
                // Nama Ayah/Nama Ibu yang mengarah ke kepala keluarga/pasangan.
                $parentPair = array_values(array_filter([
                    $head['nama'] ?? null,
                    $spouse['nama'] ?? null,
                ]));

                if ($parentPair !== []) {
                    $children = array_values(array_filter(
                        $members,
                        fn (array $m) => $m['__no'] !== $targetNo
                            && (in_array($m['nama_ayah'], $parentPair, true)
                                || in_array($m['nama_ibu'], $parentPair, true))
                    ));
                }
            }

            if (empty($children) && ! $hasRelations) {
                $children = $afterSecond;
            }

            $siblings = [];
        } else {
            $siblings = array_values(array_filter(
                $members,
                fn (array $m) => $m['__no'] !== $targetNo
                    && ($isChild
                        ? self::isChildRelation($m)
                        : self::isSiblingRelation($m))
            ));

            if (empty($siblings) && ! $hasRelations) {
                // Pelamar anak tanpa kolom hubungan: saudara = anggota dengan
                // nama ayah/ibu yang sama.
                $siblings = array_values(array_filter(
                    $members,
                    fn (array $m) => $m['__no'] !== $targetNo
                        && self::hasSameParents($m, $target)
                ));
            }

            if (empty($siblings) && ! $hasRelations) {
                $siblings = $afterSecond;
            }

            $children = [];
        }

        return [
            'ayah'    => $ayah,
            'ibu'     => $ibu,
            'saudara' => array_map([self::class, 'toSibling'], $siblings),
            'anak'    => array_map([self::class, 'toChild'], $children),
        ];
    }

    private static function firstByRelation(array $members, array $needles): ?array
    {
        foreach ($members as $member) {
            $relation = strtoupper((string) ($member['hubungan'] ?? ''));

            foreach ($needles as $needle) {
                if (str_contains($relation, $needle)) {
                    return $member;
                }
            }
        }

        return null;
    }

    private static function hasSameParents(array $member, array $target): bool
    {
        $memberFather = $member['nama_ayah'] ?? '';
        $memberMother = $member['nama_ibu'] ?? '';
        $targetFather = $target['nama_ayah'] ?? '';
        $targetMother = $target['nama_ibu'] ?? '';

        if ($memberFather === '' && $memberMother === '') {
            return false;
        }

        return ($memberFather !== '' && $memberFather === $targetFather)
            || ($memberMother !== '' && $memberMother === $targetMother);
    }

    private static function isChildRelation(array $member): bool
    {
        return str_contains(strtoupper((string) ($member['hubungan'] ?? '')), 'ANAK');
    }

    private static function isSiblingRelation(array $member): bool
    {
        $relation = strtoupper((string) ($member['hubungan'] ?? ''));

        return str_contains($relation, 'ANAK') || str_contains($relation, 'SAUDARA');
    }

    /* ---------------------------------------------------------------------
     | Pencocokan
     |---------------------------------------------------------------------*/

    /**
     * Cari baris pelamar: NIK dulu (paling akurat), lalu nama.
     */
    private static function findTarget(array $members, ?string $nik, ?string $nama): ?array
    {
        $nik = self::digits($nik);

        if ($nik !== '') {
            foreach ($members as $member) {
                if ($member['nik'] !== '' && $member['nik'] === $nik) {
                    return $member;
                }
            }
        }

        $nama = self::cleanName($nama);

        if ($nama === '') {
            return null;
        }

        $exact = [];

        foreach ($members as $member) {
            if ($member['nama'] === $nama) {
                $exact[] = $member;
            }
        }

        if (count($exact) === 1) {
            return $exact[0];
        }

        if (count($exact) > 1) {
            // Nama sama persis lebih dari satu (mis. ayah & anak) dan NIK tidak
            // membantu → jangan menebak baris yang salah.
            return null;
        }

        // Nama di KTP kadang diberi gelar/singkatan → cocokkan longgar.
        // Hanya pakai bila hasilnya tunggal; kalau ambigu (mis. nama ayah dan
        // anak sama-sama mengandung nama KTP), jangan menebak.
        $loose = [];

        foreach ($members as $member) {
            if (strlen($nama) >= 4 && str_contains($member['nama'], $nama)) {
                $loose[] = $member;

                continue;
            }

            if (strlen($member['nama']) >= 4 && str_contains($nama, $member['nama'])) {
                $loose[] = $member;
            }
        }

        return count($loose) === 1 ? $loose[0] : null;
    }

    /**
     * Data orang tua: nama dari kolom baris pelamar + detail dari tabel
     * anggota bila namanya ditemukan di sana.
     */
    private static function parent(array $members, ?string $name): ?array
    {
        $name = self::cleanName($name);

        if ($name === '') {
            return null;
        }

        $row = self::findByName($members, $name);

        if ($row === null) {
            // KK berbeda / nama tidak terdaftar → detail sengaja dikosongkan.
            return ['nama' => $name, 'tgl_lahir' => null, 'pendidikan' => null, 'pekerjaan' => null];
        }

        return [
            'nama'       => $row['nama'],
            'tgl_lahir'  => $row['tgl_lahir'],
            'pendidikan' => $row['pendidikan'],
            'pekerjaan'  => $row['pekerjaan'],
        ];
    }

    private static function findByName(array $members, string $name): ?array
    {
        // Sengaja exact match: kalau nama di kolom orang tua berbeda dengan
        // tabel anggota, artinya KK sudah beda → detail jangan diambil.
        foreach ($members as $member) {
            if ($member['nama'] === $name) {
                return $member;
            }
        }

        return null;
    }

    /* ---------------------------------------------------------------------
     | Pemetaan ke bentuk field form
     |---------------------------------------------------------------------*/

    private static function toSibling(array $member): array
    {
        return [
            'nama'       => $member['nama'],
            'tgl_lahir'  => $member['tgl_lahir'],
            'gender'     => $member['gender'],
            'pendidikan' => $member['pendidikan'],
            'pekerjaan'  => $member['pekerjaan'],
        ];
    }

    private static function toChild(array $member): array
    {
        return [
            'nama'         => $member['nama'],
            'tempat_lahir' => $member['tempat_lahir'],
            'tgl_lahir'    => $member['tgl_lahir'],
            'gender'       => $member['gender'],
            'pendidikan'   => $member['pendidikan'],
            'status'       => $member['pekerjaan'],
        ];
    }

    /* ---------------------------------------------------------------------
     | Normalisasi
     |---------------------------------------------------------------------*/

    private static function normalizeRows(array $rows): array
    {
        $out      = [];
        $sequence = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $sequence++;

            $nama = self::cleanName($row['nama'] ?? $row['name'] ?? '');

            if ($nama === '') {
                continue;
            }

            $no = (int) ($row['no'] ?? 0);

            if ($no <= 0) {
                $no = $sequence;
            }

            $out[] = [
                '__no'         => $no,
                'nama'         => $nama,
                'nik'          => self::digits($row['nik'] ?? ''),
                'tempat_lahir' => self::title($row['tempat_lahir'] ?? ''),
                'tgl_lahir'    => self::date($row['tgl_lahir'] ?? ''),
                'gender'       => self::gender($row['gender'] ?? ($row['jenis_kelamin'] ?? '')),
                'pendidikan'   => self::education($row['pendidikan'] ?? ''),
                'pekerjaan'    => self::title($row['pekerjaan'] ?? ''),
                'hubungan'     => self::cleanName($row['hubungan'] ?? ''),
                'nama_ayah'    => self::cleanName($row['nama_ayah'] ?? ($row['ayah'] ?? '')),
                'nama_ibu'     => self::cleanName($row['nama_ibu'] ?? ($row['ibu'] ?? '')),
            ];
        }

        usort($out, fn (array $a, array $b) => $a['__no'] <=> $b['__no']);

        return $out;
    }

    private static function cleanName($value): string
    {
        $value = strtoupper((string) $value);
        $value = preg_replace('/[^A-Z0-9 ]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private static function title($value): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');

        if ($value === '' || $value === '-' || strtoupper($value) === 'TIDAK ADA') {
            return null;
        }

        return $value;
    }

    private static function digits($value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private static function date($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '-') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }

        if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})$#', $value, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
    }

    private static function gender($value): ?string
    {
        $value = strtoupper(trim((string) $value));

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'L') || str_contains($value, 'LAKI')) {
            return 'Laki-laki';
        }

        if (str_starts_with($value, 'P') || str_contains($value, 'PEREMPUAN') || str_contains($value, 'WANITA')) {
            return 'Perempuan';
        }

        return null;
    }

    private static function education($value): ?string
    {
        $value = strtoupper(trim((string) $value));

        if ($value === '' || str_contains($value, 'TIDAK') || str_contains($value, 'BELUM')) {
            return null;
        }

        foreach (self::EDUCATION_MAP as $needle => $label) {
            if (preg_match('/\b' . preg_quote($needle, '/') . '\b/', $value)) {
                return $label;
            }
        }

        if (str_contains($value, 'SLTA') || str_contains($value, 'SMU') || str_contains($value, 'SMA') || str_contains($value, 'SMK')) {
            return 'SMA/SMK';
        }

        if (str_contains($value, 'SLTP') || str_contains($value, 'SMP')) {
            return 'SMP';
        }

        if (preg_match('/\bSD\b/', $value)) {
            return 'SD';
        }

        return null;
    }
}
