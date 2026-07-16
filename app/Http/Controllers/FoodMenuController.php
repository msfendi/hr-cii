<?php

namespace App\Http\Controllers;

use App\Models\Canteen;
use App\Models\FoodMenu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FoodMenuController extends Controller
{
    public function index()
    {
        $data = FoodMenu::with('canteen')->orderByDesc('id')->get();
        return view('food_menu.index', compact('data'));
    }

    public function create()
    {
        $canteens = Canteen::where('is_active', true)->orderBy('name')->get();
        return view('food_menu.create', compact('canteens'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateMenu($request);

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('food-menus', 'public');
        }

        $validated['price']            = $validated['price'] ?? 0;
        $validated['available_dates']  = $this->normalizeDates($request->input('available_dates', []));
        $validated['is_active']        = $request->boolean('is_active', true);

        FoodMenu::create($validated);

        return redirect()->route('food-menus.index')->with('success', 'Menu berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $menu = FoodMenu::findOrFail($id);
        $canteens = Canteen::where('is_active', true)->orderBy('name')->get();
        return view('food_menu.edit', compact('menu', 'canteens'));
    }

    public function update(Request $request, $id)
    {
        $menu = FoodMenu::findOrFail($id);
        $validated = $this->validateMenu($request);

        if ($request->hasFile('photo')) {
            if ($menu->photo) {
                Storage::disk('public')->delete($menu->photo);
            }
            $validated['photo'] = $request->file('photo')->store('food-menus', 'public');
        }

        $validated['price']           = $validated['price'] ?? 0;
        $validated['available_dates'] = $this->normalizeDates($request->input('available_dates', []));
        $validated['is_active']       = $request->boolean('is_active', true);

        $menu->update($validated);

        return redirect()->route('food-menus.index')->with('success', 'Menu berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $menu = FoodMenu::findOrFail($id);

        if ($menu->photo) {
            Storage::disk('public')->delete($menu->photo);
        }

        $menu->delete();

        return redirect()->route('food-menus.index')->with('success', 'Menu berhasil dihapus.');
    }

    private function validateMenu(Request $request): array
    {
        return $request->validate([
            'canteen_id'         => 'required|exists:canteens,id',
            'name'               => 'required|string|max:150',
            'description'        => 'nullable|string|max:500',
            'price'              => 'nullable|numeric|min:0',
            'photo'              => 'nullable|image|max:2048',
            'available_start'    => 'nullable|date',
            'available_end'      => 'nullable|date|after_or_equal:available_start',
            'available_dates'    => 'nullable|array',
            'available_dates.*'  => 'date',
            'quota'              => 'nullable|integer|min:1',
        ]);
    }

    /**
     * Normalisasi & urutkan daftar tanggal khusus ke format Y-m-d, buang duplikat.
     */
    private function normalizeDates(array $dates): array
    {
        $normalized = collect($dates)
            ->filter()
            ->map(fn($d) => \Carbon\Carbon::parse($d)->toDateString())
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $normalized;
    }
}
