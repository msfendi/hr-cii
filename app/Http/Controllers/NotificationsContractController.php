<?php

namespace App\Http\Controllers;

use App\Models\NotificationsContract;
use Illuminate\Http\Request;

class NotificationsContractController extends Controller
{
    public function __construct()
    {
        // Protect dengan auth & hr role
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->hasAnyRole(['Admin', 'HRD', 'Payroll_STAFF', 'Payroll_SEWING', 'Payroll_NONSEWING'])) {
                abort(403, 'Unauthorized');
            }
            return $next($request);
        });
    }

    /**
     * Get all notifications (paginated)
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 20);
        $status = $request->input('status', 'unread');
        $type = $request->input('type');

        $query = NotificationsContract::query();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($type) {
            $query->where('type', $type);
        }

        $notifications = $query->recent()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'pagination' => [
                'total' => $notifications->total(),
                'per_page' => $notifications->perPage(),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
            ]
        ]);
    }

    /**
     * Get unread notifications count & recent data
     */
    public function unread()
    {
        $unreadCount = NotificationsContract::unread()->count();
        
        $recentUnread = NotificationsContract::unread()
            ->recent()
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount,
            'recent' => $recentUnread->toArray()
        ]);
    }

    /**
     * Get single notification detail
     */
    public function show($id)
    {
        $notification = NotificationsContract::find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $notification->toArray()
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($id)
    {
        $notification = NotificationsContract::find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'data' => $notification->toArray()
        ]);
    }

    /**
     * Mark all unread notifications as read
     */
    public function markAllAsRead()
    {
        NotificationsContract::unread()->update([
            'status' => 'read',
            'read_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
    }

    /**
     * Archive notification
     */
    public function archive($id)
    {
        $notification = NotificationsContract::find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        $notification->markAsArchived();

        return response()->json([
            'success' => true,
            'message' => 'Notification archived',
            'data' => $notification->toArray()
        ]);
    }

    /**
     * Delete notification
     */
    public function destroy($id)
    {
        $notification = NotificationsContract::find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted'
        ]);
    }

    /**
     * Get statistics
     */
    public function statistics()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total' => NotificationsContract::count(),
                'unread' => NotificationsContract::unread()->count(),
                'read' => NotificationsContract::read()->count(),
                'archived' => NotificationsContract::archived()->count(),
                'expiring' => NotificationsContract::expiring()->count(),
                'expired' => NotificationsContract::expired()->count(),
            ]
        ]);
    }
}
