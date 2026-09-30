{{-- email-template
version: 1.0.0
language: ru
category: fax
subcategory: service-alert
format: html
layout: standard
subject: Оповещение службы факсов
description: Оповещение службы факсов
--}}
@extends('emails.ru.email_layout')

@section('content')
<!-- Start Content-->

<h1>Предупреждение службы факсов</h1>

@if(isset($attributes["pendingFaxes"]))
    <p>{{ $attributes["pendingFaxes"] }} исходящих факсов ожидают отправки дольше {{ $attributes["waitTimeThreshold"] }} минут. Проверьте состояние службы факсов.</p>
@endif

@if(isset($attributes["failedFaxes"]))
    <p>{{ $attributes["failedFaxes"] }} из {{ $attributes["totalChecked"] }} недавно обработанных факсов завершились ошибкой ({{ $attributes["failureRate"] }}% неудачных отправок).</p>
    <p>Это может указывать на проблему со службой факсов.</p>
@endif

<!-- Action -->
<p>Спасибо,<br>Команда {{ config('app.name', 'Laravel') }}</p>

@endsection
