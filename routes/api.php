<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeaveController;
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

// ── Legacy API routes (existing) ──
Route::post('/leave-request', [LeaveRequestController::class, 'submitForm']);
Route::post('/leave-history', [LeaveRequestController::class, 'getHistory']);

// ── PDF API routes (existing) ──
Route::get('/pdf-documents', [PdfExtractionController::class, 'list']);
Route::post('/pdf-documents', [PdfExtractionController::class, 'store']);
Route::get('/pdf-documents/{document}', [PdfExtractionController::class, 'show']);
Route::get('/pdf-data/search', [PdfExtractionController::class, 'searchByKey']);
Route::get('/categories', [PdfExtractionController::class, 'categories']);

// ══════════════════════════════════════════════════════════
//  V1 — Leave Management API
// ══════════════════════════════════════════════════════════

Route::prefix('v1')->group(function () {

    // ── Auth (Public) ──
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
    });

    // ── Auth (Protected) ──
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });

        // Leave Management
        Route::prefix('leave')->group(function () {
            Route::post('/request', [LeaveController::class, 'submitRequest']);          // Pengajuan cuti
            Route::get('/history', [LeaveController::class, 'getHistory']);              // Riwayat cuti
            Route::get('/history/{token}', [LeaveController::class, 'getDetail']);       // Detail cuti
            Route::get('/balance', [LeaveController::class, 'getBalance']);              // Saldo cuti
            Route::get('/types', [LeaveController::class, 'getLeaveTypes']);             // Jenis cuti
            Route::get('/reasons/{leave_type_id}', [LeaveController::class, 'getReasons']); // Alasan cuti
        });
    });
});
