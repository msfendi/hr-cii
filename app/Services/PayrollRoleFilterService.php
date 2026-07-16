<?php

namespace App\Services;

use App\Models\User;

class PayrollRoleFilterService
{
    public const ROLE_ALL       = 'Payroll_ALL';
    public const ROLE_STAFF     = 'Payroll_STAFF';
    public const ROLE_NONSTAFF  = 'Payroll_NONSTAFF';
    public const ROLE_SEWING    = 'Payroll_SEWING';
    public const ROLE_NONSEWING = 'Payroll_NONSEWING';

    public const PAYROLL_ROLES = [
        self::ROLE_STAFF,
        self::ROLE_NONSTAFF,
        self::ROLE_SEWING,
        self::ROLE_NONSEWING,
        self::ROLE_ALL,
    ];

    /**
     * Ambil payroll_role efektif milik user dari table role_payrolls.
     * Sumber kebenaran SEKARANG adalah table role_payrolls, BUKAN lagi
     * nama Spatie role (auth()->user()->roles->first()->name).
     *
     * Return null artinya user tidak punya assignment payroll_role sama sekali
     * (mis. Admin/HRD yang aksesnya dihandle terpisah lewat hasRole('Admin'), dsb).
     */
    public static function getRole(?User $user): ?string
    {
        if (!$user) {
            return null;
        }

        return optional($user->rolePayroll)->payroll_role;
    }

    public static function isAll(?string $role): bool
    {
        return $role === self::ROLE_ALL;
    }

    /**
     * Apakah role ini VALID/terdaftar di role_payrolls (salah satu dari 5 payroll role)?
     */
    public static function isRegistered(?string $role): bool
    {
        return in_array($role, self::PAYROLL_ROLES, true);
    }

    /**
     * Terapkan filter ke Query Builder (DB::table(...)) berdasarkan payroll_role.
     * $staffColumn / $sewingColumn boleh diisi dengan alias table, mis. 'emp.IS_STAFF'.
     *
     * ATURAN (deny by default):
     * - Payroll_ALL         -> tidak difilter, semua data tampil.
     * - STAFF/NONSTAFF/dst  -> difilter sesuai kategori.
     * - null / role lain    -> user BELUM terdaftar di role_payrolls -> data DIKOSONGKAN.
     *   (Admin/role lain yang memang harus bypass wajib dicek terpisah lewat
     *   hasRole('Admin') di controller SEBELUM memanggil method ini.)
     */
    public static function applyToQuery($query, ?string $role, string $staffColumn = 'IS_STAFF', string $sewingColumn = 'IS_SEWING')
    {
        if (self::isAll($role)) {
            return $query;
        }

        switch ($role) {
            case self::ROLE_STAFF:
                return $query->where($staffColumn, 1);

            case self::ROLE_NONSTAFF:
                return $query->where($staffColumn, 0);

            case self::ROLE_SEWING:
                return $query->where($staffColumn, 0)->where($sewingColumn, 0);

            case self::ROLE_NONSEWING:
                return $query->where($staffColumn, 0)->where($sewingColumn, 1);
        }

        // Belum terdaftar di role_payrolls -> jangan tampilkan data apapun
        return $query->whereRaw('1 = 0');
    }

    /**
     * Filter koleksi/array baris hasil query (stdClass, array, atau Eloquent) yang
     * punya field IS_STAFF / IS_SEWING (atau custom key, mis. 'is_staff','is_sewing').
     *
     * Aturan sama seperti applyToQuery(): deny by default kalau belum terdaftar.
     */
    public static function filterCollection($rows, ?string $role, string $staffKey = 'IS_STAFF', string $sewingKey = 'IS_SEWING')
    {
        $collection = collect($rows);

        if (self::isAll($role)) {
            return $collection->values();
        }

        if (!self::isRegistered($role)) {
            // Belum terdaftar di role_payrolls -> data dikosongkan
            return collect();
        }

        return $collection->filter(function ($row) use ($role, $staffKey, $sewingKey) {

            $isStaff  = is_array($row) ? ($row[$staffKey] ?? 0) : ($row->{$staffKey} ?? 0);
            $isSewing = is_array($row) ? ($row[$sewingKey] ?? 0) : ($row->{$sewingKey} ?? 0);

            $isStaff  = (int) $isStaff;
            $isSewing = (int) $isSewing;

            switch ($role) {
                case self::ROLE_STAFF:
                    return $isStaff === 1;

                case self::ROLE_NONSTAFF:
                    return $isStaff === 0;

                case self::ROLE_SEWING:
                    return $isStaff === 0 && $isSewing === 0;

                case self::ROLE_NONSEWING:
                    return $isStaff === 0 && $isSewing === 1;
            }

            return false;
        })->values();
    }

    /**
     * Folder penyimpanan export sesuai payroll_role (dipakai di index_blade.php).
     */
    public static function folder(?string $role): string
    {
        return match ($role) {
            self::ROLE_STAFF     => 'STAFF/',
            self::ROLE_NONSTAFF  => 'NON_STAFF/',
            self::ROLE_SEWING    => 'SEWING/',
            self::ROLE_NONSEWING => 'NON_SEWING/',
            default              => '', // Payroll_ALL atau null
        };
    }

    /**
     * Dipakai untuk canSeeSalary di process_blade.php / details().
     * Admin/Audit/Management tetap dihandle terpisah lewat hasRole di controller/blade,
     * di sini cukup cek apakah user punya payroll_role assignment yang valid
     * (termasuk Payroll_ALL). Kalau belum terdaftar (null) -> false (gaji disamarkan).
     */
    public static function canSeeSalary(?string $role): bool
    {
        return self::isRegistered($role);
    }
}
