<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtractedTable extends Model
{
    use HasFactory;

    protected $fillable = [
        'pdf_document_id',
        'table_name',
        'headers',
        'table_index',
    ];

    protected $casts = [
        'headers' => 'array',
    ];

    public function pdfDocument()
    {
        return $this->belongsTo(PdfDocument::class);
    }

    public function rows()
    {
        return $this->hasMany(ExtractedTableRow::class)->orderBy('row_index');
    }
}
