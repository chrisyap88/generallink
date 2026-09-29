@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_events.contribution_add_button'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_events.contribution_add_button') }}</div>
            <div style="font-size:9.5px; color:#6b7280;">{{ $event->event_name }}</div>
        </div>
        <a href="{{ route('cbe.contributions.index', $event->event_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    @if($donors->isEmpty())
    <div style="background:#fff8e1; border-left:3px solid #f9a825; color:#7a5c00; border-radius:6px; padding:8px 10px; font-size:10.5px; margin-bottom:8px;">
        {{ __('cbe_events.no_donors_note') }} <a href="{{ route('cbe.donors.create', ['event' => $event->event_id]) }}" style="color:var(--gl-blue); font-weight:600;">{{ __('cbe_events.donor_add_button') }}</a>
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.contributions.store', $event->event_id) }}" enctype="multipart/form-data" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            <div style="flex:1; min-height:0; display:flex; gap:16px;">
                <div style="flex:1; display:flex; flex-direction:column; gap:8px;">
                    <div>
                        <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_events.field_donor') }}</label>
                        <div style="display:flex; gap:6px;">
                            <select name="donor_id" required style="flex:1; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                                <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                                @foreach($donors as $d)
                                <option value="{{ $d->donor_id }}" {{ old('donor_id') == $d->donor_id ? 'selected' : '' }}>{{ $d->donor_name }}</option>
                                @endforeach
                            </select>
                            <a href="{{ route('cbe.donors.create', ['event' => $event->event_id]) }}" style="background:#F7FAFC; border:1px solid #d1d5db; color:var(--gl-blue); text-decoration:none; border-radius:6px; padding:6px 10px; font-size:9.5px; font-weight:600; white-space:nowrap;">{{ __('cbe_events.new_donor_link') }}</a>
                        </div>
                    </div>
                    <div>
                        <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_events.field_contribution_type') }}</label>
                        <select name="contribution_type" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                            <option value="CASH_DONATION">{{ __('cbe_events.type_cash_donation') }}</option>
                            <option value="SPONSORSHIP">{{ __('cbe_events.type_sponsorship') }}</option>
                            <option value="IN_KIND_GIFT">{{ __('cbe_events.type_in_kind_gift') }}</option>
                            <option value="SERVICE_SPONSORSHIP">{{ __('cbe_events.type_service_sponsorship') }}</option>
                            <option value="AUCTION_ITEM">{{ __('cbe_events.type_auction_item') }}</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_events.field_item_description') }}</label>
                        <input type="text" name="item_description" value="{{ old('item_description') }}" maxlength="500" placeholder="{{ __('cbe_events.item_description_hint') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div style="display:flex; gap:8px;">
                        <div style="flex:1;">
                            <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_events.field_pledged_amount') }}</label>
                            <input type="number" step="0.01" min="0" name="pledged_amount" value="{{ old('pledged_amount') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        </div>
                        <div style="flex:1;">
                            <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_events.field_estimated_value') }}</label>
                            <input type="number" step="0.01" min="0" name="estimated_value" value="{{ old('estimated_value') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        </div>
                    </div>
                    <div style="font-size:8.5px; color:#9ca3af;">{{ __('cbe_events.amount_fields_hint') }}</div>
                </div>
                <div style="flex:1; display:flex; flex-direction:column; gap:8px;">
                    <div>
                        <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_events.field_status') }}</label>
                        <select name="status" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                            <option value="PLEDGED">{{ __('cbe_events.status_pledged') }}</option>
                            <option value="PARTIALLY_PAID">{{ __('cbe_events.status_partially_paid') }}</option>
                            <option value="FULLY_PAID">{{ __('cbe_events.status_fully_paid') }}</option>
                            <option value="RECEIVED">{{ __('cbe_events.status_received') }}</option>
                            <option value="CANCELLED">{{ __('cbe_events.status_cancelled') }}</option>
                        </select>
                        <div style="font-size:8.5px; color:#9ca3af; margin-top:2px;">{{ __('cbe_events.status_field_hint') }}</div>
                    </div>
                    <div>
                        <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_events.field_receipt_no') }}</label>
                        <input type="text" name="receipt_no" value="{{ old('receipt_no') }}" maxlength="50" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_events.field_receipt_attachment') }}</label>
                        <input type="file" name="receipt_attachment" accept=".jpg,.jpeg,.png,.pdf" style="width:100%; font-size:10px;">
                    </div>
                    <div style="flex:1; min-height:0; display:flex; flex-direction:column;">
                        <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_events.field_notes') }}</label>
                        <textarea name="notes" id="contribNotesField" maxlength="2000" style="width:100%; flex:1; min-height:0; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box; resize:none;">{{ old('notes') }}</textarea>
@include('partials.carolyn-write-assist', ['carolynBodyId' => 'contribNotesField', 'carolynType' => 'cbe_contribution_note'])
                    </div>
                </div>
            </div>
            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.contributions.index', $event->event_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
