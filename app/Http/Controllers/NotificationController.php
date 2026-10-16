<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PHASE N4 — In-app notification centre.
 *
 * Owner-scoped by construction: EVERY query starts from the authenticated
 * user's own `appNotifications()` relation, so a user can never read, mark, or
 * delete another user's notification. The route parameter is intentionally a
 * scalar id (never an implicitly-bound model) so `findOrFail()` can only ever
 * resolve a row the caller owns — a hand-crafted id cannot reach another user's
 * notification (IDOR closed).
 *
 * No email, no queue, no broadcast: read/unread + delete only.
 */
class NotificationController extends Controller
{
    /**
     * The notification list — newest first, paginated (20 per page).
     */
    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->appNotifications()
            ->with('complaint')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('notifications.index', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * Open a notification: mark it read, then redirect to its safe target.
     * Opening marks read (frozen read/unread decision).
     */
    public function open(Request $request, int $notification): RedirectResponse
    {
        $record = $this->ownedOrFail($request, $notification);

        $record->markAsRead();

        $target = $record->targetUrl($request->user());

        return redirect()->to($target ?? route('notifications.index'));
    }

    /**
     * Mark a single notification as read (without navigating).
     */
    public function markRead(Request $request, int $notification): RedirectResponse
    {
        $this->ownedOrFail($request, $notification)->markAsRead();

        return back();
    }

    /**
     * Mark ALL of the caller's notifications as read.
     */
    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()
            ->appNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    /**
     * Delete one of the caller's OWN notifications (G11). The complaint itself
     * is never touched — only the notification row.
     */
    public function destroy(Request $request, int $notification): RedirectResponse
    {
        $this->ownedOrFail($request, $notification)->delete();

        return back();
    }

    /**
     * Resolve a notification STRICTLY within the caller's own set. Any id that
     * does not belong to the caller yields a 404 (never another user's data).
     */
    private function ownedOrFail(Request $request, int $notificationId): AppNotification
    {
        return $request->user()
            ->appNotifications()
            ->with('complaint')
            ->findOrFail($notificationId);
    }
}
