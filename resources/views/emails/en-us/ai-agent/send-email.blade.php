{{-- email-template
version: 1.0.1
language: en-us
category: ai-agent
subcategory: send-email
format: html
layout: standard
subject: {{ $email_subject }}
description: Follow-up email sent by an AI Agent custom function
--}}
@extends('emails.email_layout')

@section('content')
<h1>{{ $attributes['email_subject'] }}</h1>

<p>An AI Agent collected the following information for follow-up:</p>

<ul>
    @foreach ($attributes['fields'] as $field)
        <li><strong>{{ $field['label'] }}:</strong> {{ $field['value'] }}</li>
    @endforeach
</ul>

@if (!empty($attributes['notes']))
<p><strong>Additional information:</strong><br>{{ $attributes['notes'] }}</p>
@endif

@endsection
