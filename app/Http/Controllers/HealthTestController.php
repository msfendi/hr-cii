<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HealthTest;
use App\Models\Pelamar;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;

class HealthTestController extends Controller
{
    public function index()
    {
        $data = HealthTest::query()
            ->join('pelamar', 'pelamar.NIK', '=', 'health_tests.nik')
            ->select(
                'health_tests.*',
                'pelamar.NAMA as nama'
            )
            ->latest('health_tests.created_at')
            ->get();

        // dd($data[0]);

        return view('health_test.index', compact('data'));
    }

    public function create()
    {
        $pelamar = Pelamar::select('NIK', 'NAMA', 'TINGGI_BADAN', 'BERAT_BADAN')->leftJoin('pelamar_details', 'pelamar_details.id_pelamar', '=', 'PELAMAR.ID')->where('pelamar_details.status_apply', '=', 'APPLIED')->get();
        // dd($pelamar[0]);

        return view('health_test.create', compact('pelamar'));
    }

    public function store(Request $request)
    {
        HealthTest::create([
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
            'remark' => $request->remark
        ]);

        Alert::success('Success', 'Health Test created');

        return redirect()->route('health-test.index');
    }

    public function delete($id)
    {
        HealthTest::findOrFail($id)->delete();

        Alert::success('Deleted', 'Data deleted');
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
}
