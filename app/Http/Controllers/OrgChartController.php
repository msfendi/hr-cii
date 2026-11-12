<?php

namespace App\Http\Controllers;

use App\Models\OrgChartSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Organization Chart (OrgChart JS) — SETTING MANUAL.
 *
 * User mengatur sendiri node organisasi di /org-chart/settings dengan
 * memasukkan ID_DEPT + level (Company/Department)
 * + parent node. Chart membaca setting itu, lalu di bawah tiap node
 * menampilkan karyawan AKTIF (PKWT.TKK IS NULL) dari BIODATA/PKWT yang
 * ID_DEPT-nya sama dengan setting node tersebut.
 */
class OrgChartController extends Controller
{
    public function index(): View
    {
        return view('org_chart.index');
    }

    /**
     * Halaman setting manual org chart (CRUD node).
     */
    public function settings(): View
    {
        $settings = OrgChartSetting::query()
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $departments = $this->departmentOptions();

        // Peta id -> nama dept untuk tampilan tabel setting.
        $deptNames = $departments->pluck('DEPARTEMENT', 'ID_DEPT');

        return view('org_chart.settings', [
            'settings' => $settings,
            'departments' => $departments,
            'deptNames' => $deptNames,
            'levels' => OrgChartSetting::LEVELS,
        ]);
    }

    public function storeSetting(Request $request): RedirectResponse
    {
        $validated = $this->validateSetting($request);

        OrgChartSetting::create($validated);

        return redirect()->route('org-chart.settings')
            ->with('success', 'Node organization chart berhasil ditambahkan.');
    }

    public function updateSetting(Request $request, OrgChartSetting $setting): RedirectResponse
    {
        $validated = $this->validateSetting($request, $setting->id);

        // Cegah parent = diri sendiri / turunan sendiri (loop).
        if (!empty($validated['parent_id']) && $this->isDescendant((int) $validated['parent_id'], $setting->id)) {
            return back()->withInput()->withErrors([
                'parent_id' => 'Parent tidak boleh node itu sendiri atau turunannya.',
            ]);
        }

        $setting->update($validated);

        return redirect()->route('org-chart.settings')
            ->with('success', 'Node organization chart berhasil diperbarui.');
    }

    public function destroySetting(OrgChartSetting $setting): RedirectResponse
    {
        // Anak-anak node ini tidak dihapus: dipindahkan ke parent node yang
        // dihapus supaya struktur tidak putus.
        OrgChartSetting::where('parent_id', $setting->id)->update(['parent_id' => $setting->parent_id]);
        $setting->delete();

        return redirect()->route('org-chart.settings')
            ->with('success', 'Node organization chart berhasil dihapus. Child node dipindahkan ke parent-nya.');
    }

    /**
     * Data node OrgChart (JSON) — dibangun dari setting manual.
     * GET /org-chart/data
     */
    public function data(): JsonResponse
    {
        try {
            $payload = $this->buildNodesFromSettings();
        } catch (\Throwable $e) {
            Log::error('OrgChart data gagal: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data organization chart: ' . $e->getMessage(),
                'nodes' => [],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'nodes' => $payload['nodes'],
            'tree' => $payload['tree'] ?? null,
            'employees' => $payload['employees'] ?? [],
            'total_employees' => $payload['total_employees'],
            'has_settings' => $payload['has_settings'],
        ]);
    }

