<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// NEW 8 Aug 2026 (Task #83) — Admin-only view of whatsapp_message_log.
// Shows every agent's WhatsApp send attempt (allowed or blocked by the
// recipient-scoping rule in DataScopeService::verifyWhatsAppRecipient(),
// and sent or failed by Meta) — sender, recipient, status, reason, when.
// Filterable by status and by sender, same GET-param + Prev/Next
// convention as Notice Board (no numbered "jump" pages).
class WhatsAppAuditController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'all');
        $senderId = $request->get('sender', 'all');

        $query = DB::table('whatsapp_message_log')
            ->join('agents', 'agents.agent_id', '=', 'whatsapp_message_log.sender_agent_id')
            ->select('whatsapp_message_log.*', 'agents.full_name as sender_name');

        if ($status !== 'all') {
            $query->where('whatsapp_message_log.status', $status);
        }
        if ($senderId !== 'all') {
            $query->where('whatsapp_message_log.sender_agent_id', $senderId);
        }

        $logs = $query
            ->orderByDesc('whatsapp_message_log.created_at')
            // REDUCED 8 Aug 2026 per Chris: strict no-truncation rule —
            // Sender/Recipient/Detail/Message columns now wrap instead of
            // ellipsis-cutting, so fewer rows/page keeps everything fitting
            // one screen without scrolling.
            ->paginate(5, ['*'], 'waPage')
            ->appends(['status' => $status, 'sender' => $senderId]);

        $senders = DB::table('agents')
            ->join('whatsapp_message_log', 'whatsapp_message_log.sender_agent_id', '=', 'agents.agent_id')
            ->distinct()
            ->orderBy('agents.full_name')
            ->pluck('agents.full_name', 'agents.agent_id');

        return view('admin.whatsapp-audit.index', compact('logs', 'status', 'senderId', 'senders'));
    }
}
