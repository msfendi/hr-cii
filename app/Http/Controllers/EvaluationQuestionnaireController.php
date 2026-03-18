<?php

namespace App\Http\Controllers;

use App\Exports\EvaluationTemplateExport;
use App\Imports\EvaluationImport;
use Illuminate\Http\Request;
use App\Models\EvaluationQuestionnaire;
use App\Models\EvaluationJobscope;
use Maatwebsite\Excel\Facades\Excel;

class EvaluationQuestionnaireController extends Controller
{
    public function index()
    {
        $data = EvaluationQuestionnaire::with('jobscope')->get();
        return view('evaluation_questionnaire.index', compact('data'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls'
        ]);

        Excel::import(new EvaluationImport, $request->file('file'));

        return redirect()->back()->with('success', 'Data berhasil diimport');
    }

    public function template()
    {
        return Excel::download(new EvaluationTemplateExport, 'template_evaluation.xlsx');
    }

    public function delete($id)
    {
        EvaluationQuestionnaire::findOrFail($id)->delete();
        return redirect()->back()->with('success', 'Data berhasil dihapus');
    }
}
