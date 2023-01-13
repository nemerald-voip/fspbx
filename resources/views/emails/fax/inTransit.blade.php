@extends('emails.email_layout')

@section('content')
<!-- Start Content-->

<p>Your fax message to {{ $attributes['fax_destination'] }} is now in transit.</p>
<!-- Action -->

@endsection