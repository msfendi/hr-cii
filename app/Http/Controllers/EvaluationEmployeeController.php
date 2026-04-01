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
        // =============================
        // UNION BIODATA
        // =============================
        $biodataUnion = DB::table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT')
            ->union(
                DB::table('BIODATA_KELUAR')
                    ->select('NPK', 'NAMA_KARYAWAN', 'ID_DEPT')
            );

        $data = EvaluationEmployee::query()
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
            )
            ->get();

        // =============================
        // AMBIL QUESTION MASTER
        // =============================
        $questions = EvaluationQuestionnaire::get()
            ->keyBy('id');

        // =============================
        // BUILD QUESTIONNAIRE RESULT
        // =============================
        foreach ($data as $row) {

            $employeeQuestions = $row->employee_question ?? [];
            $employeeAnswers   = $row->employee_answer ?? [];

            $result = [];

            foreach ($employeeQuestions as $i => $questionId) {

                $question = $questions[$questionId] ?? null;
                if (!$question) continue;

                $answer = $employeeAnswers[$i] ?? null;

                $result[] = [
                    'step' => $i + 1,
                    'question' => $question->question,
                    'answer' => $answer,
                    'correct_answer' => $question->correct_answer,
                    'is_correct' => $answer === $question->correct_answer,
                ];
            }

            $row->questionnaire_result = $result;
        }

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
        $answers = $request->answers ?? [];
        $jobscope_id = $request->jobscope_id;

        // =============================
        // CEK SUDAH PERNAH TEST
        // =============================
        $already = EvaluationEmployee::where('npk', $npk)
            ->where('jobscope_id', $jobscope_id)
            ->exists();

        if ($already) {
            return redirect('evaluation-employee/thankyou')
                ->with('error', 'Anda sudah mengerjakan evaluasi ini');
        }

        // =============================
        // HITUNG SCORE + SIMPAN DETAIL
        // =============================
        $score = 0;

        $employeeQuestions = [];
        $employeeAnswers   = [];

        foreach ($answers as $questionId => $answer) {

            $question = EvaluationQuestionnaire::find($questionId);
            if (!$question) continue;

            // simpan detail jawaban
            $employeeQuestions[] = $questionId;
            $employeeAnswers[]   = $answer;

            // hitung score
            if ($question->correct_answer == $answer) {
                $score++;
            }
        }

        // =============================
        // SIMPAN HASIL
        // =============================
        EvaluationEmployee::create([
            'npk' => $npk,
            'jobscope_id' => $jobscope_id,
            'score' => $score,
            'employee_question' => $employeeQuestions,
            'employee_answer' => $employeeAnswers,
            'evaluation_date' => now(),
        ]);

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
