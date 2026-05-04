<?php

namespace App\Http\Controllers;

use App\Models\ForeignGuest;
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
}
