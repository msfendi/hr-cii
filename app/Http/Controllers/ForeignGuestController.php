<?php

namespace App\Http\Controllers;

use App\Models\ForeignGuest;
use App\Models\GuestMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ForeignGuestController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        $data = ForeignGuest::latest()->get();

        return view('foreign_guest.index', compact('data'));
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */
    public function create()
    {
        return view('foreign_guest.create');
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $data = $request->all();

        foreach (
            [
                'photo',
                'passport',
                'visa_application',
                'hotel_file'
            ] as $file
        ) {

            if ($request->hasFile($file)) {
                $data[$file] = $request->file($file)
                    ->store('foreign_guest', 'public');
            }
        }

        ForeignGuest::create($data);

        return redirect()
            ->route('foreign-guest.index')
            ->with('success', 'Foreign Guest created successfully');
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */
    public function edit($id)
    {
        $data = ForeignGuest::findOrFail($id);

        return view('foreign_guest.edit', compact('data'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
    {
        $guest = ForeignGuest::findOrFail($id);

        $data = $request->all();

        foreach (
            [
                'photo',
                'passport',
                'visa_application',
                'hotel_file'
            ] as $file
        ) {

            if ($request->hasFile($file)) {

                if ($guest->$file) {
                    Storage::disk('public')->delete($guest->$file);
                }

                $data[$file] = $request->file($file)
                    ->store('foreign_guest', 'public');
            }
        }

        $guest->update($data);

        return redirect()
            ->route('foreign-guest.index')
            ->with('success', 'Foreign Guest updated successfully');
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
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
            ->route('foreign-guest.index')
            ->with('success', 'Foreign Guest deleted successfully');
    }

    /*
|--------------------------------------------------------------------------
| DASHBOARD (Foreign Guest)
|--------------------------------------------------------------------------
*/

    public function dashboardData(Request $request)
    {
        $days = (int) ($request->days ?? 30);
        $nationality = $request->nationality;

        $query = ForeignGuest::query()
            ->leftJoin('guest_masters', 'foreign_guests.id', '=', 'guest_masters.foreign_guest_id')
            ->select(
                'foreign_guests.id',
                'foreign_guests.guest_name',
                'foreign_guests.visa_type',
                'foreign_guests.visa_status',
                'foreign_guests.status as guest_status',
                'foreign_guests.return',
                'guest_masters.id as guest_master_id',
                'guest_masters.nationality',
                'guest_masters.passport_no',
                'guest_masters.issue_date',
                'guest_masters.must_used_date',
                'guest_masters.arrival_date',
                'guest_masters.visa_expiry',
                'guest_masters.status as master_status'
            );

        if ($nationality) {
            $query->where('guest_masters.nationality', $nationality);
        }

        $guests = $query->orderBy('foreign_guests.guest_name')->get();

        $docTypes = [
            'must_used_date' => 'Must Used Date (Visa)',
            'visa_expiry'    => 'Visa Expiry',
        ];

        $expiring = collect();
        $countByType = array_fill_keys($docTypes, 0);

        foreach ($guests as $g) {
            foreach ($docTypes as $field => $label) {
                if (!$g->$field) continue;

                $expiry = \Carbon\Carbon::parse($g->$field);
                $daysLeft = now()->diffInDays($expiry, false);

                if ($daysLeft >= 0 && $daysLeft <= $days) {
                    $expiring->push([
                        'id'          => $g->id,
                        'guest_name'  => $g->guest_name,
                        'doc_type'    => $label,
                        'expiry_date' => $expiry->format('Y-m-d'),
                        'days_left'   => (int) $daysLeft,
                    ]);
                    $countByType[$label] = ($countByType[$label] ?? 0) + 1;
                }
            }
        }
        $expiring = $expiring->sortBy('days_left')->values();

        return response()->json([
            'total_guest'    => $guests->count(),
            'expiring_count' => $expiring->count(),
            'expiring'       => $expiring,
            'count_by_type'  => collect($docTypes)->mapWithKeys(fn($label) => [$label => $countByType[$label] ?? 0]),
            'guests'         => $guests->map(fn($g) => [
                'id'             => $g->id,
                'guest_name'     => $g->guest_name,
                'nationality'    => $g->nationality,
                'visa_type'      => $g->visa_type,
                'visa_status'    => $g->visa_status,
                'passport_no'    => $g->passport_no,
                'must_used_date' => $g->must_used_date,
                'visa_expiry'    => $g->visa_expiry,
                'status'         => $g->guest_status,
            ])->values(),
        ]);
    }

    public function dashboardDetail($id)
    {
        $guest = ForeignGuest::findOrFail($id);
        $master = GuestMaster::where('foreign_guest_id', $id)->first();

        return response()->json([
            'guest'  => $guest,
            'master' => $master,
        ]);
    }
}
