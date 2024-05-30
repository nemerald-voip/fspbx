@extends('emails.email_layout')

@section('content')
<!-- Start Content-->

<p>{{ $attributes['message'] }}</p>

@endsection