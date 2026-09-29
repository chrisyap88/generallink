@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_cbe_directory.customer_profile_title'))

@section('content')

<style>
.cbd-box{background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; box-shadow:0 2px 8px rgba(21,101,192,0.08); display:flex; flex-direction:column; overflow:hidden; box-sizing:border-box; padding:10px 12px;}
.cbd-field{display:flex; flex-direction:column; gap:3px;}
.cbd-field label{font-size:8px; font-weight:700; color:#6b7280; text-transform:uppercase;}
.cbd-field input, .cbd-field select{font-family:'Poppins',sans-serif; font-size:9px; color:#263238; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; box-sizing:border-box; width:100%;}
.cbd-btn{background:var(--gl-blue); color:#fff; border:none; border-radius:5px; padding:6px 16px; font-size:9px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;}
.cbd-btn-outline{background:var(--gl-light); color:var(--gl-blue); border:1px solid var(--gl-cyan2); border-radius:5px; padding:5px 12px; font-size:8.5px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;}
.cbd-info-row{display:flex; gap:6px; font-size:9px; padding:3px 0;}
.cbd-info-row span:first-child{color:#6b7280; flex:0 0 100px;}
.cbd-info-row span:last-child{color:#263238; font-weight:600;}
.cbd-stat{text-align:center; flex:1;}
.cbd-stat .v{font-size:16px; font-weight:700; color:var(--gl-blue);}
.cbd-stat .l{font-size:7.5px; color:#6b7280;}
.cbd-row{display:flex; align-items:center; padding:5px 10px; border-bottom:1px solid #eef2f7; font-size:8.5px;}
.cbd-row:last-child{border-bottom:none;}
.cbd-pg-btn{background:#0D5A8E; color:#fff; text-decoration:none; border-radius:5px; padding:4px 12px; font-size:9px; font-weight:700;}
.cbd-pg-btn-disabled{background:#f3f4f6; color:#9ca3af; border-radius:5px; padding:4px 12px; font-size:9px; font-weight:700;}
.cbd-back-btn{display:inline-flex; align-items:center; gap:4px; background:var(--gl-blue); color:#fff; text-decoration:none; font-size:8.5px; font-weight:700; padding:5px 14px; border-radius:5px;}
.cbd-tabtoggle{background:none; border:none; font-family:'Poppins',sans-serif; font-size:9px; font-weight:700; color:#6b7280; padding:8px 12px; cursor:pointer; border-bottom:2px solid transparent;}
.cbd-tabtoggle.active{color:var(--gl-blue); border-bottom-color:var(--gl-blue);}
.cbd-fixed-prev, .cbd-fixed-next{position:fixed; bottom:14px; background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:8px 20px; font-size:9.5px; font-weight:700; text-decoration:none; z-index:50;}
.cbd-fixed-prev{left:276px;}
.cbd-fixed-next{right:16px;}
.cbd-fixed-prev-disabled, .cbd-fixed-next-disabled{position:fixed; bottom:14px; background:#1565C0; color:#fff; border-radius:20px; padding:8px 20px; font-size:9.5px; font-weight:700; z-index:50;}
.cbd-fixed-prev-disabled{left:276px;}
.cbd-fixed-next-disabled{right:16px;}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; gap:6px;">

    <div style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between; gap:10px;">
        <div style="min-width:0;">
            <div style="font-size:13px; font-weight:700; color:#263238; white-space:nowrap;">{{ __('admin_cbe_directory.customer_profile_title') }}</div>
            <div style="font-size:9px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $nodePrimary }}{{ $nodeSecondary ? ' ('.$nodeSecondary.')' : '' }}</div>
        </div>
        @if($browsing ?? false)
        <div style="flex-shrink:0; display:flex; align-items:center; gap:6px; background:#E3F2FD; border-radius:6px; padding:5px 8px;">
            <span style="font-size:8.5px; color:#0d3c72; font-weight:700; white-space:nowrap;">{{ __('admin_cbe_directory.browse_record_of', ['pos' => $resultPos, 'total' => $resultTotal]) }}</span>
            <a href="{{ $modifySearchUrl }}" class="cbd-btn-outline">{{ __('admin_cbe_directory.btn_modify_search') }}</a>
        </div>
        @endif
    </div>

    {{-- NEW 27 Aug 2026 — per Chris: "if i already search the donor in
    the first tap do i need to re search again?" Merged this profile's
    own Profile/Event Participation/Appointments tabs into the SAME row
    as the persistent bar — Participation/Appointments now show THIS
    customer's own already-loaded history via instant JS toggle. --}}
    @include('admin.cbe-kpi.partials.persistent-tabs', [
        'activeTab' => 'customers',
        'primaryTabKey' => 'customers',
        'primaryTabLabel' => __('admin_cbe_directory.customers_title'),
        'primaryTabRoute' => 'admin.cbe-kpi.customers',
        'localTabs' => [
            ['label' => __('admin_cbe_directory.subtab_profile'), 'key' => 'profile', 'active' => true],
        ],
        'participationLocalKey' => 'participation',
        'appointmentsLocalKey' => 'appointments',
        'participationCount' => $participationCount,
        'appointmentCount' => $appointmentCount,
        'switchFn' => 'cbdSwitchTab',
    ])

    {{-- NEW 26 Aug 2026 — per Chris (compulsory master-spec rule, Sec.
    37.13.1 / 39.3): Prev/Next must be fixed blue corner buttons,
    bottom-left / bottom-right — NOT a pill up top. --}}
    @if($browsing ?? false)
        @if($prevUrl)<a href="{{ $prevUrl }}" class="cbd-fixed-prev">← {{ __('admin_cbe_kpi.prev') }}</a>@else<span class="cbd-fixed-prev-disabled">← {{ __('admin_cbe_kpi.prev') }}</span>@endif
        @if($nextUrl)<a href="{{ $nextUrl }}" class="cbd-fixed-next">{{ __('admin_cbe_kpi.next') }} →</a>@else<span class="cbd-fixed-next-disabled">{{ __('admin_cbe_kpi.next') }} →</span>@endif
    @endif

    {{-- NEW 26 Aug 2026 — Profile info moved INTO its own tab (matching
    Members) so Event Participation / Appointments tabs get near-full
    screen height, no cramped internal scrolling. --}}
    <div class="cbd-box" style="flex:1; min-height:0; padding:0;">
        <div id="panel-profile" style="flex:1; min-height:0; display:flex; flex-direction:column; overflow-y:auto; padding:12px;">
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <div class="cbd-box" style="flex:1.6; min-width:220px;">
                    <div style="font-size:12px; font-weight:700; color:#263238; margin-bottom:4px;">{{ $customer->full_name }}</div>
                    <div class="cbd-info-row"><span>{{ __('admin_cbe_directory.search_customer_phone') }}</span><span>{{ $customer->phone ?: '—' }}</span></div>
                    <div class="cbd-info-row"><span>{{ __('admin_cbe_directory.search_customer_email') }}</span><span>{{ $customer->email ?: '—' }}</span></div>
                    <div class="cbd-info-row"><span>{{ __('admin_cbe_directory.search_customer_city') }}</span><span>{{ $customer->city ?: '—' }}</span></div>
                    <div class="cbd-info-row"><span>{{ __('admin_cbe_directory.search_customer_state') }}</span><span>{{ $customer->state ?: '—' }}</span></div>
                    <div class="cbd-info-row"><span>{{ __('admin_cbe_directory.search_customer_postcode') }}</span><span>{{ $customer->postcode ?: '—' }}</span></div>
                    <div class="cbd-info-row"><span>{{ __('admin_cbe_directory.search_customer_address') }}</span><span>{{ $customer->address ?: '—' }}</span></div>
                </div>
                <div class="cbd-box" style="flex:1; min-width:180px; flex-direction:row; align-items:center;">
                    <div class="cbd-stat">
                        <div class="v">{{ number_format($participationCount) }}</div>
                        <div class="l">{{ __('admin_cbe_directory.participation_count') }}</div>
                    </div>
                    <div class="cbd-stat">
                        <div class="v">RM {{ number_format($participationTotal, 2) }}</div>
                        <div class="l">{{ __('admin_cbe_directory.participation_total_amount') }}</div>
                    </div>
                    <div class="cbd-stat">
                        <div class="v">{{ number_format($appointmentCount) }}</div>
                        <div class="l">{{ __('admin_cbe_directory.appointment_count') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div id="panel-participation" style="flex:1; min-height:0; display:none; flex-direction:column;">
            <div style="flex-shrink:0; padding:8px 12px; border-bottom:1px solid #eef2f7;">
                @if(count($events) === 0)
                <div style="font-size:8.5px; color:#94A3B8;">{{ __('admin_cbe_directory.no_events_for_node') }}</div>
                @else
                <form method="POST" action="{{ route('admin.cbe-kpi.customers.store-participation') }}">
                    @csrf
                    <input type="hidden" name="node" value="{{ $node->node_id }}">
                    <input type="hidden" name="customer_id" value="{{ $customer->customer_id }}">
                    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end;">
                        <div class="cbd-field" style="flex:1.3; min-width:140px;">
                            <label>{{ __('admin_cbe_directory.form_event') }}</label>
                            <select name="event_id" required>
                                <option value="">{{ __('admin_cbe_directory.form_select_event') }}</option>
                                @foreach($events as $ev)
                                @php $evName = (app()->getLocale()==='zh' && $ev->event_name_zh) ? $ev->event_name_zh : $ev->event_name; @endphp
                                <option value="{{ $ev->event_id }}">{{ $evName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="cbd-field" style="flex:1.3; min-width:140px;">
                            <label>{{ __('admin_cbe_directory.form_item_name') }}</label>
                            <input type="text" name="item_name" required>
                        </div>
                        <div class="cbd-field" style="flex:0.5; min-width:60px;">
                            <label>{{ __('admin_cbe_directory.form_quantity') }}</label>
                            <input type="number" name="quantity" value="1" min="1">
                        </div>
                        <div class="cbd-field" style="flex:0.7; min-width:80px;">
                            <label>{{ __('admin_cbe_directory.form_amount') }}</label>
                            <input type="number" step="0.01" name="amount_paid" value="0">
                        </div>
                        <div class="cbd-field" style="flex:0.7; min-width:100px;">
                            <label>{{ __('admin_cbe_directory.form_paid_date') }}</label>
                            <input type="date" name="paid_at" value="{{ now()->toDateString() }}">
                        </div>
                        <button type="submit" class="cbd-btn">{{ __('admin_cbe_directory.btn_save_participation') }}</button>
                    </div>
                    @if(session('cbe_participation_saved'))
                    <span style="font-size:8.5px; color:#2e7d32; font-weight:700;">{{ __('admin_cbe_directory.participation_saved') }}</span>
                    @endif
                    @error('item_name')<span style="font-size:8.5px; color:#c62828;">{{ $message }}</span>@enderror
                </form>
                @endif
            </div>
            <div style="flex:1; min-height:0; overflow-y:auto;">
                @forelse($history as $h)
                @php $evName = (app()->getLocale()==='zh' && $h->event_name_zh) ? $h->event_name_zh : $h->event_name; @endphp
                <div class="cbd-row">
                    <span style="flex:1.4; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:#263238; font-weight:600;">{{ $evName }}</span>
                    <span style="flex:1.4; color:#6b7280;">{{ $h->item_name }}</span>
                    <span style="flex:0.5; color:#6b7280;">×{{ $h->quantity }}</span>
                    <span style="flex:0.8; color:#0d3c72; font-weight:600;">RM {{ number_format($h->amount_paid, 2) }}</span>
                    <span style="flex:0.8; color:#94A3B8;">{{ $h->paid_at ? \Carbon\Carbon::parse($h->paid_at)->format('d M Y') : '—' }}</span>
                    @if($h->receipt_id)
                    <a href="{{ route('admin.cbe-kpi.receipts.show', ['id' => $h->receipt_id]) }}" target="_blank" style="flex:0 0 auto; color:var(--gl-blue); font-weight:700; text-decoration:none;">🧾</a>
                    @endif
                </div>
                @empty
                <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('admin_cbe_directory.no_participation') }}</div>
                @endforelse
            </div>
            <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding:8px 12px; border-top:1px solid #eef2f7;">
                <span style="font-size:8.5px; color:#718096;">{{ $history->firstItem() ?? 0 }}–{{ $history->lastItem() ?? 0 }} {{ __('admin_cbe_kpi.of_total', ['total' => $history->total()]) }}</span>
                <div style="display:flex; gap:5px;">
                    @if($history->onFirstPage())
                        <span class="cbd-pg-btn-disabled">← {{ __('admin_cbe_kpi.prev') }}</span>
                    @else
                        <a href="{{ $history->previousPageUrl() }}" class="cbd-pg-btn">← {{ __('admin_cbe_kpi.prev') }}</a>
                    @endif
                    @if($history->hasMorePages())
                        <a href="{{ $history->nextPageUrl() }}" class="cbd-pg-btn">{{ __('admin_cbe_kpi.next') }} →</a>
                    @else
                        <span class="cbd-pg-btn-disabled">{{ __('admin_cbe_kpi.next') }} →</span>
                    @endif
                </div>
            </div>
        </div>

        <div id="panel-appointments" style="flex:1; min-height:0; display:none; flex-direction:column;">
            <div style="flex-shrink:0; padding:8px 12px; border-bottom:1px solid #eef2f7;">
                @if(count($advisors) === 0)
                <div style="font-size:8.5px; color:#94A3B8;">{{ __('admin_cbe_directory.no_practitioners_for_node', ['term' => __($faithTerms['practitioner_label_key'])]) }}</div>
                @else
                {{-- CHANGED 12 Sep 2026 — per Chris: a community can now
                enable SEVERAL appointment positions at once, same
                mechanism as the Member profile screen. --}}
                <form method="POST" action="{{ route('admin.cbe-kpi.customers.store-appointment') }}" id="apForm">
                    @csrf
                    <input type="hidden" name="node" value="{{ $node->node_id }}">
                    <input type="hidden" name="customer_id" value="{{ $customer->customer_id }}">
                    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end;">
                        @if(count($positions) > 1)
                        <div class="cbd-field" style="flex:1.1; min-width:130px;">
                            <label>{{ __('admin_cbe_directory.form_position') }}</label>
                            <select name="practice_type_id" id="apPosition" onchange="apOnPositionChange()" required>
                                @foreach($positions as $p)
                                <option value="{{ $p['id'] }}">{{ __($p['tab_label_key']) }}</option>
                                @endforeach
                            </select>
                        </div>
                        @else
                        <input type="hidden" name="practice_type_id" value="{{ $positions[0]['id'] }}">
                        @endif
                        <div class="cbd-field" style="flex:1.3; min-width:140px;">
                            <label id="apPractitionerLabel">{{ __($faithTerms['practitioner_label_key']) }}</label>
                            <select name="advisor_id" required>
                                <option value="">{{ __('admin_cbe_directory.select_practitioner_placeholder', ['term' => __($faithTerms['practitioner_label_key'])]) }}</option>
                                @foreach($advisors as $adv)
                                <option value="{{ $adv->agent_id }}">{{ $adv->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="cbd-field" style="flex:1; min-width:120px;">
                            <label>{{ __('admin_cbe_directory.form_appointment_type') }}</label>
                            <select name="appointment_type" id="apReason" required></select>
                        </div>
                        <div class="cbd-field" style="flex:0.7; min-width:100px;">
                            <label>{{ __('admin_cbe_directory.form_appointment_date') }}</label>
                            <input type="date" name="appointment_date" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="cbd-field" style="flex:0.6; min-width:80px;">
                            <label>{{ __('admin_cbe_directory.form_fee_amount') }}</label>
                            <input type="number" step="0.01" name="fee_amount" value="0">
                        </div>
                        <div class="cbd-field" style="flex:1.4; min-width:150px;">
                            <label>{{ __('admin_cbe_directory.form_notes') }}</label>
                            <input type="text" name="notes">
                        </div>
                        <button type="submit" class="cbd-btn">{{ __('admin_cbe_directory.btn_save_appointment') }}</button>
                    </div>
                    <div style="font-size:8px; color:#94A3B8; margin-top:4px;">{{ __('admin_cbe_directory.receipt_auto_issued_note') }}</div>
                    @if(session('cbe_appointment_saved'))
                    <span style="font-size:8.5px; color:#2e7d32; font-weight:700;">{{ __('admin_cbe_directory.appointment_saved') }}</span>
                    @endif
                    @error('advisor_id')<span style="font-size:8.5px; color:#c62828;">{{ $message }}</span>@enderror
                    @error('practice_type_id')<span style="font-size:8.5px; color:#c62828;">{{ $message }}</span>@enderror
                    @error('appointment_type')<span style="font-size:8.5px; color:#c62828;">{{ $message }}</span>@enderror
                </form>
                <script>
                (function () {
                    var apPositions = {!! json_encode(collect($positions)->keyBy('id')->map(function ($p) {
                        return ['label' => __($p['practitioner_label_key']), 'reasons' => $p['reasons']];
                    })) !!};
                    window.apOnPositionChange = function () {
                        var sel = document.getElementById('apPosition');
                        var id = sel ? sel.value : Object.keys(apPositions)[0];
                        var pos = apPositions[id] || Object.values(apPositions)[0];
                        if (!pos) return;
                        document.getElementById('apPractitionerLabel').textContent = pos.label;
                        var reasonSelect = document.getElementById('apReason');
                        reasonSelect.innerHTML = '';
                        pos.reasons.forEach(function (r) {
                            var opt = document.createElement('option');
                            opt.value = r;
                            opt.textContent = r;
                            reasonSelect.appendChild(opt);
                        });
                    };
                    document.addEventListener('DOMContentLoaded', window.apOnPositionChange);
                })();
                </script>
                @endif
            </div>
            <div style="flex:1; min-height:0; overflow-y:auto;">
                @forelse($appointments as $ap)
                <div class="cbd-row">
                    <span style="flex:1; color:var(--gl-blue); font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $ap->advisor_name }}</span>
                    <span style="flex:0.8; color:#6b7280;">{{ $ap->appointment_type }}</span>
                    <span style="flex:0.7; color:#94A3B8;">{{ \Carbon\Carbon::parse($ap->appointment_date)->format('d M Y') }}</span>
                    <span style="flex:0.6; color:#0d3c72; font-weight:600;">{{ $ap->fee_amount > 0 ? 'RM '.number_format($ap->fee_amount, 2) : '—' }}</span>
                    <span style="flex:1.3; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $ap->notes ?: '—' }}</span>
                    @if($ap->receipt_id)
                    <a href="{{ route('admin.cbe-kpi.receipts.show', ['id' => $ap->receipt_id]) }}" target="_blank" style="flex:0 0 auto; color:var(--gl-blue); font-weight:700; text-decoration:none;">🧾</a>
                    @endif
                </div>
                @empty
                <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('admin_cbe_directory.no_appointments') }}</div>
                @endforelse
            </div>
            <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding:8px 12px; border-top:1px solid #eef2f7;">
                <span style="font-size:8.5px; color:#718096;">{{ $appointments->firstItem() ?? 0 }}–{{ $appointments->lastItem() ?? 0 }} {{ __('admin_cbe_kpi.of_total', ['total' => $appointments->total()]) }}</span>
                <div style="display:flex; gap:5px;">
                    @if($appointments->onFirstPage())
                        <span class="cbd-pg-btn-disabled">← {{ __('admin_cbe_kpi.prev') }}</span>
                    @else
                        <a href="{{ $appointments->previousPageUrl() }}" class="cbd-pg-btn">← {{ __('admin_cbe_kpi.prev') }}</a>
                    @endif
                    @if($appointments->hasMorePages())
                        <a href="{{ $appointments->nextPageUrl() }}" class="cbd-pg-btn">{{ __('admin_cbe_kpi.next') }} →</a>
                    @else
                        <span class="cbd-pg-btn-disabled">{{ __('admin_cbe_kpi.next') }} →</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @unless($browsing ?? false)
    <div style="flex-shrink:0;">
        <a href="{{ route('admin.cbe-kpi.customers', ['node' => $node->node_id]) }}" class="cbd-back-btn"><i class="ti ti-arrow-back-up"></i> {{ __('admin_cbe_directory.btn_new_search') }}</a>
    </div>
    @endunless
</div>

<script>
function cbdSwitchTab(tab){
    document.getElementById('panel-profile').style.display = tab === 'profile' ? 'flex' : 'none';
    document.getElementById('panel-participation').style.display = tab === 'participation' ? 'flex' : 'none';
    document.getElementById('panel-appointments').style.display = tab === 'appointments' ? 'flex' : 'none';
    Array.prototype.forEach.call(document.querySelectorAll('.cbd-tabtoggle'), function(el){
        el.classList.toggle('active', el.getAttribute('data-p') === tab);
    });
}
</script>
@endsection
