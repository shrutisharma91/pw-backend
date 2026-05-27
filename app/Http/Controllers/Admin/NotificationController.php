<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| NotificationController
|--------------------------------------------------------------------------
| Handles Screen 05 — Notification Center
|
| APIs:
|   GET    /api/v1/admin/notifications           → list with tabs/filters
|   PUT    /api/v1/admin/notifications/read-all  → mark all read
|   PUT    /api/v1/admin/notifications/{id}/read → mark one read
|   PUT    /api/v1/admin/notifications/{id}/snooze
|   PUT    /api/v1/admin/notifications/{id}/archive
|   DELETE /api/v1/admin/notifications/{id}
|
| Screen has tabs: All / Approvals / Alerts / System / Mentions
| Priority colours: critical / high / medium / info
*/

class NotificationController extends Controller
{
    // ------------------------------------------------------------------
    // GET /api/v1/admin/notifications
    // Supports: ?tab=approvals&priority=critical&search=kyc&page=1
    // ------------------------------------------------------------------
    public function index(Request $request)
    {
        /** @var User|null $user */
        $user  = Auth::user();
        $query = AdminNotification::where('user_id', $user->id)->visible();

        // Tab filter — maps to 'type' column
        $tabMap = [
            'approvals' => 'approval',
            'alerts'    => 'alert',
            'system'    => 'system',
            'mentions'  => 'mention',
        ];

        if ($request->filled('tab') && isset($tabMap[$request->tab])) {
            $query->where('type', $tabMap[$request->tab]);
        }

        // Priority filter
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        // Search in title and message
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('message', 'like', '%' . $request->search . '%');
            });
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Unread only
        if ($request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        // Sort newest first, critical priority on top
        $query->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'info')")
              ->orderBy('created_at', 'desc');

        // Paginate — 20 per page
        $notifications = $query->paginate(20);

        // Count unread per tab for badge display
        $unreadCounts = [
            'all'       => AdminNotification::where('user_id', $user->id)->visible()->where('is_read', false)->count(),
            'approvals' => AdminNotification::where('user_id', $user->id)->visible()->where('type', 'approval')->where('is_read', false)->count(),
            'alerts'    => AdminNotification::where('user_id', $user->id)->visible()->where('type', 'alert')->where('is_read', false)->count(),
            'system'    => AdminNotification::where('user_id', $user->id)->visible()->where('type', 'system')->where('is_read', false)->count(),
            'mentions'  => AdminNotification::where('user_id', $user->id)->visible()->where('type', 'mention')->where('is_read', false)->count(),
        ];

        return response()->json([
            'success'       => true,
            'unread_counts' => $unreadCounts,
            'data'          => $notifications->items(),
            'meta' => [
                'total'        => $notifications->total(),
                'per_page'     => $notifications->perPage(),
                'current_page' => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // PUT /api/v1/admin/notifications/{id}/read
    // ------------------------------------------------------------------
    public function markRead(int $id)
    {
        $notification = $this->findNotification($id);
        if (!$notification) {
            return $this->notFound();
        }

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }

    // ------------------------------------------------------------------
    // PUT /api/v1/admin/notifications/read-all
    // ------------------------------------------------------------------
    public function markAllRead()
    {
        /** @var User|null $user */
        $user = Auth::user();

        AdminNotification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
        ]);
    }

    // ------------------------------------------------------------------
    // PUT /api/v1/admin/notifications/{id}/snooze
    // Body: { "snooze_until": "2024-12-01 10:00:00" }
    // ------------------------------------------------------------------
    public function snooze(Request $request, int $id)
    {
        $request->validate([
            'snooze_until' => 'required|date|after:now',
        ]);

        $notification = $this->findNotification($id);
        if (!$notification) {
            return $this->notFound();
        }

        $notification->update(['snoozed_until' => $request->snooze_until]);

        return response()->json([
            'success'      => true,
            'message'      => 'Notification snoozed.',
            'snoozed_until' => $request->snooze_until,
        ]);
    }

    // ------------------------------------------------------------------
    // PUT /api/v1/admin/notifications/{id}/archive
    // ------------------------------------------------------------------
    public function archive(int $id)
    {
        $notification = $this->findNotification($id);
        if (!$notification) {
            return $this->notFound();
        }

        $notification->update(['is_archived' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Notification archived.',
        ]);
    }

    // ------------------------------------------------------------------
    // DELETE /api/v1/admin/notifications/{id}
    // ------------------------------------------------------------------
    public function destroy(int $id)
    {
        $notification = $this->findNotification($id);
        if (!$notification) {
            return $this->notFound();
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted.',
        ]);
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------
    private function findNotification(int $id): ?AdminNotification
    {
        /** @var int|null $userId */
        $userId = Auth::id();
        return AdminNotification::where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    private function notFound()
    {
        return response()->json([
            'success' => false,
            'message' => 'Notification not found.',
        ], 404);
    }
}
