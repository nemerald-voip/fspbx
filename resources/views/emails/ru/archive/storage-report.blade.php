{{-- email-template
version: 1.0.0
language: ru
category: archive
subcategory: storage-report
format: html
layout: standard
subject: Отчёт об архивном хранилище
description: Отчёт об архивном хранилище
--}}
@extends('emails.ru.email_layout')

@section('content')
<!-- Start Content-->

<h1>Новый отчёт об архивном хранилище</h1>
<p>Скрипт переноса выполнен успешно. Отчёт приведён ниже.</p>
<!-- Action -->

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>Сервер:</strong>
            {{ $attributes['hostname'] ?? 'неизвестно' }}
          </td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>Успешно:</strong>
            @if (isset($attributes['success'])) {{ count($attributes['success'])}} @else 0 @endif
          </td>
        </tr>
        @if (isset($attributes['failed']) && count($attributes['failed']) > 0)
        <tr>
          <td class="attributes_item"><strong>Ошибка:</strong>
              {{ count($attributes['failed'])}}
            </td>
        </tr>
        @endif
      </table>
    </td>
  </tr>
</table>

@if (isset($attributes['failed']) && count($attributes['failed']) > 0)

  @foreach ($attributes['failed'] as $rec)
    <li>{{ $rec['name'] }} — Причина: {{ $rec['msg'] }}</li>
  @endforeach

@endif

@endsection
