<?php

namespace App\Http\Controllers;

use App\Models\Canteen;
use Illuminate\Http\Request;

class CanteenController extends Controller
{
    public function index()
    {
        $data = Canteen::orderBy('name')->get();
        return view('canteen.index', compact('data'));
    }

    public function create()
    {
        return view('canteen.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:150',
            'location'  => 'nullable|string|max:150',
            'pic_name'  => 'nullable|string|max:100',
            'pic_phone' => 'nullable|string|max:30',
        ]);

        Canteen::create($request->only('name', 'location', 'pic_name', 'pic_phone') + [
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('canteens.index')->with('success', 'Kantin berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $canteen = Canteen::findOrFail($id);
        return view('canteen.edit', compact('canteen'));
    }

    public function update(Request $request, $id)
    {
        $canteen = Canteen::findOrFail($id);

        $request->validate([
            'name'      => 'required|string|max:150',
            'location'  => 'nullable|string|max:150',
            'pic_name'  => 'nullable|string|max:100',
            'pic_phone' => 'nullable|string|max:30',
        ]);

        $canteen->update($request->only('name', 'location', 'pic_name', 'pic_phone') + [
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
