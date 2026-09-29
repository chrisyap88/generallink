@extends('layouts.dashboard')
@section('title','Customers')
@section('page-title','Customer Management')

@section('content')

@if(session('success'))
<div style="background:#C6F6D5;border:1px solid #9AE6B4;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#22543D;font-size:13px">✓ {{ session('success') }}</div>
@endif

{{-- Search + Add --}}
<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
    <form method="GET" style="display:flex;gap:8px;flex:1;min-width:200px">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name, email or phone..."
            style="flex:1;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
        <button type="submit" style="background:#0D5A8E;color:#fff;padding:8px 16px;border:none;border-radius:8px;font-size:13px;cursor:pointer">
            <i class="ti ti-search"></i> Search
        </button>
        @if(request('search'))
        <a href="{{ route('customers.index') }}" style="padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;text-decoration:none;color:#4A5568">Clear</a>
        @endif
    </form>
    <a href="{{ route('customers.create') }}"
        style="background:#059669;color:#fff;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px">
        <i class="ti ti-user-plus"></i> Add Customer
    </a>
</div>

<div class="card">
    <div class="card-title">
        <i class="ti ti-users" style="color:#0D5A8E"></i> Customers
        <span style="margin-left:auto;font-size:12px;color:#718096">{{ $customers->total() }} records</span>
    </div>

    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead>
                <tr style="background:#F7FAFC">
                    @foreach(['Name','Phone','Email','City','State','Agent','Policies','Action'] as $h)
                    <th style="padding:9px 10px;text-align:left;border-bottom:1px solid #E2E8F0;font-size:12px;color:#4A5568;font-weight:600">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $c)
                <tr style="border-bottom:1px solid #F7FAFC">
                    <td style="padding:9px 10px;font-weight:500">{{ $c->full_name }}</td>
                    <td style="padding:9px 10px;color:#4A5568">{{ $c->phone }}</td>
                    <td style="padding:9px 10px;color:#4A5568">{{ $c->email ?: '—' }}</td>
                    <td style="padding:9px 10px;color:#718096">{{ $c->city ?: '—' }}</td>
                    <td style="padding:9px 10px;color:#718096">{{ $c->state ?: '—' }}</td>
                    <td style="padding:9px 10px;font-size:12px;color:#0D5A8E">{{ $c->agent_name }}</td>
                    <td style="padding:9px 10px;text-align:center">
                        @php
                            $policyCount = \DB::table('sales_transactions')
                                ->where('customer_id', $c->customer_id)
                                ->where('is_deleted', false)->count();
                        @endphp
                        <span style="background:#EBF8FF;color:#2B6CB0;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700">{{ $policyCount }}</span>
                    </td>
                    <td style="padding:9px 10px">
                        <a href="{{ route('customers.show', $c->customer_id) }}"
                            style="background:#EBF8FF;border:1px solid #BEE3F8;color:#2B6CB0;padding:4px 10px;border-radius:6px;font-size:11px;text-decoration:none;font-weight:600">
                            <i class="ti ti-eye"></i> View
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" style="padding:30px;text-align:center;color:#A0AEC0">No customers found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $customers->links() }}</div>
</div>

@endsection
