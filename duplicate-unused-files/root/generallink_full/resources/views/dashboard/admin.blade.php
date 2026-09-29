@extends('layouts.dashboard')

@section('title', 'ADMIN Dashboard')
@section('page-title', 'ADMIN Dashboard')

@section('content')

@php
$roleLabel = match($agent->role) {
    'ADMIN' => 'Administrator',
    'GROUP_LEADER' => 'Group Leader',
    'TEAM_LEADER' => 'Team Leader',
    default => 'Introducer',
};
@endphp

{{-- Welcome banner --}}
<div style="background:linear-gradient(135deg,#0D5A8E,#1B9AE4);border-radius:12px;padding:20px 24px;margin-bottom:20px;color:#fff">
    <div style="font-size:13px;opacity:0.8">Welcome back,</div>
    <div style="font-size:20px;font-weight:700;margin-top:2px">{{ $agent->full_name }}</div>
    <div style="font-size:12px;opacity:0.7;margin-top:4px">
        {{ $roleLabel }} &middot; {{ now()->format('l, d F Y') }}
    </div>
</div>

@include('dashboard._shared')

@endsection
