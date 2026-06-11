<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pelamar;
use App\Models\PelamarDetails;

class PortalRecruitmentStatusController extends Controller
{
    public function index()
    {
        return view('portal_recruitment_status.form');
    }

    public function check(Request $request)
    {
        $request->validate([
            'nik' => 'required|string',
            'reg_date' => 'required|date'
        ]);

        $detail = PelamarDetails::select('pelamar_details.*', 'PELAMAR.NIK', 'PELAMAR.NAMA', 'PELAMAR.KABUPATEN')
            ->join('PELAMAR', 'pelamar_details.id_pelamar', '=', 'PELAMAR.id')
            ->where('PELAMAR.NIK', $request->nik)
            ->whereDate('pelamar_details.created_at', $request->reg_date)
            ->first();

        if (!$detail) {
            return back()->with('error', 'Aplikasi Anda dengan NIK dan tanggal pendaftaran tersebut belum ditemukan dalam sistem kami.');
        }

        return view('portal_recruitment_status.detail_application', compact('detail'));
    }
}
