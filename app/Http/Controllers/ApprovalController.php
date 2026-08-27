<?php

namespace App\Http\Controllers;

use App\Models\ApprovalDept;
use App\Models\ApprovalRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ApprovalController extends Controller
{
    /**
     * Display the Master Approval index page.
     */
    public function index()
    {
        $depts = DB::connection('cii')
            ->table('DEPT')
            ->select('ID_DEPT', 'DEPARTEMENT')
            ->orderBy('DEPARTEMENT')
            ->get();

        return view('approval.index', compact('depts'));
    }

    /**
     * Return JSON for ApprovalDept DataTable.
     */
    public function getData()
    {
        $data = ApprovalDept::with('rules')->get()->map(function ($item) {
            // Resolve department names from CII DB
            $deptNames = DB::connection('cii')
                ->table('DEPT')
                ->whereIn('ID_DEPT', $item->dept ?? [])
                ->pluck('DEPARTEMENT')
                ->toArray();

            // Resolve approval names from BIODATA
            $rulesData = $item->rules->map(function ($rule) {
                $bio = DB::connection('cii')
                    ->table('BIODATA')
                    ->where('NPK', $rule->approval_id)
                    ->first();

                return [
                    'id'          => $rule->id,
                    'name'        => $rule->name,
                    'approval_id' => $rule->approval_id,
                    'approval_name' => $bio ? $bio->NAMA_KARYAWAN : $rule->approval_id,
                    'level'       => $rule->level,
                ];
            });

            return [
                'id'         => $item->id,
                'name'       => $item->name,
                'dept'       => $item->dept,
                'dept_names' => $deptNames,
                'rules'      => $rulesData,
                'rules_count' => $rulesData->count(),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Store a new ApprovalDept.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'dept' => 'required|array|min:1',
            'dept.*' => 'string',
        ]);

        try {
            $approvalDept = ApprovalDept::create([
                'name' => $request->name,
                'dept' => array_map('strval', $request->dept),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Approval Group berhasil ditambahkan',
                'data' => $approvalDept,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menambahkan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing ApprovalDept.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'dept' => 'required|array|min:1',
            'dept.*' => 'string',
        ]);

        try {
            $approvalDept = ApprovalDept::findOrFail($id);
            $approvalDept->update([
                'name' => $request->name,
                'dept' => array_map('strval', $request->dept),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Approval Group berhasil diperbarui',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memperbarui: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an ApprovalDept and its rules.
     */
    public function destroy($id)
    {
        try {
            $dept = ApprovalDept::findOrFail($id);
            // Delete related rules first
            ApprovalRule::where('rules_id', $id)->delete();
            $dept->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Approval Group berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================
    // APPROVAL RULES (nested under a dept group)
    // =========================================================

    /**
     * Store a new ApprovalRule under a group.
     */
    public function storeRule(Request $request)
    {
        $request->validate([
            'rules_id'    => 'required|exists:approval_depts,id',
            'name'        => 'required|string|max:255',
            'approval_id' => 'required|string|max:50',
            'level'       => 'required|integer|min:1',
        ]);

        try {
            $rule = ApprovalRule::create($request->only('rules_id', 'name', 'approval_id', 'level'));

            return response()->json([
                'status' => 'success',
                'message' => 'Approval Rule berhasil ditambahkan',
                'data' => $rule,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menambahkan rule: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing ApprovalRule.
     */
    public function updateRule(Request $request, $id)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'approval_id' => 'required|string|max:50',
            'level'       => 'required|integer|min:1',
        ]);

        try {
            $rule = ApprovalRule::findOrFail($id);
            $rule->update($request->only('name', 'approval_id', 'level'));

            return response()->json([
                'status' => 'success',
                'message' => 'Approval Rule berhasil diperbarui',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memperbarui rule: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an ApprovalRule.
     */
    public function destroyRule($id)
    {
        try {
            ApprovalRule::findOrFail($id)->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Approval Rule berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus rule: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search employees by NPK or name for autocomplete.
     */
    public function searchEmployee(Request $request)
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $results = DB::connection('cii')
            ->table('BIODATA')
            ->where(function ($query) use ($q) {
                $query->where('NPK', 'like', "%{$q}%")
                      ->orWhere('NAMA_KARYAWAN', 'like', "%{$q}%");
            })
            ->select('NPK', 'NAMA_KARYAWAN')
            ->take(15)
            ->get();

        return response()->json($results);
    }

    // =========================================================
    // EXCEL IMPORT (ApprovalDept + ApprovalRule sekaligus)
    // =========================================================

    /**
     * Kirim file template Excel supaya user tahu format kolom yang benar.
     * Simpan file template (dibuat sekali) di storage/app/templates/.
     */
    public function downloadTemplate()
    {
        $path = storage_path('app/templates/Template_Import_Approval.xlsx');

        if (!file_exists($path)) {
            abort(404, 'Template belum tersedia. Hubungi administrator.');
        }

        return response()->download($path, 'Template_Import_Approval.xlsx');
    }

    /**
     * Import ApprovalDept + ApprovalRule dari satu file Excel.
     *
     * Format kolom (baris 1 = header, data mulai baris 2):
     *   A: Nama Group           - nama approval group (baris dengan nama sama akan digabung)
     *   B: Kode Departemen      - satu atau lebih ID_DEPT dipisah koma, contoh: "10,11,12"
     *   C: Level Approval       - urutan approval (1, 2, 3, ...)
     *   D: Nama Jabatan         - label posisi approver, contoh: "SPV", "Manager"
     *   E: NPK Approver         - NPK karyawan yang menjadi approver di level tsb
     *   F: Nama Approver        - hanya referensi/kemudahan baca, tidak disimpan
     *
     * Setiap baris = satu approver (satu ApprovalRule). Baris-baris dengan
     * "Nama Group" yang sama digabung menjadi satu ApprovalDept.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls',
        ]);

        try {
            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);

            // Baris pertama adalah header
            array_shift($rows);

            $grouped = [];
            $errors = [];

            foreach ($rows as $i => $row) {
                $rowNum = $i + 2; // +2 karena index dimulai 0 dan header sudah dibuang

                $groupName  = trim((string) ($row[0] ?? ''));
                $deptRaw    = trim((string) ($row[1] ?? ''));
                $level      = trim((string) ($row[2] ?? ''));
                $posName    = trim((string) ($row[3] ?? ''));
                $approvalId = trim((string) ($row[4] ?? ''));

                // Lewati baris yang sepenuhnya kosong (mis. baris pemisah di Excel)
                if ($groupName === '' && $deptRaw === '' && $level === '' && $posName === '' && $approvalId === '') {
                    continue;
                }

                if ($groupName === '' || $deptRaw === '' || $level === '' || $posName === '' || $approvalId === '') {
                    $errors[] = "Baris {$rowNum}: kolom wajib (Nama Group/Kode Departemen/Level/Nama Jabatan/NPK Approver) ada yang kosong.";
                    continue;
                }

                if (!is_numeric($level) || (int) $level < 1) {
                    $errors[] = "Baris {$rowNum}: Level harus berupa angka >= 1.";
                    continue;
                }

                $deptIds = array_values(array_filter(array_map('trim', explode(',', $deptRaw)), fn ($v) => $v !== ''));

                if (empty($deptIds)) {
                    $errors[] = "Baris {$rowNum}: Kode Departemen tidak valid.";
                    continue;
                }

                if (!isset($grouped[$groupName])) {
                    $grouped[$groupName] = [
                        'name'  => $groupName,
                        'dept'  => $deptIds,
                        'rules' => [],
                    ];
                } else {
                    // Gabungkan departemen unik jika baris lain memakai nama group yang sama
                    $grouped[$groupName]['dept'] = array_values(array_unique(array_merge($grouped[$groupName]['dept'], $deptIds)));
                }

                $grouped[$groupName]['rules'][] = [
                    'name'        => $posName,
                    'approval_id' => $approvalId,
                    'level'       => (int) $level,
                ];
            }

            if (!empty($errors)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Import dibatalkan, ditemukan kesalahan pada file:',
                    'errors'  => $errors,
                ], 422);
            }

            if (empty($grouped)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Tidak ada data valid yang ditemukan pada file. Pastikan mengikuti format template.',
                ], 422);
            }

            $groupCount = 0;
            $ruleCount = 0;

            DB::transaction(function () use ($grouped, &$groupCount, &$ruleCount) {
                foreach ($grouped as $g) {
                    $dept = ApprovalDept::updateOrCreate(
                        ['name' => $g['name']],
                        ['dept' => array_map('strval', $g['dept'])]
                    );
                    $groupCount++;

                    foreach ($g['rules'] as $r) {
                        ApprovalRule::updateOrCreate(
                            [
                                'rules_id' => $dept->id,
                                'level'    => $r['level'],
                            ],
                            [
                                'name'        => $r['name'],
                                'approval_id' => $r['approval_id'],
                            ]
                        );
                        $ruleCount++;
                    }
                }
            });

            return response()->json([
                'status'  => 'success',
                'message' => "Import berhasil: {$groupCount} approval group, {$ruleCount} approver diproses.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal memproses file: ' . $e->getMessage(),
            ], 500);
        }
    }
}