<?php

namespace App\Http\Controllers;

use App\Imports\OrdersImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class OrderImportController extends Controller
{
    public function form()
    {
        return view('monitoring.order-import');
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:51200'], // 50MB
            'mode' => ['required', 'in:append,replace'],
        ]);

        $uploaded = $request->file('file');

        // Hitung total baris data (hanya baca kolom B, jadi ringan) untuk progress bar.
        $totalRows = $this->countDataRows($uploaded->getRealPath());

        $batchId = (string) Str::uuid();

        Cache::put("order_import:{$batchId}", [
            'processed'      => 0,
            'total'          => $totalRows,
            'status'         => 'processing',
            'last'           => null,
            'skipped_cancel' => 0,
            'skipped_blank'  => 0,
        ], now()->addHour());

        if ($request->input('mode') === 'replace') {
            DB::table('mon_orders')->truncate();
        }

        // Simpan file permanen dulu -- file upload asli hilang setelah request
        // selesai, padahal import jalan di belakang layar lewat queue.
        $storedPath = $uploaded->store('order-imports');

        (new OrdersImport($batchId, $totalRows))->queue($storedPath);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'batch_id' => $batchId,
                'total'    => $totalRows,
            ]);
        }

        return back()->with('status', "Import dijalankan di background. Batch: {$batchId}");
    }

    public function progress(string $batchId)
    {
        $state = Cache::get("order_import:{$batchId}");

        if (!$state) {
            return response()->json(['status' => 'unknown'], 404);
        }

        return response()->json($state);
    }

    /**
     * Endpoint server-side untuk DataTables, menampilkan isi tabel
     * mon_orders supaya hasil import bisa langsung dicek dari halaman
     * yang sama.
     */
    public function data(Request $request)
    {
        $columns = [
            'id',
            'uraian',
            'ocf_no',
            'buyer_po',
            'buyer',
            'brand',
            'style',
            'item',
            'qty_ord',
            'destination',
            'shipment_mode',
            'production_delivery',
            'buyer_delivery',
            'catatan',
            'import_batch',
            'created_at',
        ];

        $draw   = (int) $request->input('draw', 1);
        $start  = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        $search = trim((string) $request->input('search.value', ''));

        $base = DB::table('mon_orders');

        $recordsTotal = (clone $base)->count();

        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->where('uraian', 'like', "%{$search}%")
                    ->orWhere('ocf_no', 'like', "%{$search}%")
                    ->orWhere('buyer_po', 'like', "%{$search}%")
                    ->orWhere('buyer', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('style', 'like', "%{$search}%")
                    ->orWhere('item', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%")
                    ->orWhere('import_batch', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $base)->count();

        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        $orderDir = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';

        $rows = $base
            ->orderBy($orderColumn, $orderDir)
            ->offset($start)
            ->limit($length > 0 ? $length : 25)
            ->get($columns);

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $rows,
        ]);
    }

    private function countDataRows(string $path): int
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            // Hanya baca kolom B (uraian) supaya hemat memori walau file besar.
            $reader->setReadFilter(new class implements IReadFilter {
                public function readCell($columnAddress, $row, $worksheetName = ''): bool
                {
                    return $columnAddress === 'B';
                }
            });

            $spreadsheet = $reader->load($path);
            $highestRow = $spreadsheet->getSheet(0)->getHighestDataRow();

            return max(0, $highestRow - 1); // dikurangi 1 baris header
        } catch (\Throwable $e) {
            return 0; // gagal dihitung -> progress bar jadi indeterminate di UI
        }
    }
}
