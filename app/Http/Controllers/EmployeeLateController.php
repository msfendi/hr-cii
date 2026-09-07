<?php

namespace App\Http\Controllers;

use App\Exports\EmployeeLateTemplateExport;
use App\Imports\EmployeeLateImport;
use App\Models\EmployeeLate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeLateController extends Controller
{
    /**
     * Display a listing of employee late records.
     */
    public function index()
    {
        $employee = DB::table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT')
            ->unionAll(
                DB::table('BIODATA_KELUAR')
                    ->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT')
            );

        $data = DB::table('employee_lates')
            ->leftJoinSub($employee, 'biodata', function ($join) {
                $join->on('biodata.NPK', '=', 'employee_lates.npk');
            })
            ->leftJoin('DEPT', 'DEPT.ID_DEPT', '=', 'biodata.ID_DEPT')
            ->select(
                'employee_lates.*',
                'biodata.NAMA_KARYAWAN',
                'DEPT.DEPARTEMENT'
            )
            ->orderBy('employee_lates.date', 'desc')
            ->get();

        return view('employee_late.index', compact('data'));
    }

    /**
     * Show the form for creating a new record.
     */
    public function create()
    {
        $biodatas = $this->getBiodatas();

        return view('employee_late.create', compact('biodatas'));
    }

    /**
     * Store a newly created record in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'npk'           => 'required|string|max:50',
            'date'          => 'required|date',
            'arrival_time'  => 'required',
            'reason'        => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->back()
                ->withErrors($validator)
                ->withInput();
        }

        EmployeeLate::create($request->only([
            'npk',
            'date',
            'arrival_time',
            'reason',
        ]));

        return redirect()
            ->route('employee-late.index')
            ->with('success', 'Data keterlambatan karyawan berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified record.
     */
    public function edit($id)
    {
        $row = EmployeeLate::findOrFail($id);
        $biodatas = $this->getBiodatas();

        return view('employee_late.edit', compact('row', 'biodatas'));
    }

    /**
     * Update the specified record in storage.
     */
    public function update(Request $request, $id)
    {
        $row = EmployeeLate::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'npk'           => 'required|string|max:50',
            'date'          => 'required|date',
            'arrival_time'  => 'required',
            'reason'        => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->back()
                ->withErrors($validator)
                ->withInput();
        }

        $row->update($request->only([
            'npk',
            'date',
            'arrival_time',
            'reason',
        ]));

        return redirect()
            ->route('employee-late.index')
            ->with('success', 'Data keterlambatan karyawan berhasil diperbarui.');
    }

    /**
     * Remove the specified record from storage.
     */
    public function destroy($id)
    {
        $row = EmployeeLate::findOrFail($id);
        $row->delete();

        return redirect()
            ->route('employee-late.index')
            ->with('success', 'Data keterlambatan karyawan berhasil dihapus.');
    }

    /**
     * Download template Excel untuk import Employee Late.
     * Kolom: NPK, Date, Arrival Time, Reason (id tidak disertakan).
     */
    public function template()
    {
        return Excel::download(new EmployeeLateTemplateExport(), 'template_employee_late.xlsx');
    }

    /**
     * Import data Employee Late dari file Excel/CSV.
     * Baris dengan format Date/Arrival Time yang tidak valid akan dilewati
     * (tidak membuat seluruh proses import gagal) dan dilaporkan ke user.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $import = new EmployeeLateImport();

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $e) {
            return redirect()
                ->route('employee-late.index')
                ->with('error', 'Import gagal: ' . $e->getMessage());
        }

        $message = "Import selesai. {$import->importedCount} data berhasil diimport.";

        if (!empty($import->failedRows)) {
            $message .= ' ' . count($import->failedRows) . ' baris dilewati karena format tidak valid.';
            session()->flash('import_errors', $import->failedRows);
        }

        return redirect()
            ->route('employee-late.index')
            ->with('success', $message);
    }

    /**
     * Endpoint AJAX untuk select2 pencarian NPK.
     * Sumber data: UNION ALL BIODATA + BIODATA_KELUAR, di-JOIN ke DEPT via ID_DEPT.
     */
    public function searchNpk(Request $request)
    {
        $search = trim((string) $request->get('q', $request->get('search', '')));

        $query = $this->employeeQuery();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('emp.NPK', 'like', "%{$search}%")
                    ->orWhere('emp.NAMA_KARYAWAN', 'like', "%{$search}%");
            });
        }

        $employees = $query->orderBy('emp.NAMA_KARYAWAN')->limit(20)->get();

        $results = $employees->map(function ($emp) {
            $label = $emp->npk . ' - ' . $emp->nama_karyawan;
            if (!empty($emp->departement)) {
                $label .= ' (' . $emp->departement . ')';
            }

            return [
                'id'   => $emp->npk,
                'text' => $label,
            ];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Union of active + resigned employees for the NPK dropdown.
     */
    private function getBiodatas()
    {
        return DB::query()
            ->fromSub(
                DB::table('BIODATA')
                    ->select('NPK', 'NAMA_KARYAWAN')
                    ->union(
                        DB::table('BIODATA_KELUAR')
                            ->select('NPK', 'NAMA_KARYAWAN')
                    ),
                'emp'
            )
            ->orderBy('NPK')
            ->get();
    }

    /**
     * Query builder: UNION ALL BIODATA + BIODATA_KELUAR, JOIN DEPT via ID_DEPT.
     * Dipakai bersama oleh searchNpk() dan edit().
     */
    private function employeeQuery()
    {
        $biodata = DB::table('BIODATA')->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT');
        $biodataKeluar = DB::table('BIODATA_KELUAR')->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT');

        $union = $biodata->unionAll($biodataKeluar);

        return DB::table(DB::raw("({$union->toSql()}) as emp"))
            ->mergeBindings($union)
            ->leftJoin('DEPT', 'DEPT.ID_DEPT', '=', 'emp.ID_DEPT')
            ->select(
                'emp.NPK as npk',
                'emp.NAMA_KARYAWAN as nama_karyawan',
                'DEPT.DEPARTEMENT as departement'
            );
    }
}
