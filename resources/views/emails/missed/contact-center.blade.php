{{-- email-template
version: 1.0.0
language: en-us
category: missed
subcategory: contact-center
format: html
layout: standard
subject: {{ $email_subject }}
description: Contact Center abandoned call notification
--}}
@extends('emails.email_layout')

@section('content')
<h1>Abandoned call{{ $attributes['caller_id_number'] ? ' from '.$attributes['caller_id_number'] : '' }}.</h1>

<p>A caller left {{ $attributes['queue_display'] }} before an agent answered.</p>

<ul>
    <li><strong>From:</strong> {{ $attributes['caller_display'] }}</li>
    <li><strong>Contact Center:</strong> {{ $attributes['queue_display'] }}</li>
    <li><strong>Reason:</strong> {{ $attributes['departure_reason'] }}</li>
    @if (!empty($attributes['wait_duration']))
        <li><strong>Time waiting:</strong> {{ $attributes['wait_duration'] }}</li>
    @endif
    <li><strong>Call ID:</strong> {{ $attributes['call_uuid'] }}</li>
</ul>

<p>Thanks,<br>{{ config('app.name', 'FS PBX') }} Team</p>
@endsection
