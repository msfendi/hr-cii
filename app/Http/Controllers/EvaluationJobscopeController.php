<?php

namespace App\Http\Controllers;

use App\Models\EvaluationJobscope;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class EvaluationJobscopeController extends Controller
{
    public function index()
    {

        $data = EvaluationJobscope::all();

        return view('evaluation_jobscope.index', compact('data'));
    }

    public function delete($id)
    {
        $jobscope = EvaluationJobscope::findOrFail($id);
        $jobscope->delete();

        Alert::success('Delete Successfully!', 'Jobscope ' . $jobscope->job_name . ' successfully deleted!');
        return redirect()->route('evaluation-jobscope.index');
    }
}