    /**
     * Foto karyawan sebagai base64 (dipakai modal "Daftar Karyawan" saat
     * card unit diklik).
     * GET /org-chart/photo/{npk}
     */
    public function photo(string $npk): JsonResponse
    {
        $npk = trim($npk);

        if ($npk === '') {
            return response()->json(['success' => false, 'photo' => null]);
        }

        try {
            $cii = DB::connection('cii');

            $pkwt = $cii->table('PKWT')
                ->where('NPK', $npk)
                ->first(['NPK', 'NAMA', 'BAGIAN', 'file_pas_foto', 'TKK']);

            if (!$pkwt || !empty($pkwt->TKK)) {
                return response()->json(['success' => false, 'photo' => null]);
            }
        } catch (\Throwable $e) {
            Log::error('OrgChart photo gagal: ' . $e->getMessage());

            return response()->json(['success' => false, 'photo' => null]);
        }

        $path = $this->resolvePhotoPath($pkwt);

        if ($path === null) {
            return response()->json(['success' => false, 'photo' => null, 'name' => $pkwt->NAMA ?? null]);
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png' : ($ext === 'gif' ? 'image/gif' : 'image/jpeg');

        return response()->json([
            'success' => true,
            'photo' => 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path)),
            'name' => $pkwt->NAMA,
        ]);
    }

    /**
     * Cari file foto karyawan.
     *
     * Urutan:
     *   1. PKWT.file_pas_foto (kalau kolom itu berisi path/nama file).
     *   2. Pola standar halaman Biodata (BiodataController::updateFoto):
     *        $deptName = trim($pkwt->BAGIAN);
     *        $fileName = $NPK . '_' . trim($pkwt->NAMA) . '.jpg';
     *        $fullPath = 'public/img/profile/' . $deptName . '/' . $fileName;
     *      'public/img/profile/...' adalah path disk 'public', jadi file fisik
     *      ada di storage/app/public/img/profile/<BAGIAN>/<NPK>_<NAMA>.jpg
     *      (disajikan lewat symlink public/storage bila ada).
     *   3. Glob cadangan: cari <NPK>_* di semua subfolder img/profile.
     */
    private function resolvePhotoPath(object $pkwt, array $roots = []): ?string
    {
        $publicStorage = rtrim((string) ($roots['public_storage'] ?? storage_path('app/public')), '/');
        $appStorage = rtrim((string) ($roots['app_storage'] ?? storage_path('app')), '/');
        $publicDir = rtrim((string) ($roots['public'] ?? public_path()), '/');

        $npk = trim((string) ($pkwt->NPK ?? ''));
        $nama = trim((string) ($pkwt->NAMA ?? ''));
        $bagian = trim((string) ($pkwt->BAGIAN ?? ''));
        $stored = trim((string) ($pkwt->file_pas_foto ?? ''));

        $candidates = [];

        // 1) file_pas_foto
        if ($stored !== '') {
            $rel = ltrim($stored, '/');
            $candidates[] = $publicStorage . '/' . $rel;
            $candidates[] = $appStorage . '/' . $rel;
            $candidates[] = $publicDir . '/' . $rel;
            $candidates[] = $publicDir . '/storage/' . $rel;
            if (str_starts_with($rel, 'public/')) {
                $tanpaPrefix = substr($rel, 7);
                $candidates[] = $publicStorage . '/' . $tanpaPrefix;
                $candidates[] = $publicDir . '/' . $tanpaPrefix;
            }
        }

        // 2) Pola standar: img/profile/<BAGIAN>/<NPK>_<NAMA>.jpg
        if ($npk !== '' && $nama !== '') {
            $ekstensi = ['jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG'];
            $folder = $bagian !== '' ? [$bagian] : [];

            foreach ($folder as $dir) {
                foreach ($ekstensi as $ext) {
                    $file = $npk . '_' . $nama . '.' . $ext;
                    $candidates[] = $publicStorage . '/img/profile/' . $dir . '/' . $file;
                    $candidates[] = $publicDir . '/storage/img/profile/' . $dir . '/' . $file;
                    $candidates[] = $publicDir . '/img/profile/' . $dir . '/' . $file;
                }
            }

            // Bila BAGIAN kosong/berubah, tetap coba tanpa folder.
            $candidates[] = $publicStorage . '/img/profile/' . $npk . '_' . $nama . '.jpg';
        }

        // 3) Glob cadangan di semua folder bagian
        if ($npk !== '') {
            foreach ([$publicStorage, $publicDir . '/storage', $publicDir] as $base) {
                foreach ((array) glob($base . '/img/profile/*/' . $npk . '_*') as $match) {
                    $candidates[] = $match;
                }
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate) && is_readable($candidate) && filesize($candidate) > 0) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Validasi form setting.
     */
    private function validateSetting(Request $request, ?int $ignoreId = null): array
    {
        $rules = [
            'id_dept' => 'nullable|string|max:50',
            'label' => 'nullable|string|max:150',
            'level' => 'required|in:' . implode(',', array_keys(OrgChartSetting::LEVELS)),
            'parent_id' => 'nullable|integer|exists:org_chart_settings,id',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];

        $validated = $request->validate($rules, [
            'level.in' => 'Level harus salah satu dari: ' . implode(', ', OrgChartSetting::LEVELS) . '.',
        ]);

        $level = (string) $validated['level'];
        $idDept = trim((string) ($validated['id_dept'] ?? ''));
        $label = trim((string) ($validated['label'] ?? ''));

        if ($level === 'company') {
            // Level tertinggi memakai NAMA COMPANY (kolom label), bukan nama dept.
            $validated['label'] = $label !== '' ? $label : OrgChartSetting::COMPANY_NAME_FALLBACK;
        } else {
            // Level Department wajib terhubung ke master DEPT.
            if ($idDept === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'id_dept' => 'ID_DEPT wajib diisi untuk level Department.',
                ]);
            }
            if (!$this->departmentExists($idDept)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'id_dept' => 'ID_DEPT "' . $idDept . '" tidak ditemukan di master DEPT.',
                ]);
            }
            $validated['label'] = $label !== '' ? $label : null;
        }

        // ID_DEPT unik (hanya bila diisi; level Company boleh tanpa detp).
        if ($idDept !== '') {
            $dupe = OrgChartSetting::query()->where('id_dept', $idDept);
            if ($ignoreId !== null) {
                $dupe->where('id', '!=', $ignoreId);
            }
            if ($dupe->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'id_dept' => 'ID_DEPT ini sudah dipakai di setting lain.',
                ]);
            }
            if (!$this->departmentExists($idDept)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'id_dept' => 'ID_DEPT "' . $idDept . '" tidak ditemukan di master DEPT.',
                ]);
            }
        }

        $validated['id_dept'] = $idDept !== '' ? $idDept : null;
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        $validated['is_active'] = (bool) ($request->input('is_active', false));
        $validated['parent_id'] = $validated['parent_id'] ?: null;

        return $validated;
    }

    private function departmentExists(string $idDept): bool
    {
        try {
            return DB::connection('cii')->table('DEPT')->where('ID_DEPT', $idDept)->exists();
        } catch (\Throwable $e) {
            Log::warning('OrgChart cek DEPT gagal: ' . $e->getMessage());

            return true; // jangan blokir saat koneksi master bermasalah
        }
    }

    /**
     * Cek apakah $candidateId adalah turunan dari $nodeId (deteksi loop).
     */
    private function isDescendant(int $candidateId, int $nodeId): bool
    {
        $visited = [];
        $current = $candidateId;

        while ($current && !in_array($current, $visited, true)) {
            if ($current === $nodeId) {
                return true;
            }
            $visited[] = $current;
            $current = (int) (OrgChartSetting::whereKey($current)->value('parent_id') ?? 0);
        }

        return false;
    }

    /**
     * Daftar dept untuk dropdown (semua dept, urut nama).
     */
    private function departmentOptions(): \Illuminate\Support\Collection
    {
        try {
            return DB::connection('cii')->table('DEPT')
                ->select('ID_DEPT', 'DEPARTEMENT')
                ->orderBy('DEPARTEMENT')
                ->get();
        } catch (\Throwable $e) {
            Log::warning('OrgChart departmentOptions gagal: ' . $e->getMessage());

            return collect();
        }
    }

    /**
     * Bangun node dari setting manual:
     *  - node setting: id 'cfg-{id}', pid 'cfg-{parent}' atau '' (root),
     *    name = nama dept (DEPT.DEPARTEMENT), code = ID_DEPT, type = level.
     *  - karyawan aktif per node: PKWT.TKK IS NULL, join BIODATA
     *    (NPK -> ID_DEPT), photo dari PKWT.file_pas_foto.
     * Kalau ada lebih dari satu root, ditambahkan root virtual "Organization"
     * supaya OrgChart JS tetap punya satu akar.
     *
     * @return array{nodes: array<int, array<string, mixed>>, total_employees: int, has_settings: bool}
     */
    private function buildNodesFromSettings(): array
    {
        $cii = DB::connection('cii');

        $settings = OrgChartSetting::active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($settings->isEmpty()) {
            return ['nodes' => [], 'total_employees' => 0, 'has_settings' => false];
        }

        // Nama dept per ID_DEPT (level Company tidak memakai nama dept).
        $deptIds = $settings->pluck('id_dept')
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->values()
            ->all();
        $deptNames = [];
        if (!empty($deptIds)) {
            // Trim dilakukan di PHP (LTRIM/RTRIM/ISNULL hanya ada di SQL Server
            // dan membuat query tidak portabel untuk pengujian).
            $rows = $cii->table('DEPT')
                ->whereIn('ID_DEPT', $deptIds)
                ->select('ID_DEPT', 'DEPARTEMENT')
                ->get();
            foreach ($rows as $row) {
                $deptNames[(string) $row->ID_DEPT] = trim((string) $row->DEPARTEMENT);
            }
        }

        // Karyawan aktif per ID_DEPT (PKWT.TKK IS NULL).
        $employeesByDept = [];
        if (!empty($deptIds)) {
            $employees = $cii->table('PKWT as p')
                ->join('BIODATA as b', 'b.NPK', '=', 'p.NPK')
                ->whereNull('p.TKK')
                ->whereIn('b.ID_DEPT', $deptIds)
                ->select('b.NPK as NPK', 'p.NAMA', 'p.file_pas_foto', 'b.ID_DEPT')
                ->orderBy('p.NAMA')
                ->get();

            foreach ($employees as $emp) {
                $npk = trim((string) $emp->NPK);
                if ($npk === '') {
                    continue;
                }
                // Satu karyawan satu node: PKWT bisa punya beberapa baris kontrak.
                $deptKey = trim((string) $emp->ID_DEPT);
                if (!isset($employeesByDept[$deptKey])) {
                    $employeesByDept[$deptKey] = [];
                }
                if (!isset($employeesByDept[$deptKey][$npk])) {
                    $employeesByDept[$deptKey][$npk] = $emp;
                }
            }
        }

        $nodes = [];
        $totalEmployees = 0;
        $treeNodes = [];
        $treeRoots = [];
        $employeesMap = [];   // nodeId => daftar karyawan (untuk modal)
        $seenNpk = [];        // hindari hitung ganda (PKWT bisa beberapa baris)

        // Metadata untuk mengurutkan anak: semua anak kini unit
        // (Company/Department) — karyawan tidak lagi menjadi node bagan.
        $meta = [];

        // 1) Buat node setting + karyawan (belum di-link ke parent).
        foreach ($settings as $setting) {
            $nodeId = 'cfg-' . $setting->id;
            $deptCode = trim((string) $setting->id_dept);
            $label = trim((string) $setting->label);

            if ($setting->level === 'company') {
                // Level tertinggi: NAMA COMPANY, bukan nama department.
                $nodeName = $label !== '' ? $label : OrgChartSetting::COMPANY_NAME_FALLBACK;
            } else {
                $nodeName = $label !== '' ? $label : ($deptNames[$deptCode] ?? ($deptCode !== '' ? 'DEPT ' . $deptCode : 'Department'));
            }

            $meta[$nodeId] = [
                'rank' => 0,
                'sort' => (int) $setting->sort_order,
                'name' => $nodeName,
            ];

            // Karyawan TIDAK dibuat sebagai card di bagan (permintaan user):
            // cukup dihitung pada card departemen, sedangkan daftar namanya
            // dikirim lewat key 'employees' -> dipakai modal "Daftar Karyawan"
            // ketika card departemen diklik.
            $empList = [];
            foreach (($employeesByDept[$deptCode] ?? []) as $emp) {
                $npk = trim((string) $emp->NPK);
                if ($npk === '') {
                    continue;
                }
                if (!isset($seenNpk[$npk])) {
                    $seenNpk[$npk] = true;
                    $totalEmployees++;
                }
                $empList[] = [
                    'npk' => $npk,
                    'nama' => (string) ($emp->NAMA ?: $npk),
                    'dept_code' => $deptCode,
                    'photo' => '/org-chart/photo/' . rawurlencode($npk),
                ];
            }
            usort($empList, function ($a, $b) {
                return strcasecmp((string) $a['nama'], (string) $b['nama']);
            });

            $nodes[] = [
                'id' => $nodeId,
                'pid' => $setting->parent_id ? 'cfg-' . $setting->parent_id : '',
                'name' => $nodeName,
                'title' => $setting->level_label,
                'type' => $setting->level,
                'code' => $deptCode,
                'emp_count' => count($empList),
            ];

            if (! empty($empList)) {
                $employeesMap[$nodeId] = $empList;
            }

            $treeNodes[$nodeId] = [
                'id' => $nodeId,
                'name' => $nodeName,
                'title' => $setting->level_label,
                'type' => $setting->level,
                'code' => $deptCode,
                'emp_count' => count($empList),
                'children' => [],
            ];
        }

        // 2) Peta parent -> child (id), lalu bangun pohon rekursif.
        //    Tidak memakai referensi array karena penyalinan PHP bisa membuat
        //    root tersalin sebelum child-nya ditempel.
        $childIds = [];
        $rootIds = [];
        foreach ($settings as $setting) {
            $nodeId = 'cfg-' . $setting->id;
            $parentId = $setting->parent_id ? 'cfg-' . $setting->parent_id : null;

            if ($parentId && $parentId !== $nodeId && isset($treeNodes[$parentId])) {
                $childIds[$parentId][] = $nodeId;
            } else {
                $rootIds[] = $nodeId;
            }
        }

        // Nama company untuk root (level tertinggi).
        $companyName = OrgChartSetting::COMPANY_NAME_FALLBACK;
        foreach ($settings as $setting) {
            if ($setting->level === 'company') {
                $lbl = trim((string) $setting->label);
                $companyName = $lbl !== '' ? $lbl : OrgChartSetting::COMPANY_NAME_FALLBACK;
                break;
            }
        }

        // Urutan anak: unit (Company/Department) dulu sesuai Urutan, karyawan di
        // belakang diurutkan nama. Sebelumnya karyawan tampil di kiri dan unit di
        // kanan sehingga bagan membingungkan.
        $sortChildren = function (array $children) use ($meta): array {
            usort($children, function ($a, $b) use ($meta) {
                $ma = $meta[(string) ($a['id'] ?? '')] ?? ['rank' => 0, 'sort' => 0, 'name' => ''];
                $mb = $meta[(string) ($b['id'] ?? '')] ?? ['rank' => 0, 'sort' => 0, 'name' => ''];

                if ($ma['rank'] !== $mb['rank']) {
                    return $ma['rank'] <=> $mb['rank'];
                }
                if ($ma['rank'] === 0 && $ma['sort'] !== $mb['sort']) {
                    return $ma['sort'] <=> $mb['sort'];
                }

                return strcasecmp((string) $ma['name'], (string) $mb['name']);
            });

            return array_values($children);
        };

        $buildTree = function (string $id) use (&$buildTree, $treeNodes, $childIds, $sortChildren): array {
            $node = $treeNodes[$id];
            $children = $node['children'];

            foreach ($childIds[$id] ?? [] as $childId) {
                $children[] = $buildTree($childId);
            }

            $node['children'] = $sortChildren($children);

            return $node;
        };

        // 3) Level tertinggi wajib Company.
        //    a. Ada setting level company tanpa parent -> node itu jadi root, root
        //       lain ditempel sebagai anaknya (bagan tetap satu root).
        //    b. Tidak ada -> root virtual level company bernama NAMA COMPANY.
        $companyRootId = null;
        foreach ($rootIds as $rid) {
            if (($treeNodes[$rid]['type'] ?? '') === 'company') {
                $companyRootId = $rid;
                break;
            }
        }

        if ($companyRootId !== null) {
            foreach ($rootIds as $rid) {
                if ($rid !== $companyRootId) {
                    $childIds[$companyRootId][] = $rid;
                }
            }

            $tree = $buildTree($companyRootId);
        } else {
            $treeRoots = array_map($buildTree, $rootIds);

            array_unshift($nodes, [
                'id' => 'root',
                'pid' => '',
                'name' => $companyName,
                'title' => 'Company',
                'type' => 'company',
                'code' => '',
            ]);

            $tree = [
                'id' => 'root',
                'name' => $companyName,
                'title' => 'Company',
                'type' => 'company',
                'code' => '',
                'children' => $treeRoots,
            ];
        }

        return [
            'nodes' => $nodes,
            'tree' => $tree,
            'employees' => $employeesMap,
            'total_employees' => $totalEmployees,
            'has_settings' => true,
        ];
    }
}