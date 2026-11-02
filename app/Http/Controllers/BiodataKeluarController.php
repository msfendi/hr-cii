<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BiodataKeluarController extends Controller
{
    public function index()
    {
        $departments = DB::connection('cii')
            ->table('DEPT')
            ->select('ID_DEPT', 'DEPARTEMENT')
            ->where('SECTION', 'CHUTEX')
            ->get();

        return view('biodata_keluar.index', compact('departments'));
    }

    // Ambil data dari PKWT yang TKK-nya sudah terisi
    public function getData(Request $request)
    {
        $query = DB::connection('cii')->table('PKWT')
            ->select('NPK', 'NAMA', 'KTP', 'TMK', 'TKK', 'KETERANGAN', 'leave_reasons', 'BAGIAN',
                     'file_penalty_letter', 'is_agreement_signed')
            ->whereNotNull('TKK')
            ->orderBy('NPK', 'asc');

        if ($request->filled('department_id')) {
            $query->where('ID_DEPT', $request->department_id);
        }

        $data = $query->get();

        return response()->json(['data' => $data]);
    }

    // Update data di PKWT berdasarkan NPK
    public function update(Request $request, $npk)
    {
        $request->validate([
            'keterangan'          => 'required|in:SPD,HK,MA',
            'tkk'                 => 'required|date',
            'leave_reasons'       => 'nullable|string',
            'file_penalty_letter' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
            'is_agreement_signed' => 'nullable',
        ]);

        try {
            $npkClean = strtoupper(trim($npk));

            $updateData = [
                'KETERANGAN'    => $request->keterangan,
                'leave_reasons' => $request->leave_reasons,
                'TKK'           => $request->tkk,
            ];

            // Resign-only: penalty letter & agreement toggle
            if ($request->keterangan === 'SPD') {
                if ($request->hasFile('file_penalty_letter')) {
                    $penaltyFile = $request->file('file_penalty_letter');
                    $penaltyName = $npkClean . '_PENALTY_LETTER_' . now()->format('Ymd') . '.' . $penaltyFile->getClientOriginalExtension();
                    $penaltyFile->storeAs('employees/penalty_letters', $penaltyName, 'public');
                    $updateData['file_penalty_letter'] = 'employees/penalty_letters/' . $penaltyName;
                }
                $updateData['is_agreement_signed'] = $request->filled('is_agreement_signed') ? 1 : 0;
            }

            DB::connection('cii')->table('PKWT')
                ->where('NPK', $npkClean)
                ->update($updateData);

            return response()->json(['status' => 'success', 'message' => 'Data berhasil diperbarui.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
