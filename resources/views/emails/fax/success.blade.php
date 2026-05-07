@extends('emails.email_layout')

@section('content')
<!-- Start Content-->

<h1>Your fax to {{ $attributes['fax_destination'] }} was delivered.</h1>

<p>The fax was successfully transmitted on
{{ $attributes['fax_date'] ?? now()->format('Y-m-d H:i') }}.</p>

@if (!empty($attributes['fax_pages']))
    <p>Pages sent: <strong>{{ $attributes['fax_pages'] }}</strong>{!! isset($attributes['fax_total_pages']) && $attributes['fax_total_pages'] !== $attributes['fax_pages'] ? ' of ' . $attributes['fax_total_pages'] : '' !!}.</p>
@endif

@if (!empty($attributes['fax_duration_formatted']))
    <p>Duration: {{ $attributes['fax_duration_formatted'] }}.</p>
@endif

<p>The transmitted fax is attached to this email for your records.</p>

<p>Thanks,<br>{{ config('app.name', 'Laravel') }} Team</p>

@endsection
