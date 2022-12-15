@extends('emails.email_layout')

@section('content')
<!-- Start Content-->

<h1>Your fax message to {{ $attributes['invalid_number'] }} has failed</h1>
<p>The destination number is not a valid US phone number.</p>
<!-- Action -->

@endsection