<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class IjinMeninggalkanExport implements FromView, ShouldAutoSize
{
    protected $data;
    protected string $label;

    public function __construct($data, string $label)
    {
        $this->data  = $data;
        $this->label = $label;
    }

    public function view(): View
    {
        return view('ijin_meninggalkan_pekerjaan.export_excel', [
            'data'  => $this->data,
            'label' => $this->label,
        ]);
    }
}
