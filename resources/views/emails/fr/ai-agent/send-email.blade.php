{{-- email-template
version: 1.0.0
language: fr
category: ai-agent
subcategory: send-email
format: html
layout: standard
subject: {{ $email_subject }}
description: E-mail envoyé par un agent IA
--}}
@extends('emails.fr.email_layout')

@section('content')
<h1>{{ $attributes['email_subject'] }}</h1>

<p>Un agent IA a recueilli les informations suivantes pour le suivi :</p>

<ul>
    @foreach ($attributes['fields'] as $field)
        <li><strong>{{ $field['label'] }}:</strong> {{ $field['value'] }}</li>
    @endforeach
</ul>

@if (!empty($attributes['notes']))
<p><strong>Informations complémentaires :</strong><br>{{ $attributes['notes'] }}</p>
@endif

@endsection
