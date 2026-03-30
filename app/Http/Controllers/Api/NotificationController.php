<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()
            ->notifications()
            ->latest()
            ->limit(20)
            ->get();
    }

    public function unread(Request $request)
    {
        return $request->user()
            ->notifications()
            ->whereNull('read_at')
            ->latest()
            ->get();
    }

    public function countUnread(Request $request)
    {
        return [
            'count' => $request->user()
                ->notifications()
                ->whereNull('read_at')
                ->count(),
        ];
    }

    public function markAsRead($id, Request $request)
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        if (! $notification->read_at) {
            $notification->update([
                'read_at' => now(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function markAll(Request $request)
    {
        $request->user()
            ->notifications()
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }
}
