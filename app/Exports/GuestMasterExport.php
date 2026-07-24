<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class GuestMasterExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'Guest Master'  => new GuestMasterSheetExport(),
            'Foreign Guest' => new ForeignGuestSheetExport(),
        ];
    }
}
