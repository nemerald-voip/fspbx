@extends('emails.email_layout')

@section('content')
@php
    $callerName = $attributes['caller_id_name'] ?? '';
    $callerNumber = $attributes['caller_id_number'] ?? '';
    $caller = trim($callerName . ($callerNumber ? ' <' . $callerNumber . '>' : ''));
    $ringGroup = trim(($attributes['ring_group_name'] ?? '') . (($attributes['ring_group_extension'] ?? '') ? ' ext ' . $attributes['ring_group_extension'] : ''));
@endphp

<h1>Missed call{{ $callerNumber ? ' from ' . $callerNumber : '' }}.</h1>

<p>A call to {{ $ringGroup ?: 'your ring group' }} was not answered.</p>

<ul>
    <li><strong>From:</strong> {{ $caller ?: 'Unknown caller' }}</li>
    <li><strong>To:</strong> {{ $ringGroup ?: 'Ring group' }}</li>
    @if (!empty($attributes['destination_number']))
        <li><strong>Dialed:</strong> {{ $attributes['destination_number'] }}</li>
    @endif
</ul>

<p>Thanks,<br>{{ config('app.name', 'FS PBX') }} Team</p>
@endsection
