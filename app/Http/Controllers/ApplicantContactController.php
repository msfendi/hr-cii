<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ApplicantContact;
use App\Models\WhatsappDevice;
use App\Models\WhatsappLog;
use App\Models\WhatsappTemplate;

class ApplicantContactController extends Controller
{

    public function index()
    {
        $applicant_contact = ApplicantContact::latest()->get();

        /*
    |--------------------------------------------------------------------------
    | Ambil Semua Template
    |--------------------------------------------------------------------------
    */
        $templates = WhatsappTemplate::select('id', 'name')->get();

        /*
    |--------------------------------------------------------------------------
    | Log Yang Sudah Pernah Dikirim
    |--------------------------------------------------------------------------
    */
        $sentLogs = WhatsappLog::select('target', 'template_id')
            ->get()
            ->groupBy('target');

        return view('applicant_contact.index', compact(
            'applicant_contact',
            'templates',
            'sentLogs'
        ));
    }

    public function create()
    {
        $device = WhatsappDevice::where('is_active', 1)->first();
        return view('applicant_contact.create', compact('device'));
    }

    public function send(Request $request)
    {
        $devices = WhatsappDevice::where('is_active', true)->get();
        $templates = WhatsappTemplate::where('id', $request->template_id)->get();

        /*
    |--------------------------------------------------------------------------
    | Ambil data dari URL
    |--------------------------------------------------------------------------
    */
        $contact = ApplicantContact::find($request->contact_id);
        $selectedTemplate = $request->template_id;

        return view('applicant_contact.send', compact(
            'devices',
            'templates',
            'contact',
            'selectedTemplate'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'phone' => 'required',
            'name' => 'required',
            'position' => 'nullable'
        ]);

        /*
        |--------------------------------------------------------------------------
        | SIMPAN DATA
        |--------------------------------------------------------------------------
        */

        ApplicantContact::updateOrCreate(
            ['phone' => $request->phone], // kondisi pencarian
            [
                'name' => $request->name,
                'position' => $request->position
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | NORMALISASI NOMOR WHATSAPP
        |--------------------------------------------------------------------------
        */

        // hapus semua karakter selain angka dan +
        $phone = trim($request->phone);

        // hilangkan spasi, dash, dll
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // jika diawali +62
        if (str_starts_with($phone, '+62')) {
            $phone = substr($phone, 1);
        }

        // jika diawali 0
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        /*
        |--------------------------------------------------------------------------
        | TEMPLATE MESSAGE
        |--------------------------------------------------------------------------
        */

        $message = "Perkenalkan saya {$request->name}, "
            . "Saya melamar sebagai {$request->position}.";

        /*
        |--------------------------------------------------------------------------
        | REDIRECT KE WHATSAPP
        |--------------------------------------------------------------------------
        */

        $url = "https://wa.me/{$phone}?text=" . urlencode($message);

        return redirect()->away($url);
    }

    public function destroy($id)
    {
        ApplicantContact::findOrFail($id)->delete();

        return redirect()
            ->route('applicant-contact.index')
            ->with('success', 'Data berhasil dihapus');
    }
}
