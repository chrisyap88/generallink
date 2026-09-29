<?php

namespace App\Http\Controllers\Cbe;

use App\Http\Controllers\Cbe\Concerns\ResolvesCbeActiveNode;
use App\Http\Controllers\Controller;
use App\Services\CbeReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// NEW 22 Aug 2026 — per Chris: the Official Donation Register itself —
// recording a contribution (cash/sponsorship/in-kind/service/auction)
// against an Event and a Donor, tracking outstanding pledges via partial
// payments, and issuing/attaching receipts.
//
// UPDATED 28 Aug 2026 — per Chris: "develop all the program, all the
// program that label with the word soon." EventController is now
// Admin-capable (ResolvesCbeActiveNode), so this controller — reached
// FROM an Event — needs the same node resolution or Admin would hit a
// 404 the moment they went past the Events list.
class ContributionController extends Controller
{
    use ResolvesCbeActiveNode;

    private function eventFor(string $eventId)
    {
        $agent = auth('agent')->user();
        return DB::table('cbe_events')->where('event_id', $eventId)->where('cbe_node_id', $this->resolveCbeNodeId($agent))->firstOrFail();
    }

    public function index(Request $request, string $eventId)
    {
        $event = $this->eventFor($eventId);

        $contributions = DB::table('cbe_contributions as c')
            ->join('cbe_donors as d', 'd.donor_id', '=', 'c.donor_id')
            ->where('c.event_id', $eventId)
            ->select('c.*', 'd.donor_name')
            ->orderByDesc('c.created_at')
            ->paginate(8, ['*'], 'conPage');

        return view('cbe.contributions.index', compact('event', 'contributions'));
    }

    public function create(string $eventId)
    {
        $agent = auth('agent')->user();
        $event = $this->eventFor($eventId);

        $donors = DB::table('cbe_donors')->where('cbe_node_id', $this->resolveCbeNodeId($agent))->orderBy('donor_name')->get();

        return view('cbe.contributions.create', compact('event', 'donors'));
    }

