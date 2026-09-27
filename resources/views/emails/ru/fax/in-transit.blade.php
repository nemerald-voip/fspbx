{{-- email-template
version: 1.0.0
language: ru
category: fax
subcategory: in-transit
format: html
layout: standard
subject: Отправка факса на номер {{ $fax_destination }}
description: Уведомление о начале отправки факса
--}}
@extends('emails.ru.email_layout')

@section('content')
<!-- Start Content-->

<h1>Ваши файлы отправляются факсом на номер {{ $attributes['fax_destination'] }}.</h1>
<p>Результат передачи будет отправлен отдельным уведомлением.</p>
<!-- Action -->

<p>Если у вас есть вопросы, <a href="mailto:{{ $attributes["support_email"] ?? ''}}">напишите нашей службе поддержки</a>. (Мы отвечаем очень быстро.)</p>
<p>Спасибо,
  <br>Команда {{ config('app.name', 'Laravel') }}</p>
<p><strong>P.S.</strong> Нужна помощь с началом работы? Служба поддержки {{ config('app.name', 'Laravel') }} всегда готова помочь! Откройте нашу <a href="{{ $attributes["help_url"] ?? ''}}">справочную документацию</a>. Или просто ответьте на это письмо.</p>


@endsection
