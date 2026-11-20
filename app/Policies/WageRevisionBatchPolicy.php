<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WageRevisionBatch;
use App\Services\PayrollRoleFilterService;
use App\Services\WageRevisionAccess;

/**
 * Gate for the wage revision screens. Discovery is automatic for WageRevisionBatch.
 */
class WageRevisionBatchPolicy
{
    private function role(?User $user): ?string
    {
        return PayrollRoleFilterService::getRole($user);
    }

    private function isAdmin(?User $user): bool
    {
        return (bool) $user?->hasRole('Admin');
    }

    public function viewAny(?User $user): bool
    {
        return $this->isAdmin($user) || PayrollRoleFilterService::isRegistered($this->role($user));
    }

    public function view(?User $user, WageRevisionBatch $batch): bool
    {
        return $this->viewAny($user);
    }

    public function create(?User $user): bool
    {
        return $this->isAdmin($user) || PayrollRoleFilterService::isRegistered($this->role($user));
    }

    public function update(?User $user, WageRevisionBatch $batch): bool
    {
        return $batch->isEditable() && $this->create($user);
    }

    /** Isi/ubah/import gaji staff & expat. */
    public function manageStaff(?User $user, WageRevisionBatch $batch): bool
    {
        return $batch->isEditable()
            && WageRevisionAccess::canManageStaffSegment($this->role($user), $this->isAdmin($user));
    }

    /** Generate/ubah item non-staff. */
    public function manageNonStaff(?User $user, WageRevisionBatch $batch): bool
    {
        return $batch->isEditable()
            && WageRevisionAccess::canManageNonStaffSegment($this->role($user), $this->isAdmin($user));
    }

    public function approve(?User $user, WageRevisionBatch $batch): bool
    {
        return $batch->isEditable()
            && WageRevisionAccess::canApprove($this->role($user), $this->isAdmin($user));
    }

    public function apply(?User $user, WageRevisionBatch $batch): bool
    {
        return in_array($batch->status, [WageRevisionBatch::STATUS_APPROVED, WageRevisionBatch::STATUS_APPLYING], true)
            && WageRevisionAccess::canApply($this->role($user), $this->isAdmin($user));
    }

    public function rollback(?User $user, WageRevisionBatch $batch): bool
    {
        return in_array($batch->status, [WageRevisionBatch::STATUS_APPLIED, WageRevisionBatch::STATUS_APPLYING], true)
            && WageRevisionAccess::canRollback($this->role($user), $this->isAdmin($user));
    }

    public function delete(?User $user, WageRevisionBatch $batch): bool
    {
        return $batch->isEditable() && ($this->isAdmin($user) || $this->role($user) === PayrollRoleFilterService::ROLE_ALL);
    }

    /**
     * Buka kunci batch yang sudah dibatalkan supaya bisa diproses lagi.
     * Hanya Admin: pemrosesan ulang setelah pembatalan harus lewat admin.
     */
    public function unlock(?User $user, WageRevisionBatch $batch): bool
    {
        return $batch->isRolledBack() && $this->isAdmin($user);
    }
}
