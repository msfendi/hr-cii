<?php

namespace App\Http\Controllers;

use App\Events\NotificationEvent;
use App\Jobs\GenerateCompensation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\CompensationDetails;
use App\Models\Compensations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use RealRashid\SweetAlert\Facades\Alert;

class CompensationsController extends Controller
{

    public function index(Request $request)
    {

        // =========================
        // FILTER STATUS PERIOD
        // =========================
        $filter = $request->get('status', 'open');

        $query = DB::table('compensations as c')
            ->leftJoin('compensation_approve as ca', 'ca.run_id', '=', 'c.id')
            ->select(
                'c.*',
                'ca.status as approve_status'
            );

        if ($filter === 'open') {
            $query->where('c.is_closed', false);
        }

        if ($filter === 'closed') {
            $query->where('c.is_closed', true);
        }

        $compensations = $query
            ->latest('c.id')
            ->get();

        return view('compensation.index', compact('compensations', 'filter'));
    }

    public function details($date)
    {
        $data = DB::table('compensation_details as cd')
            ->leftJoin('DEPT as d', 'd.ID_DEPT', '=', 'cd.id_dept')
            ->whereDate('cd.cutoff_date', $date)
            ->select(
                'cd.id',
                'cd.npk',
                'd.DEPARTEMENT as dept',
                'cd.amount',
                'cd.status',
                'cd.is_active'
            )
            ->get();

        return response()->json(['data' => $data]);
    }


    /*
    |--------------------------------------------------------------------------
    | GENERATE COMPENSATION
    |--------------------------------------------------------------------------
    */



    public function generate(Request $request)
    {
        $user = Auth::user();
        $today = Carbon::parse($request->generate_date);
        $periodName = Carbon::parse($today)->translatedFormat('F_Y');

        $exists = Compensations::where('cutoff_date', $today)->exists();

        // if ($exists) {
        //     Alert::error('Gagal', 'Compensation for this period has been generated previously.');
        //     return redirect()->back();
        // }

        $master = Compensations::create([
            'cutoff_date' => $today,
            'status' => 'Waiting Queue',
            'progress' => 0,
            'is_closed' => 0
        ]);

        GenerateCompensation::dispatch(
            $today,
            $master->id,
            'process'
        );

        Alert::success('Success', 'Compensation successfully processed!');
        event(new NotificationEvent(
            'Process Compensations!',
            'Users : ' . $user->name . ' has been process Compensations ' . $periodName . '!',
            'success'
        ));

        return back();
    }
}
