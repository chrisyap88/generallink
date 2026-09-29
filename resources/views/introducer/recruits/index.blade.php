@extends('layouts.dashboard')

@section('title', __('introducer.my_recruits_title'))

@section('page-title')
{{ __('introducer.name_heading_possessive', ['name' => $agent->full_name, 'heading' => __('introducer.recruits_word')]) }} <span style="font-size:12px;color:#9ca3af;font-weight:400;margin-left:10px;">{{ $agent->agent_code }}</span>
@endsection

@push('styles')
<style>
.net-wrap{padding:6px 8px;display:flex;flex-direction:column;gap:5px;height:calc(100vh - 66px);box-sizing:border-box;overflow:hidden;}
.net-card{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:8px 12px;}
.net-table{width:100%;border-collapse:collapse;font-size:10px;}
.net-table th{padding:4px 6px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;background:#F7FAFC;white-space:nowrap;}
.net-table td{padding:4px 6px;line-height:1.3;border-bottom:1px solid #F7FAFC;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.net-badge{padding:1px 8px;border-radius:20px;font-size:10px;font-weight:600;}
.fsel{font-size:10px;padding:3px 8px;border-radius:6px;border:1px solid #B2EBF2;background:#fff;color:#0D5A8E;font-weight:600;cursor:pointer;height:26px;}
.fsearch{font-size:10px;padding:3px 8px;border-radius:6px;border:1px solid #B2EBF2;background:#fff;height:26px;outline:none;}
</style>
@endpush

@section('content')
<div class="net-wrap">

    <div class="net-card" style="flex:1;overflow:hidden;padding:8px 12px 50px 12px;display:flex;flex-direction:column;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap;">
            @php
                $headingText = match($filter) {
                    'active' => __('introducer.active_recruits_heading'),
                    'inactive' => __('introducer.inactive_recruits_heading'),
                    'new' => __('introducer.new_recruits_this_month_heading'),
                    default => __('introducer.recruits_word'),
                };
            @endphp
            <div style="font-size:11px;font-weight:700;color:#374151;">
                🏆 {{ __('introducer.name_heading_possessive', ['name' => $agent->full_name, 'heading' => $headingText]) }}
                @isset($totalSalesAll)
                <span style="font-size:10px;color:#38A169;font-weight:700;margin-left:10px;">{{ __('tl.total_sales_mtd_colon') }} RM {{ number_format($totalSalesAll, 2) }}</span>
                @endisset
            </div>

            <form method="GET" style="display:flex;align-items:center;gap:8px;margin-left:8px;flex-wrap:wrap;">
                <input type="hidden" name="month" value="{{ request('month', date('n')) }}">
                <input type="hidden" name="year" value="{{ request('year', date('Y')) }}">
                <button type="submit" name="show_all" value="1" style="background:#38A169;color:#fff;border:none;border-radius:5px;padding:3px 12px;font-size:10px;cursor:pointer;height:26px;font-weight:600;">{{ __('network.show_all_button') }}</button>
                <span style="color:#718096;font-size:10px;">{{ __('gl.or_filter_label') }}</span>
                <div style="display:flex;flex-direction:column;gap:1px;">
                    <span style="font-size:9px;color:#718096;">{{ __('introducer.recruit_name_label') }}</span>
                    <input type="text" name="search_name" class="fsearch" placeholder="{{ __('introducer.example_ali_placeholder') }}" value="{{ request('search_name') }}" style="width:130px;" list="recruit-names-list" autocomplete="off">
                    <datalist id="recruit-names-list">
                        @foreach($recruitList as $r)
                        <option value="{{ $r->full_name }}">
                        @endforeach
                    </datalist>
                </div>
                <div style="display:flex;flex-direction:column;gap:1px;">
                    <span style="font-size:9px;color:#718096;">{{ __('introducer.recruit_code_label') }}</span>
                    <input type="text" name="search_code" class="fsearch" placeholder="{{ __('introducer.example_code_placeholder') }}" value="{{ request('search_code') }}" style="width:100px;" list="recruit-codes-list" autocomplete="off">
                    <datalist id="recruit-codes-list">
                        @foreach($recruitList as $r)
                        <option value="{{ $r->agent_code }}">
                        @endforeach
                    </datalist>
                </div>
                <div style="display:flex;flex-direction:column;gap:1px;">
                    <span style="font-size:9px;color:#718096;">{{ __('network.status') }}</span>
                    <select name="filter" class="fsel" style="width:110px;">
                        <option value="">{{ __('network.all_status_option') }}</option>
                        <option value="active" {{ $filter==='active'?'selected':'' }}>{{ __('network.active') }}</option>
                        <option value="inactive" {{ $filter==='inactive'?'selected':'' }}>{{ __('network.inactive') }}</option>
                        <option value="new" {{ $filter==='new'?'selected':'' }}>{{ __('tl.new_this_month_option') }}</option>
                    </select>
                </div>
                <button type="submit" style="background:#1B9AE4;color:#fff;border:none;border-radius:5px;padding:3px 12px;font-size:10px;cursor:pointer;height:26px;margin-top:10px;">{{ __('network.go_button') }}</button>
                <a href="{{ route('introducer.recruits') }}?month={{ request('month', date('n')) }}&year={{ request('year', date('Y')) }}" style="background:#f3f4f6;border:1px solid #E2E8F0;color:#4A5568;border-radius:5px;padding:3px 8px;font-size:10px;text-decoration:none;margin-top:10px;">{{ __('network.clear') }}</a>
            </form>
        </div>

        @if($recruits->total() === 0 && !request()->hasAny(['show_all','search_name','search_code','filter']))
            <div style="text-align:center;color:#A0AEC0;padding:40px;font-size:12px;">
                {!! __('introducer.click_show_all_recruits_prompt') !!}
            </div>
        @elseif($recruits->isEmpty())
            <div style="text-align:center;color:#A0AEC0;padding:30px;font-size:12px;">
                @if($filter === 'active') {{ __('introducer.no_active_recruits_note') }}
                @elseif($filter === 'inactive') {{ __('introducer.all_recruits_active_note') }}
                @elseif($filter === 'new') {{ __('introducer.no_new_recruits_this_month_note') }}
                @else {{ __('introducer.no_recruits_found_note') }}
                @endif
            </div>
        @else
            <table class="net-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>{{ __('introducer.col_recruit') }}</th>
                        <th style="width:90px;white-space:nowrap;">{{ __('network.col_code') }}</th>
                        <th style="text-align:center;width:90px;">{{ __('gl.col_transactions') }}</th>
                        <th style="text-align:right;width:130px;">{{ __('network.col_sales_mtd') }}</th>
                        <th style="text-align:right;width:150px;">{{ __('tl.col_earning_income_mtd_rm') }}</th>
                        <th style="text-align:center;width:100px;">{{ __('network.col_joined') }}</th>
                        <th style="text-align:center;width:80px;">{{ __('network.col_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recruits as $recruit)
                    <tr style="cursor:pointer;" onclick="window.location='{{ route('introducer.recruits.transactions', $recruit->agent_id) }}?month={{ request('month', date('n')) }}&year={{ request('year', date('Y')) }}&search_name={{ request('search_name') }}&search_code={{ request('search_code') }}&filter={{ request('filter') }}&show_all={{ request('show_all') }}'" onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''">
                        <td style="color:#718096;">{{ $loop->iteration }}</td>
                        <td style="font-weight:600;color:#0D5A8E;">{{ $recruit->full_name }} →</td>
                        <td style="color:#9ca3af;white-space:nowrap;">{{ $recruit->agent_code }}</td>
                        <td style="text-align:center;">{{ $recruit->txn_count }}</td>
                        <td style="text-align:right;font-weight:700;">{{ number_format($recruit->sales_mtd, 2) }}</td>
                        <td style="text-align:right;font-weight:700;color:#38A169;">{{ number_format($recruit->earn_mtd, 2) }}</td>
                        <td style="text-align:center;font-size:10px;color:#9ca3af;">{{ \Carbon\Carbon::parse($recruit->created_at)->format('d M Y') }}</td>
                        <td style="text-align:center;">
                            <span class="net-badge" style="background:{{ $recruit->status==='ACTIVE'?'#C8E6C9':'#FFCDD2' }};color:{{ $recruit->status==='ACTIVE'?'#1B5E20':'#B71C1C' }};">{{ $recruit->status==='ACTIVE' ? __('network.active') : __('network.inactive') }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @if($recruits->hasPages())
            <div style="margin-top:8px;display:flex;align-items:center;justify-content:space-between;font-size:10px;">
                <span style="color:#9ca3af;">{{ __('gl.showing_x_to_y_of_z_records', ['first' => $recruits->firstItem(), 'last' => $recruits->lastItem(), 'total' => $recruits->total()]) }}</span>
                <div>{{ $recruits->links() }}</div>
            </div>
            @endif
        @endif
    </div>

    <a href="{{ route('introducer.dashboard') }}" style="position:fixed;bottom:16px;left:276px;background:#1565C0;color:#fff;border:none;border-radius:5px;padding:6px 18px;font-size:11px;font-weight:600;cursor:pointer;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,.2);line-height:1.4;text-decoration:none;display:inline-block;">{{ __('network.back_dashboard') }}</a>

</div>
@endsection
