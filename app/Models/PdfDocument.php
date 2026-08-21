<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdfDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'original_filename',
        'file_path',
        'raw_text',
        'category_id',
        'status',
        'error_message',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function extractedData()
    {
        return $this->hasMany(ExtractedData::class);
    }

    public function extractedTables()
    {
        return $this->hasMany(ExtractedTable::class);
    }

    // Ambil satu value berdasarkan key, contoh: $doc->getValue('Vessel/Voy')
    public function getValue(string $key): ?string
    {
        return $this->extractedData()
            ->where('data_key', $key)
            ->value('data_value');
    }
}
