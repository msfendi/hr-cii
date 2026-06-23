<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeExitHistoryController extends Controller
{
    public function index()
    {
        $departments = DB::connection('cii')->table('DEPT')->select('ID_DEPT', 'DEPARTEMENT')->where('SECTION', 'CHUTEX')->get();
        return view('employee_exit_history.index', compact('departments'));
    }

    public function getData(Request $request)
    {
        $deptFilter = ($request->has('department_id') && $request->department_id != '')
            ? "AND BK.ID_DEPT = " . intval($request->department_id)
            : "";

        $sql = "
            SELECT
                base.NPK,
                base.NAMA_KARYAWAN,
                base.BARCODE,
                base.DEPARTEMENT,
                base.KTP,
                -- NULLIF converts 0->NULL so ISNULL default of 1 fires when no PKWT match
                ISNULL(NULLIF((
                    SELECT COUNT(DISTINCT p.TKK)
                    FROM PKWT p
                    WHERE p.TKK IS NOT NULL
                      AND (
                          (base.KTP_KEY IS NOT NULL AND LTRIM(RTRIM(p.KTP)) = LTRIM(RTRIM(base.KTP_KEY)))
                       OR (base.KTP_KEY IS NULL AND p.NPK = base.NPK_KEY)
                      )
                ), 0), 1) as total_riwayat,
                STUFF((
                    SELECT DISTINCT ', ' + CONVERT(varchar, p.TKK, 103)
                    FROM PKWT p
                    WHERE p.TKK IS NOT NULL
                      AND (
                          (base.KTP_KEY IS NOT NULL AND LTRIM(RTRIM(p.KTP)) = LTRIM(RTRIM(base.KTP_KEY)))
                       OR (base.KTP_KEY IS NULL AND p.NPK = base.NPK_KEY)
                      )
                    FOR XML PATH('')
                ), 1, 2, '') as riwayat_tkk
            FROM (
                SELECT
                    MAX(BK.NPK)           as NPK,
                    MAX(BK.NAMA_KARYAWAN) as NAMA_KARYAWAN,
                    MAX(BK.BARCODE)       as BARCODE,
                    MAX(D.DEPARTEMENT)    as DEPARTEMENT,
                    MAX(PK.KTP)           as KTP,
                    MAX(BK.NPK)           as NPK_KEY,
                    MAX(PK.KTP)           as KTP_KEY
                FROM BIODATA_KELUAR BK
                LEFT JOIN DEPT D  ON BK.ID_DEPT = D.ID_DEPT
                LEFT JOIN PKWT PK ON BK.NPK = PK.NPK
                WHERE 1=1 {$deptFilter}
                GROUP BY COALESCE(PK.KTP, BK.NPK)
            ) as base
        ";

        $data = DB::connection('cii')->select($sql);

        return response()->json(['data' => $data]);
    }
}
