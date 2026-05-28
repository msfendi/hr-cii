<?php

namespace App\Http\Controllers;

use App\Models\Kunjungan;
use App\Models\ResepObat;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DokterAntrianController extends Controller
{
    /**
     * Display today's queue (antrian hari ini)
     */
    public function index()
    {
        $today = today();

        $antrians = Kunjungan::whereDate('tanggal_kunjungan', $today)
            ->orderBy('no_antrian', 'desc')
            ->get();

        // Bulk-fetch karyawan info
        $npks = $antrians->pluck('NPK')->filter()->unique()->toArray();
        $karyawanMap = [];
        if (!empty($npks)) {
            $karyawanMap = DB::connection('cii')->table('BIODATA')
                ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
                ->whereIn('BIODATA.NPK', $npks)
                ->select('BIODATA.NPK', 'BIODATA.NAMA_KARYAWAN', 'DEPT.DEPARTEMENT')
                ->get()
                ->keyBy('NPK')
                ->toArray();
        }

        // Stats
        $totalHariIni = $antrians->count();
        $totalMenunggu = $antrians->where('status', 'menunggu')->count();
        $totalDiperiksa = $antrians->where('status', 'diperiksa')->count();
        $totalSelesai = $antrians->where('status', 'selesai')->count();

        return view('dokter.antrian', compact(
            'antrians',
            'karyawanMap',
            'totalHariIni',
            'totalMenunggu',
            'totalDiperiksa',
            'totalSelesai'
        ));
    }

    /**
     * Start examination: set status to diperiksa
     */
    public function mulaiPeriksa($id)
    {
        $kunjungan = Kunjungan::findOrFail($id);

        if ($kunjungan->status !== 'menunggu') {
            return redirect()->route('dokter.antrian')
                ->with('error', 'Pasien ini sudah/sedang diperiksa.');
        }

        $kunjungan->update([
            'status' => 'diperiksa',
            'jam_masuk' => Carbon::now('Asia/Jakarta')->format('H:i'),
            'dokter_id' => auth()->id(),
        ]);

        return redirect()->route('dokter.periksa', $kunjungan->id)
            ->with('success', 'Pemeriksaan dimulai.');
    }

    /**
     * Show examination form
     */
    public function formPeriksa($id)
    {
        $kunjungan = Kunjungan::with(['resepObats', 'users'])->findOrFail($id);

        if ($kunjungan->status === 'menunggu') {
            return redirect()->route('dokter.antrian')
                ->with('error', 'Silakan klik "Mulai Periksa" terlebih dahulu.');
        }

        // Get karyawan info
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

        return view('dokter.periksa', compact('kunjungan', 'karyawan'));
    }

    /**
     * Complete examination: save diagnosa, tindakan, obat, and mark as selesai
     */
    public function selesaiPeriksa(Request $request, $id)
    {
        $request->validate([
            'diagnosa' => 'required|string',
            'catatan_dokter' => 'nullable|string',
            'tindakan' => 'nullable|string',
            'obat' => 'nullable|array',
            'obat.*' => 'nullable|string',
        ]);

        $kunjungan = Kunjungan::findOrFail($id);

        $kunjungan->update([
            'diagnosa' => $request->diagnosa,
            'catatan_dokter' => $request->catatan_dokter,
            'tindakan' => $request->tindakan,
            'status' => 'selesai',
            'jam_selesai' => Carbon::now('Asia/Jakarta')->format('H:i'),
        ]);

        // Save resep obat
        if ($request->filled('obat')) {
            foreach ($request->obat as $obat) {
                if (!empty(trim($obat))) {
                    ResepObat::create([
                        'kunjungan_id' => $kunjungan->id,
                        'keterangan_obat' => trim($obat),
                    ]);
                }
            }
        }

        // Get karyawan name for flash message
        if ($kunjungan->NPK) {
            $namaKaryawan = DB::connection('cii')->table('BIODATA')
                ->where('NPK', $kunjungan->NPK)
                ->value('NAMA_KARYAWAN') ?? '';
        } else {
            $namaKaryawan = $kunjungan->nama ?? '';
        }

        return redirect()->route('dokter.antrian')
            ->with('success', 'Pemeriksaan selesai. Pasien: ' . $namaKaryawan);
    }

    /**
     * Get prescriptions for dispensing
     */
    public function getResep($id)
    {
        $kunjungan = Kunjungan::with('resepObats')->findOrFail($id);
        return response()->json($kunjungan->resepObats);
    }

    /**
     * Save dispensed quantities
     */
    public function simpanQtyObat(Request $request, $id)
    {
        $request->validate([
            'qty' => 'required|array',
            'qty.*' => 'nullable|string',
        ]);

        $kunjungan = Kunjungan::findOrFail($id);

        foreach ($request->qty as $resepId => $qty) {
            if ($qty !== null) {
                ResepObat::where('id', $resepId)
                    ->where('kunjungan_id', $kunjungan->id)
                    ->update(['qty' => $qty]);
            }
        }

        return redirect()->back()->with('success', 'Berhasil menyimpan qty obat');
    }
}
