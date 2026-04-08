<?php

namespace App\Http\Controllers;

use App\Models\WhatsappDevice;
use App\Services\FonnteService;
use Illuminate\Http\Request;

class WhatsappDeviceController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $devices = WhatsappDevice::latest()->get();

        return view('whatsapp.devices.index', compact('devices'));
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

        if (!$result['status']) {
            return back()->with('error', $result['reason']);
        }


        WhatsappDevice::create([
            'name'       => $request->name,
            'phone'      => $request->phone,
            'token'      => $result['token'],
            'is_active'  => 0
        ]);

        return redirect()
            ->route('devices.index')
            ->with('success', 'Device berhasil dibuat, scan QR sekarang');
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

        return redirect()
            ->route('devices.index')
            ->with('success', 'Device berhasil dihapus');
    }
}
