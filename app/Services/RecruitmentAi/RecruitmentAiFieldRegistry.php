<?php

namespace App\Services\RecruitmentAi;

/**
 * Registry field & dokumen Form Recruitment (versi AI).
 *
 * Satu sumber kebenaran untuk:
 *  - daftar dokumen yang boleh diunggah,
 *  - daftar section (accordion) + field di dalamnya,
 *  - field mana saja yang boleh diekstrak AI dari dokumen tertentu,
 *  - bentuk JSON schema yang dikirim ke model AI.
 *
 * Sengaja berada di file TERPISAH (bukan di RecruitmentFormController) supaya
 * alur rekrutmen lama tidak tersentuh sama sekali.
 */
class RecruitmentAiFieldRegistry
{
    /** Status perkawinan */
    public const STATUS_PERNIKAHAN = ['BM', 'M', 'CH', 'CM'];

    /** Label status perkawinan (nilai tetap singkatan, seperti form lama) */
    public const STATUS_PERNIKAHAN_LABELS = [
        'BM' => 'Belum Menikah',
        'M'  => 'Menikah',
        'CH' => 'Cerai Hidup',
        'CM' => 'Cerai Mati',
    ];

    /** Tingkat pendidikan */
    public const PENDIDIKAN = ['SD', 'SMP', 'SMA', 'SMK', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'];

    /** Tingkat pendidikan untuk data ayah/ibu/saudara (sama dengan form lama) */
    public const PENDIDIKAN_AYAH = ['SD', 'SMP', 'SMA/SMK', 'D3', 'S1', 'S2', 'S3'];

    /** Tingkat pendidikan untuk data anak */
    public const PENDIDIKAN_ANAK = ['Belum Sekolah', 'TK', 'SD', 'SMP', 'SMA/SMK', 'D3', 'S1', 'S2', 'S3'];

    /** Agama */
    public const AGAMA = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Khonghucu'];

    /** Status domisili */
    public const STATUS_DOMISILI = ['Milik Sendiri', 'Sewa/Kontrak', 'Ikut Orang Tua'];

    /** Golongan darah */
    public const GOLONGAN_DARAH = ['A', 'B', 'AB', 'O'];

    /** Mode transportasi utama */
    public const TRANSPORTASI = [
        'Kendaraan Pribadi (Mobil)',
        'Kendaraan Pribadi (Motor)',
        'Kendaraan Umum',
        'Jalan Kaki',
    ];

    /** Jenis kelamin */
    public const JENIS_KELAMIN = ['L', 'P'];

    /**
     * Daftar dokumen rekrutmen.
     *
     * @return array<string, array{label:string, short:string, icon:string, color:string, required:bool, accept:string, hint:string, extract:bool}>
     */
    public static function documents(): array
    {
        return [
            'surat_lamaran'         => [
                'label'    => 'Surat Lamaran',
                'short'    => 'Lamaran',
                'icon'     => 'fa-file-signature',
                'color'    => 'info',
                'required' => true,
                'accept'   => 'pdf,jpg,jpeg,png',
                'hint'     => 'Position & motivation letter (PDF/JPG/PNG, maks 5MB)',
                'extract'  => true,
            ],
            'cv'                    => [
                'label'    => 'CV / Curriculum Vitae',
                'short'    => 'CV',
                'icon'     => 'fa-file-alt',
                'color'    => 'primary',
                'required' => true,
                'accept'   => 'pdf,jpg,jpeg,png',
                'hint'     => 'PDF/JPG/PNG hasil scan (maks 5MB)',
                'extract'  => true,
            ],
            'scan_ktp'              => [
                'label'    => 'Scan KTP',
                'short'    => 'KTP',
                'icon'     => 'fa-id-card',
                'color'    => 'secondary',
                'required' => true,
                'accept'   => 'pdf,jpg,jpeg,png',
                'hint'     => 'PDF/JPG/PNG, tulisan harus jelas (maks 5MB)',
                'extract'  => true,
            ],
            'scan_kk'               => [
                'label'    => 'Scan Kartu Keluarga',
                'short'    => 'KK',
                'icon'     => 'fa-users',
                'color'    => 'warning',
                'required' => true,
                'accept'   => 'pdf,jpg,jpeg,png',
                'hint'     => 'Kartu Keluarga asli / scan (PDF/JPG/PNG)',
                'extract'  => true,
            ],
            'pas_foto'              => [
                'label'    => 'Pas Foto',
                'short'    => 'Foto',
                'icon'     => 'fa-camera',
                'color'    => 'secondary',
                'required' => true,
                'accept'   => 'jpg,jpeg,png',
                'hint'     => 'Foto(Selfie) atau foto resmi 3x4 (maks 5MB)',
                'extract'  => false,
            ],
            'ijazah'                => [
                'label'    => 'Scan Ijazah',
                'short'    => 'Ijazah',
                'icon'     => 'fa-graduation-cap',
                'color'    => 'success',
                'required' => true,
                'accept'   => 'pdf,jpg,jpeg,png',
                'hint'     => 'Ijazah terakhir / transkrip (PDF/JPG/PNG)',
                'extract'  => true,
            ],
            'scan_akta_kelahiran'   => [
                'label'    => 'Scan Akta Kelahiran',
                'short'    => 'Akta',
                'icon'     => 'fa-certificate',
                'color'    => 'secondary',
                'required' => true,
                'accept'   => 'pdf,jpg,jpeg,png',
                'hint'     => 'Akta kelahiran (PDF/JPG/PNG)',
                'extract'  => true,
            ],
            'scan_skck'             => [
                'label'    => 'Scan SKCK',
                'short'    => 'SKCK',
                'icon'     => 'fa-heartbeat',
                'color'    => 'danger',
                'required' => true,
                'accept'   => 'pdf,jpg,jpeg,png',
                'hint'     => 'Catatan Kepolisian (PDF/JPG/PNG)',
                'extract'  => true,
            ],
            'scan_blanko_kesehatan' => [
                'label'    => 'Scan Blanko Kesehatan',
                'short'    => 'Kesehatan',
                'icon'     => 'fa-stethoscope',
                'color'    => 'info',
                'required' => false,
                'accept'   => 'pdf,jpg,jpeg,png',
                'hint'     => 'Opsional — form pemeriksaan kesehatan (PDF/JPG/PNG)',
                'extract'  => true,
            ],
        ];
    }

    /**
     * Ikon (Font Awesome 5) per field — dipakai untuk mempercantik input.
     *
     * @return array<string,string>
     */
    public static function fieldIcons(): array
    {
        return [
            // Data pribadi
            'nama_lengkap'      => 'fa-user',
            'nik'               => 'fa-id-card',
            'no_kk'             => 'fa-users',
            'sim'               => 'fa-id-badge',
            'tempat_lahir'      => 'fa-map-marker-alt',
            'tanggal_lahir'     => 'fa-calendar-alt',
            'jenis_kelamin'     => 'fa-mars',
            'golongan_darah'    => 'fa-tint',
            'agama'             => 'fa-pray',
            'status_pernikahan' => 'fa-ring',
            'kb'                => 'fa-female',
            'tanggungan'        => 'fa-user-friends',
            'warga_negara'      => 'fa-globe',
            'nomor_hp'          => 'fa-mobile-alt',
            'hobby'             => 'fa-futbol',
            'transportasi'      => 'fa-car',
            'pendidikan'        => 'fa-graduation-cap',
            'jabatan'           => 'fa-briefcase',
            'department'        => 'fa-building',
            'bpjs_tk'           => 'fa-shield-alt',
            'bpjs_kes'          => 'fa-heartbeat',

            // Alamat & kontak
            'alamat_asal'              => 'fa-map-marker-alt',
            'kab_kota_asal'            => 'fa-city',
            'status_domisili_asal'     => 'fa-home',
            'alamat_sekarang'          => 'fa-map-marker-alt',
            'kab_kota_sekarang'        => 'fa-city',
            'status_domisili_sekarang' => 'fa-home',
            'no_telepon'               => 'fa-phone',
            'nama_darurat'             => 'fa-user-shield',
            'hubungan_darurat'         => 'fa-link',
            'no_telepon_darurat'       => 'fa-phone',

            // Riwayat pendidikan
            'education'            => 'fa-graduation-cap',
            'education.tingkat'    => 'fa-layer-group',
            'education.institusi'  => 'fa-school',
            'education.jurusan'    => 'fa-book-open',
            'education.dari'       => 'fa-calendar-alt',
            'education.sampai'     => 'fa-calendar-check',
            'education.lulus'      => 'fa-check-circle',

            // Pengalaman kerja
            'experiences'          => 'fa-briefcase',
            'experiences.perusahaan' => 'fa-building',
            'experiences.dari'     => 'fa-calendar-alt',
            'experiences.sampai'   => 'fa-calendar-check',
            'experiences.masih_bekerja' => 'fa-briefcase',
            'experiences.jabatan'  => 'fa-user-tie',
            'experiences.departemen' => 'fa-sitemap',
            'experiences.alasan'   => 'fa-sign-out-alt',

            // Keluarga
            'ayah'                => 'fa-user-tie',
            'ibu'                 => 'fa-user-tie',
            'ayah.nama'           => 'fa-user',
            'ayah.tgl_lahir'      => 'fa-calendar-alt',
            'ayah.pendidikan'     => 'fa-graduation-cap',
            'ayah.pekerjaan'      => 'fa-briefcase',
            'ibu.nama'            => 'fa-user',
            'ibu.tgl_lahir'       => 'fa-calendar-alt',
            'ibu.pendidikan'      => 'fa-graduation-cap',
            'ibu.pekerjaan'       => 'fa-briefcase',
            'saudara'             => 'fa-user-friends',
            'saudara.nama'        => 'fa-user',
            'saudara.tgl_lahir'   => 'fa-calendar-alt',
            'saudara.gender'      => 'fa-mars',
            'saudara.pendidikan'  => 'fa-graduation-cap',
            'saudara.pekerjaan'   => 'fa-briefcase',
            'anak'                => 'fa-child',
            'anak.nama'           => 'fa-user',
            'anak.tempat_lahir'   => 'fa-map-marker-alt',
            'anak.tgl_lahir'      => 'fa-calendar-alt',
            'anak.gender'         => 'fa-mars',
            'anak.pendidikan'     => 'fa-graduation-cap',
            'anak.status'         => 'fa-heart',

            // Motivasi & fisik
            'motivasi'        => 'fa-bullhorn',
            'kegiatan_ekstra' => 'fa-medal',
            'tinggi_badan'    => 'fa-ruler-vertical',
            'berat_badan'     => 'fa-weight-hanging',
        ];
    }

    /**
     * Section (accordion) beserta field-nya.
     *
     * @return array<string, array{title:string, icon:string, tone:string, fields:array<string, array>}>
     */
    public static function sections(): array
    {
        return [
            'pribadi' => [
                'title' => 'Data Pribadi',
                'icon'  => 'fa-id-card',
                'tone'  => 'primary',
                'fields' => [
                    // Identitas pelamar berpusat pada KTP — KK hanya dipakai untuk
                    // data keluarga (lihat KkFamilyResolver), bukan identitas.
                    'nama_lengkap'      => self::text('Nama Lengkap', true, ['scan_ktp', 'scan_akta_kelahiran', 'scan_skck', 'cv', 'surat_lamaran']),
                    'nik'               => self::text('NIK', true, ['scan_ktp', 'scan_skck', 'cv'], '16 digit sesuai KTP', 'digits'),
                    'no_kk'             => self::text('No. Kartu Keluarga', true, ['scan_kk'], '16 digit sesuai KK', 'digits'),
                    'sim'               => self::text('No. SIM', false, ['scan_ktp', 'cv']),
                    'tempat_lahir'      => self::text('Tempat Lahir', true, ['scan_ktp', 'scan_akta_kelahiran', 'scan_skck']),
                    'tanggal_lahir'     => self::date('Tanggal Lahir', true, ['scan_ktp', 'scan_akta_kelahiran', 'scan_skck', 'cv']),
                    'jenis_kelamin'     => self::radio('Jenis Kelamin', true, self::JENIS_KELAMIN, ['L' => 'Laki-laki', 'P' => 'Perempuan'], ['scan_ktp', 'scan_akta_kelahiran', 'scan_skck']),
                    'golongan_darah'    => self::select('Golongan Darah', false, self::GOLONGAN_DARAH, ['scan_ktp', 'scan_skck', 'scan_blanko_kesehatan']),
                    'agama'             => self::select('Agama', false, self::AGAMA, ['scan_ktp', 'scan_akta_kelahiran', 'scan_skck']),
                    'status_pernikahan' => self::select('Status Pernikahan', true, self::STATUS_PERNIKAHAN, ['scan_ktp'], false, self::STATUS_PERNIKAHAN_LABELS),
                    'kb'                => self::radio('Mengikuti KB?', false, ['Ya', 'Tidak'], [], ['scan_ktp', 'cv'], 'Tidak'),
                    'tanggungan'        => self::number('Jumlah Tanggungan', false, ['scan_ktp', 'scan_kk']),
                    'warga_negara'      => self::select('Kewarganegaraan', false, ['WNI', 'WNA'], ['scan_ktp', 'scan_akta_kelahiran', 'cv']),
                    'nomor_hp'          => self::text('Nomor HP', true, ['cv', 'scan_ktp', 'surat_lamaran'], '08xxxxxxxxxx', 'phone'),
                    'hobby'             => self::text('Bakat / Hobby', false, ['cv', 'surat_lamaran']),
                    'transportasi'      => self::select('Mode Transportasi Utama', false, self::TRANSPORTASI, ['cv', 'surat_lamaran']),
                    'pendidikan'        => self::select('Pendidikan Terakhir', true, self::PENDIDIKAN, ['scan_ktp', 'ijazah', 'cv', 'scan_skck']),
                    // Department & posisi diisi dari menu career, bukan dari AI.
                    'department'        => self::select('Department', false, [], [], true),
                    'jabatan'           => self::select('Posisi yang Dilamar', false, [], [], true),
                    'bpjs_tk'           => self::text('No. BPJS Ketenagakerjaan', false, ['scan_ktp', 'cv'], '', 'digits'),
                    'bpjs_kes'          => self::text('No. BPJS Kesehatan', false, ['scan_ktp', 'cv'], '', 'digits'),
                ],
            ],

            'alamat' => [
                'title' => 'Alamat & Kontak',
                'icon'  => 'fa-map-marker-alt',
                'tone'  => 'info',
                'fields' => [
                    // Alamat asal murni dari KTP (KK bisa beda alamat / beda orang).
                    'status_domisili_asal'     => self::select('Status Domisili Asal', false, self::STATUS_DOMISILI, []),
                    'kab_kota_asal'            => self::text('Kabupaten/Kota Asal', true, ['scan_ktp']),
                    'alamat_asal'              => self::textarea('Alamat Asal', true, ['scan_ktp']) + ['width' => 12],
                    'alamat_sekarang'          => self::textarea('Alamat Sekarang', false, ['scan_kk', 'cv']) + [
                        'width' => 12,
                        'copy_from' => [
                            'label'   => 'Sama dengan alamat asal',
                            'sources' => ['alamat_asal', 'kab_kota_asal', 'status_domisili_asal'],
                            'targets' => ['alamat_sekarang', 'kab_kota_sekarang', 'status_domisili_sekarang'],
                        ],
                    ],
                    'status_domisili_sekarang' => self::select('Status Domisili Sekarang', false, self::STATUS_DOMISILI, ['scan_kk', 'cv']),
                    'kab_kota_sekarang'        => self::text('Kabupaten/Kota Sekarang', false, ['scan_kk', 'cv']),
                    'no_telepon'               => self::text('No. Telepon', false, ['cv', 'scan_kk'], '', 'phone'),
                    'nama_darurat'             => self::text('Nama Kontak Darurat', true, ['scan_kk', 'cv']),
                    'hubungan_darurat'         => self::text('Hubungan dengan Pelamar', true, ['scan_kk', 'cv']),
                    'no_telepon_darurat'       => self::text('No. Telepon Darurat', true, ['scan_kk', 'cv'], '', 'phone'),
                ],
            ],

            'pendidikan' => [
                'title' => 'Riwayat Pendidikan',
                'icon'  => 'fa-graduation-cap',
                'tone'  => 'success',
                'fields' => [
                    'education' => self::list('Riwayat Pendidikan', [
                        'tingkat'   => ['label' => 'Tingkat', 'type' => 'select', 'options' => self::PENDIDIKAN],
                        'institusi' => ['label' => 'Nama Institusi', 'type' => 'text'],
                        'jurusan'   => ['label' => 'Jurusan', 'type' => 'text'],
                        'dari'      => ['label' => 'Tahun Masuk', 'type' => 'number'],
                        'sampai'    => ['label' => 'Tahun Lulus', 'type' => 'number'],
                        'lulus'     => ['label' => 'Sudah Lulus?', 'type' => 'boolean'],
                    ], ['ijazah', 'cv'], 'Tambahkan riwayat pendidikan'),
                ],
            ],

            'pengalaman' => [
                'title' => 'Pengalaman Kerja',
                'icon'  => 'fa-briefcase',
                'tone'  => 'warning',
                'fields' => [
                    'experiences' => self::list('Pengalaman Kerja', [
                        'perusahaan'    => ['label' => 'Nama Perusahaan', 'type' => 'text'],
                        'jabatan'       => ['label' => 'Jabatan', 'type' => 'text'],
                        'departemen'    => ['label' => 'Departemen', 'type' => 'text'],
                        'dari'          => ['label' => 'Mulai (YYYY-MM)', 'type' => 'month'],
                        'sampai'        => ['label' => 'Selesai (YYYY-MM)', 'type' => 'month'],
                        'masih_bekerja' => ['label' => 'Masih Bekerja?', 'type' => 'boolean'],
                        'alasan'        => ['label' => 'Alasan Berpindah', 'type' => 'textarea'],
                    ], ['cv'], 'Tambahkan pengalaman kerja'),
                ],
            ],

            'keluarga' => [
                'title' => 'Data Keluarga',
                'icon'  => 'fa-users',
                'tone'  => 'danger',
                'fields' => [
                    // Ayah/ibu/saudara/anak dari KK dihitung oleh KkFamilyResolver
                    // setelah baris KK + identitas KTP diketahui. Akta hanya
                    // dipakai sebagai cadangan untuk nama orang tua.
                    'ayah' => self::group('Data Ayah', [
                        'nama'       => ['label' => 'Nama Ayah', 'type' => 'text'],
                        'tgl_lahir'  => ['label' => 'Tanggal Lahir Ayah', 'type' => 'date'],
                        'pendidikan' => ['label' => 'Pendidikan Ayah', 'type' => 'select', 'options' => self::PENDIDIKAN_AYAH],
                        'pekerjaan'  => ['label' => 'Pekerjaan Ayah', 'type' => 'text'],
                    ], ['scan_akta_kelahiran']),
                    'ibu' => self::group('Data Ibu', [
                        'nama'       => ['label' => 'Nama Ibu', 'type' => 'text'],
                        'tgl_lahir'  => ['label' => 'Tanggal Lahir Ibu', 'type' => 'date'],
                        'pendidikan' => ['label' => 'Pendidikan Ibu', 'type' => 'select', 'options' => self::PENDIDIKAN_AYAH],
                        'pekerjaan'  => ['label' => 'Pekerjaan Ibu', 'type' => 'text'],
                    ], ['scan_akta_kelahiran']),
                    'saudara' => self::list('Saudara Kandung', [
                        'nama'       => ['label' => 'Nama Saudara', 'type' => 'text'],
                        'tgl_lahir'  => ['label' => 'Tanggal Lahir', 'type' => 'date'],
                        'gender'     => ['label' => 'Jenis Kelamin', 'type' => 'radio', 'options' => ['Laki-laki', 'Perempuan']],
                        'pendidikan' => ['label' => 'Pendidikan', 'type' => 'select', 'options' => self::PENDIDIKAN_AYAH],
                        'pekerjaan'  => ['label' => 'Pekerjaan', 'type' => 'text'],
                    ], [], 'Tambahkan saudara'),
                    'anak' => self::list('Data Anak', [
                        'nama'          => ['label' => 'Nama Anak', 'type' => 'text'],
                        'tempat_lahir'  => ['label' => 'Tempat Lahir', 'type' => 'text'],
                        'tgl_lahir'     => ['label' => 'Tanggal Lahir', 'type' => 'date'],
                        'gender'        => ['label' => 'Jenis Kelamin', 'type' => 'radio', 'options' => ['Laki-laki', 'Perempuan']],
                        'pendidikan'    => ['label' => 'Pendidikan', 'type' => 'select', 'options' => self::PENDIDIKAN_ANAK],
                        'status'        => ['label' => 'Status / Pekerjaan', 'type' => 'text'],
                    ], [], 'Tambahkan anak'),
                ],
            ],

            'motivasi' => [
                'title' => 'Motivasi & Kegiatan',
                'icon'  => 'fa-bullhorn',
                'tone'  => 'violet',
                'fields' => [
                    'motivasi'       => self::textarea('Motivasi Melamar', false, [], 'Minimal 50 karakter'),
                    'kegiatan_ekstra' => self::textarea('Kegiatan Ekstra / Prestasi', false, ['cv', 'surat_lamaran']),
                ],
            ],

            'fisik' => [
                'title' => 'Data Fisik',
                'icon'  => 'fa-ruler-horizontal',
                'tone'  => 'slate',
                'fields' => [
                    'tinggi_badan' => self::number('Tinggi Badan (cm)', false, ['scan_blanko_kesehatan', 'scan_skck', 'scan_ktp']),
                    'berat_badan'  => self::number('Berat Badan (kg)', false, ['scan_blanko_kesehatan', 'scan_skck', 'scan_ktp']),
                ],
            ],
        ];
    }

    /**
     * Daftar semua field (flattened) => definisi + nama section.
     *
     * @return array<string, array>
     */
    public static function fields(): array
    {
        $fields = [];

        foreach (self::sections() as $sectionKey => $section) {
            foreach ($section['fields'] as $key => $definition) {
                $definition['section'] = $sectionKey;
                $definition['section_title'] = $section['title'];
                $fields[$key] = $definition;
            }
        }

        return $fields;
    }

    /**
     * Field yang boleh diekstrak AI dari dokumen tertentu.
     *
     * @return array<string, array>
     */
    public static function fieldsForDocument(string $document): array
    {
        $result = [];

        foreach (self::fields() as $key => $definition) {
            if (in_array($document, $definition['ai'] ?? [], true)) {
                $result[$key] = $definition;
            }
        }

        // Field bantu (tidak tampil di form): baris mentah tabel KK.
        // Dipakai KkFamilyResolver untuk mencocokkan orang di KTP dengan
        // kolom nama ayah/ibu dan nomor baris pada KK.
        foreach (self::extractionOnly($document) as $key => $definition) {
            $result[$key] = $definition;
        }

        return $result;
    }

    /**
     * Field yang hanya dipakai saat ekstraksi (tidak dirender ke form).
     *
     * @return array<string, array>
     */
    public static function extractionOnly(string $document): array
    {
        if ($document !== 'scan_kk') {
            return [];
        }

        return [
            'kk_members' => [
                'label'  => 'Baris tabel anggota keluarga',
                'type'   => 'list',
                'hidden' => true,
                'ai'     => ['scan_kk'],
                'items'  => [
                    'no'           => ['label' => 'Nomor urut baris', 'type' => 'number'],
                    'nama'         => ['label' => 'Nama anggota', 'type' => 'text'],
                    'nik'          => ['label' => 'NIK anggota', 'type' => 'text', 'format' => 'digits'],
                    'tempat_lahir' => ['label' => 'Tempat lahir', 'type' => 'text'],
                    'tgl_lahir'    => ['label' => 'Tanggal lahir', 'type' => 'date'],
                    'gender'       => ['label' => 'Jenis kelamin', 'type' => 'text'],
                    'pendidikan'   => ['label' => 'Pendidikan', 'type' => 'text'],
                    'pekerjaan'    => ['label' => 'Pekerjaan', 'type' => 'text'],
                    'hubungan'     => ['label' => 'Status hubungan dalam keluarga', 'type' => 'text'],
                    'nama_ayah'    => ['label' => 'Nama ayah (kolom nama orang tua)', 'type' => 'text'],
                    'nama_ibu'     => ['label' => 'Nama ibu (kolom nama orang tua)', 'type' => 'text'],
                ],
            ],
        ];
    }

    /* ---------------------------------------------------------------------
     | Helper pembentuk definisi field
     |---------------------------------------------------------------------*/

    private static function text(string $label, bool $required, array $ai, string $placeholder = '', ?string $format = null): array
    {
        return [
            'label' => $label, 'type' => 'text', 'required' => $required,
            'ai' => $ai, 'placeholder' => $placeholder, 'format' => $format,
        ];
    }

    private static function textarea(string $label, bool $required, array $ai, string $placeholder = ''): array
    {
        return [
            'label' => $label, 'type' => 'textarea', 'required' => $required,
            'ai' => $ai, 'placeholder' => $placeholder,
        ];
    }

    private static function number(string $label, bool $required, array $ai): array
    {
        return [
            'label' => $label, 'type' => 'number', 'required' => $required,
            'ai' => $ai, 'placeholder' => '',
        ];
    }

    private static function date(string $label, bool $required, array $ai): array
    {
        return [
            'label' => $label, 'type' => 'date', 'required' => $required,
            'ai' => $ai, 'placeholder' => '',
        ];
    }

    private static function select(string $label, bool $required, array $options, array $ai, bool $dynamic = false, array $labels = []): array
    {
        return [
            'label' => $label, 'type' => 'select', 'required' => $required,
            'options' => $options, 'labels' => $labels, 'ai' => $ai, 'placeholder' => '',
            'dynamic' => $dynamic,
        ];
    }

    /**
     * Field pilihan tunggal yang tampil sebagai tombol radio.
     *
     * @param  array<string,string>  $labels  label tampilan per nilai (opsional)
     */
    private static function radio(string $label, bool $required, array $options, array $labels, array $ai, ?string $default = null): array
    {
        return [
            'label' => $label, 'type' => 'radio', 'required' => $required,
            'options' => $options, 'labels' => $labels, 'default' => $default,
            'ai' => $ai, 'placeholder' => '',
        ];
    }

    /**
     * Field berbentuk grup (ayah / ibu).
     */
    private static function group(string $label, array $items, array $ai): array
    {
        return [
            'label' => $label, 'type' => 'group', 'required' => false,
            'items' => $items, 'ai' => $ai, 'placeholder' => '',
        ];
    }

    /**
     * Field berupa daftar berulang (riwayat pendidikan, pengalaman, dsb).
     */
    private static function list(string $label, array $items, array $ai, string $addLabel): array
    {
        return [
            'label' => $label, 'type' => 'list', 'required' => false,
            'items' => $items, 'ai' => $ai, 'add_label' => $addLabel, 'placeholder' => '',
        ];
    }
}
