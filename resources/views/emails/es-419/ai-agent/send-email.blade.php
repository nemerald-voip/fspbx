{{-- email-template
version: 1.0.0
language: es-419
category: ai-agent
subcategory: send-email
format: html
layout: standard
subject: {{ $email_subject }}
description: Correo enviado por un agente de IA
--}}
@extends('emails.es-419.email_layout')

@section('content')
<h1>{{ $attributes['email_subject'] }}</h1>

<p>Un agente de IA recopiló la siguiente información para dar seguimiento:</p>

<ul>
    @foreach ($attributes['fields'] as $field)
        <li><strong>{{ $field['label'] }}:</strong> {{ $field['value'] }}</li>
    @endforeach
</ul>

@if (!empty($attributes['notes']))
<p><strong>Información adicional:</strong><br>{{ $attributes['notes'] }}</p>
@endif

@endsection
