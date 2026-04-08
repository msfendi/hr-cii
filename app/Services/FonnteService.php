<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\WhatsappDevice;
use App\Models\WhatsappLog;

class FonnteService
{
    protected $masterKey;

    public function __construct()
    {
        $this->masterKey = config('services.fonnte.master_key');
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE DEVICE
    |--------------------------------------------------------------------------
    */
    public function createDevice($name, $phone = null)
    {
        $response = Http::asMultipart()
            ->withHeaders([
                'Authorization' => $this->masterKey,
            ])
            ->post('https://api.fonnte.com/add-device', [
                [
                    'name' => 'name',
                    'contents' => $name
                ],
                [
                    'name' => 'device',
                    'contents' => $phone ?? ''
                ],
                [
                    'name' => 'autoread',
                    'contents' => 'false'
                ],
                [
                    'name' => 'personal',
                    'contents' => 'false'
                ],
                [
                    'name' => 'group',
                    'contents' => 'false'
                ],
            ]);

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | GET QR CODE
    |--------------------------------------------------------------------------
    */
    public function getQr($device)
    {
        $response = Http::asMultipart()
            ->withHeaders([
                'Authorization' => $device->token,
            ])
            ->post('https://api.fonnte.com/qr', [
                [
                    'name' => 'type',
                    'contents' => 'qr'
                ],
                [
                    'name' => 'whatsapp',
                    'contents' => $device->phone
                ],
            ]);

        return $response->json();
    }

    /*
    |--------------------------------------------------------------------------
    | DEVICE STATUS
    |--------------------------------------------------------------------------
    */
    public function deviceStatus($token)
    {
        return Http::withHeaders([
            'Authorization' => $token
        ])->get('https://api.fonnte.com/device')->json();
    }

    public function send($deviceId, $target, $message)
    {
        $device = WhatsappDevice::findOrFail($deviceId);

        $response = Http::withHeaders([
            'Authorization' => $device->token
        ])->asForm()->post(config('fonnte.base_url') . '/send', [
            'target' => $target,
            'message' => $message,
        ]);

        WhatsappLog::create([
            'device_id' => $device->id,
            'target' => $target,
            'message' => $message,
            'status' => $response->successful() ? 'success' : 'failed'
        ]);

        return $response->json();
    }

    public function deleteDevice($deviceToken)
    {
        $response = Http::asMultipart()
            ->withHeaders([
                'Authorization' => $deviceToken,
            ])
            ->post('https://api.fonnte.com/delete-device', []);

        return $response->json();
    }
}
