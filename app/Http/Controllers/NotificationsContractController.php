<?php

namespace App\Http\Controllers;

use App\Models\NotificationsContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationsContractController extends Controller
{
    public function __construct()
    {
        // Protect dengan auth & hr role.
        // PENTING: dipakai juga dari halaman yang login lewat sesi face-attendance
        // (tanpa auth()->user()). Dulu closure ini memanggil hasAnyRole() pada user
        // null -> fatal -> HTTP 500 saat bell notifikasi di navbar di-klik.
        // Sekarang dikembalikan sebagai JSON 200 success:false (UI tetap jalan).
        $this->middleware('auth')->except([]);

        $this->middleware(function ($request, $next) {
            $allowed = ['Admin', 'HRD', 'Payroll_STAFF', 'Payroll_SEWING', 'Payroll_NONSEWING'];
            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi ini tidak memiliki akses notifikasi kontrak.',
                    'data' => [],
                    'unread_count' => 0,
                ]);
            }

            try {
                if (!$user->hasAnyRole($allowed)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda tidak memiliki akses notifikasi kontrak.',
                        'data' => [],
                        'unread_count' => 0,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('NotificationsContractController role check error: ' . $e->getMessage());

                return response()->json([
                    'success' => false,
                    'message' => 'Gagal memeriksa hak akses notifikasi.',
                    'data' => [],
                    'unread_count' => 0,
                ]);
            }

            return $next($request);
        });
    }

    /**
     * Get all notifications (paginated)
     */
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->input('per_page', 20);
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
        } catch (\Throwable $e) {
            Log::warning('NotificationsContractController index error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data' => [],
                'pagination' => [
                    'total' => 0,
                    'per_page' => (int) $request->input('per_page', 20),
                    'current_page' => 1,
                    'last_page' => 1,
                ]
            ]);
        }
    }

    /**
     * Get unread notifications count & recent data
     */
    public function unread()
    {
        try {
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
        } catch (\Throwable $e) {
            Log::warning('NotificationsContractController unread error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'unread_count' => 0,
                'recent' => []
            ]);
        }
    }

    /**
     * Get single notification detail
     */
    public function show($id)
    {
        try {
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
        } catch (\Throwable $e) {
            Log::warning('NotificationsContractController show error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Gagal memuat notifikasi.', 'data' => null]);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($id)
    {
        try {
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
        } catch (\Throwable $e) {
            Log::warning('NotificationsContractController markAsRead error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Gagal menandai notifikasi.']);
        }
    }

    /**
     * Mark all unread notifications as read
     */
    public function markAllAsRead()
    {
        try {
            NotificationsContract::unread()->update([
                'status' => 'read',
                'read_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read'
            ]);
        } catch (\Throwable $e) {
            Log::warning('NotificationsContractController markAllAsRead error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Gagal menandai semua notifikasi.']);
        }
    }

    /**
     * Archive notification
     */
    public function archive($id)
    {
        try {
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
        } catch (\Throwable $e) {
            Log::warning('NotificationsContractController archive error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Gagal mengarsipkan notifikasi.']);
        }
    }

    /**
     * Delete notification
     */
    public function destroy($id)
    {
        try {
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
        } catch (\Throwable $e) {
            Log::warning('NotificationsContractController destroy error: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Gagal menghapus notifikasi.']);
        }
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
