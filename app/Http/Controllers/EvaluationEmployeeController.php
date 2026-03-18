<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EvaluationQuestionnaire;
use App\Models\EvaluationEmployee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use RealRashid\SweetAlert\Facades\Alert;

class EvaluationEmployeeController extends Controller
{

    public function index(Request $request)
    {
        // UNION BIODATA dan BIODATA_KELUAR
        $biodataUnion = DB::table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT')
            ->union(
                DB::table('BIODATA_KELUAR')
                    ->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT')
            );

        $query = EvaluationEmployee::query()
            ->leftJoinSub($biodataUnion, 'biodata', function ($join) {
                $join->on('evaluation_employee.npk', '=', 'biodata.NPK');
            })
            ->leftJoin('DEPT', 'biodata.ID_DEPT', '=', 'DEPT.ID_DEPT')
            ->leftJoin('evaluation_jobscope', 'evaluation_employee.jobscope_id', '=', 'evaluation_jobscope.id')
            ->select(
                'evaluation_employee.*',
                'evaluation_jobscope.job_name',
                'biodata.NAMA_KARYAWAN',
                'DEPT.DEPARTEMENT'
            );

        $query->select(
            'evaluation_employee.*',
            'evaluation_jobscope.job_name',
            'biodata.NAMA_KARYAWAN',
            'DEPT.DEPARTEMENT'
        );

        $data = $query->get();

        return view('evaluation_employee.index', compact('data'));
    }

    public function cbt(Request $request)
    {
        $npk = $request->npk;
        $jobscope_id = $request->jobscope_id;

        // CEK SESSION
        // if (session('cbt_done_' . $npk . '_' . $jobscope_id)) {
        //     return redirect('evaluation-employee/thankyou');
        // }

        // CEK DATABASE
        $already = EvaluationEmployee::where('npk', $npk)
            ->where('jobscope_id', $jobscope_id)
            ->exists();

        if ($already) {
            return redirect('evaluation-employee/thankyou');
        }

        $seed = crc32($npk);

        $questions = EvaluationQuestionnaire::where('jobscope_id', $jobscope_id)
            ->inRandomOrder()
            ->limit(10)
            ->get();

        return view('evaluation_employee.cbt', compact('questions', 'npk', 'jobscope_id'));
    }

    public function submit(Request $request)
    {
        $npk = $request->npk;
        $answers = $request->answers;
        $jobscope_id = $request->jobscope_id;

        // CEK SUDAH PERNAH TEST
        $already = EvaluationEmployee::where('npk', $npk)
            ->where('jobscope_id', $jobscope_id)
            ->exists();

        if ($already) {
            return redirect('evaluation-employee/thankyou')
                ->with('error', 'Anda sudah mengerjakan evaluasi ini');
        }

        $score = 0;
        $total = count($answers);

        foreach ($answers as $questionId => $answer) {

            $question = EvaluationQuestionnaire::find($questionId);

            if (!$question) continue;

            if ($question->correct_answer == $answer) {
                $score++;
            }
        }

        // SIMPAN 1 RECORD (FINAL SCORE)
        EvaluationEmployee::create([
            'npk' => $npk,
            'jobscope_id' => $jobscope_id,
            'score' => $score,
            'evaluation_date' => Carbon::now()
        ]);

        // LOCK SESSION (ANTI BACK)
        // Session::put('cbt_done_' . $npk . '_' . $jobscope_id, true);

        // Alert::success('Test selesai. Score: ' . $score);
        return redirect('evaluation-employee/thankyou');
    }

    public function portal()
    {
        return view('evaluation_employee.portal');
    }
    public function thankyou()
    {
        return view('evaluation_employee.thankyou');
    }
}
