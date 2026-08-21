<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtractedTableRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'extracted_table_id',
        'row_index',
        'row_data',
    ];

    protected $casts = [
        'row_data' => 'array',
    ];

    public function table()
    {
        return $this->belongsTo(ExtractedTable::class, 'extracted_table_id');
    }
}
