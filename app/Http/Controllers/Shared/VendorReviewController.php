<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 12 Aug 2026 — per Chris: "GL,TL,Introducer in their dashboard menu,
// they can write review on vendor and product the feedback on the vendor
// rebate programs, and they can search and review all reviews for this
// vendor and products... follow the concept of facebook how they display
// the review with star rating, comments etc." Shared controller (same
// pattern as RebateOfferSearchController) — every role reaches this
// through the same route; only INTRODUCER/TEAM_LEADER/GROUP_LEADER can
// actually write a review (Admin views only, same as any other Admin
// oversight screen).
class VendorReviewController extends Controller
{
    public function index(Request $request, string $vendorId)
    {
        $agent = auth('agent')->user();
        $vendor = DB::table('vendors')->where('vendor_id', $vendorId)->first();
        abort_unless($vendor, 404);

        $productId = trim((string) $request->get('product_id', ''));

        $products = DB::table('products')->where('vendor_id', $vendorId)->where('is_active', true)->orderBy('product_name')->get(['product_id', 'product_name']);

        $query = DB::table('vendor_product_reviews as r')
            ->join('agents as a', 'r.reviewer_agent_id', '=', 'a.agent_id')
            ->leftJoin('products as p', 'r.product_id', '=', 'p.product_id')
            ->where('r.vendor_id', $vendorId)
            ->select('r.review_id', 'r.rating', 'r.comment', 'r.created_at', 'r.product_id', 'a.full_name as reviewer_name', 'a.role as reviewer_role', 'p.product_name');

        if ($productId !== '') {
            $query->where('r.product_id', $productId);
        }

        // Small page size — Facebook-style cards take real vertical
        // space (avatar, name, stars, comment), so this must stay small
        // to genuinely fit one screen with no scroll, Prev/Next doing
        // the rest even when a vendor has hundreds of reviews.
        $reviews = $query->orderByDesc('r.created_at')->paginate(4)->withQueryString();

        // Rating summary — average + count, computed over the SAME
        // filter (all reviews, or just this product if filtered).
        $summaryQuery = DB::table('vendor_product_reviews')->where('vendor_id', $vendorId);
        if ($productId !== '') {
            $summaryQuery->where('product_id', $productId);
        }
        $avgRating = (clone $summaryQuery)->avg('rating');
        $reviewCount = (clone $summaryQuery)->count();

        $canWrite = in_array($agent->role, ['INTRODUCER', 'TEAM_LEADER', 'GROUP_LEADER'], true);

        return view('vendor-reviews.index', compact('vendor', 'products', 'reviews', 'productId', 'avgRating', 'reviewCount', 'canWrite'));
    }

    public function store(Request $request, string $vendorId)
    {
        $agent = auth('agent')->user();
        abort_unless(in_array($agent->role, ['INTRODUCER', 'TEAM_LEADER', 'GROUP_LEADER'], true), 403, 'Only Introducers, Team Leaders, and Group Leaders can write vendor reviews.');

        $vendor = DB::table('vendors')->where('vendor_id', $vendorId)->first();
        abort_unless($vendor, 404);

        $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:1000'],
            'product_id' => ['nullable', 'uuid'],
        ]);

        $reviewId = (string) Str::uuid();
        DB::table('vendor_product_reviews')->insert([
            'review_id' => $reviewId,
            'vendor_id' => $vendorId,
            'product_id' => $request->filled('product_id') ? $request->product_id : null,
            'reviewer_agent_id' => $agent->agent_id,
            'rating' => (int) $request->rating,
            'comment' => $request->comment,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \App\Services\AuditService::logChange('vendor_product_reviews', $reviewId, 'VENDOR_REVIEW_SUBMITTED', null, ['vendor_id' => $vendorId, 'rating' => $request->rating], $agent->agent_id);

        return redirect()->route('vendor-reviews.index', ['vendorId' => $vendorId, 'product_id' => $request->product_id])->with('success', 'Your review has been posted.');
    }
}
