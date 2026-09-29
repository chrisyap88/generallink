@extends('layouts.dashboard')

@section('page-title', __('vendor_reviews.page_title'))

@section('content')
@php
    $roleLabels = ['GROUP_LEADER' => __('vendor_reviews.role_gl'), 'TEAM_LEADER' => __('vendor_reviews.role_tl'), 'INTRODUCER' => __('vendor_reviews.role_introducer'), 'ADMIN' => __('vendor_reviews.role_admin')];
    $roundedAvg = $avgRating !== null ? round($avgRating, 1) : null;
@endphp
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px; box-sizing:border-box;">

    {{-- Header: vendor + average rating summary + product filter --}}
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:10px 16px; flex-shrink:0;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:10px;">
            <div style="min-width:0;">
                <div style="font-size:13px; font-weight:700; color:#1565C0;">⭐ {{ $vendor->vendor_name }} {{ __('vendor_reviews.feedback_reviews_suffix') }}</div>
                <div style="display:flex; align-items:center; gap:6px; margin-top:3px;">
                    @if($roundedAvg !== null)
                    <span style="font-size:13px; color:#f59e0b;">{!! str_repeat('★', round($avgRating)) . str_repeat('☆', 5 - round($avgRating)) !!}</span>
                    <span style="font-size:10.5px; color:#374151; font-weight:600;">{{ $roundedAvg }}/5</span>
                    <span style="font-size:9.5px; color:#9ca3af;">({{ $reviewCount }} {{ $reviewCount === 1 ? __('vendor_reviews.review_count_suffix') : __('vendor_reviews.review_count_suffix_plural') }})</span>
                    @else
                    <span style="font-size:10.5px; color:#9ca3af;">{{ __('vendor_reviews.no_reviews_yet_note') }}</span>
                    @endif
                </div>
            </div>
            <a href="{{ route('rebate-offers.search') }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:5px 12px; font-size:10px; font-weight:600; white-space:nowrap;">{{ __('vendor_reviews.back_to_rebate_offers_link') }}</a>
        </div>
        @if($products->isNotEmpty())
        <form method="GET" action="{{ route('vendor-reviews.index', $vendor->vendor_id) }}" style="margin-top:8px;">
            <select name="product_id" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:6px; padding:4px 8px; font-size:10px; max-width:260px;">
                <option value="">{{ __('vendor_reviews.all_products_option') }}</option>
                @foreach($products as $p)
                <option value="{{ $p->product_id }}" {{ $productId === $p->product_id ? 'selected' : '' }}>{{ $p->product_name }}</option>
                @endforeach
            </select>
        </form>
        @endif
    </div>

    {{-- Write a review — NEW 12 Aug 2026, per Chris: "GL,TL,Introducer in
         their dashboard menu, they can write review." Admin sees this
         section hidden — view only, same as any Admin oversight screen. --}}
    @if($canWrite)
    <div style="background:#F5F9FF; border:1px solid #DBEAFE; border-radius:10px; padding:8px 14px; flex-shrink:0;">
        <form method="POST" action="{{ route('vendor-reviews.store', $vendor->vendor_id) }}" style="display:flex; flex-direction:column; gap:6px;">
            @csrf
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <span style="font-size:10.5px; font-weight:600; color:#1e3a5f;">{{ __('vendor_reviews.your_rating_label') }}</span>
                <div id="starPicker" style="display:flex; gap:2px;">
                    @for($i = 1; $i <= 5; $i++)
                    <span class="starPickBtn" data-value="{{ $i }}" onclick="pickStar({{ $i }})" style="cursor:pointer; font-size:16px; color:#d1d5db;">★</span>
                    @endfor
                </div>
                <input type="hidden" name="rating" id="ratingInput" value="" required>
                @if($products->isNotEmpty())
                <select name="product_id" style="border:1px solid #d1d5db; border-radius:6px; padding:3px 6px; font-size:10px;">
                    <option value="">{{ __('vendor_reviews.general_vendor_feedback_option') }}</option>
                    @foreach($products as $p)
                    <option value="{{ $p->product_id }}" {{ $productId === $p->product_id ? 'selected' : '' }}>{{ $p->product_name }}</option>
                    @endforeach
                </select>
                @endif
            </div>
            <div style="display:flex; gap:6px; align-items:flex-end;">
                <textarea name="comment" id="vendorReviewComment" required maxlength="1000" placeholder="{{ __('vendor_reviews.comment_placeholder') }}" style="flex:1; height:42px; resize:none; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;"></textarea>
