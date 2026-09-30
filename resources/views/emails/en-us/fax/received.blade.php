{{-- email-template
version: 1.1.0
language: en-us
category: fax
subcategory: received
format: html
layout: standard
subject: {{ $email_subject }}
description: Inbound fax received notification
--}}
@extends('emails.email_layout')

@section('content')
<h1>Fax received{{ $attributes['caller_id_number'] ? ' from ' . $attributes['caller_id_number'] : '' }}.</h1>

<p>A new fax was received for {{ $attributes['fax_destination'] }} and is attached to this email.</p>

<ul>
    {{-- <li><strong>Domain:</strong> {{ $attributes['domain_name'] ?? '' }}</li> --}}
    <li><strong>From:</strong> {{ $attributes['caller_display'] }}</li>
    <li><strong>To:</strong> {{ $attributes['fax_destination'] }}</li>
    <li><strong>Pages:</strong> {{ $attributes['fax_pages'] ?? '' }}</li>
    @if (!empty($attributes['fax_date']))
        <li><strong>Received:</strong> {{ $attributes['fax_date'] }}</li>
    @endif
    {{-- <li><strong>Status:</strong> {{ $attributes['fax_result_text'] ?? '' }}</li> --}}
</ul>

@if (!empty($attributes['is_test']))
    <p><strong>This is a test email.</strong> No live fax workflow was triggered.</p>
@endif

<p>The fax is attached as a {{ ($attributes['attachment_mime'] ?? '') === 'application/pdf' ? 'PDF' : 'TIFF' }} file.</p>

<p>If you have any questions, <a href="mailto:{{ $attributes['support_email'] ?? '' }}">email our customer success team</a>.</p>
<p>Thanks,<br>{{ config('app.name', 'Laravel') }} Team</p>
@endsection
