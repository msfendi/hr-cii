<?php

namespace App\Services;

use App\Models\WageRevisionItem;

/**
 * Single place that decides who may build, import, approve or apply a wage revision.
 *
 * The payroll role comes from `role_payrolls.payroll_role` through
 * PayrollRoleFilterService. Admin is a separate Spatie role and must never be inferred
 * from a missing payroll role (deny by default).
 */
class WageRevisionAccess
{
    public const STAFF_SEGMENT_ROLES = [
        PayrollRoleFilterService::ROLE_STAFF,
        PayrollRoleFilterService::ROLE_ALL,
    ];

    public const NON_STAFF_SEGMENT_ROLES = [
        PayrollRoleFilterService::ROLE_NONSTAFF,
        PayrollRoleFilterService::ROLE_SEWING,
        PayrollRoleFilterService::ROLE_NONSEWING,
        PayrollRoleFilterService::ROLE_ALL,
    ];

    public static function canManageSegment(string $segment, ?string $payrollRole, bool $isAdmin): bool
    {
        if ($isAdmin) {
            return true;
        }

        return match ($segment) {
            WageRevisionItem::SEGMENT_STAFF_EXPAT => in_array($payrollRole, self::STAFF_SEGMENT_ROLES, true),
            WageRevisionItem::SEGMENT_NON_STAFF   => in_array($payrollRole, self::NON_STAFF_SEGMENT_ROLES, true),
            default                               => false,
        };
    }

    public static function canManageStaffSegment(?string $payrollRole, bool $isAdmin): bool
    {
        return self::canManageSegment(WageRevisionItem::SEGMENT_STAFF_EXPAT, $payrollRole, $isAdmin);
    }

    public static function canManageNonStaffSegment(?string $payrollRole, bool $isAdmin): bool
    {
        return self::canManageSegment(WageRevisionItem::SEGMENT_NON_STAFF, $payrollRole, $isAdmin);
    }

    /**
     * Approve, apply and rollback move money for both segments at once, so they stay with
     * Payroll_ALL / Admin.
     */
    public static function canApprove(?string $payrollRole, bool $isAdmin): bool
    {
        return $isAdmin || $payrollRole === PayrollRoleFilterService::ROLE_ALL;
    }

    public static function canApply(?string $payrollRole, bool $isAdmin): bool
    {
        return self::canApprove($payrollRole, $isAdmin);
    }

    public static function canRollback(?string $payrollRole, bool $isAdmin): bool
    {
        return self::canApprove($payrollRole, $isAdmin);
    }

    /**
     * Which segment an employee belongs to. IS_EXPAT is treated as staff level because expat
     * pay is negotiated per person, never by a uniform percentage.
     */
    public static function segmentFor(bool $isStaff, bool $isExpat): string
    {
        return ($isStaff || $isExpat)
            ? WageRevisionItem::SEGMENT_STAFF_EXPAT
            : WageRevisionItem::SEGMENT_NON_STAFF;
    }
}
