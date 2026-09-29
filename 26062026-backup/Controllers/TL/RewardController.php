<?php

namespace App\Http\Controllers\TL;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RewardController extends Controller
{
    public function index(Request $request)
    {
        $agent = auth('agent')->user();

        $currentBalance = DB::table('reward_points_ledger')
            ->where('agent_id', $agent->agent_id)
            ->orderByDesc('created_at')
            ->value('running_balance') ?? 0;

        $summary = DB::table('reward_points_ledger')
            ->where('agent_id', $agent->agent_id)
            ->selectRaw('
                COALESCE(SUM(points_in), 0) as total_earned,
                COALESCE(SUM(points_out), 0) as total_redeemed
            ')
            ->first();

        $ledger = DB::table('reward_points_ledger')
            ->where('agent_id', $agent->agent_id)
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'page')
            ->withQueryString();

        return view('tl.rewards.index', compact('agent', 'currentBalance', 'summary', 'ledger'));
    }
}
