<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HealthTest;
use App\Models\Pelamar;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RealRashid\SweetAlert\Facades\Alert;

class HealthTestController extends Controller
{
    /**
     * Daftar Health Test.
     *
     * Perbaikan 2026-11-23 (baris ganda): join langsung ke `pelamar` +
     * `pelamar_details` mengalikan baris, karena
     *   - NIK tidak unik di tabel `pelamar` (satu orang bisa melamar berkali-kali
     *     dan setiap lamaran punya barisnya sendiri), dan
     *   - satu baris `pelamar` bisa punya beberapa baris `pelamar_details`.
     * Akibatnya satu Health Test bisa tampil 2-3 kali (mis. ID 284 dan 354).
     *
     * Sekarang nama pelamar dan surat sehat diambil sebagai subquery skalar,
     * sedangkan syarat "punya surat sehat" dipertahankan lewat whereExists.
     * Satu baris `health_tests` = satu baris tabel; isi dan urutan tetap sama.
     */
    public function index()
    {
        $namaPelamar = DB::table('pelamar as p')
            ->select('p.NAMA as nama')
            ->whereColumn('p.NIK', 'health_tests.nik')
            ->orderBy('p.ID')
            ->limit(1);

        $fileSuratSehat = DB::table('pelamar_details as pd')
            ->join('pelamar as p', 'p.ID', '=', 'pd.id_pelamar')
            ->select('pd.file_surat_sehat as file_surat_sehat')
            ->whereColumn('p.NIK', 'health_tests.nik')
            ->whereNotNull('pd.file_surat_sehat')
            ->orderBy('pd.ID')
            ->limit(1);

        $data = HealthTest::query()
            ->select('health_tests.*')
            ->addSelect([
                'nama' => $namaPelamar,
                'file_surat_sehat' => $fileSuratSehat,
            ])
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('pelamar_details as pd')
                    ->join('pelamar as p', 'p.ID', '=', 'pd.id_pelamar')
                    ->whereColumn('p.NIK', 'health_tests.nik')
                    ->whereNotNull('pd.file_surat_sehat');
            })
            ->latest('health_tests.created_at')
            ->get();

        return view('health_test.index', compact('data'));
    }

    /**
     * Detail satu Health Test untuk modal "Detail Health Test".
     *
     * Diambil dari id health test langsung (tanpa join pengganda baris) supaya
     * modal tidak menampilkan data kosong. Nilai diambil per kolom dengan
     * subquery ber-limit 1 supaya tetap benar walau NIK ganda.
     */
    public function show($id)
    {
        $row = DB::table('health_tests')->where('id', $id)->first();

        if (!$row) {
            return response()->json([
                'message' => 'Data Health Test tidak ditemukan.',
            ], 404);
        }

        $namarow = DB::table('pelamar as p')
            ->select('p.NAMA as nama')
            ->where('p.NIK', $row->nik)
            ->orderBy('p.ID')
            ->first();

        $suratrow = DB::table('pelamar_details as pd')
            ->join('pelamar as p', 'p.ID', '=', 'pd.id_pelamar')
            ->select('pd.file_surat_sehat as file_surat_sehat')
            ->where('p.NIK', $row->nik)
            ->whereNotNull('pd.file_surat_sehat')
            ->orderBy('pd.ID')
            ->first();

        return response()->json([
            'id' => $row->id,
            'nik' => $row->nik,
            'nama' => $namarow->nama ?? '-',
            'kesimpulan' => (int) $row->kesimpulan,
            'tanggal_test' => $row->created_at
                ? \Carbon\Carbon::parse($row->created_at)->format('d M Y')
                : '-',

            'cacat' => $row->cacat,
            'buta_warna' => $row->buta_warna,
            'visus_mata_od' => $row->visus_mata_od,
            'visus_mata_os' => $row->visus_mata_os,
            'tinggi' => $row->tinggi,
            'berat' => $row->berat,
            'suhu' => $row->suhu,
            'gigi' => $row->gigi,
            'tekanan_darah' => $row->tekanan_darah,
            'respirasi' => $row->respirasi,
            'denyut' => $row->denyut,
            'abdoment' => $row->abdoment,
            'cor_pulmo' => $row->cor_pulmo,
            'tht' => $row->tht,
            'extreme' => $row->extreme,
            'paru' => $row->paru,
            'hepatitis' => $row->hepatitis,
            'jantung' => $row->jantung,
            'thypoid' => $row->thypoid,
            'alergi' => $row->alergi,
            'ashma' => $row->ashma,
            'lain' => $row->lain,
            'remark' => $row->remark,
            'file_surat_sehat' => $suratrow->file_surat_sehat ?? null,
        ]);
    }

    public function create()
    {
        $pelamar = Pelamar::select('PELAMAR.ID', 'NIK', 'NAMA', 'TINGGI_BADAN', 'BERAT_BADAN')
            ->leftJoin('pelamar_details', 'pelamar_details.id_pelamar', '=', 'PELAMAR.ID')
            ->where('pelamar_details.status_apply', '!=', 'ONBOARDING')
            ->where('pelamar_details.status_apply', '!=', 'REJECTED')
            ->get();
        // dd($pelamar[0]);

        return view('health_test.create', compact('pelamar'));
    }

    public function store(Request $request)
    {
        $healthTest = HealthTest::create([
            'nik' => $request->nik,

            'cacat' => $request->cacat ?? 0,
            'tinggi' => $request->tinggi ?? 0,
            'berat' => $request->berat ?? 0,
            'buta_warna' => $request->buta_warna ?? 0,
            'visus_mata_od' => $request->visus_mata_od,
            'visus_mata_os' => $request->visus_mata_os,

            'abdoment' => $request->abdoment,
            'gigi' => $request->gigi,
            'cor_pulmo' => $request->cor_pulmo,
            'tht' => $request->tht,
            'extreme' => $request->extreme,

            'tekanan_darah' => $request->tekanan_darah,
            'respirasi' => $request->respirasi,
            'denyut' => $request->denyut,
            'suhu' => $request->suhu,

            'paru' => $request->paru ?? 0,
            'hepatitis' => $request->hepatitis ?? 0,
            'jantung' => $request->jantung ?? 0,
            'thypoid' => $request->thypoid ?? 0,
            'alergi' => $request->alergi ?? 0,
            'ashma' => $request->ashma ?? 0,

            'lain' => $request->lain,
            'kesimpulan' => $request->kesimpulan ?? 0,
            'remark' => $request->remark
        ]);

        // dd($healthTest);

        /*
        |--------------------------------------------------------------------------
        | Ambil Data Pelamar
        |--------------------------------------------------------------------------
        */
        $pelamar = DB::table('pelamar')
            ->where('NIK', $request->nik)
            ->whereNull('NPK')
            ->first();

        if ($pelamar) {

            $namaPelamar = strtolower(trim($pelamar->NAMA));

            $namaPelamar = preg_replace('/[^a-z0-9]+/i', '_', $namaPelamar);
            $namaPelamar = trim($namaPelamar, '_');

            $namaFile = 'surat_sehat_' .
                $namaPelamar . '_' .
                now()->format('Ymd') .
                '.pdf';

            $relativePath = 'pelamar/surat_sehat/' . $namaFile;

            /*
    |--------------------------------------------------------------------------
    | Data PDF
    |--------------------------------------------------------------------------
    */
            $data = DB::table('health_tests')
                ->join('pelamar', 'pelamar.NIK', '=', 'health_tests.nik')
                ->where('health_tests.id', $healthTest->id)
                ->select(
                    'pelamar.*',
                    'health_tests.*'
                )
                ->first();

            /*
    |--------------------------------------------------------------------------
    | Generate PDF
    |--------------------------------------------------------------------------
    */
            $pdf = Pdf::loadView(
                'health_test.form_medical',
                [
                    'data' => $data
                ]
            );

            Storage::disk('public')->put(
                $relativePath,
                $pdf->output()
            );

            /*
    |--------------------------------------------------------------------------
    | Update pelamar_details
    |--------------------------------------------------------------------------
    */
            DB::table('pelamar_details')
                ->where('id_pelamar', $pelamar->ID)
                ->update([
                    'file_surat_sehat' => $relativePath,
                    'result_kesehatan' => $request->kesimpulan ? 'TRUE' : 'FALSE',
                    'comment_kesehatan' => $request->remark,
                    'updated_at' => now()
                ]);
        }

        Alert::success('Success', 'Health Test created');

        return redirect()->route('health-test.index');
    }

    public function delete($id)
    {
        HealthTest::findOrFail($id)->delete();

        Alert::success('Deleted', 'Data Successfully Deleted');
        return back();
    }
    public function edit($id)
    {
        $data = HealthTest::findOrFail($id);
        $pelamar = DB::table('pelamar')->get();

        return view('health_test.edit', compact('data', 'pelamar'));
    }

    public function downloadPdf($id)
    {
        $data = DB::table('health_tests')
            ->join('pelamar', 'pelamar.NIK', '=', 'health_tests.nik')
            ->where('health_tests.id', $id)
            ->select(
                'pelamar.*',
                'health_tests.*'
            )
            ->first();

        if (!$data) {
            abort(404);
        }

        // return view('health_test.form_medical', compact('data'));

        $pdf = Pdf::loadView('health_test.form_medical', compact('data'))
            ->setPaper('A4', 'portrait');

        return $pdf->download('TEST_KESEHATAN_' . $data->NIK . '.pdf');
    }

    public function update(Request $request, $id)
    {
        $healthTest = HealthTest::find($id);

        if (!$healthTest) {
            Alert::error('Failed', 'Health Test tidak ditemukan.');
            return redirect()->route('health-test.index');
        }

        $healthTest->update([
            'nik' => $request->nik,

            'cacat' => $request->cacat ?? 0,
            'buta_warna' => $request->buta_warna ?? 0,

            'visus_mata_od' => $request->visus_mata_od,
            'visus_mata_os' => $request->visus_mata_os,

            'abdoment' => $request->abdoment,
            'gigi' => $request->gigi,
            'cor_pulmo' => $request->cor_pulmo,
            'tht' => $request->tht,
            'extreme' => $request->extreme,

            'tekanan_darah' => $request->tekanan_darah,
            'respirasi' => $request->respirasi,
            'denyut' => $request->denyut,
            'suhu' => $request->suhu,

            'paru' => $request->paru ?? 0,
            'hepatitis' => $request->hepatitis ?? 0,
            'jantung' => $request->jantung ?? 0,
            'thypoid' => $request->thypoid ?? 0,
            'alergi' => $request->alergi ?? 0,
            'ashma' => $request->ashma ?? 0,

            'lain' => $request->lain,
            'kesimpulan' => $request->kesimpulan ?? 0,
            'remark' => $request->remark,
        ]);

        /*
    |--------------------------------------------------------------------------
    | Ambil Data Pelamar
    |--------------------------------------------------------------------------
    */
        $pelamar = DB::table('pelamar')
            ->where('NIK', $request->nik)
            ->first();

        if ($pelamar) {

            $namaPelamar = strtolower(trim($pelamar->NAMA));
            $namaPelamar = preg_replace('/[^a-z0-9]+/i', '_', $namaPelamar);
            $namaPelamar = trim($namaPelamar, '_');

            $namaFile = 'surat_sehat_' .
                $namaPelamar . '_' .
                now()->format('Ymd') .
                '.pdf';

            $relativePath = 'pelamar/surat_sehat/' . $namaFile;

            /*
        |--------------------------------------------------------------------------
        | Ambil Data Terbaru
        |--------------------------------------------------------------------------
        */
            $data = DB::table('health_tests')
                ->join('pelamar', 'pelamar.NIK', '=', 'health_tests.nik')
                ->where('health_tests.id', $healthTest->id)
                ->select(
                    'pelamar.*',
                    'health_tests.*'
                )
                ->first();

            /*
        |--------------------------------------------------------------------------
        | Generate PDF Baru
        |--------------------------------------------------------------------------
        */
            $pdf = Pdf::loadView(
                'health_test.form_medical',
                [
                    'data' => $data
                ]
            );

            Storage::disk('public')->put(
                $relativePath,
                $pdf->output()
            );

            /*
        |--------------------------------------------------------------------------
        | Update pelamar_details
        |--------------------------------------------------------------------------
        */
            DB::table('pelamar_details')
                ->where('id_pelamar', $pelamar->ID)
                ->update([
                    'file_surat_sehat' => $relativePath,
                    'result_kesehatan' => $request->kesimpulan ? 'TRUE' : 'FALSE',
                    'comment_kesehatan' => $request->remark,
                    'updated_at' => now()
                ]);
        }

        Alert::success('Success', 'Health Test berhasil diperbarui.');

        return redirect()->route('health-test.index');
    }
}
