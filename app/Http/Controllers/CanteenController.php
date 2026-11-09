<?php

namespace App\Http\Controllers;

use App\Models\Canteen;
use Illuminate\Http\Request;

class CanteenController extends Controller
{
    public function index()
    {
        $data = Canteen::orderBy('canteen_id')->orderBy('name')->get();
        return view('canteen.index', compact('data'));
    }

    public function create()
    {
        $usedIds = Canteen::pluck('canteen_id')->all();
        return view('canteen.create', compact('usedIds'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'canteen_id' => 'required|in:1,2',
            'name'       => 'required|string|max:150',
            'location'   => 'nullable|string|max:150',
            'pic_name'   => 'nullable|string|max:100',
            'pic_phone'  => 'nullable|string|max:30',
            'contact_person' => 'nullable|string|max:100',
            'contact_phone'  => 'nullable|string|max:30',
        ]);

        $canteenId = (int) $request->input('canteen_id');
        if (Canteen::where('canteen_id', $canteenId)->exists()) {
            return back()->withInput()->withErrors([
                'canteen_id' => "ID {$canteenId} sudah terpakai. Kantin 1 dan Kantin 2 wajib unik (canteen_id hanya boleh 1 atau 2, satu per baris).",
            ]);
        }

        Canteen::create([
            'canteen_id' => $canteenId,
            'name' => $request->input('name'),
            'location' => $request->input('location'),
            'pic_name' => $request->input('pic_name'),
            'pic_phone' => $request->input('pic_phone'),
            'contact_person' => $request->input('contact_person') ?: $request->input('pic_name'),
            'contact_phone' => $request->input('contact_phone') ?: $request->input('pic_phone'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('canteens.index')->with('success', 'Kantin berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $canteen = Canteen::findOrFail($id);
        $usedIds = Canteen::where('id', '!=', $id)->pluck('canteen_id')->all();
        return view('canteen.edit', compact('canteen', 'usedIds'));
    }

    public function update(Request $request, $id)
    {
        $canteen = Canteen::findOrFail($id);

        $request->validate([
            'canteen_id' => 'required|in:1,2',
            'name'       => 'required|string|max:150',
            'location'   => 'nullable|string|max:150',
            'pic_name'   => 'nullable|string|max:100',
            'pic_phone'  => 'nullable|string|max:30',
            'contact_person' => 'nullable|string|max:100',
            'contact_phone'  => 'nullable|string|max:30',
        ]);

        $canteenId = (int) $request->input('canteen_id');
        $conflict = Canteen::where('canteen_id', $canteenId)->where('id', '!=', $id)->exists();
        if ($conflict) {
            return back()->withInput()->withErrors([
                'canteen_id' => "ID {$canteenId} sudah dipakai kantin lain.",
            ]);
        }

        $canteen->update([
            'canteen_id' => $canteenId,
            'name' => $request->input('name'),
            'location' => $request->input('location'),
            'pic_name' => $request->input('pic_name'),
            'pic_phone' => $request->input('pic_phone'),
            'contact_person' => $request->input('contact_person') ?: $request->input('pic_name'),
            'contact_phone' => $request->input('contact_phone') ?: $request->input('pic_phone'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('canteens.index')->with('success', 'Kantin berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $canteen = Canteen::findOrFail($id);
        $canteen->delete();

        return redirect()->route('canteens.index')->with('success', 'Kantin berhasil dihapus.');
    }
}
