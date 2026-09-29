<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    // Returns the current agent's notifications as JSON, for the bell
    // dropdown — most recent first, capped at 20 so the dropdown stays
    // usable rather than an endless list.
    public function index()
    {
        $agentId = Auth::guard('agent')->id();

        $notifications = DB::table('notifications')
            ->where('recipient_agent_id', $agentId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $unreadCount = DB::table('notifications')
            ->where('recipient_agent_id', $agentId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'unread_count'  => $unreadCount,
            'notifications' => $notifications,
        ]);
    }

    public function markRead(string $id)
    {
        DB::table('notifications')
            ->where('notification_id', $id)
            ->where('recipient_agent_id', Auth::guard('agent')->id())
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function markAllRead()
    {
        DB::table('notifications')
            ->where('recipient_agent_id', Auth::guard('agent')->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
