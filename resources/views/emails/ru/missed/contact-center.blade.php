{{-- email-template
version: 1.0.0
language: ru
category: missed
subcategory: contact-center
format: html
layout: standard
subject: Потерянный вызов в очереди {{ $attributes['queue_display'] }}
description: Уведомление о потерянном вызове в контакт-центре
--}}
@extends('emails.ru.email_layout')

@section('content')
<h1>Вызов, прерванный абонентом{{ $attributes['caller_id_number'] ? ' от '.$attributes['caller_id_number'] : '' }}.</h1>

<p>Абонент покинул {{ $attributes['queue_display'] }} до ответа оператора.</p>

<ul>
    <li><strong>От:</strong> {{ $attributes['caller_display'] }}</li>
    <li><strong>Контакт-центр:</strong> {{ $attributes['queue_display'] }}</li>
    <li><strong>Причина:</strong> {{ $attributes['departure_reason'] }}</li>
    @if (!empty($attributes['wait_duration']))
        <li><strong>Время ожидания:</strong> {{ $attributes['wait_duration'] }}</li>
    @endif
    <li><strong>ID вызова:</strong> {{ $attributes['call_uuid'] }}</li>
</ul>

<p>Спасибо,<br>Команда {{ config('app.name', 'FS PBX') }}</p>
@endsection
