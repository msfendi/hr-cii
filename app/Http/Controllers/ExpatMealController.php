<?php

namespace App\Http\Controllers;

use App\Models\ExpatMealMenu;
use App\Models\ExpatMealParticipant;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExpatMealController extends Controller
{
    /** Daftar kategori makan yang valid. */
    protected array $kategoriList = ['Sarapan', 'Makan Siang'];

    public function index(): View
    {
        return view('expat_meal.index', [
            'defaultStartDate' => now()->startOfMonth()->format('Y-m-d'),
            'defaultEndDate'   => now()->format('Y-m-d'),
            'kategoriList'     => $this->kategoriList,
        ]);
    }

/* =========================================================================
 * 1) DASHBOARD: RINGKASAN & DETAIL LAPORAN BIAYA MAKAN
 * ========================================================================= */

    /**
     * KPI + rekap total biaya makan expat per tanggal & per kategori.
     */
    public function summaryData(Request $request): JsonResponse
    {
        [$start, $end, $npk] = $this->parseFilters($request);

        $report = $this->buildDailyCostReport($start, $end, $npk);

        $participantsQuery = ExpatMealParticipant::whereBetween('tanggal', [$start, $end]);
        if (! empty($npk)) {
            $participantsQuery->whereIn('npk', $npk);
        }
        $participantsFiltered = $participantsQuery->get();

        $totalBiaya = $report->sum('total_harga');
        $totalExpat = $participantsFiltered->pluck('npk')->unique()->count();
        $totalHari  = $report->count();
        $totalPorsi = $participantsFiltered->count();

        $recapPerDate = $report
            ->map(fn(array $r) => [
                'tanggal'      => $r['tanggal'],
                'jumlah_expat' => $r['jumlah_expat'],
                'jumlah_porsi' => $r['jumlah_porsi'],
                'total_harga'  => $r['total_harga'],
            ])
            ->values();

        $recapPerKategori = [
            'sarapan'     => $report->sum('harga_sarapan'),
            'makan_siang' => $report->sum('harga_makan_siang'),
        ];

        return response()->json([
            'total_biaya'        => $totalBiaya,
            'total_expat'        => $totalExpat,
            'total_hari'         => $totalHari,
            'total_porsi'        => $totalPorsi,
            'recap_per_date'     => $recapPerDate,
            'recap_per_kategori' => $recapPerKategori,
        ]);
    }

    /**
     * Detail laporan biaya PER HARI (bukan per expat), dipakai oleh
     * DataTable tab "Laporan & Biaya". Tiap baris merangkum satu tanggal:
     * menu apa saja yang tersedia untuk Sarapan & Makan Siang hari itu,
     * beserta total biayanya (habis per hari, tidak dipecah per expat).
     */
    public function detailData(Request $request): JsonResponse
    {
        [$start, $end, $npk] = $this->parseFilters($request);

        $report = $this->buildDailyCostReport($start, $end, $npk);

        return response()->json(['data' => $report]);
    }

    /**
     * Bangun laporan total biaya makan PER TANGGAL (bukan per expat).
     *
     * @param array $npkFilter Jika tidak kosong, hanya tanggal yang dihadiri
     *              oleh salah satu expat pada daftar ini yang dimunculkan.
     *              Filter ini TIDAK mempengaruhi kategori/menu/total biaya
     *              yang dihitung untuk tanggal tsb — itu selalu dihitung
     *              dari seluruh expat yang hadir, supaya "habis per hari"
     *              tetap mencerminkan biaya sesungguhnya hari itu.
     *
     * Formula:
     * - Ambil semua baris peserta (expat_meal_participants) pada rentang
     *   tanggal, dikelompokkan per tanggal.
     * - Untuk tiap tanggal, tentukan kategori yang dihadiri (Sarapan dan/
     *   atau Makan Siang) berdasarkan peserta yang tercatat hari itu.
     * - Untuk tiap kategori yang dihadiri, ambil semua menu
     *   (expat_meal_menus) pada tanggal + kategori tsb. Total biaya
     *   kategori tsb = jumlah harga SEMUA menu (lumpsum), TIDAK dikalikan
     *   maupun dibagi dengan jumlah expat — baik untuk menu shared maupun
     *   tidak, karena laporan ini merangkum biaya per hari, bukan per
     *   expat.
     * - Total harga per hari = harga sarapan + harga makan siang hari itu.
     */
    protected function buildDailyCostReport(string $start, string $end, array $npkFilter = []): Collection
    {
        $allParticipants = ExpatMealParticipant::whereBetween('tanggal', [$start, $end])->get();

        if ($allParticipants->isEmpty()) {
            return collect();
        }

        $tanggalList = $allParticipants
            ->pluck('tanggal')
            ->map(fn($t) => $t->format('Y-m-d'))
            ->unique()
            ->values();

        $menusPerTglKategori = ExpatMealMenu::whereIn('tanggal', $tanggalList)
            ->orderBy('makanan')
            ->get()
            ->groupBy(fn($m) => Carbon::parse($m->tanggal)->format('Y-m-d') . '|' . $m->kategori);

        // Tanggal yang dihadiri oleh salah satu expat pada filter, dipakai
        // untuk menyaring baris yang dimunculkan (tidak mengubah isi baris).
        $tanggalDifilter = empty($npkFilter)
            ? null
            : $allParticipants
            ->whereIn('npk', $npkFilter)
            ->pluck('tanggal')
            ->map(fn($t) => $t->format('Y-m-d'))
            ->unique();

        return $allParticipants
            ->groupBy(fn($p) => $p->tanggal->format('Y-m-d'))
            ->when(
                $tanggalDifilter !== null,
                fn(Collection $c) => $c->only($tanggalDifilter->all())
            )
            ->map(function (Collection $rows, string $tanggalStr) use ($menusPerTglKategori) {
                $kategoriHadir = $rows->pluck('kategori')->unique();

                $menuSarapan = $kategoriHadir->contains('Sarapan')
                    ? ($menusPerTglKategori["{$tanggalStr}|Sarapan"] ?? collect())
                    : collect();
                $menuMakanSiang = $kategoriHadir->contains('Makan Siang')
                    ? ($menusPerTglKategori["{$tanggalStr}|Makan Siang"] ?? collect())
                    : collect();

                $hargaSarapan    = round((float) $menuSarapan->sum('harga'), 2);
                $hargaMakanSiang = round((float) $menuMakanSiang->sum('harga'), 2);

                return [
                    'tanggal'           => $tanggalStr,
                    'jumlah_expat'      => $rows->pluck('npk')->unique()->count(),
                    'jumlah_porsi'      => $rows->count(),
                    'menu_sarapan'      => $menuSarapan->pluck('makanan')->implode(', ') ?: '-',
                    'menu_makan_siang'  => $menuMakanSiang->pluck('makanan')->implode(', ') ?: '-',
                    'harga_sarapan'     => $hargaSarapan,
                    'harga_makan_siang' => $hargaMakanSiang,
                    'total_harga'       => round($hargaSarapan + $hargaMakanSiang, 2),
                ];
            })
            ->sortKeys()
            ->values();
    }

    /**
     * Detail makanan pada satu tanggal (satu hari penuh), dipakai oleh
     * tombol "Detail" pada tabel "Detail Biaya per Hari". Menampilkan tiap
     * item menu untuk Sarapan & Makan Siang hari itu:
     * - shared = true  -> dirinci per expat yang hadir pada kategori tsb
     *                      (harga menu dibagi rata / expat), dan ikut
     *                      diakumulasi ke `expat_totals`.
     * - shared = false -> tidak dirinci per expat, cukup ditampilkan harga
     *                      lumpsum-nya saja untuk hari itu (tidak masuk
     *                      hitungan `expat_totals`, karena memang tidak
     *                      bisa diatribusikan ke expat tertentu).
     *
     * `expat_totals` = akumulasi total biaya menu SHARED tiap expat pada
     * tanggal tsb (dari seluruh menu Sarapan + Makan Siang hari itu).
     *
     * Total biaya = jumlah harga asli semua menu hari itu (habis per hari,
     * bukan hasil kali/bagi dengan jumlah expat).
     */
    public function mealDetailData(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tanggal' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $tanggal = $request->input('tanggal');

        $kategoriHadir = ExpatMealParticipant::whereDate('tanggal', $tanggal)
            ->pluck('kategori')
            ->unique()
            ->sort()
            ->values();

        if ($kategoriHadir->isEmpty()) {
            return response()->json(['data' => [], 'expat_totals' => [], 'total_harga' => 0]);
        }

        $items       = [];
        $totalHarga  = 0.0;
        $expatTotals = []; // npk => ['npk' => ..., 'nama' => ..., 'total' => ...]

        foreach ($kategoriHadir as $kategori) {
            $expatHadir = ExpatMealParticipant::whereDate('tanggal', $tanggal)
                ->where('kategori', $kategori)
                ->get(['npk', 'nama_expat'])
                ->unique('npk')
                ->values();
            $jumlahExpat = $expatHadir->count();

            $menus = ExpatMealMenu::whereDate('tanggal', $tanggal)
                ->where('kategori', $kategori)
                ->orderBy('makanan')
                ->get();

            foreach ($menus as $menu) {
                $hargaAsli = (float) $menu->harga;

                // Rincian & akumulasi per expat HANYA untuk menu shared.
                // Menu non-shared adalah lumpsum, tidak bisa diatribusikan
                // ke expat tertentu.
                $detailExpat = null;
                if ($menu->shared) {
                    $hargaPerExpat = $jumlahExpat > 0 ? $hargaAsli / $jumlahExpat : 0.0;
                    $detailExpat   = $expatHadir
                        ->map(function ($p) use ($hargaPerExpat, &$expatTotals) {
                            $share = round($hargaPerExpat, 2);

                            if (! isset($expatTotals[$p->npk])) {
                                $expatTotals[$p->npk] = ['npk' => $p->npk, 'nama' => $p->nama_expat, 'total' => 0.0];
                            }
                            $expatTotals[$p->npk]['total'] += $share;

                            return [
                                'npk'   => $p->npk,
                                'nama'  => $p->nama_expat,
                                'harga' => $share,
                            ];
                        })
                        ->values();
                }

                $items[] = [
                    'kategori'     => $kategori,
                    'makanan'      => $menu->makanan,
                    'harga_asli'   => round($hargaAsli, 2),
                    'shared'       => (bool) $menu->shared,
                    'jumlah_expat' => $jumlahExpat,
                    'detail_expat' => $detailExpat,
                ];

                // Total biaya hari itu = jumlah harga asli tiap menu
                // (lumpsum per hari), tidak dikalikan/dibagi jumlah expat.
                $totalHarga += $hargaAsli;
            }
        }

        $expatTotals = collect($expatTotals)
            ->map(fn($e) => ['npk' => $e['npk'], 'nama' => $e['nama'], 'total' => round($e['total'], 2)])
            ->sortBy('nama')
            ->values();

        return response()->json([
            'tanggal'      => Carbon::parse($tanggal)->format('Y-m-d'),
            'data'         => $items,
            'expat_totals' => $expatTotals,
            'total_harga'  => round($totalHarga, 2),
        ]);
    }

    /**
     * Daftar expat unik (npk + nama) untuk mengisi dropdown filter "Expat"
     * pada tab Laporan & Biaya. Diambil dari seluruh riwayat peserta makan,
     * tidak dibatasi rentang tanggal filter, supaya expat tetap bisa dipilih
     * meski sedang tidak muncul pada periode yang sedang dilihat.
     */
    public function expatOptions(): JsonResponse
    {
        $rows = ExpatMealParticipant::select('npk', 'nama_expat')
            ->distinct()
            ->orderBy('nama_expat')
            ->get()
            ->map(fn($r) => [
                'npk'        => $r->npk,
                'nama_expat' => $r->nama_expat,
            ]);

        return response()->json(['data' => $rows]);
    }

    /* =========================================================================
 * 2) DAFTAR PESERTA MAKAN (expat_meal_participants) - CRUD manual
 * ========================================================================= */

    public function participantData(Request $request): JsonResponse
    {
        [$start, $end, $npk] = $this->parseFilters($request);

        $rows = ExpatMealParticipant::whereBetween('tanggal', [$start, $end])
            ->when(! empty($npk), fn($q) => $q->whereIn('npk', $npk))
            ->orderBy('tanggal')
            ->orderBy('npk')
            ->get()
            ->map(fn($r) => [
                'id'         => $r->id,
                'npk'        => $r->npk,
                'nama_expat' => $r->nama_expat,
                'tanggal'    => $r->tanggal->format('Y-m-d'),
                'kategori'   => $r->kategori,
            ]);

        return response()->json(['data' => $rows]);
    }

    /**
     * Tambah / update satu baris peserta makan secara manual (mis. lupa
     * diimport, atau koreksi data). Di-key oleh npk + tanggal + kategori,
     * sama seperti pada saat import.
     */
    public function participantStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id'         => 'nullable|integer|exists:expat_meal_participants,id',
            'npk'        => 'required|string|max:20',
            'nama_expat' => 'required|string|max:100',
            'tanggal'    => 'required|date',
            'kategori'   => 'required|in:' . implode(',', $this->kategoriList),
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        // Jika edit baris tertentu (ada id), update baris itu langsung supaya
        // tidak bentrok dengan unique constraint npk+tanggal+kategori.
        if ($request->filled('id')) {
            $row = ExpatMealParticipant::find($request->input('id'));
            if (! $row) {
                return response()->json(['message' => 'Data peserta tidak ditemukan.'], 404);
            }
            $row->npk        = $request->input('npk');
            $row->nama_expat = $request->input('nama_expat');
            $row->tanggal    = $request->input('tanggal');
            $row->kategori   = $request->input('kategori');
            $row->save();

            return response()->json(['message' => 'Data peserta makan berhasil diperbarui.']);
        }

        ExpatMealParticipant::updateOrCreate(
            [
                'npk'      => $request->input('npk'),
                'tanggal'  => $request->input('tanggal'),
                'kategori' => $request->input('kategori'),
            ],
            ['nama_expat' => $request->input('nama_expat')]
        );

        return response()->json(['message' => 'Data peserta makan berhasil disimpan.']);
    }

    public function participantDestroy(Request $request): JsonResponse
    {
        $row = ExpatMealParticipant::find($request->input('id'));

        if (! $row) {
            return response()->json(['message' => 'Data peserta tidak ditemukan.'], 404);
        }

        $row->delete();

        return response()->json(['message' => 'Data peserta makan berhasil dihapus.']);
    }

    /* =========================================================================
 * 3) MENU MAKANAN (expat_meal_menus) - CRUD manual
 * ========================================================================= */

    public function menuData(): JsonResponse
    {
        $rows = ExpatMealMenu::orderBy('tanggal', 'desc')
            ->orderBy('kategori')
            ->orderBy('makanan')
            ->get()
            ->map(fn($r) => [
                'id'       => $r->id,
                'makanan'  => $r->makanan,
                'kategori' => $r->kategori,
                'tanggal'  => Carbon::parse($r->tanggal)->format('Y-m-d'),
                'harga'    => (float) $r->harga,
                'shared'   => (bool) $r->shared,
            ]);

        return response()->json(['data' => $rows]);
    }

    public function menuStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id'       => 'nullable|integer|exists:expat_meal_menus,id',
            'makanan'  => 'required|string|max:150',
            'kategori' => 'required|in:' . implode(',', $this->kategoriList),
            'tanggal'  => 'required|date',
            'harga'    => 'required|numeric|min:0',
            'shared'   => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $row = $request->filled('id')
            ? ExpatMealMenu::find($request->input('id'))
            : new ExpatMealMenu();

        if (! $row) {
            return response()->json(['message' => 'Data makanan tidak ditemukan.'], 404);
        }

        $row->makanan  = $request->input('makanan');
        $row->kategori = $request->input('kategori');
        $row->tanggal  = $request->input('tanggal');
        $row->harga    = $request->input('harga');
        $row->shared   = $request->boolean('shared');
        $row->save();

        return response()->json(['message' => 'Data makanan berhasil disimpan.']);
    }

    public function menuDestroy(Request $request): JsonResponse
    {
        $row = ExpatMealMenu::find($request->input('id'));

        if (! $row) {
            return response()->json(['message' => 'Data makanan tidak ditemukan.'], 404);
        }

        $row->delete();

        return response()->json(['message' => 'Data makanan berhasil dihapus.']);
    }