    public function store(Request $request, string $eventId)
    {
        $agent = auth('agent')->user();
        $event = $this->eventFor($eventId);

        $request->validate([
            'donor_id'          => ['required', 'uuid', 'exists:cbe_donors,donor_id'],
            'contribution_type'  => ['required', 'in:CASH_DONATION,SPONSORSHIP,IN_KIND_GIFT,SERVICE_SPONSORSHIP,AUCTION_ITEM'],
            'item_description'   => ['nullable', 'string', 'max:500'],
            'pledged_amount'     => ['nullable', 'numeric', 'min:0'],
            'estimated_value'    => ['nullable', 'numeric', 'min:0'],
            'status'             => ['required', 'in:PLEDGED,PARTIALLY_PAID,FULLY_PAID,RECEIVED,CANCELLED'],
            'notes'              => ['nullable', 'string', 'max:2000'],
            'receipt_attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt_attachment')) {
            $receiptPath = $request->file('receipt_attachment')->store('cbe-contribution-receipts', 'local');
        }

        // If marked FULLY_PAID or RECEIVED straight away, treat the
        // pledged/estimated value as received in full from the start —
        // saves a redundant separate payment entry for the common case
        // of a donation handed over complete on the spot.
        $status = $request->input('status');
        $receivedAmount = 0;
        if (in_array($status, ['FULLY_PAID', 'RECEIVED'], true)) {
            $receivedAmount = (float) ($request->input('pledged_amount') ?: $request->input('estimated_value') ?: 0);
        }

        $donor = DB::table('cbe_donors')->where('donor_id', $request->input('donor_id'))->first();

        $contributionId = (string) Str::uuid();
        DB::table('cbe_contributions')->insert([
            'contribution_id'        => $contributionId,
            'event_id'                => $eventId,
            'donor_id'                 => $request->input('donor_id'),
            'contribution_type'        => $request->input('contribution_type'),
            'item_description'         => $request->input('item_description'),
            'pledged_amount'           => $request->input('pledged_amount') ?: null,
            'received_amount'          => $receivedAmount,
            'estimated_value'          => $request->input('estimated_value') ?: null,
            'status'                   => $status,
            'receipt_no'               => null,
            'receipt_issued_at'        => null,
            'receipt_attachment_path'  => $receiptPath,
            'notes'                    => $request->input('notes'),
            'recorded_by'              => $agent->agent_id,
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        // NEW 26 Aug 2026 — per Chris: no more manually typed receipt
        // number. The system issues the official receipt itself the
        // moment money is actually received — see CbeReceiptService.
        if ($receivedAmount > 0) {
            $typeLabel = ucwords(str_replace('_', ' ', strtolower($request->input('contribution_type'))));
            $itemDesc = trim((string) $request->input('item_description'));
            $receipt = CbeReceiptService::issue(
                $event->cbe_node_id,
                'DONATION',
                $contributionId,
                $donor->donor_name ?? 'Donor',
                $typeLabel.($itemDesc ? ' — '.$itemDesc : ''),
                $receivedAmount,
                $agent->agent_id
            );
            if ($receipt) {
                DB::table('cbe_contributions')->where('contribution_id', $contributionId)->update([
                    'receipt_no' => $receipt->receipt_no,
                    'receipt_issued_at' => $receipt->issued_at,
                    'updated_at' => now(),
                ]);
            }
        }

        return redirect()->route('cbe.contributions.index', $eventId)->with('success', __('cbe_events.contribution_saved'));
    }

    public function show(string $contributionId)
    {
        $agent = auth('agent')->user();
        $contribution = DB::table('cbe_contributions as c')
            ->join('cbe_donors as d', 'd.donor_id', '=', 'c.donor_id')
            ->join('cbe_events as e', 'e.event_id', '=', 'c.event_id')
            ->where('c.contribution_id', $contributionId)
            ->where('e.cbe_node_id', $this->resolveCbeNodeId($agent))
            ->select('c.*', 'd.donor_name', 'e.event_name', 'e.event_id')
            ->firstOrFail();

        $payments = DB::table('cbe_contribution_payments')->where('contribution_id', $contributionId)->orderByDesc('payment_date')->get();

        return view('cbe.contributions.show', compact('contribution', 'payments'));
    }

    public function addPayment(Request $request, string $contributionId)
    {
        $agent = auth('agent')->user();
        $contribution = DB::table('cbe_contributions as c')
            ->join('cbe_events as e', 'e.event_id', '=', 'c.event_id')
            ->where('c.contribution_id', $contributionId)
            ->where('e.cbe_node_id', $this->resolveCbeNodeId($agent))
            ->select('c.*', 'e.cbe_node_id')
            ->firstOrFail();

        $request->validate([
            'payment_date'    => ['required', 'date'],
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'payment_method'   => ['nullable', 'string', 'max:50'],
            'reference_no'     => ['nullable', 'string', 'max:100'],
        ]);

        $donorName = DB::table('cbe_donors')->where('donor_id', $contribution->donor_id)->value('donor_name') ?? 'Donor';

        DB::transaction(function () use ($request, $contribution, $contributionId, $agent, $donorName) {
            $paymentId = (string) Str::uuid();
            DB::table('cbe_contribution_payments')->insert([
                'payment_id'      => $paymentId,
                'contribution_id'  => $contributionId,
                'payment_date'     => $request->input('payment_date'),
                'amount'           => $request->input('amount'),
                'payment_method'   => $request->input('payment_method'),
                'reference_no'     => $request->input('reference_no'),
                'recorded_by'      => $agent->agent_id,
                'created_at'       => now(), 'updated_at' => now(),
            ]);

            $newReceived = (float) $contribution->received_amount + (float) $request->input('amount');
            $newStatus = $contribution->status;
            if ($contribution->pledged_amount && $newReceived >= (float) $contribution->pledged_amount) {
                $newStatus = 'FULLY_PAID';
            } elseif ($newReceived > 0) {
                $newStatus = 'PARTIALLY_PAID';
            }

            DB::table('cbe_contributions')->where('contribution_id', $contributionId)->update([
                'received_amount' => $newReceived,
                'status' => $newStatus,
                'updated_at' => now(),
            ]);

            // NEW 26 Aug 2026 — per Chris: every actual collection issues
            // its own receipt, including a follow-up payment against an
            // earlier pledge (a receipt per payment, not just once).
            CbeReceiptService::issue(
                $contribution->cbe_node_id,
                'DONATION',
                $contributionId,
                $donorName,
                'Donation payment'.($request->input('reference_no') ? ' (Ref: '.$request->input('reference_no').')' : ''),
                (float) $request->input('amount'),
                $agent->agent_id
            );
        });

        return back()->with('success', __('cbe_events.payment_saved'));
    }

    public function downloadReceipt(string $contributionId)
    {
        $agent = auth('agent')->user();
        $contribution = DB::table('cbe_contributions as c')
            ->join('cbe_events as e', 'e.event_id', '=', 'c.event_id')
            ->where('c.contribution_id', $contributionId)
            ->where('e.cbe_node_id', $this->resolveCbeNodeId($agent))
            ->select('c.*')
            ->firstOrFail();

        if (! $contribution->receipt_attachment_path || ! Storage::disk('local')->exists($contribution->receipt_attachment_path)) {
            abort(404, 'Attachment not found.');
        }

        return response()->file(Storage::disk('local')->path($contribution->receipt_attachment_path));
    }
}
