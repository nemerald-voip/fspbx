@extends('emails.email_layout')

@section('content')
<!-- Start Content -->

<h1>Emergency Call Notification</h1>

<p>An emergency call was placed from extension <strong>{{ $attributes['caller'] }}</strong>.</p>

<p>Please take appropriate action immediately.</p>

<p>Thank you and stay safe.</p>

@endsection
