<?php

namespace App\Http\Controllers;

use App\Models\Kunjungan;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    /**
     * Generate Kartu Berobat PDF for a specific karyawan
     */
    public function kartuBerobat($npk)
    {
        // Get karyawan info with dept
        $karyawan = DB::connection('cii')->table('BIODATA')
            ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->where('BIODATA.NPK', $npk)
            ->select('BIODATA.NPK', 'BIODATA.NAMA_KARYAWAN', 'BIODATA.SECTION', 'DEPT.DEPARTEMENT')
            ->first();

        if (!$karyawan) {
            abort(404, 'Karyawan tidak ditemukan.');
        }

        $kunjungans = Kunjungan::with('resepObats')
            ->where('NPK', $npk)
            ->where('status', 'selesai')
            ->orderBy('tanggal_kunjungan', 'asc')
            ->get();

        // Attach dokter names
        $dokterIds = $kunjungans->pluck('dokter_id')->filter()->unique()->toArray();
        $dokters = [];
        if (!empty($dokterIds)) {
            $dokters = DB::connection('cii')->table('users')
                ->whereIn('id', $dokterIds)
                ->pluck('name', 'id')
                ->toArray();
        }

        $totalKunjungan = $kunjungans->count();
        $kunjunganTahunIni = $kunjungans->filter(function ($k) {
            return $k->tanggal_kunjungan->year === now()->year;
        })->count();

        $pdf = Pdf::loadView('reports.kartu-berobat', compact(
            'karyawan',
            'kunjungans',
            'dokters',
            'totalKunjungan',
            'kunjunganTahunIni'
        ))->setPaper('a4', 'portrait');

        return $pdf->stream('kartu-berobat-' . $npk . '.pdf');
    }

    /**
     * Rekap kunjungan with filters and summary
     */
    public function rekap(Request $request)
    {
        $selectedKaryawan = null;
        if ($request->filled('nama')) {
            $selectedKaryawan = DB::connection('cii')->table('BIODATA')->where('NPK', $request->nama)->first();
        }

        $query = Kunjungan::where('status', 'selesai');

        // Filter by date range
        if ($request->filled('dari_tanggal') && $request->filled('sampai_tanggal')) {
            $query->whereBetween('tanggal_kunjungan', [
                $request->dari_tanggal,
                $request->sampai_tanggal,
            ]);
        } else {
            // Default: current month
            $query->whereMonth('tanggal_kunjungan', now()->month)
                ->whereYear('tanggal_kunjungan', now()->year);
        }

        $kunjungans = $query->orderBy('tanggal_kunjungan', 'desc')->get();

        // Bulk-fetch karyawan info for all NPKs
        $npks = $kunjungans->pluck('NPK')->unique()->toArray();
        $karyawanMap = [];
        if (!empty($npks)) {
            $karyawanMap = DB::connection('cii')->table('BIODATA')
                ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
                ->whereIn('BIODATA.NPK', $npks)
                ->select('BIODATA.NPK', 'BIODATA.NAMA_KARYAWAN', 'DEPT.DEPARTEMENT', 'DEPT.ID_DEPT')
                ->get()
                ->keyBy('NPK')
                ->toArray();
        }

        // Bulk-fetch dokter names
        $dokterIds = $kunjungans->pluck('dokter_id')->filter()->unique()->toArray();
        $dokterMap = [];
        if (!empty($dokterIds)) {
            $dokterMap = DB::connection('cii')->table('users')
                ->whereIn('id', $dokterIds)
                ->pluck('name', 'id')
                ->toArray();
        }

        // Filter by departemen (after fetch, since cross-DB)
        if ($request->filled('departemen')) {
            $kunjungans = $kunjungans->filter(function ($k) use ($karyawanMap, $request) {
                $bio = $karyawanMap[$k->NPK] ?? null;
                return $bio && ($bio->ID_DEPT ?? '') == $request->departemen;
            })->values();
        }

        // Filter by Nama/NPK
        if ($request->filled('nama')) {
            $nama = $request->nama;
            $kunjungans = $kunjungans->filter(function ($k) use ($karyawanMap, $nama) {
                $bio = $karyawanMap[$k->NPK] ?? null;
                return (stripos($k->NPK, $nama) !== false) ||
                    ($bio && stripos($bio->NAMA_KARYAWAN ?? '', $nama) !== false);
            })->values();
        }

        // Summary stats
        $totalKunjungan = $kunjungans->count();

        // Diagnosa terbanyak
        $diagnosaTerbanyak = $kunjungans->whereNotNull('diagnosa')
            ->where('diagnosa', '!=', '')
            ->groupBy('diagnosa')
            ->map->count()
            ->sortDesc()
            ->take(5);

        // Per departemen
        $perDepartemen = $kunjungans->groupBy(function ($k) use ($karyawanMap) {
            $bio = $karyawanMap[$k->NPK] ?? null;
            return $bio->DEPARTEMENT ?? 'N/A';
        })->map->count()->sortDesc();

        $departemens = DB::connection('cii')->table('DEPT')
            ->where('SECTION', 'CHUTEX')
            ->orderBy('DEPARTEMENT')
            ->pluck('DEPARTEMENT', 'ID_DEPT');

        return view('reports.rekap', compact(
            'kunjungans',
            'karyawanMap',
            'dokterMap',
            'totalKunjungan',
            'diagnosaTerbanyak',
            'perDepartemen',
            'departemens',
            'selectedKaryawan'
        ));
    }
}
