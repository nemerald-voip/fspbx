@extends('emails.email_layout')

@section('content')
<h1>New fax received for {{ $attributes['fax_destination'] ?? ($attributes['fax_extension'] ?? 'your fax line') }}.</h1>

<p>A new fax has been received and attached to this email.</p>

<ul>
    {{-- <li><strong>Domain:</strong> {{ $attributes['domain_name'] ?? '' }}</li> --}}
    <li><strong>Fax destination:</strong> {{ $attributes['fax_destination'] ?? '' }}</li>
    <li><strong>Fax extension:</strong> {{ $attributes['fax_extension'] ?? '' }}</li>
    <li><strong>Caller ID name:</strong> {{ $attributes['caller_id_name'] ?? '' }}</li>
    <li><strong>Caller ID number:</strong> {{ $attributes['caller_id_number'] ?? '' }}</li>
    <li><strong>Pages:</strong> {{ $attributes['fax_pages'] ?? '' }}</li>
    {{-- <li><strong>Status:</strong> {{ $attributes['fax_result_text'] ?? '' }}</li> --}}
</ul>

@if (!empty($attributes['is_test']))
    <p><strong>This is a test email.</strong> No live fax workflow was triggered.</p>
@endif

<p>If you have any questions, <a href="mailto:{{ $attributes['support_email'] ?? '' }}">email our customer success team</a>.</p>
<p>Thanks,<br>{{ config('app.name', 'Laravel') }} Team</p>
<p><strong>P.S.</strong> Need immediate help getting started? The {{ config('app.name', 'Laravel') }} support team is always ready to help. Check out our <a href="{{ $attributes['help_url'] ?? '' }}">help documentation</a>.</p>
@endsection