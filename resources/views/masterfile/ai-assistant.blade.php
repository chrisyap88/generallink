@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('masterfile.ai_assistant_hub_title'))

@section('content')

{{-- NEW 19 Sep 2026 -- per Chris's uploaded spec
     (AI_Master_Data_and_Transaction_Assistant_Specification.docx): the
     top item in Master File Maintenance. Chris confirmed the build
     order: Phase 1 is Chart of Accounts only ("COA Chat" -- see
     CbeAccountingController::coaChatForm()); Supplier, Customer, Bank
     Account, Fixed Asset, Cost Centre and Tax Code are shown here as
     "Coming Soon" tiles (not yet clickable) so the roadmap is visible
     without pretending they're ready. Part 2 of the spec (the AI also
     auto-creating transactions, e.g. an Expense Claim) is deliberately
     out of scope for now -- set aside per Chris. --}}
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('masterfile.ai_assistant_hub_title') }}</div>
        <a href="{{ route('admin.dashboard') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('masterfile.dashboard_link') }}</a>
    </div>

    <div style="flex-shrink:0; font-size:10.5px; color:#6b7280; margin-bottom:10px; max-width:640px;">{{ __('masterfile.ai_assistant_hub_intro') }}</div>

    <div style="flex:1; min-height:0; display:flex; flex-direction:column; gap:8px;">
        <div style="flex:1; display:flex; gap:8px;">
            {{-- CHANGED 19 Sep 2026 -- per Chris: "forget carolyn use
                 another ai agents call AI Accountant" — opens the
                 dedicated AI Accountant bubble (top-left) instead of a
                 separate screen, with a starter phrase pre-filled for
                 the user to review and send themselves. --}}
            <a href="#" onclick="event.preventDefault(); aiAccountantOpenWithHint({{ json_encode(__('coa_chat.hint_prefill')) }});" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:12px; display:flex; flex-direction:column; text-decoration:none; cursor:pointer;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('masterfile.ai_assistant_tile_coa') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('masterfile.ai_assistant_tile_coa_desc') }}</div>
            </a>
            <div style="flex:1; background:#f5f6f7; border:1px solid #e2e5e8; border-left:3px solid #c4c9d0; border-radius:0 8px 8px 0; padding:12px; display:flex; flex-direction:column;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div style="font-size:10.5px; font-weight:700; color:#9aa4ab;">{{ __('masterfile.ai_assistant_tile_supplier') }}</div>
                    <div style="font-size:7.5px; font-weight:700; color:#9aa4ab; background:#e9ecee; border-radius:10px; padding:2px 7px; white-space:nowrap;">{{ __('masterfile.ai_assistant_coming_soon') }}</div>
                </div>
            </div>
        </div>
        <div style="flex:1; display:flex; gap:8px;">
            <div style="flex:1; background:#f5f6f7; border:1px solid #e2e5e8; border-left:3px solid #c4c9d0; border-radius:0 8px 8px 0; padding:12px; display:flex; flex-direction:column;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div style="font-size:10.5px; font-weight:700; color:#9aa4ab;">{{ __('masterfile.ai_assistant_tile_customer') }}</div>
                    <div style="font-size:7.5px; font-weight:700; color:#9aa4ab; background:#e9ecee; border-radius:10px; padding:2px 7px; white-space:nowrap;">{{ __('masterfile.ai_assistant_coming_soon') }}</div>
                </div>
            </div>
            <div style="flex:1; background:#f5f6f7; border:1px solid #e2e5e8; border-left:3px solid #c4c9d0; border-radius:0 8px 8px 0; padding:12px; display:flex; flex-direction:column;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div style="font-size:10.5px; font-weight:700; color:#9aa4ab;">{{ __('masterfile.ai_assistant_tile_bank_account') }}</div>
                    <div style="font-size:7.5px; font-weight:700; color:#9aa4ab; background:#e9ecee; border-radius:10px; padding:2px 7px; white-space:nowrap;">{{ __('masterfile.ai_assistant_coming_soon') }}</div>
                </div>
            </div>
        </div>
        <div style="flex:1; display:flex; gap:8px;">
            <div style="flex:1; background:#f5f6f7; border:1px solid #e2e5e8; border-left:3px solid #c4c9d0; border-radius:0 8px 8px 0; padding:12px; display:flex; flex-direction:column;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div style="font-size:10.5px; font-weight:700; color:#9aa4ab;">{{ __('masterfile.ai_assistant_tile_fixed_asset') }}</div>
                    <div style="font-size:7.5px; font-weight:700; color:#9aa4ab; background:#e9ecee; border-radius:10px; padding:2px 7px; white-space:nowrap;">{{ __('masterfile.ai_assistant_coming_soon') }}</div>
                </div>
            </div>
            <div style="flex:1; background:#f5f6f7; border:1px solid #e2e5e8; border-left:3px solid #c4c9d0; border-radius:0 8px 8px 0; padding:12px; display:flex; flex-direction:column;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div style="font-size:10.5px; font-weight:700; color:#9aa4ab;">{{ __('masterfile.ai_assistant_tile_cost_centre') }}</div>
                    <div style="font-size:7.5px; font-weight:700; color:#9aa4ab; background:#e9ecee; border-radius:10px; padding:2px 7px; white-space:nowrap;">{{ __('masterfile.ai_assistant_coming_soon') }}</div>
                </div>
            </div>
        </div>
        <div style="flex:1; display:flex; gap:8px;">
            <div style="flex:1; background:#f5f6f7; border:1px solid #e2e5e8; border-left:3px solid #c4c9d0; border-radius:0 8px 8px 0; padding:12px; display:flex; flex-direction:column;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div style="font-size:10.5px; font-weight:700; color:#9aa4ab;">{{ __('masterfile.ai_assistant_tile_tax_code') }}</div>
                    <div style="font-size:7.5px; font-weight:700; color:#9aa4ab; background:#e9ecee; border-radius:10px; padding:2px 7px; white-space:nowrap;">{{ __('masterfile.ai_assistant_coming_soon') }}</div>
                </div>
            </div>
            <div style="flex:1;"></div>
        </div>
    </div>
</div>
@endsection
