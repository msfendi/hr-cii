<?php

namespace App\Http\Controllers;

use App\Models\Kunjungan;
use App\Models\ResepObat;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminKunjunganController extends Controller
{
    /**
     * Display kunjungan list page
     */
    public function index()
    {
        $departemens = DB::connection('cii')->table('DEPT')
            ->where('SECTION', 'CHUTEX')
            ->orderBy('DEPARTEMENT')
            ->pluck('DEPARTEMENT', 'ID_DEPT');

        return view('kunjungan.index', compact('departemens'));
    }

    /**
     * DataTables AJAX data source — uses raw DB query to avoid SQL Server offset issues
     */
    public function getData(Request $request)
    {
        $query = DB::connection('cii')->table('kunjungans')
            ->select([
                'kunjungans.id',
                'kunjungans.NPK',
                'kunjungans.nama',
                'kunjungans.dept',
                'kunjungans.tanggal_kunjungan',
                'kunjungans.keluhan',
                'kunjungans.status',
                'kunjungans.no_antrian',
                'kunjungans.jam_masuk',
                'kunjungans.jam_selesai',
            ]);

        // Default: show today if no tanggal filter
        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal_kunjungan', $request->tanggal);
        } else {
            $query->whereDate('tanggal_kunjungan', today());
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return DataTables::of($query)
            ->order(function ($query) {
                $query->orderBy('no_antrian', 'desc');
            })
            ->addColumn('nama_karyawan', function ($row) {
                if ($row->NPK) {
                    $bio = DB::connection('cii')->table('BIODATA')->where('NPK', $row->NPK)->first();
                    return $bio->NAMA_KARYAWAN ?? '-';
                }
                return $row->nama ?? '-';
            })
            ->addColumn('departemen', function ($row) {
                if ($row->NPK) {
                    $bio = DB::connection('cii')->table('BIODATA')
                        ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
                        ->where('BIODATA.NPK', $row->NPK)
                        ->select('DEPT.DEPARTEMENT')
                        ->first();
                    return $bio->DEPARTEMENT ?? '-';
                }
                return $row->dept ?? '-';
            })
            ->addColumn('tanggal', function ($row) {
                return Carbon::parse($row->tanggal_kunjungan)->format('d/m/Y');
            })
            ->addColumn('status_badge', function ($row) {
                $colors = [
                    'menunggu' => 'warning',
                    'diperiksa' => 'info',
                    'selesai' => 'success',
                ];
                $color = $colors[$row->status] ?? 'secondary';
                return '<span class="badge badge-' . $color . '">' . ucfirst($row->status) . '</span>';
            })
            ->rawColumns(['status_badge'])
            ->make(true);
    }

    /**
     * Store new kunjungan (patient registration)
     */
    public function store(Request $request)
    {
        $isNonEmployee = $request->has('is_non_employee');

        if ($isNonEmployee) {
            $request->validate([
                'nama' => 'required|string',
                'dept' => 'required|string',
                'keluhan' => 'required|string',
                'tanggal_kunjungan' => 'nullable|date',
            ]);
        } else {
            $request->validate([
                'NPK' => 'required|string',
                'keluhan' => 'required|string',
                'tanggal_kunjungan' => 'nullable|date',
            ]);
        }

        $tanggal = $request->tanggal_kunjungan ?? today()->toDateString();

        // Auto-generate no_antrian for today
        $noAntrian = Kunjungan::whereDate('tanggal_kunjungan', $tanggal)->count() + 1;

        Kunjungan::create([
            'NPK' => $isNonEmployee ? null : $request->NPK,
            'nama' => $isNonEmployee ? strtoupper($request->nama) : null,
            'dept' => $isNonEmployee ? strtoupper($request->dept) : null,
            'tanggal_kunjungan' => $tanggal,
            'keluhan' => $request->keluhan,
            'no_antrian' => $noAntrian,
            'status' => 'menunggu',
        ]);

        return response()->json([
            'message' => 'Kunjungan berhasil didaftarkan. No Antrian: ' . $noAntrian,
            'no_antrian' => $noAntrian,
        ]);
    }

    /**
     * Search karyawan for Select2 AJAX — LEFT JOIN with DEPT to show department
     */
    public function searchKaryawan(Request $request)
    {
        $search = $request->get('q', '');

        $results = DB::connection('cii')->table('BIODATA')
            ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->where(function ($q) use ($search) {
                $q->where('BIODATA.NPK', 'LIKE', "%{$search}%")
                    ->orWhere('BIODATA.NAMA_KARYAWAN', 'LIKE', "%{$search}%");
            })
            ->select('BIODATA.NPK', 'BIODATA.NAMA_KARYAWAN', 'DEPT.DEPARTEMENT')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->NPK,
                    'text' => $item->NPK . ' - ' . $item->NAMA_KARYAWAN . ' (' . ($item->DEPARTEMENT ?? '-') . ')',
                ];
            });

        return response()->json(['results' => $results]);
    }

    /**
     * Print kartu kunjungan PDF for a single visit
     */
    public function cetakKartu($id)
    {
        $kunjungan = Kunjungan::with('resepObats')->findOrFail($id);

        // Get karyawan info via DB query or use nama/dept for non-employees
        if ($kunjungan->NPK) {
            $karyawan = DB::connection('cii')->table('BIODATA')
                ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
                ->where('BIODATA.NPK', $kunjungan->NPK)
                ->select('BIODATA.NPK', 'BIODATA.NAMA_KARYAWAN', 'DEPT.DEPARTEMENT')
                ->first();
        } else {
            $karyawan = (object) [
                'NPK' => '-',
                'NAMA_KARYAWAN' => $kunjungan->nama,
                'DEPARTEMENT' => $kunjungan->dept,
            ];
        }

        // Get dokter name
        $dokter = $kunjungan->dokter_id
            ? DB::connection('cii')->table('users')->where('id', $kunjungan->dokter_id)->value('name')
            : null;

        $pdf = Pdf::loadView('reports.kartu-kunjungan', compact('kunjungan', 'karyawan', 'dokter'))
            ->setPaper('a5', 'portrait');

        return $pdf->stream('kartu-kunjungan-' . $kunjungan->no_antrian . '.pdf');
    }
}
