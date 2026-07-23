{{-- email-template
version: 1.2.0
language: en-us
category: fax
subcategory: invalid-destination
format: html
layout: standard
subject: Fax to {{ $invalid_number }} Failed - Invalid Fax Destination Number
description: Invalid fax destination notification
--}}
@extends('emails.email_layout')

@section('content')
<!-- Start Content-->

<h1>Your fax message to {{ $attributes['invalid_number'] }} has failed</h1>
<p>The destination number is not a valid US phone number.</p>
<!-- Action -->

@endsection
