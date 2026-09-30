{{-- email-template
version: 1.0.0
language: pt-br
category: fax
subcategory: sent
format: html
layout: standard
subject: Fax entregue para {{ $attributes['fax_destination'] }}
description: Notificação de fax entregue
--}}
@extends('emails.pt-br.email_layout')

@section('content')
<!-- Start Content-->

<h1>Seu fax para {{ $attributes['fax_destination'] }} foi entregue.</h1>

<p>O fax foi transmitido com sucesso em
{{ $attributes['fax_date'] ?? now()->format('Y-m-d H:i') }}.</p>

@if (!empty($attributes['fax_pages']))
    <p>Páginas enviadas: <strong>{{ $attributes['fax_pages'] }}</strong>{!! isset($attributes['fax_total_pages']) && $attributes['fax_total_pages'] !== $attributes['fax_pages'] ? ' de ' . $attributes['fax_total_pages'] : '' !!}.</p>
@endif

@if (!empty($attributes['fax_duration_formatted']))
    <p>Duração: {{ $attributes['fax_duration_formatted'] }}.</p>
@endif

<p>O fax transmitido está anexado a este e-mail para seus registros.</p>

<p>Obrigado,<br>A equipe de {{ config('app.name', 'Laravel') }}</p>

@endsection
