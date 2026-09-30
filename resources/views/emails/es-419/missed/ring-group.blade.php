{{-- email-template
version: 1.0.0
language: es-419
category: missed
subcategory: ring-group
format: html
layout: standard
subject: Llamada perdida a {{ $attributes['ring_group_display'] }}
description: Notificación de llamada perdida a un grupo de timbrado
--}}
@extends('emails.es-419.email_layout')

@section('content')
<h1>Llamada perdida{{ $attributes['caller_id_number'] ? ' de ' . $attributes['caller_id_number'] : '' }}.</h1>

<p>Una llamada a {{ $attributes['ring_group_display'] ?: 'tu grupo de timbrado' }} no fue atendida.</p>

<ul>
    <li><strong>De:</strong> {{ $attributes['caller_display'] ?: 'Persona que llama desconocida' }}</li>
    <li><strong>Para:</strong> {{ $attributes['ring_group_display'] ?: 'Grupo de timbrado' }}</li>
    @if (!empty($attributes['destination_number']))
        <li><strong>Número marcado:</strong> {{ $attributes['destination_number'] }}</li>
    @endif
</ul>

<p>Gracias,<br>El equipo de {{ config('app.name', 'FS PBX') }}</p>
@endsection
