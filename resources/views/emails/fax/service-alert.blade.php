{{-- email-template
version: 1.1.0
language: en-us
category: fax
subcategory: service-alert
format: html
layout: standard
subject: {{ $email_subject }}
description: Fax service health alert
--}}
@extends('emails.email_layout')

@section('content')
<!-- Start Content-->

<h1>Fax Service Alert</h1>

@if(isset($attributes["pendingFaxes"]))
    <p>{{ $attributes["pendingFaxes"] }} outbound faxes have been pending for longer than {{ $attributes["waitTimeThreshold"] }} minutes. Check the fax service status.</p>
@endif

@if(isset($attributes["failedFaxes"]))
    <p>{{ $attributes["failedFaxes"] }} out of {{ $attributes["totalChecked"] }} recently processed faxes have failed ({{ $attributes["failureRate"] }}% failure rate).</p>
    <p>This indicates a potential issue with the fax service.</p>
@endif

<!-- Action -->
<p>Thanks,<br>{{ config('app.name', 'Laravel') }} Team</p>

@endsection