/* =========================================================================
 * 4) TEMPLATE EXCEL & IMPORT (2 SHEET: "Daftar Expat" & "Makanan")
 * ========================================================================= */

    /**
     * Download template excel dengan 2 sheet sesuai kebutuhan import:
     * - "Daftar Expat" : npk, nama_expat, tanggal, kategori
     * - "Makanan"      : makanan, kategori, harga, shared
     * Sheet "Lists" (hidden) dipakai sebagai sumber data validation dropdown
     * kolom kategori, mengikuti pola import multi-sheet yang sudah ada.
     */
    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();

        // --- Sheet 1: Daftar Expat ---
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Daftar Expat');

        $headers1 = ['npk', 'nama_expat', 'tanggal', 'kategori'];
        $sheet1->fromArray($headers1, null, 'A1');
        $sheet1->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet1->getStyle('A1:D1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F0F0');

        $sheet1->setCellValue('A2', 'E-001');
        $sheet1->setCellValue('B2', 'John Doe');
        $sheet1->setCellValue('C2', now()->format('Y-m-d'));
        $sheet1->setCellValue('D2', 'Sarapan');
        $sheet1->getStyle('C2')->getNumberFormat()->setFormatCode('yyyy-mm-dd');

        foreach (['A' => 12, 'B' => 24, 'C' => 14, 'D' => 16] as $col => $w) {
            $sheet1->getColumnDimension($col)->setWidth($w);
        }

        // Dropdown kategori pada kolom D, baris 2-1000.
        $this->applyKategoriValidation($sheet1, 'D2:D1000');

        // --- Sheet 2: Makanan ---
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Makanan');

        $headers2 = ['tanggal', 'makanan', 'kategori', 'harga', 'shared'];
        $sheet2->fromArray($headers2, null, 'A1');
        $sheet2->getStyle('A1:E1')->getFont()->setBold(true);
        $sheet2->getStyle('A1:E1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F0F0');
        $sheet1->getStyle('A2')->getNumberFormat()->setFormatCode('yyyy-mm-dd');

        $sheet2->setCellValue('A2', now()->format('Y-m-d'));
        $sheet2->setCellValue('B2', 'Nasi Goreng');
        $sheet2->setCellValue('C2', 'Sarapan');
        $sheet2->setCellValue('D2', 200000);
        $sheet2->setCellValue('E2', 'TRUE');

        foreach (['A' => 28, 'B' => 16, 'C' => 14, 'D' => 12, 'E' => 12] as $col => $w) {
            $sheet2->getColumnDimension($col)->setWidth($w);
        }

        $this->applyKategoriValidation($sheet2, 'C2:C1000');

        // Dropdown TRUE/FALSE untuk kolom shared.
        for ($row = 2; $row <= 1000; $row++) {
            $validation = $sheet2->getCell("E{$row}")->getDataValidation();
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setFormula1('"TRUE,FALSE"');
        }

        // --- Sheet referensi (hidden), sumber dropdown kategori ---
        $sheetList = $spreadsheet->createSheet();
        $sheetList->setTitle('Lists');
        $sheetList->setCellValue('A1', 'Sarapan');
        $sheetList->setCellValue('A2', 'Makan Siang');
        $sheetList->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        $spreadsheet->setActiveSheetIndex(0);

        $writer   = new Xlsx($spreadsheet);
        $fileName = 'template_expat_meal_import.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    protected function applyKategoriValidation($sheet, string $range): void
    {
        [$start, $end] = explode(':', $range);
        preg_match('/([A-Z]+)(\d+)/', $start, $mStart);
        preg_match('/([A-Z]+)(\d+)/', $end, $mEnd);
        $col = $mStart[1];

        for ($row = (int) $mStart[2]; $row <= (int) $mEnd[2]; $row++) {
            $validation = $sheet->getCell("{$col}{$row}")->getDataValidation();
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setFormula1('Lists!$A$1:$A$2');
        }
    }

    /**
     * Import file excel berisi 2 sheet ("Daftar Expat" & "Makanan") sekaligus.
     * Masing-masing sheet diproses independen; jika salah satu sheet tidak
     * ditemukan, sheet tsb dilewati (bukan gagal total).
     */
    public function import(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        } catch (\Throwable $e) {
            return response()->json(['message' => 'File tidak dapat dibaca: ' . $e->getMessage()], 422);
        }

        DB::beginTransaction();
        try {
            [$pCreated, $pUpdated, $pErrors] = $this->importDaftarExpat($spreadsheet);
            [$mCreated, $mUpdated, $mErrors] = $this->importMakanan($spreadsheet);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal import: ' . $e->getMessage()], 500);
        }

        $errors = array_merge($pErrors, $mErrors);
        if (! empty($errors)) {
            session()->flash('import_errors', $errors);
        }

        return response()->json([
            'message' => "Import selesai. Daftar Expat: {$pCreated} baru, {$pUpdated} diperbarui. "
                . "Makanan: {$mCreated} baru, {$mUpdated} diperbarui."
                . (! empty($errors) ? ' (' . count($errors) . ' baris dilewati, lihat detail di log import.)' : ''),
            'errors' => $errors,
        ]);
    }

    /**
     * Import sheet "Daftar Expat" (npk, nama_expat, tanggal, kategori) ke
     * expat_meal_participants. Baris di-key oleh npk + tanggal + kategori.
     */
    protected function importDaftarExpat(Spreadsheet $spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName('Daftar Expat');

        if (! $sheet) {
            return [0, 0, ['Sheet "Daftar Expat" tidak ditemukan di file.']];
        }

        $rows = $sheet->toArray(null, true, true, true);

        $created = 0;
        $updated = 0;
        $errors  = [];

        foreach ($rows as $i => $r) {
            if ($i == 1) {
                continue; // skip header
            }

            $npk      = trim((string) ($r['A'] ?? ''));
            $nama     = trim((string) ($r['B'] ?? ''));
            $tanggal  = $r['C'] ?? null;
            $kategori = trim((string) ($r['D'] ?? ''));

            if ($npk === '' && $nama === '' && ! $tanggal && $kategori === '') {
                continue; // baris kosong
            }

            if ($npk === '' || $nama === '' || ! $tanggal || $kategori === '') {
                $errors[] = "Daftar Expat baris {$i}: npk/nama_expat/tanggal/kategori wajib diisi, dilewati.";
                continue;
            }

            $kategoriNormal = $this->normalizeKategori($kategori);
            if (! $kategoriNormal) {
                $errors[] = "Daftar Expat baris {$i}: kategori '{$kategori}' tidak dikenali (harus Sarapan / Makan Siang), dilewati.";
                continue;
            }

            $tanggalParsed = $this->parseExcelDate($tanggal);
            if (! $tanggalParsed) {
                $errors[] = "Daftar Expat baris {$i}: format tanggal tidak valid, dilewati.";
                continue;
            }

            $existing = ExpatMealParticipant::where('npk', $npk)
                ->where('tanggal', $tanggalParsed)
                ->where('kategori', $kategoriNormal)
                ->exists();

            ExpatMealParticipant::updateOrCreate(
                ['npk' => $npk, 'tanggal' => $tanggalParsed, 'kategori' => $kategoriNormal],
                ['nama_expat' => $nama]
            );

            $existing ? $updated++ : $created++;
        }

        return [$created, $updated, $errors];
    }

    /**
     * Import sheet "Makanan" (makanan, kategori, harga, shared) ke
     * expat_meal_menus. Baris di-key oleh makanan + kategori.
     */
    protected function importMakanan(Spreadsheet $spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName('Makanan');

        if (! $sheet) {
            return [0, 0, ['Sheet "Makanan" tidak ditemukan di file.']];
        }

        $rows = $sheet->toArray(null, true, true, true);

        $created = 0;
        $updated = 0;
        $errors  = [];

        foreach ($rows as $i => $r) {
            if ($i == 1) {
                continue;
            }

            $tanggal  = $r['A'] ?? null;
            $makanan   = trim((string) ($r['B'] ?? ''));
            $kategori  = trim((string) ($r['C'] ?? ''));
            $harga     = $r['D'] ?? null;
            $sharedRaw = $r['E'] ?? null;


            $tanggalParsed = $this->parseExcelDate($tanggal);
            if (! $tanggalParsed) {
                $errors[] = "Makanan baris {$i}: format tanggal tidak valid, dilewati.";
                continue;
            }

            if ($makanan === '' && $kategori === '' && ($harga === null || $harga === '')) {
                continue; // baris kosong
            }

            if ($makanan === '' || $kategori === '' || $harga === null || $harga === '') {
                $errors[] = "Makanan baris {$i}: makanan/kategori/harga wajib diisi, dilewati.";
                continue;
            }

            if (! is_numeric($harga)) {
                $errors[] = "Makanan baris {$i}: harga '{$harga}' bukan angka, dilewati.";
                continue;
            }

            $kategoriNormal = $this->normalizeKategori($kategori);
            if (! $kategoriNormal) {
                $errors[] = "Makanan baris {$i}: kategori '{$kategori}' tidak dikenali (harus Sarapan / Makan Siang), dilewati.";
                continue;
            }

            $shared = $this->parseBoolean($sharedRaw);

            $existing = ExpatMealMenu::where('makanan', $makanan)
                ->where('kategori', $kategoriNormal)
                ->exists();

            ExpatMealMenu::updateOrCreate(
                ['makanan' => $makanan, 'kategori' => $kategoriNormal, 'tanggal' => $tanggalParsed,],
                ['harga' => (float) $harga, 'shared' => $shared]
            );

            $existing ? $updated++ : $created++;
        }

        return [$created, $updated, $errors];
    }

    /**
     * Normalisasi teks kategori dari excel ('sarapan', 'Makan Siang', 'siang',
     * dsb) menjadi salah satu nilai baku: 'Sarapan' atau 'Makan Siang'.
     * Return null jika tidak dikenali.
     */
    protected function normalizeKategori(string $value): ?string
    {
        $value = Str::lower(trim($value));

        return match (true) {
            in_array($value, ['sarapan', 'breakfast', 'pagi'], true)        => 'Sarapan',
            in_array($value, ['makan siang', 'siang', 'lunch'], true)       => 'Makan Siang',
            default                                                        => null,
        };
    }

    /**
     * Parse nilai tanggal dari excel, baik berupa serial date number, objek
     * DateTime, maupun string tanggal biasa.
     */
    protected function parseExcelDate($value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d');
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function parseBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = Str::lower(trim((string) $value));

        return in_array($value, ['1', 'true', 'ya', 'yes', 'shared'], true);
    }

