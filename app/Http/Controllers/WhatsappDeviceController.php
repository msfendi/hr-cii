<?php

namespace App\Http\Controllers;

use App\Models\WhatsappDevice;
use App\Services\FonnteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RealRashid\SweetAlert\Facades\Alert;

class WhatsappDeviceController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */
    public function index(FonnteService $fonnte)
    {
        /*
        |--------------------------------------------------------------------------
        | GET DEVICES DB
        |--------------------------------------------------------------------------
        */
        $devices = WhatsappDevice::latest()->get();

        /*
        |--------------------------------------------------------------------------
        | GET DEVICES FROM FONNTE MASTER API
        |--------------------------------------------------------------------------
        */
        $fonnteDevices = [];

        try {

            $response = Http::withHeaders([
                'Authorization' => env('FONNTE_MASTER_KEY')
            ])->post('https://api.fonnte.com/get-devices');

            $result = $response->json();

            if (($result['status'] ?? false) === true) {

                foreach ($result['data'] as $apiDevice) {

                    /*
            |--------------------------------------------------------------------------
            | SIMPAN DATA UNTUK VIEW
            |--------------------------------------------------------------------------
            */
                    $fonnteDevices[$apiDevice['device']] = [
                        'status' => strtolower($apiDevice['status']),
                        'quota'  => $apiDevice['quota']
                    ];

                    /*
            |--------------------------------------------------------------------------
            | SYNC STATUS KE DATABASE
            |--------------------------------------------------------------------------
            */
                    $device = $devices
                        ->where('phone', $apiDevice['device'])
                        ->first();

                    if ($device) {

                        $device->update([
                            'is_active' =>
                            strtolower($apiDevice['status']) === 'connect'
                                ? 1
                                : 0
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            // fail silent supaya page tetap load
        }


        return view('whatsapp.devices.index', compact(
            'devices',
            'fonnteDevices'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE FORM
    |--------------------------------------------------------------------------
    */
    public function create()
    {
        return view('whatsapp.devices.create');
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public function store(Request $request, FonnteService $fonnte)
    {
        $request->validate([
            'name'  => 'required',
            'phone' => 'required'
        ]);

        $result = $fonnte->createDevice(
            $request->name,
            $request->phone
        );

        // dd($result['reason']);

        /*
        |--------------------------------------------------------------------------
        | DEVICE ALREADY EXIST
        |--------------------------------------------------------------------------
        */
        if (($result['reason'] ?? null) === 'device already exist') {

            Alert::warning(
                'Device already exist',
                'Device with phone ' . $request->phone . ' already exist in Fonnte.'
            );

            return back();
        }

        /*
        |--------------------------------------------------------------------------
        | FAILED RESPONSE
        |--------------------------------------------------------------------------
        */
        if (!($result['status'] ?? false)) {

            Alert::error('Failed!', $result['reason'] ?? 'Unknown error');

            return back();
        }


        WhatsappDevice::create([
            'name'       => $request->name,
            'phone'      => $request->phone,
            'token'      => $result['token'],
            'is_active'  => 0
        ]);

        Alert::success('Create Successfully!', 'Device ' . $request->name . ' successfully created!');
        return redirect()
            ->route('devices.index');
    }

    public function qr($id, FonnteService $fonnte)
    {
        $device = WhatsappDevice::findOrFail($id);

        $qr = $fonnte->getQr($device);

        if (!$qr['status']) {

            if (($qr['reason'] ?? '') == 'device already connect') {
                $device->update(['is_active' => 1]);

                return response()->json([
                    'connected' => true
                ]);
            }

            return response()->json([
                'error' => $qr['reason']
            ], 422);
        }

        return response()->json([
            'qr' => $qr['url']
        ]);
    }

    public function checkStatus($id, FonnteService $fonnte)
    {
        $device = WhatsappDevice::findOrFail($id);

        $status = $fonnte->deviceStatus($device->token);

        if ($status['device'] == 'connected') {
            $device->update(['is_active' => 1]);
        }

        return back()->with('success', 'Status diperbarui');
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT FORM
    |--------------------------------------------------------------------------
    */
    public function edit($id)
    {
        $device = WhatsappDevice::findOrFail($id);

        return view('whatsapp.devices.edit', compact('device'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        $device = WhatsappDevice::findOrFail($id);

        $request->validate([
            'name' => 'required',
        ]);

        $device->update([
            'name' => $request->name,
            'token' => $request->token,
            'phone' => $request->phone,
            'is_active' => $request->is_active ?? 1
        ]);

        return redirect()
            ->route('devices.index')
            ->with('success', 'Device berhasil diupdate');
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
    public function destroy($id, FonnteService $fonnte)
    {
        $device = WhatsappDevice::findOrFail($id);

        /*
    |--------------------------------------------------------------------------
    | DELETE DEVICE DI FONNTE
    |--------------------------------------------------------------------------
    */

        $result = $fonnte->deleteDevice($device->token);

        if (!$result['status']) {
            return back()->with(
                'error',
                $result['reason'] ?? 'Gagal hapus device di Fonnte'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | DELETE LOCAL DATABASE
        |--------------------------------------------------------------------------
        */

        $device->logs()->delete(); // optional jika ada relasi
        $device->delete();

        Alert::success(
            'Deleted!',
            'Device successfully deleted.'
        );

        return redirect()
            ->route('devices.index')
            ->with('success', 'Device berhasil dihapus');
    }

    public function disconnect(WhatsappDevice $device)
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | CALL FONNTE API
            |--------------------------------------------------------------------------
            */

            $response = Http::withHeaders([
                'Authorization' => $device->token // DEVICE TOKEN
            ])->post('https://api.fonnte.com/disconnect');

            $result = $response->json();

            /*
            |--------------------------------------------------------------------------
            | SUCCESS / ALREADY DISCONNECTED
            |--------------------------------------------------------------------------
            */

            if (($result['status'] ?? false) === true ||
                ($result['detail'] ?? '') === 'device already disconnected'
            ) {

                $device->update([
                    'is_active' => 0
                ]);

                Alert::success(
                    'Disconnected!',
                    'Device successfully disconnected.'
                );
            } else {

                Alert::error(
                    'Failed',
                    $result['detail'] ?? 'Disconnect failed'
                );
            }
        } catch (\Exception $e) {

            Alert::error(
                'Error',
                'Cannot connect to Fonnte API'
            );
        }

        return redirect()->route('devices.index');
    }
}
