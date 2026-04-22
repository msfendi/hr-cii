<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\HolidayImport;
use App\Exports\HolidayExport;
use Illuminate\Support\Facades\Http;
use RealRashid\SweetAlert\Facades\Alert;

class HolidayController extends Controller
{

    public function index()
    {
        $data = Holiday::orderBy('holiday_date')->get();

        return view('holidays.index', compact('data'));
    }

    public function create()
    {
        return view('holidays.create');
    }

    public function store(Request $request)
    {

        $request->validate([
            'holiday_date' => 'required|date|unique:holidays',
            'name' => 'required'
        ]);

        Holiday::create($request->all());

        Alert::success('Holiday created successfully!');
        return redirect()->route('holidays.index');
    }

    public function destroy($id)
    {
        Holiday::findOrFail($id)->delete();

        Alert::success('Holiday deleted successfully!');
        return back();
    }

    public function import(Request $request)
    {
        Excel::import(new HolidayImport, $request->file('file'));

        Alert::success('Import berhasil');
        return back();
    }


    public function sync()
    {

        $response = Http::get(
            'https://raw.githubusercontent.com/guangrei/APIHariLibur_V2/main/calendar.json'
        );

        if (!$response->successful()) {
            return back()->with('error', 'Gagal mengambil data holiday');
        }

        $data = $response->json();

        $count = 0;

        foreach ($data as $date => $info) {

            if (isset($info['holiday']) && $info['holiday'] == true) {

                $summary = $info['summary'] ?? 'Hari Libur';

                // jika summary berupa array
                if (is_array($summary)) {
                    $summary = implode(', ', $summary);
                }

                Holiday::updateOrCreate(
                    ['holiday_date' => $date],
                    [
                        'name' => $summary,
                        'is_national' => 1
                    ]
                );

                $count++;
            }
        }

        return back()->with('success', "Sync berhasil: $count hari libur diperbarui");
    }
}
