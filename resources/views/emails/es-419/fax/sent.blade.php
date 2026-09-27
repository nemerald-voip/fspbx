{{-- email-template
version: 1.0.0
language: es-419
category: fax
subcategory: sent
format: html
layout: standard
subject: Fax entregado a {{ $attributes['fax_destination'] }}
description: Notificación de fax entregado
--}}
@extends('emails.es-419.email_layout')

@section('content')
<!-- Start Content-->

<h1>Tu fax a {{ $attributes['fax_destination'] }} fue entregado.</h1>

<p>El fax se transmitió correctamente el
{{ $attributes['fax_date'] ?? now()->format('Y-m-d H:i') }}.</p>

@if (!empty($attributes['fax_pages']))
    <p>Páginas enviadas: <strong>{{ $attributes['fax_pages'] }}</strong>{!! isset($attributes['fax_total_pages']) && $attributes['fax_total_pages'] !== $attributes['fax_pages'] ? ' de ' . $attributes['fax_total_pages'] : '' !!}.</p>
@endif

@if (!empty($attributes['fax_duration_formatted']))
    <p>Duración: {{ $attributes['fax_duration_formatted'] }}.</p>
@endif

<p>El fax transmitido está adjunto a este correo para tus registros.</p>

<p>Gracias,<br>El equipo de {{ config('app.name', 'Laravel') }}</p>

@endsection
