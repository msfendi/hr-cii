<?php

/**
 * Konfigurasi Work Assignment Request (Surat Ijin Dinas Luar).
 *
 * Dipisah ke config supaya pemetaan peran approver bisa disesuaikan tanpa
 * mengubah kode.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Kolom tanda tangan pada dokumen
    |--------------------------------------------------------------------------
    | Urutannya mengikuti form CII.FM.HR.02.08. `key` dipakai sebagai
    | role_key pada snapshot approval_chain & answers.
    */
    'signature_columns' => [
        ['key' => 'yang_diberi_tugas',   'label' => 'Yang diberi Tugas'],
        ['key' => 'manager_departemen',  'label' => 'Manager Departemen'],
        ['key' => 'hr_manager',          'label' => 'HR Manager'],
        ['key' => 'general_manager',     'label' => 'General Manager'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pemetaan jabatan approver → kolom tanda tangan
    |--------------------------------------------------------------------------
    | approval_rules.name berisi jabatan approver (mis. "MANAGER SEWING",
    | "HRD MANAGER", "SECTION HEAD A"). Pencocokan case-insensitive dengan
    | str_contains, urut sesuai daftar di bawah (yang lebih spesifik didahulukan).
    | Tidak cocok → jatuh ke fallback.
    */
    'role_matchers' => [
        'general_manager'    => ['GENERAL MANAGER', 'G.M'],
        'hr_manager'         => ['MANAGER HR', 'HR MANAGER', 'HRD MANAGER', 'HRD', 'HUMAN RESOURCE', 'PERSONALIA'],
        'manager_departemen' => ['MANAGER', 'KEPALA BAGIAN', 'KA.'],
        'yang_diberi_tugas'  => ['LEADER', 'SECTION HEAD', 'SUPERVISOR', 'ASS', 'HOD', 'ADMIN'],
    ],

    /** role_key default kalau tidak ada pola yang cocok. */
    'default_role' => 'yang_diberi_tugas',

    /*
    |--------------------------------------------------------------------------
    | Batas tanggal pengajuan
    |--------------------------------------------------------------------------
    | Surat izin dinas boleh diajukan mundur (mis. lupa input) tapi dibatasi,
    | dan ke depan juga dibatasi supaya tidak salah tanggal jauh.
    */
    'back_days'    => 30,
    'forward_days' => 90,

    /** Maksimal jumlah tanggal dinas dalam satu pengajuan. */
    'max_dates' => 31,
];
