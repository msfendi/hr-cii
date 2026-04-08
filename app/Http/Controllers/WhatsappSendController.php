<?php

namespace App\Http\Controllers;

use App\Models\ApplicantContact;
use App\Models\WhatsappDevice;
use Illuminate\Http\Request;

use App\Services\FonnteService;
use App\Models\WhatsappTemplate;
use Carbon\Carbon;
use RealRashid\SweetAlert\Facades\Alert;

class WhatsappSendController extends Controller
{

    public function create()
    {
        $applicants = ApplicantContact::all();
        $devices = WhatsappDevice::where('is_active', true)->get();
        $templates = WhatsappTemplate::latest()->get();
        return view('whatsapp.send.create', compact('devices', 'templates', 'applicants'));
    }

    public function sendTemplate(Request $request, FonnteService $fonnte)
    {
        $template = WhatsappTemplate::findOrFail($request->template_id);

        $message = $template->message;

        $variables = $request->variables ?? [];

        /*
    |--------------------------------------------------------------------------
    | FORMAT TANGGAL INDONESIA
    |--------------------------------------------------------------------------
    */
        if (!empty($variables['tanggal'])) {

            Carbon::setLocale('id');

            $variables['tanggal'] = Carbon::parse($variables['tanggal'])
                ->translatedFormat('l, d F Y');
        }

        /*
        |--------------------------------------------------------------------------
        | REPLACE VARIABLE TEMPLATE
        |--------------------------------------------------------------------------
        */
        foreach ($variables as $key => $value) {
            $message = str_replace("{" . $key . "}", $value, $message);
        }

        // SEND WA
        $fonnte->send(
            $request->device_id,
            $request->target,
            $message,
            $request->template_id
        );

        Alert::success(
            'Send Successfully!',
            'Whatsapp message successfully sent!'
        );

        return redirect()
            ->route('applicant-contact.index');
    }
}
