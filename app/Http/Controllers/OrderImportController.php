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
            'processed' => 0,
            'total'     => $totalRows,
            'status'    => 'processing',
            'last'      => null,
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
