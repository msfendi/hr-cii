<?php

namespace App\Http\Controllers;

use App\Exports\GuestMasterExport;
use App\Models\ForeignGuest;
use App\Models\GuestMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use RealRashid\SweetAlert\Facades\Alert;

class GuestMasterController extends Controller
{
    public function index()
    {
        $data = GuestMaster::select('guest_masters.*', 'foreign_guests.guest_name', 'foreign_guests.return', 'foreign_guests.visa_type', 'foreign_guests.visa_status')->leftJoin('foreign_guests', 'guest_masters.foreign_guest_id', '=', 'foreign_guests.id')->get();

        return view('guest_master.index', compact('data'));
    }

    public function create()
    {
        $guests = DB::table('foreign_guests')
            ->select('id', 'guest_name')
            ->orderBy('guest_name')
            ->get();

        return view('guest_master.create', compact('guests'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'foreign_guest_id' => 'integer',
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
            'foreign_guest_id' => $request->id,
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
            'id' => 'required'
        ]);

        DB::table('guest_masters')
            ->where('id', $request->id)
            ->update([
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
        $data = GuestMaster::query()
            ->leftJoin('foreign_guests', 'guest_masters.foreign_guest_id', '=', 'foreign_guests.id')
            ->where('guest_masters.id', $id)
            ->select(
                'guest_masters.*',
                'foreign_guests.guest_name',
                'foreign_guests.id as foreign_guest_id_master'
            )
            ->firstOrFail();
        // dd($data);

        return view('guest_master.edit', compact('data'));
    }

    public function destroy($id)
    {
        $guest = GuestMaster::findOrFail($id);

        foreach (
            [
                'photo',
                'passport',
                'visa_application',
                'hotel_file'
            ] as $file
        ) {

            if ($guest->$file) {
                Storage::disk('public')->delete($guest->$file);
            }
        }

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
