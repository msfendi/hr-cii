<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ExtractedData;
use App\Models\PdfDocument;
use App\Services\PdfAiExtractionService;
use Illuminate\Http\Request;

class PdfExtractionController extends Controller
{
    public function __construct(protected PdfAiExtractionService $service)
    {
    }

    // Tampilkan halaman upload & daftar dokumen
    public function index()
    {
        return view('pdf.index');
    }

    // List semua dokumen (dipakai tabel di halaman index), terbaru dulu
    public function list()
    {
        $documents = PdfDocument::with('category:id,name')
            ->latest()
            ->get(['id', 'original_filename', 'category_id', 'status', 'created_at']);

        return response()->json($documents);
    }

    // List kategori untuk dropdown upload
    public function categories()
    {
        return response()->json(Category::orderBy('name')->get(['id', 'name']));
    }

    // Halaman/endpoint upload PDF
    public function store(Request $request)
    {
        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:20480', // max 20MB
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $file = $request->file('pdf');
        $path = $file->store('pdf-uploads', 'local'); // tersimpan di storage/app/pdf-uploads
        $absolutePath = storage_path('app/' . $path);

        $document = PdfDocument::create([
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $absolutePath,
            'category_id' => $request->input('category_id'),
            'status' => 'pending',
        ]);

        // Proses langsung (untuk skala kecil).
        // Untuk PDF banyak/besar, sebaiknya di-queue: PdfExtractionJob::dispatch($document);
        $document = $this->service->process($document);

        return response()->json([
            'message' => $document->status === 'processed'
                ? 'PDF berhasil diproses'
                : 'PDF gagal diproses: ' . $document->error_message,
            'document' => $document->load('extractedData', 'extractedTables.rows'),
        ]);
    }

    // Cari data berdasarkan key tertentu, contoh: GET /api/pdf-data/search?key=Vessel/Voy
    public function searchByKey(Request $request)
    {
        $request->validate(['key' => 'required|string']);

        $results = ExtractedData::byKey($request->input('key'))
            ->with('pdfDocument:id,original_filename,category_id')
            ->get(['id', 'pdf_document_id', 'data_key', 'data_value']);

        return response()->json($results);
    }

    // Ambil detail lengkap satu dokumen (field + tabel)
    public function show(PdfDocument $document)
    {
        return response()->json(
            $document->load('extractedData', 'extractedTables.rows', 'category')
        );
    }
}
