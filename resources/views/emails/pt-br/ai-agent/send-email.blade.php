{{-- email-template
version: 1.0.0
language: pt-br
category: ai-agent
subcategory: send-email
format: html
layout: standard
subject: {{ $email_subject }}
description: E-mail enviado por um agente de IA
--}}
@extends('emails.pt-br.email_layout')

@section('content')
<h1>{{ $attributes['email_subject'] }}</h1>

<p>Um agente de IA coletou as seguintes informações para acompanhamento:</p>

<ul>
    @foreach ($attributes['fields'] as $field)
        <li><strong>{{ $field['label'] }}:</strong> {{ $field['value'] }}</li>
    @endforeach
</ul>

@if (!empty($attributes['notes']))
<p><strong>Informações adicionais:</strong><br>{{ $attributes['notes'] }}</p>
@endif

@endsection
