{{-- email-template
version: 1.1.0
language: en-us
category: emergency
subcategory: call
format: html
layout: standard
subject: {{ $email_subject }}
description: Emergency call notification
--}}
@extends('emails.email_layout')

@section('content')
<!-- Start Content -->

<h1>Emergency Call Notification</h1>

<p>An emergency call was placed from extension <strong>{{ $attributes['caller'] }}</strong>.</p>

<p>Please take appropriate action immediately.</p>

<p>Thank you and stay safe.</p>

@endsection
