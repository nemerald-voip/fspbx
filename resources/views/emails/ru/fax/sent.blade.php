{{-- email-template
version: 1.0.0
language: ru
category: fax
subcategory: sent
format: html
layout: standard
subject: Факс доставлен на номер {{ $attributes['fax_destination'] }}
description: Уведомление о доставке факса
--}}
@extends('emails.ru.email_layout')

@section('content')
<!-- Start Content-->

<h1>Ваш факс на номер {{ $attributes['fax_destination'] }} доставлен.</h1>

<p>Факс успешно передан
{{ $attributes['fax_date'] ?? now()->format('Y-m-d H:i') }}.</p>

@if (!empty($attributes['fax_pages']))
    <p>Отправлено страниц: <strong>{{ $attributes['fax_pages'] }}</strong>{!! isset($attributes['fax_total_pages']) && $attributes['fax_total_pages'] !== $attributes['fax_pages'] ? ' из ' . $attributes['fax_total_pages'] : '' !!}.</p>
@endif

@if (!empty($attributes['fax_duration_formatted']))
    <p>Длительность: {{ $attributes['fax_duration_formatted'] }}.</p>
@endif

<p>Переданный факс приложен к письму для ваших записей.</p>

<p>Спасибо,<br>Команда {{ config('app.name', 'Laravel') }}</p>

@endsection
