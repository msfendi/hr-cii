<?php

namespace App\Http\Controllers;

use App\Models\Outsource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

class OutsourceController extends Controller
{
    public function index()
    {
        $data = Outsource::orderBy('NAMA')->get();

        return view('outsource.index', compact('data'));
    }

    public function create()
    {
        $nextNpk = $this->generateNextNpk();

        return view('outsource.create', compact('nextNpk'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'NAMA'   => 'required|string|max:150',
            'VENDOR' => 'nullable|string|max:150',
        ]);

        // NPK di-generate ulang di server (bukan dari input) supaya tetap
        // increment yang benar meski ada 2 orang buka form create bersamaan.
        DB::transaction(function () use ($request) {
            $npk = $this->generateNextNpk(true);

            Outsource::create([
                'NPK'    => $npk,
                'NAMA'   => $request->input('NAMA'),
                'VENDOR' => $request->input('VENDOR'),
                'void'   => 'false',
            ]);
        });

        return redirect()->route('outsource.index')->with('success', 'Data outsource berhasil ditambahkan.');
    }

    /**
     * Ambil NPK terakhir dengan format "O-00007" lalu naikkan 1, dengan padding
     * angka mengikuti panjang digit NPK terakhir (default 5 digit).
     *
     * $lock=true mengunci baris terakhir (lockForUpdate) selama dipanggil di
     * dalam DB::transaction(), untuk menghindari NPK ganda saat submit bersamaan.
     */
    protected function generateNextNpk(bool $lock = false): string
    {
        $prefix = 'O-';

        $query = Outsource::where('NPK', 'like', $prefix . '%');

        if ($lock) {
            $query->lockForUpdate();
        }

        $lastNpk = $query
            ->orderByRaw('CAST(REPLACE(NPK, ?, \'\') AS INT) DESC', [$prefix])
            ->value('NPK');

        $digits = 5; // O-00007 -> 5 digit
        $lastNumber = 0;

        if ($lastNpk) {
            $numericPart = preg_replace('/\D/', '', str_replace($prefix, '', $lastNpk));
            $lastNumber  = (int) $numericPart;
            $digits      = max($digits, strlen($numericPart));
        }

        $nextNumber = $lastNumber + 1;

        return $prefix . str_pad($nextNumber, $digits, '0', STR_PAD_LEFT);
    }

    public function edit($id)
    {
        $row = Outsource::findOrFail($id);

        return view('outsource.edit', compact('row'));
    }

    public function update(Request $request, $id)
    {
        $row = Outsource::findOrFail($id);

        $request->validate([
            'NPK'    => 'required|string|max:50,',
            'NAMA'   => 'required|string|max:150',
            'VENDOR' => 'nullable|string|max:150',
        ]);

        $row->update($request->only(['NPK', 'NAMA', 'VENDOR']));

        return redirect()->route('outsource.index')->with('success', 'Data outsource berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $row = Outsource::findOrFail($id);

        // Ubah kolom void menjadi true
        $row->void = 'true';
        $row->save();

        // Atau jika ingin langsung delete setelah update
        // $row->delete();

        return redirect()->route('outsource.index')->with('success', 'Data outsource berhasil dihapus.');
    }



    /**
     * Download template Excel untuk import data outsource.
     */
    public function template()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Outsource');

        $sheet->fromArray(['NPK', 'NAMA', 'VENDOR'], null, 'A1');
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        foreach (['A', 'B', 'C'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(25);
        }

        $writer = new Xlsx($spreadsheet);

        $fileName = 'template_outsource.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    /**
     * Import data outsource dari file Excel (kolom: NPK, NAMA, VENDOR).
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $path = $request->file('file')->getRealPath();

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'File tidak dapat dibaca: ' . $e->getMessage()], 422);
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        $errors  = [];
        $imported = 0;

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                if ($i == 1) {
                    continue; // skip header
                }

                $npk    = trim((string) ($row['A'] ?? ''));
                $nama   = trim((string) ($row['B'] ?? ''));
                $vendor = trim((string) ($row['C'] ?? ''));

                if ($npk === '' && $nama === '') {
                    continue; // baris kosong
                }

                if ($npk === '' || $nama === '') {
                    $errors[] = "Baris $i: NPK/NAMA wajib diisi, dilewati.";
                    continue;
                }

                Outsource::updateOrCreate(
                    ['NPK' => $npk],
                    ['NAMA' => $nama, 'VENDOR' => $vendor ?: null]
                );

                $imported++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal import: ' . $e->getMessage()], 500);
        }

        if (! empty($errors)) {
            session()->flash('import_errors', $errors);
        }

        return response()->json([
            'message' => "Import selesai. $imported baris berhasil diproses.",
        ]);
    }
}
