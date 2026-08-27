<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtractedData extends Model
{
    use HasFactory;

    protected $fillable = [
        'pdf_document_id',
        'category_id',
        'data_key',
        'data_value',
    ];

    public function pdfDocument()
    {
        return $this->belongsTo(PdfDocument::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Scope untuk pencarian cepat berdasarkan key
    // Contoh pakai: ExtractedData::byKey('Vessel/Voy')->get();
    public function scopeByKey($query, string $key)
    {
        return $query->where('data_key', 'like', "%{$key}%");
    }
}
