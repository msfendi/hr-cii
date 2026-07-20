<?php

namespace App\Http\Controllers;

use App\Models\ExpatMaster;
use App\Models\ExpatOnleave;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ExpatMasterImport;
use App\Imports\ExpatOnleaveImport;
use App\Exports\ExpatMasterTemplateExport;
use App\Exports\ExpatOnleaveTemplateExport;
use App\Exports\ExpatRekapExport;
use App\Models\ExpatCost;
use App\Models\ExpatCostComponent;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;

class ExpatController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | MASTER CRUD
    |--------------------------------------------------------------------------
    */

    public function indexMaster()
    {
        $data = ExpatMaster::select('expat_master.*', 'PKWT.TGLLAHIR')->leftJoin('PKWT', 'expat_master.npk', '=', 'PKWT.NPK')->get();
        return view('expat_master.index', compact('data'));
    }


    public function createMaster()
    {
        $employees = DB::query()
            ->fromSub(
                DB::table('BIODATA')
                    ->select('NPK', 'NAMA_KARYAWAN')
                    ->union(
                        DB::table('BIODATA_KELUAR')
                            ->select('NPK', 'NAMA_KARYAWAN')
                    ),
                'emp'
            )
            ->orderBy('NAMA_KARYAWAN')
            ->get();
        return view('expat_master.create', compact('employees'));
    }

    public function storeMaster(Request $request)
    {
        ExpatMaster::updateOrCreate(
            ['npk' => $request->npk],
            $request->all()
        );

        return redirect()
            ->route('expat.master.index')
            ->with('success', 'Expat master berhasil dibuat');
    }

    public function indexOnleave()
    {
        $data = ExpatOnleave::leftJoin('BIODATA', 'expat_onleave.npk', '=', 'BIODATA.NPK')
            ->select('expat_onleave.*', 'BIODATA.NAMA_KARYAWAN')
            ->latest('expat_onleave.created_at')
            ->get();

        $components = DB::table('expat_cost_components')
            ->pluck('component', 'id');

        foreach ($data as $row) {

            // COMPONENT ARRAY
            $row->component_array = is_array($row->component)
                ? $row->component
                : json_decode($row->component, true) ?? [];

            // AMOUNT ARRAY
            $row->amount_array = is_array($row->amount)
                ? $row->amount
                : json_decode($row->amount, true) ?? [];

            // ✅ TOTAL AMOUNT
            $row->total_amount = collect($row->amount_array)
                ->map(fn($val) => (float) $val)
                ->sum();

            // COMPONENT NAME
            $row->component_name = collect($row->component_array)
                ->map(fn($id) => $components[$id] ?? $id)
                ->values()
                ->toArray();

            // TRANSACTION DATE
            $row->transactions_date = is_array($row->transactions_date)
                ? $row->transactions_date
                : json_decode($row->transactions_date, true) ?? [];
        }

        return view('expat_onleave.index', compact('data'));
    }

    public function createOnLeave()
    {
        $components = ExpatCostComponent::orderBy('component')->get();
        $employees = DB::query()
            ->fromSub(
                DB::table('BIODATA')
                    ->select('NPK', 'NAMA_KARYAWAN')
                    ->union(
                        DB::table('BIODATA_KELUAR')
                            ->select('NPK', 'NAMA_KARYAWAN')
                    ),
                'emp'
            )
            ->orderBy('NAMA_KARYAWAN')
            ->get();

        return view('expat_onleave.create', compact('components', 'employees'));
    }

    public function storeOnleave(Request $request)
    {
        $request->validate([
            'npk' => 'required',
            'component' => 'required|array',
            'amount' => 'required|array',
            'onleave_start' => 'required|date',
            'onleave_end' => 'required|date',
            'leave_type' => 'required',
            'transactions_date' => 'required|array',
        ]);

        ExpatOnleave::create([
            'npk' => $request->npk,
            'onleave_start' => $request->onleave_start,
            'onleave_end' => $request->onleave_end,
            'leave_type' => $request->leave_type,

            // SAVE ARRAY AS JSON
            'component' => $request->component,
            'amount' => $request->amount,

            'transactions_date' => $request->transactions_date,
            'remark' => $request->remark,
        ]);

        return redirect()
            ->route('expat.onleave.index')
            ->with('success', 'Expat On Leave saved successfully');
    }

    public function indexCost()
    {
        $data = ExpatCost::latest()
            ->leftJoin(
                'expat_cost_components',
                'expat_cost.component',
                '=',
                'expat_cost_components.id'
            )
            ->leftJoin(
                'BIODATA',
                'expat_cost.npk',
                '=',
                'BIODATA.NPK'
            )
            ->select(
                'expat_cost.*',
                'expat_cost_components.component as component_name',
                'BIODATA.NAMA_KARYAWAN as NAMA_KARYAWAN'
            )
            ->get();

        // dd($data);

        return view('expat_cost.index', compact('data'));
    }

    public function createCost()
    {
        $employees = DB::query()
            ->fromSub(
                DB::table('BIODATA')
                    ->select('NPK', 'NAMA_KARYAWAN')
                    ->union(
                        DB::table('BIODATA_KELUAR')
                            ->select('NPK', 'NAMA_KARYAWAN')
                    ),
                'emp'
            )
            ->orderBy('NAMA_KARYAWAN')
            ->get();
        $components = ExpatCostComponent::orderBy('component')->get();

        return view('expat_cost.create', compact('components', 'employees'));
    }

    public function storeCost(Request $request)
    {
        $request->validate([
            'npk' => 'required',
            'component' => 'required',
            'amount' => 'required|numeric',
            'transactions_date' => 'required|date',
            'remark' => 'nullable'
        ]);

        ExpatCost::create([
            'npk' => $request->npk,
            'component' => $request->component,
            'amount' => $request->amount,
            'transactions_date' => $request->transactions_date,
            'remark' => $request->remark,
        ]);


        return redirect()
            ->route('expat.cost.index')
            ->with('success', 'Expat Cost saved successfully');
    }

    /*
    |--------------------------------------------------------------------------
    | IMPORT
    |--------------------------------------------------------------------------
    */

    public function importMaster(Request $request)
    {
        Excel::import(new ExpatMasterImport, $request->file('file'));

        return back()->with('success', 'Import success');
    }

    public function importOnleave(Request $request)
    {
        Excel::import(new ExpatOnleaveImport, $request->file('file'));

        return back()->with('success', 'Import success');
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT TEMPLATE
    |--------------------------------------------------------------------------
    */

    public function templateMaster()
    {
        return Excel::download(
            new ExpatMasterTemplateExport,
            'expat_master_template.xlsx'
        );
    }

    public function templateOnleave()
    {
        return Excel::download(
            new ExpatOnleaveTemplateExport,
            'expat_onleave_template.xlsx'
        );
    }

    public function exportRekap(Request $request)
    {
        $start = $request->start_date;
        $end   = $request->end_date;
        $filename = 'Expat_Rekap_' . date('Ymd_His') . '.xlsx';

        return Excel::download(
            new ExpatRekapExport($start, $end),
            $filename
        );
    }

    public function deleteMaster($id)
    {
        ExpatMaster::findOrFail($id)->delete();

        Alert::success('Expat Master deleted successfully!');
        return back();
    }

    public function deleteOnleave($id)
    {
        ExpatOnleave::findOrFail($id)->delete();

        Alert::success('Expat On Leave deleted successfully!');
        return back();
    }

    public function deleteCost($id)
    {
        ExpatCost::findOrFail($id)->delete();

        Alert::success('Expat Cost deleted successfully!');
        return back();
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function editMaster($id)
    {
        $data = ExpatMaster::findOrFail($id);
        $employees = DB::query()
            ->fromSub(
                DB::table('BIODATA')
                    ->select('NPK', 'NAMA_KARYAWAN')
                    ->union(
                        DB::table('BIODATA_KELUAR')
                            ->select('NPK', 'NAMA_KARYAWAN')
                    ),
                'emp'
            )
            ->orderBy('NAMA_KARYAWAN')
            ->get();

        return view('expat_master.edit', compact('data', 'employees'));
    }

    public function editOnLeave($id)
    {
        $data = ExpatOnleave::findOrFail($id);
        $employees = DB::query()
            ->fromSub(
                DB::table('BIODATA')
                    ->select('NPK', 'NAMA_KARYAWAN')
                    ->union(
                        DB::table('BIODATA_KELUAR')
                            ->select('NPK', 'NAMA_KARYAWAN')
                    ),
                'emp'
            )
            ->orderBy('NAMA_KARYAWAN')
            ->get();
        $components = ExpatCostComponent::orderBy('component')->get();

        return view('expat_onleave.edit', compact('data', 'employees', 'components'));
    }


    public function editCost($id)
    {
        $data = ExpatCost::findOrFail($id);
        $employees = DB::query()
            ->fromSub(
                DB::table('BIODATA')
                    ->select('NPK', 'NAMA_KARYAWAN')
                    ->union(
                        DB::table('BIODATA_KELUAR')
                            ->select('NPK', 'NAMA_KARYAWAN')
                    ),
                'emp'
            )
            ->orderBy('NAMA_KARYAWAN')
            ->get();
        $components = ExpatCostComponent::orderBy('component')->get();

        return view('expat_cost.edit', compact('data', 'employees', 'components'));
    }

    public function updateCost(Request $request, $id)
    {
        $request->validate([
            'npk' => 'required',
            'component' => 'required',
            'amount' => 'required|numeric',
            'transactions_date' => 'required|date',
            'remark' => 'nullable'
        ]);

        $data = ExpatCost::findOrFail($id);
        $data->update([
            'npk' => $request->npk,
            'component' => $request->component,
            'amount' => $request->amount,
            'transactions_date' => $request->transactions_date,
            'remark' => $request->remark,
        ]);

        return redirect()
            ->route('expat.cost.index')
            ->with('success', 'Expat Cost updated successfully');
    }

    public function updateOnleave(Request $request, $id)
    {
        $request->validate([
            'npk' => 'required',
            'component' => 'required|array',
            'amount' => 'required|array',
            'onleave_start' => 'required|date',
            'onleave_end' => 'required|date',
            'leave_type' => 'required',
            'transactions_date' => 'required|array',
        ]);

        $data = ExpatOnleave::findOrFail($id);
        $data->update([
            'npk' => $request->npk,
            'onleave_start' => $request->onleave_start,
            'onleave_end' => $request->onleave_end,
            'leave_type' => $request->leave_type,
            'component' => $request->component,
            'amount' => $request->amount,
            'transactions_date' => $request->transactions_date,
            'remark' => $request->remark,
        ]);

        return redirect()
            ->route('expat.onleave.index')
            ->with('success', 'Expat On Leave updated successfully');
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function updateMaster(Request $request, $id)
    {
        DB::table('expat_master')
            ->where('id', $id)
            ->update([
                'npk' => $request->npk,
                'name' => $request->name,
                'position' => $request->position,
                'joining_date' => $request->joining_date,
                'end_date' => $request->end_date,
                'passport_number' => $request->passport_number,
                'passport_expiry' => $request->passport_expiry,
                'kitas_expiry' => $request->kitas_expiry,
                'rptka_expiry' => $request->rptka_expiry,
                'merp_expiry' => $request->merp_expiry,
                'house_address' => $request->house_address,
                'house_startdate' => $request->house_startdate,
                'lease_enddate' => $request->lease_enddate,
                'place' => $request->place,
                'nationality' => $request->nationality,
                'direct_report' => $request->direct_report,
                'npwp' => $request->npwp,
                'updated_at' => now()
            ]);

        return redirect()
            ->route('expat.master.index')
            ->with('success', 'Expat Master Updated');
    }

    /*
|--------------------------------------------------------------------------
| EXPAT DASHBOARD
|--------------------------------------------------------------------------
*/

    public function dashboard()
    {
        $years = ExpatCost::selectRaw('YEAR(transactions_date) as y')
            ->union(ExpatOnleave::selectRaw('YEAR(onleave_start) as y'))
            ->pluck('y')
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();

        if ($years->isEmpty()) {
            $years = collect([date('Y')]);
        }

        $nationalities = ExpatMaster::whereNotNull('nationality')
            ->distinct()
            ->orderBy('nationality')
            ->pluck('nationality');

        $components = ExpatCostComponent::orderBy('component')->get();

        return view('expat_dashboard.index', compact('years', 'nationalities', 'components'));
    }

    public function searchEmployee(Request $request)
    {
        $q = $request->q;

        $employees = ExpatMaster::query()
            ->when($q, fn($query) => $query->where('name', 'like', "%{$q}%")->orWhere('npk', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(20)
            ->get(['npk', 'name']);

        return response()->json([
            'results' => $employees->map(fn($e) => [
                'id'   => $e->npk,
                'text' => "{$e->npk} - {$e->name}",
            ]),
        ]);
    }

    /*
|--------------------------------------------------------------------------
| SECTION 1: Rekap Biaya (chart bulanan)
|--------------------------------------------------------------------------
*/

    public function chartData(Request $request)
    {
        $year = $request->year ?? date('Y');
        $npk = $request->npk;
        $nationality = $request->nationality;
        $costType = $request->cost_type ?? 'all'; // all | direct | onleave

        $npksFilter = null;
        if ($nationality) {
            $npksFilter = ExpatMaster::where('nationality', $nationality)->pluck('npk');
        }

        // ===== DIRECT COST per bulan =====
        $directQuery = ExpatCost::whereYear('transactions_date', $year);
        if ($npk) $directQuery->where('npk', $npk);
        if ($npksFilter) $directQuery->whereIn('npk', $npksFilter);

        $directByMonth = $directQuery->selectRaw('MONTH(transactions_date) as bulan, SUM(amount) as total, COUNT(*) as jml')
            ->groupBy(DB::raw('MONTH(transactions_date)'))
            ->get()
            ->keyBy('bulan');

        // ===== ON LEAVE COST per bulan (data JSON, diproses manual) =====
        $onleaveQuery = ExpatOnleave::whereYear('onleave_start', $year);
        if ($npk) $onleaveQuery->where('npk', $npk);
        if ($npksFilter) $onleaveQuery->whereIn('npk', $npksFilter);

        $onleaveByMonth = array_fill(1, 12, ['total' => 0, 'jml' => 0]);

        foreach ($onleaveQuery->get() as $row) {
            $amounts = is_array($row->amount) ? $row->amount : (json_decode($row->amount, true) ?? []);
            $dates = is_array($row->transactions_date) ? $row->transactions_date : (json_decode($row->transactions_date, true) ?? []);

            foreach ($amounts as $i => $amt) {
                $date = $dates[$i] ?? null;
                if (!$date) continue;
                $carbon = \Carbon\Carbon::parse($date);
                if ($carbon->year != $year) continue;

                $onleaveByMonth[$carbon->month]['total'] += (float) $amt;
                $onleaveByMonth[$carbon->month]['jml']++;
            }
        }

        $labels = [];
        $directValues = [];
        $onleaveValues = [];
        $totalValues = [];
        $directCounts = [];
        $onleaveCounts = [];

        for ($m = 1; $m <= 12; $m++) {
            $labels[] = sprintf('%04d-%02d', $year, $m);

            $d = $costType === 'onleave' ? 0 : (float) ($directByMonth[$m]->total ?? 0);
            $o = $costType === 'direct' ? 0 : (float) $onleaveByMonth[$m]['total'];

            $directValues[] = round($d, 2);
            $onleaveValues[] = round($o, 2);
            $totalValues[] = round($d + $o, 2);
            $directCounts[] = (int) ($directByMonth[$m]->jml ?? 0);
            $onleaveCounts[] = (int) $onleaveByMonth[$m]['jml'];
        }

        $grandTotal = array_sum($totalValues);
        $totalTransaksi = array_sum($directCounts) + array_sum($onleaveCounts);
        $monthsWithData = count(array_filter($totalValues));

        return response()->json([
            'labels' => $labels,
            'direct_values' => $directValues,
            'onleave_values' => $onleaveValues,
            'values' => $totalValues,
            'direct_counts' => $directCounts,
            'onleave_counts' => $onleaveCounts,
            'grand_total' => $grandTotal,
            'avg_per_month' => $monthsWithData ? round($grandTotal / $monthsWithData, 2) : 0,
            'total_transaksi' => $totalTransaksi,
            'range' => ['start' => "{$year}-01", 'end' => "{$year}-12"],
        ]);
    }

    /*
|--------------------------------------------------------------------------
| SECTION 2: Detail / Rekap per Expat
|--------------------------------------------------------------------------
*/

    public function recapData(Request $request)
    {
        $year = $request->year ?? date('Y');
        $npk = $request->npk;
        $nationality = $request->nationality;

        $expatQuery = ExpatMaster::query();
        if ($npk) $expatQuery->where('npk', $npk);
        if ($nationality) $expatQuery->where('nationality', $nationality);
        $expats = $expatQuery->get();

        // Direct cost per npk
        $directPerNpk = ExpatCost::whereYear('transactions_date', $year)
            ->selectRaw('npk, SUM(amount) as total, COUNT(*) as jml')
            ->groupBy('npk')
            ->get()
            ->keyBy('npk');

        // Onleave cost per npk (manual, karena JSON)
        $onleaveRows = ExpatOnleave::whereYear('onleave_start', $year)->get();
        $onleavePerNpk = [];
        $onleaveByType = [];

        foreach ($onleaveRows as $row) {
            $amounts = is_array($row->amount) ? $row->amount : (json_decode($row->amount, true) ?? []);
            $sum = collect($amounts)->map(fn($v) => (float) $v)->sum();

            $onleavePerNpk[$row->npk] = ($onleavePerNpk[$row->npk] ?? 0) + $sum;
            $onleaveByType[$row->leave_type] = ($onleaveByType[$row->leave_type] ?? 0) + 1;
        }

        // Biaya per komponen (direct cost)
        $costByComponent = ExpatCost::join('expat_cost_components', 'expat_cost.component', '=', 'expat_cost_components.id')
            ->whereYear('expat_cost.transactions_date', $year)
            ->selectRaw('expat_cost_components.component as name, SUM(expat_cost.amount) as total')
            ->groupBy('expat_cost_components.component')
            ->orderByDesc('total')
            ->get();

        $recap = $expats->map(function ($e) use ($directPerNpk, $onleavePerNpk) {
            $direct = (float) ($directPerNpk[$e->npk]->total ?? 0);
            $onleave = (float) ($onleavePerNpk[$e->npk] ?? 0);
            $isActive = !$e->end_date || $e->end_date >= now();

            return [
                'npk' => $e->npk,
                'name' => $e->name,
                'position' => $e->position,
                'nationality' => $e->nationality,
                'status' => $isActive ? 'aktif' : 'nonaktif',
                'direct_cost' => $direct,
                'onleave_cost' => $onleave,
                'total_cost' => $direct + $onleave,
            ];
        })->sortByDesc('total_cost')->values();

        return response()->json([
            'recap' => $recap,
            'cost_by_component' => $costByComponent,
            'onleave_by_type' => collect($onleaveByType)->map(fn($total, $type) => [
                'leave_type' => $type,
                'total' => $total,
            ])->values(),
            'grand_direct' => $recap->sum('direct_cost'),
            'grand_onleave' => $recap->sum('onleave_cost'),
        ]);
    }

    public function transactionDetail(Request $request)
    {
        $npk = $request->npk;
        $year = $request->year ?? date('Y');

        $costs = ExpatCost::join('expat_cost_components', 'expat_cost.component', '=', 'expat_cost_components.id')
            ->where('expat_cost.npk', $npk)
            ->whereYear('expat_cost.transactions_date', $year)
            ->selectRaw('expat_cost.transactions_date as tanggal, expat_cost_components.component as komponen, expat_cost.amount as amount, expat_cost.remark as remark')
            ->orderByDesc('expat_cost.transactions_date')
            ->get();

        $onleaves = ExpatOnleave::where('npk', $npk)->whereYear('onleave_start', $year)->get();
        $components = DB::table('expat_cost_components')->pluck('component', 'id');

        $onleaveDetail = collect();
        foreach ($onleaves as $row) {
            $comps = is_array($row->component) ? $row->component : (json_decode($row->component, true) ?? []);
            $amounts = is_array($row->amount) ? $row->amount : (json_decode($row->amount, true) ?? []);
            $dates = is_array($row->transactions_date) ? $row->transactions_date : (json_decode($row->transactions_date, true) ?? []);

            foreach ($amounts as $i => $amt) {
                $onleaveDetail->push([
                    'tanggal' => $dates[$i] ?? null,
                    'komponen' => $components[$comps[$i] ?? null] ?? '-',
                    'amount' => (float) $amt,
                    'remark' => $row->remark,
                    'leave_type' => $row->leave_type,
                ]);
            }
        }

        return response()->json([
            'direct' => $costs,
            'onleave' => $onleaveDetail->sortByDesc('tanggal')->values(),
        ]);
    }

    /*
|--------------------------------------------------------------------------
| SECTION 3: Dokumen & Kepatuhan
|--------------------------------------------------------------------------
*/

    public function documentData(Request $request)
    {
        $days = (int) ($request->days ?? 30);
        $nationality = $request->nationality;

        $query = ExpatMaster::query();
        if ($nationality) $query->where('nationality', $nationality);
        $expats = $query->get();

        $totalExpat = $expats->count();
        $totalActive = $expats->filter(fn($e) => !$e->end_date || $e->end_date >= now())->count();

        $docTypes = [
            'passport_expiry' => 'Passport',
            'kitas_expiry' => 'KITAS',
            'rptka_expiry' => 'RPTKA',
            'merp_expiry' => 'MERP',
            'lease_enddate' => 'Sewa Rumah',
        ];

        $expiring = collect();
        $countByType = array_fill_keys($docTypes, 0);

        foreach ($expats as $e) {
            foreach ($docTypes as $field => $label) {
                if (!$e->$field) continue;

                $expiry = \Carbon\Carbon::parse($e->$field);
                $daysLeft = now()->diffInDays($expiry, false);

                if ($daysLeft >= 0 && $daysLeft <= $days) {
                    $expiring->push([
                        'npk' => $e->npk,
                        'name' => $e->name,
                        'doc_type' => $label,
                        'expiry_date' => $expiry->format('Y-m-d'),
                        'days_left' => (int) $daysLeft,
                    ]);
                    $countByType[$label] = ($countByType[$label] ?? 0) + 1;
                }
            }
        }

        $expiring = $expiring->sortBy('days_left')->values();

        return response()->json([
            'total_expat' => $totalExpat,
            'total_active' => $totalActive,
            'expiring_count' => $expiring->count(),
            'expiring' => $expiring,
            'count_by_type' => collect($docTypes)->mapWithKeys(fn($label) => [$label => $countByType[$label] ?? 0]),
        ]);
    }
}
