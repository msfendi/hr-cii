<?php

namespace App\Http\Controllers;

use App\Exports\GuestMasterExport;
use App\Models\GuestMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use RealRashid\SweetAlert\Facades\Alert;

class GuestMasterController extends Controller
{
    public function index()
    {
        $data = GuestMaster::latest()->get();

        return view('guest_master.index', compact('data'));
    }

    public function create()
    {
        return view('guest_master.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'gender' => 'nullable|string|max:20',
            'place' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'nationality' => 'nullable|string|max:100',
            'passport_no' => 'nullable|string|max:100',
            'remark' => 'nullable|string',
            'issue_date' => 'nullable|date',
            'must_used_date' => 'nullable|date',
            'arrival_date' => 'nullable|date',
            'visa_expiry' => 'nullable|date',
            'status' => 'nullable|string',
        ]);

        GuestMaster::create([
            'name' => $request->name,
            'gender' => $request->gender,
            'place' => $request->place,
            'date_of_birth' => $request->date_of_birth,
            'nationality' => $request->nationality,
            'passport_no' => $request->passport_no,
            'remark' => $request->remark,
            'issue_date' => $request->issue_date,
            'must_used_date' => $request->must_used_date,
            'arrival_date' => $request->arrival_date,
            'visa_expiry' => $request->visa_expiry,
            'status' => $request->status,
        ]);

        Alert::success('Success', 'Guest Master Has been Created!');
        return redirect()
            ->route('guest-master.index')
            ->with('success', 'Guest Master created successfully.');
    }

    public function update(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'name' => 'required|string|max:255',
        ]);

        DB::table('guest_masters')
            ->where('id', $request->id)
            ->update([
                'name'           => $request->name,
                'gender'         => $request->gender,
                'place'          => $request->place,
                'date_of_birth'  => $request->date_of_birth,
                'nationality'    => $request->nationality,
                'passport_no'    => $request->passport_no,
                'remark'         => $request->remark,
                'issue_date'     => $request->issue_date,
                'must_used_date' => $request->must_used_date,
                'arrival_date'   => $request->arrival_date,
                'visa_expiry'    => $request->visa_expiry,
                'updated_at'     => now(),
            ]);
        Alert::success('Success', 'Guest Master Has been Updated!');

        return redirect()
            ->route('guest-master.index')
            ->with('success', 'Guest Master updated successfully.');
    }

    public function edit($id)
    {
        $data = GuestMaster::findOrFail($id);

        return view('guest_master.edit', compact('data'));
    }

    public function destroy($id)
    {
        $guest = GuestMaster::findOrFail($id);
        $guest->delete();

        return redirect()
            ->route('guest-master.index')
            ->with('success', 'Guest Master deleted successfully');
    }

    public function export()
    {
        return Excel::download(
            new GuestMasterExport(),
            'guest_master_' . date('Ymd_His') . '.xlsx'
        );
    }
}