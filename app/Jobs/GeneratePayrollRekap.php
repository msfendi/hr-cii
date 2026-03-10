<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GeneratePayrollRekap implements ShouldQueue
{
    use Dispatchable, Queueable;

    protected $run_id;

    public function __construct($run_id)
    {
        $this->run_id = $run_id;
    }

    public function handle()
    {

        $rows = DB::table('payroll_run_details as prd')
            ->leftJoin('BIODATA as b', 'b.NPK','=','prd.employee_npk')
            ->leftJoin('DEPT as d','d.id_dept','=','b.id_dept')
            ->where('prd.run_id',$this->run_id)
            ->select(
                'prd.employee_npk',
                'prd.employee_name',
                'prd.components',
                'prd.total_salary',
                'd.DEPARTEMENT'
            )
            ->orderBy('d.DEPARTEMENT')
            ->cursor(); // <<< hemat memory

        $employees = [];

        foreach($rows as $row){

            $components = json_decode($row->components,true);

            $employees[] = [
                'npk'=>$row->employee_npk,
                'name'=>$row->employee_name,
                'dept'=>$row->DEPARTEMENT,
                'components'=>$components,
                'total'=>$row->total_salary
            ];
        }

        $pdf = Pdf::loadView('payroll.rekap_pdf',[
            'employees'=>$employees
        ])->setPaper('A4','landscape');

        Storage::put(
            'payroll/rekap_'.$this->run_id.'.pdf',
            $pdf->output()
        );
    }
}
