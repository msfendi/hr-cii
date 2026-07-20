<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChuFamily;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ChuFamilyImport;
use App\Exports\ChuFamilyExport;
use App\Exports\ChuFamilyTemplateExport;

class ChuFamilyController extends Controller
{

    public function index()
    {
        $data = ChuFamily::latest()->get();
        return view('chu_family.index', compact('data'));
    }

    public function create()
    {
        return view('chu_family.create');
    }

    public function store(Request $request)
    {
        ChuFamily::create($request->all());

        return redirect()
            ->route('chu-family.index')
            ->with('success', 'Data created');
    }

    public function edit($id)
    {
        $data = ChuFamily::findOrFail($id);
        return view('chu_family.edit', compact('data'));
    }

    public function update(Request $request, $id)
    {
        $data = ChuFamily::findOrFail($id);
        $data->update($request->all());

        return redirect()
            ->route('chu-family.index')
            ->with('success', 'Data updated');
    }

    public function delete($id)
    {
        ChuFamily::findOrFail($id)->delete();

        return redirect()->back()
            ->with('success', 'Data deleted');
    }

    /*
    | IMPORT
    */
    public function import(Request $request)
    {
        Excel::import(new ChuFamilyImport, $request->file('file'));

        return response()->json(['success' => true]);
    }

    /*
    | TEMPLATE
    */
    public function template()
    {
        return Excel::download(
            new ChuFamilyTemplateExport,
            'template-chu-family.xlsx'
        );
    }

    /*
    | EXPORT REKAP
    */
    public function export()
    {
        return Excel::download(
            new ChuFamilyExport,
            'chu_family.xlsx'
        );
    }

    /*
|--------------------------------------------------------------------------
| DASHBOARD (Chu Family)
|--------------------------------------------------------------------------
*/

    public function dashboardData(Request $request)
    {
        $days = (int) ($request->days ?? 30);
        $nationality = $request->nationality;

        $query = ChuFamily::query();
        if ($nationality) {
            $query->where('nationality', $nationality);
        }
        $families = $query->orderBy('name')->get();

        $docTypes = [
            'passport_expiry' => 'Passport',
            'visa_expiry'     => 'Visa',
            'kitas_expiry'    => 'KITAS',
            'rptka_expiry'    => 'RPTKA',
        ];

        $expiring = collect();
        $countByType = array_fill_keys($docTypes, 0);

        foreach ($families as $f) {
            foreach ($docTypes as $field => $label) {
                if (!$f->$field) continue;

                $expiry = \Carbon\Carbon::parse($f->$field);
                $daysLeft = now()->diffInDays($expiry, false);

                if ($daysLeft >= 0 && $daysLeft <= $days) {
                    $expiring->push([
                        'id'          => $f->id,
                        'name'        => $f->name,
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
            'total_family'   => $families->count(),
            'expiring_count' => $expiring->count(),
            'expiring'       => $expiring,
            'count_by_type'  => collect($docTypes)->mapWithKeys(fn($label) => [$label => $countByType[$label] ?? 0]),
            'families'       => $families->map(fn($f) => [
                'id'               => $f->id,
                'name'             => $f->name,
                'gender'           => $f->gender,
                'nationality'      => $f->nationality,
                'passport_number'  => $f->passport_number,
                'passport_expiry'  => $f->passport_expiry,
                'visa_expiry'      => $f->visa_expiry,
                'kitas_expiry'     => $f->kitas_expiry,
                'rptka_expiry'     => $f->rptka_expiry,
            ])->values(),
        ]);
    }

    public function dashboardDetail($id)
    {
        $family = ChuFamily::findOrFail($id);

        return response()->json(['family' => $family]);
    }
}
