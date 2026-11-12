<?php

namespace App\Services\WorkAssignment;

use App\Models\ApprovalDept;
use App\Services\WorkAssignment\EmployeeLookupService;
use Throwable;

/**
 * Membangun snapshot chain approval untuk Work Assignment Request.
 *
 * Sumber: approval_depts + approval_rules (master yang sama dipakai pengajuan
 * cuti) — dipilih berdasarkan departemen + section karyawan.
 *
 * Hasil disimpan sebagai JSON di `work_assignment_requests.approval_chain`
 * supaya pengajuan yang sudah jalan tidak terpengaruh perubahan master.
 *
 * Bentuk satu entri:
 *   {
 *     "level": 1,
 *     "role_key": "yang_diberi_tugas",
 *     "role_label": "Yang diberi Tugas",
 *     "npk": "C-00827",
 *     "name": "IT HOD",            <- nama asli orang (dari PKWT.NAMA)
 *     "jabatan": "IT HOD"          <- jabatan approver (dari approval_rules.name)
 *   }
 *
 * MEKANISME APPROVAL MASIH DRAFT — struktur dibuat generik supaya bisa dipakai
 * untuk chain bertingkat maupun 4 kolom tanda tangan dokumen.
 */
class ApprovalChainBuilder
{
    /**
     * Bangun chain untuk departemen + section karyawan.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function build(?string $deptId, ?string $sectionId = null): array
    {
        $group = self::selectGroup($deptId, $sectionId);

        if (!$group || $group->rules->isEmpty()) {
            return [];
        }

        $chain = [];

        foreach ($group->rules as $rule) {
            $level = (int) $rule->level;
            $jabatan = (string) ($rule->name ?? '');
            $key   = self::roleKeyFor($jabatan);

            $chain[] = [
                'level'      => $level,
                'role_key'   => $key,
                'role_label' => self::roleLabelFor($key),
                'npk'        => (string) $rule->approval_id,
                'name'       => $jabatan,
                'jabatan'    => $jabatan,
            ];
        }

        usort($chain, fn ($a, $b) => $a['level'] <=> $b['level']);

        // Ganti nama = jabatan dengan nama asli orang dari master PKWT.
        $names = EmployeeLookupService::namesFor(array_column($chain, 'npk'));

        foreach ($chain as &$step) {
            $real = $names[$step['npk']] ?? null;
            if ($real !== null) {
                $step['name'] = $real;
            }
        }
        unset($step);

        return $chain;
    }

    /**
     * Pilih approval group: prioritaskan group khusus section, lalu group umum
     * departemen (section kosong), terakhir group pertama yang cocok.
     * Logika sama dengan pengajuan cuti.
     */
    private static function selectGroup(?string $deptId, ?string $sectionId): ?ApprovalDept
    {
        if (empty($deptId)) {
            return null;
        }

        try {
            $groups = ApprovalDept::with(['rules' => fn ($q) => $q->orderBy('level', 'asc')])
                ->get()
                ->filter(function ($group) use ($deptId) {
                    $depts = is_array($group->dept)
                        ? $group->dept
                        : (json_decode((string) $group->dept, true) ?? []);

                    return in_array((string) $deptId, array_map('strval', $depts), true);
                });
        } catch (Throwable) {
            return null;
        }

        if ($groups->isEmpty()) {
            return null;
        }

        if (!empty($sectionId)) {
            $bySection = $groups->first(fn ($g) => (string) $g->section === (string) $sectionId);
            if ($bySection) {
                return $bySection;
            }
        }

        return $groups->first(fn ($g) => empty($g->section)) ?? $groups->first();
    }

    /**
     * Tentukan role_key dari nama jabatan approver.
     */
    public static function roleKeyFor(string $jabatan): string
    {
        $needle = strtoupper(trim($jabatan));

        if ($needle === '') {
            return (string) config('work_assignment.default_role', 'yang_diberi_tugas');
        }

        foreach ((array) config('work_assignment.role_matchers', []) as $roleKey => $patterns) {
            foreach ((array) $patterns as $pattern) {
                $pattern = strtoupper(trim((string) $pattern));
                if ($pattern !== '' && str_contains($needle, $pattern)) {
                    return (string) $roleKey;
                }
            }
        }

        return (string) config('work_assignment.default_role', 'yang_diberi_tugas');
    }

    /**
     * Label tampilan untuk sebuah role_key.
     */
    public static function roleLabelFor(string $roleKey): string
    {
        foreach ((array) config('work_assignment.signature_columns', []) as $col) {
            if (($col['key'] ?? null) === $roleKey) {
                return (string) ($col['label'] ?? $roleKey);
            }
        }

        return $roleKey;
    }
}
