{{-- email-template
version: 1.0.0
language: ru
category: missed
subcategory: ring-group
format: html
layout: standard
subject: Пропущенный вызов в группу {{ $attributes['ring_group_display'] }}
description: Уведомление о пропущенном вызове в группу
--}}
@extends('emails.ru.email_layout')

@section('content')
<h1>Пропущенный вызов{{ $attributes['caller_id_number'] ? ' от ' . $attributes['caller_id_number'] : '' }}.</h1>

<p>Вызов на {{ $attributes['ring_group_display'] ?: 'вашу группу вызова' }} остался без ответа.</p>

<ul>
    <li><strong>От:</strong> {{ $attributes['caller_display'] ?: 'Неизвестный абонент' }}</li>
    <li><strong>Кому:</strong> {{ $attributes['ring_group_display'] ?: 'Группа вызова' }}</li>
    @if (!empty($attributes['destination_number']))
        <li><strong>Набранный номер:</strong> {{ $attributes['destination_number'] }}</li>
    @endif
</ul>

<p>Спасибо,<br>Команда {{ config('app.name', 'FS PBX') }}</p>
@endsection
