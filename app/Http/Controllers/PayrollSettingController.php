<?php

namespace App\Http\Controllers;

use App\Models\PayrollSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;

class PayrollSettingController extends Controller
{
    public function index()
    {
        /*
    =====================================
    GET PAYROLL SETTINGS
    =====================================
    */
        $data = PayrollSetting::latest()->get();

        /*
    =====================================
    UNION BIODATA + BIODATA_KELUAR
    =====================================
    */
        $employees = collect(DB::select("
        SELECT NPK, NAMA_KARYAWAN FROM BIODATA
        UNION
        SELECT NPK, NAMA_KARYAWAN FROM BIODATA_KELUAR
    "))->keyBy('NPK');


        /*
    =====================================
    MAP APPROVAL USERS
    =====================================
    */
        $data->transform(function ($row) use ($employees) {

            $npkList = is_array($row->approval)
                ? $row->approval
                : json_decode($row->approval, true);

            if (!is_array($npkList)) {
                $npkList = [];
            }

            $approvalUsers = collect($npkList)->map(function ($npk) use ($employees) {

                return [
                    'npk'   => $npk,
                    'name'  => $employees[$npk]->NAMA_KARYAWAN ?? '-',
                    'status' => 'approve'
                ];
            })->values();

            $row->approval_users = $approvalUsers;

            return $row;
        });

        return view('payroll_settings.index', compact('data'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'component' => 'required',
            'approval' => 'required',
            'level' => 'required|integer'
        ]);

        return PayrollSetting::create($request->all());
    }

    // Tampilkan form edit
    public function edit($id)
    {
        $setting = PayrollSetting::findOrFail($id);

        // Ambil semua karyawan dari BIODATA
        $employees = collect(DB::select("
        SELECT NPK, NAMA_KARYAWAN FROM BIODATA
    "))->keyBy('NPK'); // keyBy agar bisa akses value berdasarkan NPK

        return view('payroll_settings.edit', compact('setting', 'employees'));
    }

    // Update data
    public function update(Request $request, $id)
    {
        $request->validate([
            'approval' => 'required|array',
        ]);

        try {
            $setting = PayrollSetting::findOrFail($id);
            $setting->approval = json_encode($request->approval); // simpan sebagai JSON array
            $setting->save();

            Alert::success('Update Successfully!', 'Payroll Setting ' . $request->component . ' successfully updated!');
            return redirect()->route('payroll-setting.index');
        } catch (\Exception $e) {
            Alert::error('Update Failed!', 'Failed to update Payroll Setting.');
            return redirect()->back();
        }
    }

    // Hapus data
    public function delete($id)
    {
        try {
            $setting = PayrollSetting::findOrFail($id);
            $setting->delete();

            Alert::success('Delete Successfully!', 'Payroll Setting ' . $setting->component . ' successfully deleted!');
            return redirect()->route('payroll-setting.index');
        } catch (\Exception $e) {
            Alert::error('Delete Failed!', 'Failed to delete Payroll Setting.');
            return redirect()->back();
        }
    }
}
