{{-- email-template
version: 1.0.0
language: ru
category: extension
subcategory: welcome
format: html
layout: standard
subject: Ваш внутренний номер {{ $attributes['extension'] }} готов
description: Данные внутреннего номера и голосовой почты
--}}
@extends('emails.ru.email_layout')

@section('content')
<h1>Добро пожаловать, {{ $attributes['recipient_name'] }}!</h1>

<p>Мы настроили для вас внутренний номер <strong>{{ $attributes['extension'] }}</strong> . Сохраните это письмо: в нём указаны данные вашего телефона и голосовой почты.</p>

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>Внутренний номер:</strong> {{ $attributes['extension'] }}</td>
        </tr>
        @if (!empty($attributes['direct_numbers']))
          <tr>
            <td class="attributes_item"><strong>Прямые номера:</strong> {{ implode(', ', $attributes['direct_numbers']) }}</td>
          </tr>
        @endif
        <tr>
          <td class="attributes_item"><strong>Ящик голосовой почты:</strong> {{ $attributes['voicemail_id'] }}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>PIN-код голосовой почты:</strong> {{ $attributes['voicemail_pin'] }}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<h2>Настройте приветствие голосовой почты</h2>
<ol>
  <li>Наберите <strong>*97</strong> на своём телефоне.</li>
  <li>Введите PIN-код голосовой почты и нажмите <strong>#</strong>.</li>
  <li>Нажмите <strong>5</strong> для перехода к настройкам почтового ящика.</li>
  <li>Нажмите <strong>1</strong> чтобы записать приветствие на случай недоступности.</li>
</ol>

@if (!empty($attributes['help_url']))
  <p>Дополнительная информация доступна в <a href="{{ $attributes['help_url'] }}">центре справки</a>.</p>
@endif

@if (!empty($attributes['support_email']))
  <p>Есть вопросы? Напишите на <a href="mailto:{{ $attributes['support_email'] }}">{{ $attributes['support_email'] }}</a>.</p>
@endif

<p>Добро пожаловать,<br>{{ $attributes['app_name'] }}</p>
@endsection
