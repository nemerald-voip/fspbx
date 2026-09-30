{{-- email-template
version: 1.0.0
language: ru
category: ai-agent
subcategory: send-email
format: html
layout: standard
subject: {{ $email_subject }}
description: Письмо от ИИ-агента
--}}
@extends('emails.ru.email_layout')

@section('content')
<h1>{{ $attributes['email_subject'] }}</h1>

<p>ИИ-агент собрал следующие сведения для дальнейшей обработки:</p>

<ul>
    @foreach ($attributes['fields'] as $field)
        <li><strong>{{ $field['label'] }}:</strong> {{ $field['value'] }}</li>
    @endforeach
</ul>

@if (!empty($attributes['notes']))
<p><strong>Дополнительная информация:</strong><br>{{ $attributes['notes'] }}</p>
@endif

@endsection
