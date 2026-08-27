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
    public function index()
    {
        $data = HealthTest::query()
            ->join('pelamar', 'pelamar.NIK', '=', 'health_tests.nik')
            ->leftJoin('pelamar_details', 'pelamar_details.id_pelamar', '=', 'pelamar.id')
            ->select(
                'health_tests.*',
                'pelamar.NAMA as nama',
                'pelamar_details.file_surat_sehat'
            )
            ->whereNotNull('pelamar_details.file_surat_kesehatan')
            ->latest('health_tests.created_at')
            ->get();

        return view('health_test.index', compact('data'));
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
