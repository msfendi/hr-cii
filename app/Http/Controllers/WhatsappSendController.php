<?php

namespace App\Http\Controllers;

use App\Models\WhatsappDevice;
use Illuminate\Http\Request;

use App\Services\FonnteService;
use App\Models\WhatsappTemplate;

class WhatsappSendController extends Controller
{

    public function create()
    {
        $devices = WhatsappDevice::where('is_active', true)->get();
        $templates = WhatsappTemplate::latest()->get();
        return view('whatsapp.send.create', compact('devices', 'templates'));
    }

    public function sendTemplate(Request $request, FonnteService $fonnte)
    {
        $template = WhatsappTemplate::findOrFail($request->template_id);

        $message = $template->message;

        /*
        Replace variable template
        contoh:
        Halo {nama}
        */

        foreach ($request->variables ?? [] as $key => $value) {
            $message = str_replace("{" . $key . "}", $value, $message);
        }

        return $fonnte->send(
            $request->device_id,
            $request->target,
            $message
        );
    }
}
