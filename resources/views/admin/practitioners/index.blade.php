@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_practitioners.page_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:8px 16px; box-sizing:border-box;">

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
        <div style="min-width:0;">
            <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('admin_practitioners.page_title') }}</div>
            <div style="font-size:9px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $node->node_name }}</div>
        </div>
        <a href="{{ route('admin.practitioners.index') }}" style="background:var(--gl-light); color:var(--gl-blue); border:1px solid var(--gl-cyan2); border-radius:5px; padding:6px 14px; font-size:10px; font-weight:700; text-decoration:none;">{{ __('cbe_masterfile.switch_entity') }}</a>
    </div>

    @if(session('practitioner_saved'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:5px 10px; font-size:11px; margin-bottom:8px;">{{ __('admin_practitioners.saved') }}</div>
    @endif
    @if(session('practitioner_delete_blocked'))
    <div style="background:#fff8e1; border-left:3px solid #f9a825; border-radius:6px; padding:5px 10px; font-size:11px; color:#8d6e00; margin-bottom:8px;">{{ session('practitioner_delete_blocked') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:5px 10px; font-size:11px; color:#b71c1c; margin-bottom:8px;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 12px; margin-bottom:8px;">
        <form method="POST" action="{{ route('admin.practitioners.store', ['node' => $node->node_id]) }}" id="paAddForm">
            @csrf
            <div style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; position:relative;">
                <div style="flex:0 0 150px;">
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('admin_practitioners.form_practitioner_type') }}</label>
                    <select name="practitioner_type_id" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; background:#fff; box-sizing:border-box;">
                        @foreach($practitionerTypeCatalog as $pt)
                        <option value="{{ $pt->id }}">{{ $pt->type_label }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="flex:1 1 180px; position:relative;">
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('admin_practitioners.form_search_agent') }}</label>
                    <input type="text" id="paAgentSearch" autocomplete="off" placeholder="{{ __('admin_practitioners.form_search_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                    <input type="hidden" name="agent_id" id="paAgentId">
                    <div id="paAgentDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:200px; overflow-y:auto; margin-top:2px;"></div>
                </div>
                <div style="flex:0 0 110px;">
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('admin_practitioners.form_slot_duration') }}</label>
                    <input type="number" name="slot_duration_minutes" value="30" min="5" max="480" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                </div>
                <div style="flex:0 0 110px;">
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('admin_practitioners.form_max_per_day') }}</label>
                    <input type="number" name="max_slots_per_day" value="16" min="1" max="100" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                </div>
                <div style="flex:0 0 120px;">
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('admin_practitioners.form_booking_window') }}</label>
                    <input type="number" name="booking_window_days" value="60" min="1" max="730" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                </div>
                <div style="flex:0 0 120px;">
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('admin_practitioners.form_max_upcoming') }}</label>
                    <input type="number" name="max_upcoming_per_member" value="1" min="1" max="20" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                </div>
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 18px; font-size:12px; font-weight:600; cursor:pointer; height:29px;">{{ __('admin_practitioners.add_practitioner') }}</button>
            </div>
        </form>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:11.5px;">
            <thead>
                <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db;">
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('admin_practitioners.col_practitioner_type') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('admin_practitioners.col_name') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('admin_practitioners.col_slot_duration') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('admin_practitioners.col_max_per_day') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('admin_practitioners.col_booking_window') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('masterfile.status') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151; width:70px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($practitioners as $p)
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:5px 8px; font-weight:600; color:#1565C0;">{{ $p->type_label }}</td>
                    <td style="padding:5px 8px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:160px;" title="{{ $p->full_name }} ({{ $p->agent_code }})">{{ $p->full_name }}</td>
                    <td style="padding:5px 8px; color:#4b5563;">{{ $p->slot_duration_minutes }} {{ __('admin_practitioners.minutes_suffix') }}</td>
                    <td style="padding:5px 8px; color:#4b5563;">{{ $p->max_slots_per_day }}</td>
                    <td style="padding:5px 8px; color:#4b5563;">{{ $p->booking_window_days }} {{ __('admin_practitioners.days_suffix') }}</td>
                    <td style="padding:5px 8px;">
                        <span style="padding:2px 8px; border-radius:20px; font-size:9.5px; font-weight:600; {{ $p->is_active ? 'background:#e8f5e9;color:#1b5e20;' : 'background:#f3f4f6;color:#6b7280;' }}">{{ $p->is_active ? __('masterfile.active') : __('masterfile.inactive') }}</span>
                    </td>
                    <td style="padding:5px 8px;">
                        <a href="{{ route('admin.practitioners.edit', $p->id) }}" style="color:#1B9AE4; text-decoration:none; font-weight:600;">{{ __('admin_practitioners.manage') }}</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" style="padding:20px; text-align:center; color:#9ca3af; font-size:11.5px;">{{ __('admin_practitioners.no_practitioners') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>
    (function () {
        var input = document.getElementById('paAgentSearch');
        var hiddenId = document.getElementById('paAgentId');
        var dropdown = document.getElementById('paAgentDropdown');
        var timer;

        input.addEventListener('input', function () {
            hiddenId.value = '';
            clearTimeout(timer);
            var q = this.value.trim();
            if (q.length < 1) { dropdown.style.display = 'none'; return; }
            timer = setTimeout(function () {
                fetch('{{ route('admin.practitioners.agent-typeahead', ['node' => $node->node_id]) }}?q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.length) {
                            dropdown.innerHTML = '<div style="padding:8px 10px; font-size:11px; color:#9ca3af;">—</div>';
                            dropdown.style.display = 'block';
                            return;
                        }
                        dropdown.innerHTML = '';
                        data.forEach(function (item) {
                            var d = document.createElement('div');
                            d.style.cssText = 'padding:8px 10px; font-size:12px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                            d.innerHTML = '<span style="font-weight:600; color:#1565C0;">' + item.full_name + '</span> <span style="color:#9ca3af; font-size:10.5px;">(' + item.agent_code + ')</span>';
                            d.onmousedown = function (e) {
                                e.preventDefault();
                                input.value = item.full_name + ' (' + item.agent_code + ')';
                                hiddenId.value = item.agent_id;
                                dropdown.style.display = 'none';
                            };
                            dropdown.appendChild(d);
                        });
                        dropdown.style.display = 'block';
                    });
            }, 250);
        });

        document.addEventListener('click', function (e) { if (e.target !== input) dropdown.style.display = 'none'; });

        document.getElementById('paAddForm').addEventListener('submit', function (e) {
            if (!hiddenId.value) {
                e.preventDefault();
                alert('{{ __('masterfile.committee_no_match') }}');
            }
        });
    })();
    </script>

</div>
@endsection
