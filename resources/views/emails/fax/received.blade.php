@extends('emails.email_layout')

@section('content')
@php
    $senderName = $attributes['caller_id_name'] ?? '';
    $senderNumber = $attributes['caller_id_number'] ?? '';
    $sender = trim($senderName . ($senderNumber ? ' <' . $senderNumber . '>' : ''));
    $destination = $attributes['fax_destination'] ?? ($attributes['fax_extension'] ?? 'your fax line');
@endphp

<h1>Fax received{{ $attributes['caller_id_number'] ? ' from ' . $attributes['caller_id_number'] : '' }}.</h1>

<p>A new fax was received for {{ $destination }} and is attached to this email.</p>

<ul>
    {{-- <li><strong>Domain:</strong> {{ $attributes['domain_name'] ?? '' }}</li> --}}
    <li><strong>From:</strong> {{ $sender }}</li>
    <li><strong>To:</strong> {{ $destination }}</li>
    <li><strong>Pages:</strong> {{ $attributes['fax_pages'] ?? '' }}</li>
    {{-- <li><strong>Status:</strong> {{ $attributes['fax_result_text'] ?? '' }}</li> --}}
</ul>

@if (!empty($attributes['is_test']))
    <p><strong>This is a test email.</strong> No live fax workflow was triggered.</p>
@endif

<p>The fax is attached as a {{ ($attributes['attachment_mime'] ?? '') === 'application/pdf' ? 'PDF' : 'TIFF' }} file.</p>

<p>If you have any questions, <a href="mailto:{{ $attributes['support_email'] ?? '' }}">email our customer success team</a>.</p>
<p>Thanks,<br>{{ config('app.name', 'Laravel') }} Team</p>
@endsection
