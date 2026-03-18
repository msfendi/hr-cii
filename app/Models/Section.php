<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    protected $fillable = [
        'name',
        'line_start',
        'line_end'
    ];

    /*
    cek apakah line termasuk dalam section
    */
    public function containsLine($lineNumber)
    {
        return $lineNumber >= $this->line_start && $lineNumber <= $this->line_end;
    }
}
