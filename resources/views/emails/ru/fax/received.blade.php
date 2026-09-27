{{-- email-template
version: 1.0.0
language: ru
category: fax
subcategory: received
format: html
layout: standard
subject: Получен факс для {{ $attributes['fax_destination'] }}
description: Уведомление о полученном факсе
--}}
@extends('emails.ru.email_layout')

@section('content')
<h1>Получен факс{{ $attributes['caller_id_number'] ? ' от ' . $attributes['caller_id_number'] : '' }}.</h1>

<p>Получен новый факс для {{ $attributes['fax_destination'] }} и приложен к этому письму.</p>

<ul>
    {{-- <li><strong>Domain:</strong> {{ $attributes['domain_name'] ?? '' }}</li> --}}
    <li><strong>От:</strong> {{ $attributes['caller_display'] }}</li>
    <li><strong>Кому:</strong> {{ $attributes['fax_destination'] }}</li>
    <li><strong>Страницы:</strong> {{ $attributes['fax_pages'] ?? '' }}</li>
    @if (!empty($attributes['fax_date']))
        <li><strong>Получено:</strong> {{ $attributes['fax_date'] }}</li>
    @endif
    {{-- <li><strong>Status:</strong> {{ $attributes['fax_result_text'] ?? '' }}</li> --}}
</ul>

@if (!empty($attributes['is_test']))
    <p><strong>Это тестовое письмо.</strong> Реальная обработка факса не запускалась.</p>
@endif

<p>Факс приложен к письму в формате {{ ($attributes['attachment_mime'] ?? '') === 'application/pdf' ? 'PDF' : 'TIFF' }}.</p>

<p>Если у вас есть вопросы, <a href="mailto:{{ $attributes['support_email'] ?? '' }}">напишите нашей службе поддержки</a>.</p>
<p>Спасибо,<br>Команда {{ config('app.name', 'Laravel') }}</p>
@endsection