/* =========================================================================
 * 5) EXPORT REKAP EXCEL
 * ========================================================================= */

    /**
     * Export laporan biaya makan PER HARI ke excel (1 sheet).
     */
    public function exportRekapExcel(Request $request)
    {
        [$start, $end, $npk] = $this->parseFilters($request);

        $report = $this->buildDailyCostReport($start, $end, $npk);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Makan Expat');

        $periode = Carbon::parse($start)->locale('id')->translatedFormat('d F Y')
            . ' - ' . Carbon::parse($end)->locale('id')->translatedFormat('d F Y');

        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'LAPORAN BIAYA MAKAN EXPAT');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:H2');
        $sheet->setCellValue('A2', 'Periode: ' . $periode);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headerRow = 4;
        $headers = ['NO.', 'TANGGAL', 'JUMLAH EXPAT', 'MENU SARAPAN', 'HARGA SARAPAN', 'MENU MAKAN SIANG', 'HARGA MAKAN SIANG', 'TOTAL HARGA'];
        $sheet->fromArray($headers, null, "A{$headerRow}");
        $sheet->getStyle("A{$headerRow}:H{$headerRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$headerRow}:H{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F0F0');
        $sheet->getStyle("A{$headerRow}:H{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row = $headerRow + 1;
        foreach ($report as $i => $r) {
            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", Carbon::parse($r['tanggal'])->format('d-m-Y'));
            $sheet->setCellValue("C{$row}", $r['jumlah_expat']);
            $sheet->setCellValue("D{$row}", $r['menu_sarapan']);
            $sheet->setCellValue("E{$row}", $r['harga_sarapan']);
            $sheet->setCellValue("F{$row}", $r['menu_makan_siang']);
            $sheet->setCellValue("G{$row}", $r['harga_makan_siang']);
            $sheet->setCellValue("H{$row}", $r['total_harga']);
            $row++;
        }

        $lastDataRow = $row - 1;

        if ($report->isEmpty()) {
            $sheet->mergeCells("A{$row}:H{$row}");
            $sheet->setCellValue("A{$row}", 'Tidak ada data.');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->setCellValue("H{$row}", $report->sum('total_harga'));
        $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF3CD');

        $totalRow = $row;

        $sheet->getStyle("A{$headerRow}:H{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("E" . ($headerRow + 1) . ":E{$totalRow}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
        $sheet->getStyle("G" . ($headerRow + 1) . ":G{$totalRow}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
        $sheet->getStyle("H" . ($headerRow + 1) . ":H{$totalRow}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
        $sheet->getStyle("A" . ($headerRow + 1) . ":C{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $colWidths = ['A' => 5, 'B' => 13, 'C' => 13, 'D' => 30, 'E' => 15, 'F' => 30, 'G' => 17, 'H' => 16];
        foreach ($colWidths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $this->writeExpatSheet($spreadsheet, $start, $end, $npk, $periode);
        $spreadsheet->setActiveSheetIndex(0);

        $writer   = new Xlsx($spreadsheet);
        $fileName = 'laporan_makan_expat_' . $start . '_sd_' . $end . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    /**
     * Bangun rincian & total biaya makan PER EXPAT untuk rentang tanggal
     * tertentu, dikelompokkan per expat. HANYA menu SHARED yang dirinci
     * per expat (dibagi rata dengan jumlah expat yang hadir pada tanggal +
     * kategori tsb) — menu non-shared adalah lumpsum dan tidak bisa
     * diatribusikan ke expat tertentu, jadi tidak diikutsertakan di sini.
     * Formula ini konsisten dengan mealDetailData().
     *
     * @param array $npkFilter Jika tidak kosong, hanya expat pada daftar
     *              ini yang dimunculkan sebagai blok di laporan. Jumlah
     *              expat pembagi tiap menu tetap dihitung dari SELURUH
     *              expat yang hadir hari itu (tidak dipengaruhi filter),
     *              supaya nilai bagi rata tiap expat konsisten dengan
     *              laporan lain.
     *
     * @return Collection<int, array{npk:string,nama:string,items:array,total:float}>
     */
    protected function buildExpatMealDetailReport(string $start, string $end, array $npkFilter = []): Collection
    {
        $allParticipants = ExpatMealParticipant::whereBetween('tanggal', [$start, $end])->get();

        if ($allParticipants->isEmpty()) {
            return collect();
        }

        $tanggalList = $allParticipants
            ->pluck('tanggal')
            ->map(fn($t) => $t->format('Y-m-d'))
            ->unique()
            ->values();

        $menusPerTglKategori = ExpatMealMenu::whereIn('tanggal', $tanggalList)
            ->where('shared', true)
            ->orderBy('makanan')
            ->get()
            ->groupBy(fn($m) => Carbon::parse($m->tanggal)->format('Y-m-d') . '|' . $m->kategori);

        $npkDitampilkan = empty($npkFilter)
            ? $allParticipants->pluck('npk')->unique()
            : collect($npkFilter);

        $perExpat = []; // npk => ['npk' => ..., 'nama' => ..., 'items' => [...], 'total' => ...]

        $allParticipants
            ->groupBy(fn($p) => $p->tanggal->format('Y-m-d') . '|' . $p->kategori)
            ->each(function (Collection $rows, string $key) use ($menusPerTglKategori, $npkDitampilkan, &$perExpat) {
                [$tanggalStr, $kategori] = explode('|', $key, 2);

                $expatHadir  = $rows->unique('npk')->values();
                $jumlahExpat = $expatHadir->count();
                $menusHari   = $menusPerTglKategori[$key] ?? collect();

                foreach ($menusHari as $menu) {
                    $hargaPerExpat = $jumlahExpat > 0 ? round(((float) $menu->harga) / $jumlahExpat, 2) : 0.0;

                    foreach ($expatHadir as $p) {
                        if (! $npkDitampilkan->contains($p->npk)) {
                            continue;
                        }

                        if (! isset($perExpat[$p->npk])) {
                            $perExpat[$p->npk] = ['npk' => $p->npk, 'nama' => $p->nama_expat, 'items' => [], 'total' => 0.0];
                        }

                        $perExpat[$p->npk]['items'][] = [
                            'tanggal'  => $tanggalStr,
                            'kategori' => $kategori,
                            'makanan'  => $menu->makanan,
                            'harga'    => $hargaPerExpat,
                        ];
                        $perExpat[$p->npk]['total'] += $hargaPerExpat;
                    }
                }
            });

        return collect($perExpat)
            ->map(function (array $e) {
                $e['total'] = round($e['total'], 2);
                $e['items'] = collect($e['items'])
                    ->sortBy('makanan')
                    ->sortBy('kategori')
                    ->sortBy('tanggal')
                    ->values()
                    ->all();

                return $e;
            })
            ->sortBy('nama')
            ->values();
    }

    /**
     * Tulis sheet ke-2 pada file export: "Rincian per Expat". Berisi dua
     * bagian — (1) rekap TOTAL biaya makan tiap expat pada periode
     * terpilih, dan (2) RINCIAN tiap menu yang dikonsumsi tiap expat
     * (satu blok per expat, lengkap dengan subtotalnya).
     */
    protected function writeExpatSheet(Spreadsheet $spreadsheet, string $start, string $end, array $npk, string $periode): void
    {
        $expatReport = $this->buildExpatMealDetailReport($start, $end, $npk);

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Rincian per Expat');

        $sheet->mergeCells('A1:E1');
        $sheet->setCellValue('A1', 'RINCIAN & TOTAL BIAYA MAKAN PER EXPAT');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:E2');
        $sheet->setCellValue('A2', 'Periode: ' . $periode);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A3:E3');
        $sheet->setCellValue('A3', 'Catatan: hanya mencakup menu shared (dibagi rata per expat). Menu lumpsum (non-shared) tidak diatribusikan ke expat tertentu.');
        $sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('8A8FA3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        /* ---------- Bagian 1: Total per Expat ---------- */
        $sheet->mergeCells('A5:D5');
        $sheet->setCellValue('A5', 'TOTAL PER EXPAT');
        $sheet->getStyle('A5')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D4EDDA');

        $totalHeaderRow = 6;
        $sheet->fromArray(['NO.', 'NPK', 'NAMA EXPAT', 'TOTAL BIAYA'], null, "A{$totalHeaderRow}");
        $sheet->getStyle("A{$totalHeaderRow}:D{$totalHeaderRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$totalHeaderRow}:D{$totalHeaderRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F0F0');
        $sheet->getStyle("A{$totalHeaderRow}:D{$totalHeaderRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row = $totalHeaderRow + 1;
        foreach ($expatReport as $i => $e) {
            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", $e['npk']);
            $sheet->setCellValue("C{$row}", $e['nama']);
            $sheet->setCellValue("D{$row}", $e['total']);
            $row++;
        }

        if ($expatReport->isEmpty()) {
            $sheet->mergeCells("A{$row}:D{$row}");
            $sheet->setCellValue("A{$row}", 'Tidak ada data.');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        $totalPerExpatLastRow = $row - 1;

        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("A{$row}", 'TOTAL KESELURUHAN');
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->setCellValue("D{$row}", $expatReport->sum('total'));
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:D{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF3CD');

        $grandTotalRow = $row;

        $sheet->getStyle("A{$totalHeaderRow}:D{$grandTotalRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("D" . ($totalHeaderRow + 1) . ":D{$grandTotalRow}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
        if ($totalPerExpatLastRow >= $totalHeaderRow + 1) {
            $sheet->getStyle("A" . ($totalHeaderRow + 1) . ":B{$totalPerExpatLastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        /* ---------- Bagian 2: Rincian per Expat ---------- */
        $row = $grandTotalRow + 3;

        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->setCellValue("A{$row}", 'RINCIAN PER EXPAT');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D4EDDA');
        $row += 2;

        if ($expatReport->isEmpty()) {
            $sheet->mergeCells("A{$row}:E{$row}");
            $sheet->setCellValue("A{$row}", 'Tidak ada data.');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        foreach ($expatReport as $e) {
            $sheet->mergeCells("A{$row}:E{$row}");
            $sheet->setCellValue("A{$row}", $e['nama'] . ' (' . $e['npk'] . ')');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF1FD');
            $row++;

            $itemHeaderRow = $row;
            $sheet->fromArray(['NO.', 'TANGGAL', 'KATEGORI', 'MENU', 'HARGA'], null, "A{$row}");
            $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F0F0');
            $sheet->getStyle("A{$row}:E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;

            foreach ($e['items'] as $i => $item) {
                $sheet->setCellValue("A{$row}", $i + 1);
                $sheet->setCellValue("B{$row}", Carbon::parse($item['tanggal'])->format('d-m-Y'));
                $sheet->setCellValue("C{$row}", $item['kategori']);
                $sheet->setCellValue("D{$row}", $item['makanan']);
                $sheet->setCellValue("E{$row}", $item['harga']);
                $row++;
            }

            $itemLastRow = $row - 1;

            $sheet->mergeCells("A{$row}:D{$row}");
            $sheet->setCellValue("A{$row}", 'Subtotal ' . $e['nama']);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->setCellValue("E{$row}", $e['total']);
            $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF3CD');

            $subtotalRow = $row;

            $sheet->getStyle("A{$itemHeaderRow}:E{$subtotalRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("E" . ($itemHeaderRow + 1) . ":E{$subtotalRow}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
            if ($itemLastRow >= $itemHeaderRow + 1) {
                $sheet->getStyle("A" . ($itemHeaderRow + 1) . ":A{$itemLastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            $row += 2; // spasi antar blok expat
        }

        $colWidths = ['A' => 26, 'B' => 13, 'C' => 14, 'D' => 30, 'E' => 16];
        foreach ($colWidths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
    }

    /* =========================================================================
 * HELPERS
 * ========================================================================= */

    /**
     * Parse filter tanggal & npk dari query string.
     *
     * npk dikirim sebagai array (npk[]=...&npk[]=...) dari filter multi-select
     * pada halaman. Nilai kosong/duplikat dibuang. Array kosong berarti
     * "semua expat" (tidak difilter).
     */
    protected function parseFilters(Request $request): array
    {
        $start = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
        $end   = $request->input('end_date', now()->format('Y-m-d'));

        $npk = $request->input('npk', []);
        if (! is_array($npk)) {
            $npk = ($npk === null || $npk === '') ? [] : [$npk];
        }
        $npk = collect($npk)
            ->map(fn($v) => trim((string) $v))
            ->filter(fn($v) => $v !== '')
            ->unique()
            ->values()
            ->all();

        return [$start, $end, $npk];
    }
}
