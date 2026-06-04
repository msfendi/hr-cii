<?php

namespace App\Http\Controllers;

use App\Models\ForeignGuest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RealRashid\SweetAlert\Facades\Alert;

class GuestMasterController extends Controller
{
    public function index()
    {
        $data = ForeignGuest::latest()->get();

        return view('guest_master.index', compact('data'));
    }

    public function create()
    {
        $guests = DB::table('foreign_guests')
            ->select('id', 'guest_name')
            ->where('is_created', 0)
            ->orderBy('guest_name')
            ->get();

        return view('guest_master.create', compact('guests'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'id' => 'required'
        ]);

        DB::table('foreign_guests')
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
                'is_created'     => 1,
            ]);
        Alert::success('Success', 'Guest Master Has been Updated!');

        return redirect()
            ->route('guest-master.index')
            ->with('success', 'Guest Master updated successfully.');
    }

    public function edit($id)
    {
        $data = ForeignGuest::findOrFail($id);

        return view('guest_master.edit', compact('data'));
    }

    public function destroy($id)
    {
        $guest = ForeignGuest::findOrFail($id);

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
}
