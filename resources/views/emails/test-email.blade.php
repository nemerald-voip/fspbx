@extends('emails.email_layout')

@section('content')
<p>Hello,</p>

<p>This is a test email from {{ config('app.name', 'FS PBX') }}.</p>

<p>If you received this message, the configured  mail service is able to send email.</p>

<p>Sent at {{ $attributes['sent_at'] ?? now()->toDateTimeString() }}.</p>
@endsection
