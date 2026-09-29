<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NumberToWordsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

// NEW 26 Aug 2026 — per Chris: "one of the function of accounting is to
// issue receipt upon a collection." Standalone printable view of a
// cbe_receipts row — opened from the Sponsor & Donor, Members, and
// Customers profile screens wherever a collection was made. Deliberately
// NOT wrapped in the normal dashboard layout (no sidebar/top bar) since
// this is meant to be printed or saved, same idea as a bill/invoice
// print view.
class AdminCbeReceiptController extends Controller
{
    public function show(Request $request)
    {
        $receiptId = $request->get('id');

        $receipt = DB::table('cbe_receipts as r')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'r.cbe_node_id')
            ->leftJoin('agents as a', 'a.agent_id', '=', 'r.issued_by')
            ->where('r.receipt_id', $receiptId)
            ->select('r.*', 'n.node_name', 'n.node_name_zh', 'a.full_name as issued_by_name')
            ->first();

        abort_if(! $receipt, 404, __('admin_cbe_directory.receipt_not_found'));

        $nodeName = (app()->getLocale() === 'zh' && $receipt->node_name_zh) ? $receipt->node_name_zh : $receipt->node_name;

        return view('admin.cbe-kpi.receipts.show', [
            'receipt' => $receipt,
            'nodeName' => $nodeName,
        ]);
    }

    // NEW 10 Sep 2026 (Phase 17) — printable Official Receipt PDF. Reuses
    // the exact same lookup as show() so the PDF always matches the
    // on-screen receipt; the Blade template is a separate,
    // table/block-based layout since dompdf does not support the
    // flexbox CSS used by the browser-print view.
    public function downloadPdf(Request $request)
    {
        $receiptId = $request->get('id');

        $receipt = DB::table('cbe_receipts as r')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'r.cbe_node_id')
            ->leftJoin('agents as a', 'a.agent_id', '=', 'r.issued_by')
            ->where('r.receipt_id', $receiptId)
            ->select('r.*', 'n.node_name', 'n.node_name_zh', 'a.full_name as issued_by_name')
            ->first();

        abort_if(! $receipt, 404, __('admin_cbe_directory.receipt_not_found'));

        $nodeName = (app()->getLocale() === 'zh' && $receipt->node_name_zh) ? $receipt->node_name_zh : $receipt->node_name;
        $amountWords = NumberToWordsService::ringgit((float) $receipt->amount);

        $pdf = Pdf::loadView('admin.cbe-kpi.receipts.pdf', [
            'receipt' => $receipt,
            'nodeName' => $nodeName,
            'amountWords' => $amountWords,
        ])->setPaper('a5');

        return $pdf->download('Receipt-'.$receipt->receipt_no.'.pdf');
    }
}
