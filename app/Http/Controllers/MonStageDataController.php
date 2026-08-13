<?php

namespace App\Http\Controllers;

use App\Exports\MonProdQcTemplateExport;
use App\Exports\MonStageRemarkTemplateExport;
use App\Imports\MonProdQcImport;
use App\Imports\MonStageRemarkImport;
use App\Services\MonStageDataService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;
use Throwable;

/**
 * Controller untuk fitur import Stage Remark (mon_stage_remarks) & Prod QC
 * (mon_prod_qc) -- bagian dari dashboard Rekonsiliasi OCF (rekonsiliasi_ocf
 * blade), tombolnya disatukan 1 kelompok dengan "Sync All Data" di header.
 *
 * ocf_no (mon_stage_remarks) & code_prod (mon_prod_qc) diisi lewat dropdown
 * di file template Excel, sumbernya MonStageDataService::distinctOcfList()
 * (hasil ekstraksi mon_rekonsiliasis.code_prod, pola "... OCF <kode>").
 */
class MonStageDataController extends Controller
{
    public function __construct(private MonStageDataService $service)
    {
    }

    // ===================== STAGE REMARK (mon_stage_remarks) =====================

    /**
     * Tombol "Download Template" -> file Excel kosong dengan dropdown
     * ocf_no & department_id siap diisi user.
     */
    public function templateStageRemark()
    {
        return Excel::download(
            new MonStageRemarkTemplateExport($this->service),
            'template_mon_stage_remark.xlsx'
        );
    }

    /**
     * Tombol "Import Excel" -> upload file hasil template di atas, tiap
     * baris valid di-insert ke mon_stage_remarks.
     */
    public function importStageRemark(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        try {
            Excel::import(new MonStageRemarkImport($this->service), $request->file('file'));

            return response()->json([
                'success' => true,
                'message' => 'Import Stage Remark berhasil.',
            ]);
        } catch (ExcelValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ada baris yang tidak valid, import dibatalkan.',
                'errors'  => $this->formatExcelValidationErrors($e),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // ========================= PROD QC (mon_prod_qc) =========================

    /**
     * Tombol "Download Template" -> file Excel kosong dengan dropdown
     * code_prod & department_id siap diisi user.
     */
    public function templateProdQc()
    {
        return Excel::download(
            new MonProdQcTemplateExport($this->service),
            'template_mon_prod_qc.xlsx'
        );
    }

    /**
     * Tombol "Import Excel" -> upload file hasil template di atas, tiap
     * baris valid di-insert ke mon_prod_qc.
     */
    public function importProdQc(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        try {
            Excel::import(new MonProdQcImport($this->service), $request->file('file'));

            return response()->json([
                'success' => true,
                'message' => 'Import Prod QC berhasil.',
            ]);
        } catch (ExcelValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ada baris yang tidak valid, import dibatalkan.',
                'errors'  => $this->formatExcelValidationErrors($e),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ratakan pesan error per baris dari Maatwebsite\Excel\Validators\ValidationException
     * jadi array string sederhana, gampang ditampilkan di SweetAlert frontend.
     */
    private function formatExcelValidationErrors(ExcelValidationException $e): array
    {
        $messages = [];
        foreach ($e->failures() as $failure) {
            $messages[] = "Baris {$failure->row()} ({$failure->attribute()}): " . implode(' ', $failure->errors());
        }

        return $messages;
    }
}