@include('partials.carolyn-write-assist', ['carolynBodyId' => 'vendorReviewComment', 'carolynType' => 'vendor_review_comment'])
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 16px; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('vendor_reviews.post_review_button') }}</button>
            </div>
        </form>
    </div>
    @endif

    {{-- Review list — Facebook-style cards. NEW 12 Aug 2026 per Chris:
         "it will have a lot of records... you must have prev and next
         and make sure no scroll." Small page size (4) so cards always
         fit; Prev/Next carries the rest, never inner scroll on the page
         itself. Each comment box has its OWN tiny internal scroll only
         if unusually long, so long feedback is never silently cut off. --}}
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        @if($reviews->isEmpty())
        <div style="flex:1; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:12px; text-align:center;">
            <div>
                <div style="font-size:32px; margin-bottom:8px;">💬</div>
                <div>{{ $productId ? __('vendor_reviews.no_reviews_for_product') : __('vendor_reviews.no_reviews_for_vendor') }} {{ __('vendor_reviews.no_reviews_yet_suffix') }}</div>
            </div>
        </div>
        @else
        <div style="flex:1; min-height:0; overflow:hidden; padding:10px 12px; display:flex; flex-direction:column; gap:8px;">
            @foreach($reviews as $r)
            @php
                $initials = collect(explode(' ', trim($r->reviewer_name)))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('');
            @endphp
            <div style="border:1px solid #f3f4f6; border-radius:9px; padding:9px 12px; display:flex; gap:10px; align-items:flex-start;">
                <div style="flex-shrink:0; width:32px; height:32px; border-radius:50%; background:#1565C0; color:#fff; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700;">{{ strtoupper($initials) }}</div>
                <div style="flex:1; min-width:0;">
                    <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                        <span style="font-size:11px; font-weight:700; color:#111827;">{{ $r->reviewer_name }}</span>
                        <span style="background:#eef2ff; color:#4338ca; font-size:8.5px; font-weight:700; padding:1px 7px; border-radius:10px;">{{ $roleLabels[$r->reviewer_role] ?? $r->reviewer_role }}</span>
                        @if($r->product_name)
                        <span style="background:#e0f2fe; color:#0369a1; font-size:8.5px; font-weight:600; padding:1px 7px; border-radius:10px;">{{ $r->product_name }}</span>
                        @endif
                        <span style="font-size:9px; color:#9ca3af;">{{ \Carbon\Carbon::parse($r->created_at)->format('d M Y, h:ia') }}</span>
                    </div>
                    <div style="font-size:12px; color:#f59e0b; margin:2px 0;">{!! str_repeat('★', $r->rating) . str_repeat('☆', 5 - $r->rating) !!}</div>
                    <div style="font-size:10.5px; color:#374151; line-height:1.5; word-break:break-word; max-height:52px; overflow-y:auto;">{{ $r->comment }}</div>
                </div>
            </div>
            @endforeach
        </div>
        <div style="padding:8px 12px; border-top:1px solid #f3f4f6; flex-shrink:0; display:flex; align-items:center; justify-content:space-between;">
            @if($reviews->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $reviews->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:11px; color:#6b7280;">{!! __('vendor_reviews.showing_x_to_y_of_z_reviews', ['first' => '<strong>'.$reviews->firstItem().'</strong>', 'last' => '<strong>'.$reviews->lastItem().'</strong>', 'total' => '<strong>'.$reviews->total().'</strong>', 'current' => $reviews->currentPage(), 'lastpage' => $reviews->lastPage()]) !!}</span>
            @if($reviews->hasMorePages())
                <a href="{{ $reviews->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @endif
    </div>
</div>

<script>
function pickStar(value) {
    document.getElementById('ratingInput').value = value;
    document.querySelectorAll('.starPickBtn').forEach(function (el) {
        el.style.color = parseInt(el.getAttribute('data-value'), 10) <= value ? '#f59e0b' : '#d1d5db';
    });
}
</script>
@endsection
