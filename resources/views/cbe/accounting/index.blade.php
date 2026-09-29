@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.hub_page_title'))

{{-- ADDED 19 Sep 2026 -- per Chris: "the sidebar this screen should not
     display at all" -- this hub screen opts out of the shared GLADE
     sidebar entirely (layouts/glade.blade.php's hide-sidebar flag). --}}
@section('hide-sidebar', '1')

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:10px;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.hub_page_title') }}</div>
    </div>

    @if(!$hasNode)
    <div style="margin:auto; text-align:center; color:#9ca3af; font-size:11px; max-width:320px;">{{ __('cbe_records.no_node_note') }}</div>
    @else
    {{-- REBUILT 10 Sep 2026 (Task #398) — per Chris: this screen had grown
         to ~40 tiles in one flat list and was hard to navigate. Cut down
         to 7 tiles — AR, AP, GL, FA, Bank Reconciliation, AI Accounting
         Automation, Master Files — each opening its own sub-hub. Every
         program that used to live directly on this screen still exists;
         nothing was deleted, only regrouped. Same font/colour scheme,
         no icons, matches every other screen in the system. --}}
    <div style="flex:1; min-height:0; display:flex; flex-direction:column; gap:8px;">
        <div style="flex:1; display:flex; gap:8px;">
            <a href="{{ route('cbe.accounting.ar-hub') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_ar_hub') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_ar_hub_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.ap-hub') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_ap_hub') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_ap_hub_desc') }}</div>
            </a>
        </div>
        <div style="flex:1; display:flex; gap:8px;">
            <a href="{{ route('cbe.accounting.gl-hub') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_gl_hub') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_gl_hub_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.fa-hub') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_fa_hub') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_fa_hub_desc') }}</div>
            </a>
        </div>
        <div style="flex:1; display:flex; gap:8px;">
            <a href="{{ route('cbe.accounting.bank-recon-hub') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_bank_recon_hub') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_bank_recon_hub_desc') }}</div>
            </a>
            <a href="{{ route('cbe.ai-accounting.index') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_ai_accounting_hub') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_ai_accounting_hub_desc') }}</div>
            </a>
        </div>
        <div style="flex:1; display:flex; gap:8px;">
            <a href="{{ route('cbe.accounting.master-files-hub') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_master_files_hub') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_master_files_hub_desc') }}</div>
            </a>
            <div style="flex:1;"></div>
        </div>
    </div>
    @endif
</div>
@endsection
