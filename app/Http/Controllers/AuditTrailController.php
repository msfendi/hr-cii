<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Butuh package yajra/laravel-datatables-oracle.
 * Kalau belum terpasang: composer require yajra/laravel-datatables-oracle
 */
class AuditTrailController extends Controller
{
    public function index()
    {
        $events = ['created', 'updated', 'deleted'];
        $models = AuditTrail::query()
            ->select('auditable_type')
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type');

        return view('audit_trail.index', compact('events', 'models'));
    }

    public function data(Request $request)
    {
        // PENTING: jangan panggil ->latest('id') / ->orderBy(...) di sini untuk koneksi SQL Server.
        // Yajra membungkus query ini jadi subquery saat menghitung count(*), dan SQL Server
        // melarang ORDER BY di dalam subquery tanpa TOP/OFFSET/FOR XML -> error 42000.
        // Urutan default (created_at desc) cukup diatur lewat `order: [[0, 'desc']]` di JS,
        // yajra akan menerapkannya hanya ke query data, bukan ke query count.
        $query = AuditTrail::query();

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->auditable_type);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59',
            ]);
        }

        return DataTables::of($query)
            ->addColumn('model_name', fn(AuditTrail $row) => class_basename($row->auditable_type))
            ->addColumn('event_badge', function (AuditTrail $row) {
                $color = match ($row->event) {
                    'created' => 'success',
                    'updated' => 'warning',
                    'deleted' => 'danger',
                    default => 'secondary',
                };

                return '<span class="badge badge-' . $color . '">' . strtoupper($row->event) . '</span>';
            })
            ->addColumn('action', fn(AuditTrail $row) => '<button type="button" class="btn btn-sm btn-info btn-detail" data-id="' . $row->id . '"><i class="fas fa-eye"></i> Detail</button>')
            ->editColumn('created_at', fn(AuditTrail $row) => optional($row->created_at)->format('d-m-Y H:i:s'))
            ->rawColumns(['event_badge', 'action'])
            ->make(true);
    }

    public function show(AuditTrail $auditTrail)
    {
        return response()->json([
            'id'           => $auditTrail->id,
            'user_name'    => $auditTrail->user_name ?? '-',
            'event'        => $auditTrail->event,
            'model'        => class_basename($auditTrail->auditable_type),
            'auditable_id' => $auditTrail->auditable_id,
            'url'          => $auditTrail->url,
            'ip_address'   => $auditTrail->ip_address,
            'created_at'   => optional($auditTrail->created_at)->format('d-m-Y H:i:s'),
            'old_values'   => $auditTrail->old_values,
            'new_values'   => $auditTrail->new_values,
        ]);
    }
}
