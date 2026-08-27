<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\PdfDocumentController;
use App\Http\Controllers\PdfExtractionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/leave-request', [LeaveRequestController::class, 'submitForm']);
Route::post('/leave-history', [LeaveRequestController::class, 'getHistory']);

Route::get('/pdf-documents', [PdfExtractionController::class, 'list']);
Route::post('/pdf-documents', [PdfExtractionController::class, 'store']);
Route::get('/pdf-documents/{document}', [PdfExtractionController::class, 'show']);
Route::get('/pdf-data/search', [PdfExtractionController::class, 'searchByKey']);
Route::get('/categories', [PdfExtractionController::class, 'categories']);
