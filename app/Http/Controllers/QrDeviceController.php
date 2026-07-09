<?php

namespace App\Http\Controllers;

use App\Models\QrAuthorizedDevice;
use App\Models\QrScanLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;

class QrDeviceController extends Controller
{
    public function index()
    {
        $devices = QrAuthorizedDevice::with(['user', 'assignedBy'])->latest()->get();

        // ambil semua uuid yang SUDAH diassign, supaya tidak muncul lagi di pending
        $assignedUuids = QrAuthorizedDevice::pluck('device_uuid');

        $pendingAttempts = QrScanLog::where('status', 'failed_device_not_registered')
            ->whereNotNull('user_id')
            ->whereNotIn('device_uuid', $assignedUuids)
            ->with('user')
            ->latest()
            ->limit(100)
            ->get()
            ->unique('device_uuid')
            ->values();

        $users = User::orderBy('name')->get();

        return view('qr_devices.index', compact('devices', 'pendingAttempts', 'users'));
    }

    public function rename(Request $request, QrAuthorizedDevice $qrDevice)
    {
        $validated = $request->validate([
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $qrDevice->update($validated);

        Alert::success('Berhasil', 'Nama device diperbarui');
        return back();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id'     => ['required', 'exists:users,id'],
            'device_uuid' => ['required', 'string', 'max:64', 'unique:qr_authorized_devices,device_uuid'],
            'device_name' => ['required', 'string', 'max:100'],
            'device_type' => ['nullable', 'string', 'max:20'],
            'platform'    => ['nullable', 'string', 'max:100'],
            'browser'     => ['nullable', 'string', 'max:100'],
        ]);

        $validated['assigned_by'] = Auth::id();
        $validated['is_active']   = true;

        QrAuthorizedDevice::create($validated);

        Alert::success('Berhasil', 'Device berhasil didaftarkan untuk user ini');
        return back();
    }

    public function update(Request $request, QrAuthorizedDevice $qrDevice)
    {
        $validated = $request->validate([
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $qrDevice->update($validated);

        Alert::success('Berhasil', 'Nama device berhasil diperbarui');
        return back();
    }

    public function destroyPendingAttempt($uuid)
    {
        QrScanLog::where('device_uuid', $uuid)
            ->where('status', 'failed_device_not_registered')
            ->delete();

        Alert::success('Berhasil', 'Percobaan device dihapus dari daftar menunggu persetujuan');
        return back();
    }

    public function toggle(QrAuthorizedDevice $qrDevice)
    {
        $qrDevice->update(['is_active' => !$qrDevice->is_active]);

        Alert::success('Berhasil', 'Status device diperbarui');
        return back();
    }

    public function destroy(QrAuthorizedDevice $qrDevice)
    {
        $qrDevice->delete();

        Alert::success('Berhasil', 'Device berhasil dihapus, user tidak bisa lagi login QR dari device ini');
        return back();
    }
}
