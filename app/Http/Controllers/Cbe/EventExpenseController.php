<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// NEW 22 Aug 2026 — per Chris: event-side expenses (venue, catering,
// performers, etc.), feeding the Event Income & Expenditure Statement.
// Reuses the same admin-configurable EXPENSE categories from the Bank
// Statement/Transactions module — see FinanceController::categories().
//
// UPDATED 28 Aug 2026 — per Chris: "develop all the program, all the
// program that label with the word soon." See ResolvesCbeActiveNode.
class EventExpenseController extends Controller
{
    use ResolvesCbeActiveNode;

    private function eventFor(string $eventId)
    {
        $agent = auth('agent')->user();
        return DB::table('cbe_events')->where('event_id', $eventId)->where('cbe_node_id', $this->resolveCbeNodeId($agent))->firstOrFail();
    }

    public function index(string $eventId)
    {
        $event = $this->eventFor($eventId);

        $expenses = DB::table('cbe_event_expenses as x')
            ->leftJoin('cbe_transaction_categories as c', 'c.category_id', '=', 'x.category_id')
            ->where('x.event_id', $eventId)
            ->select('x.*', 'c.category_name')
            ->orderByDesc('x.expense_date')
            ->paginate(8, ['*'], 'expPage');

        return view('cbe.expenses.index', compact('event', 'expenses'));
    }

    public function create(string $eventId)
    {
        $agent = auth('agent')->user();
        $event = $this->eventFor($eventId);

        $categories = DB::table('cbe_transaction_categories')
            ->where(function ($q) use ($agent) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $agent->group_label_id);
            })
            ->where('type', 'EXPENSE')->where('is_active', true)
            ->orderBy('display_order')->get();

        return view('cbe.expenses.create', compact('event', 'categories'));
    }

    public function store(Request $request, string $eventId)
    {
        $agent = auth('agent')->user();
        $event = $this->eventFor($eventId);

        $request->validate([
            'expense_date'         => ['required', 'date'],
            'category_id'          => ['nullable', 'uuid', 'exists:cbe_transaction_categories,category_id'],
            'description'          => ['nullable', 'string', 'max:255'],
            'amount'                => ['required', 'numeric', 'min:0.01'],
            'receipt_attachment'   => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt_attachment')) {
            $receiptPath = $request->file('receipt_attachment')->store('cbe-event-expense-receipts', 'local');
        }

        DB::table('cbe_event_expenses')->insert([
            'expense_id'              => (string) Str::uuid(),
            'event_id'                 => $eventId,
            'category_id'              => $request->input('category_id') ?: null,
            'expense_date'             => $request->input('expense_date'),
            'description'              => $request->input('description'),
            'amount'                   => $request->input('amount'),
            'receipt_attachment_path'  => $receiptPath,
            'recorded_by'              => $agent->agent_id,
            'created_at'               => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('cbe.expenses.index', $eventId)->with('success', __('cbe_events.expense_saved'));
    }

    public function downloadReceipt(string $expenseId)
    {
        $agent = auth('agent')->user();
        $expense = DB::table('cbe_event_expenses as x')
            ->join('cbe_events as e', 'e.event_id', '=', 'x.event_id')
            ->where('x.expense_id', $expenseId)
            ->where('e.cbe_node_id', $this->resolveCbeNodeId($agent))
            ->select('x.*')
            ->firstOrFail();

        if (! $expense->receipt_attachment_path || ! Storage::disk('local')->exists($expense->receipt_attachment_path)) {
            abort(404, 'Attachment not found.');
        }

        return response()->file(Storage::disk('local')->path($expense->receipt_attachment_path));
    }
}
