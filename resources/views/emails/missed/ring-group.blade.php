{{-- email-template
version: 1.1.0
language: en-us
category: missed
subcategory: ring-group
format: html
layout: standard
subject: {{ $email_subject }}
description: Ring group missed call notification
--}}
@extends('emails.email_layout')

@section('content')
<h1>Missed call{{ $attributes['caller_id_number'] ? ' from ' . $attributes['caller_id_number'] : '' }}.</h1>

<p>A call to {{ $attributes['ring_group_display'] ?: 'your ring group' }} was not answered.</p>

<ul>
    <li><strong>From:</strong> {{ $attributes['caller_display'] ?: 'Unknown caller' }}</li>
    <li><strong>To:</strong> {{ $attributes['ring_group_display'] ?: 'Ring group' }}</li>
    @if (!empty($attributes['destination_number']))
        <li><strong>Dialed:</strong> {{ $attributes['destination_number'] }}</li>
    @endif
</ul>

<p>Thanks,<br>{{ config('app.name', 'FS PBX') }} Team</p>
@endsection
