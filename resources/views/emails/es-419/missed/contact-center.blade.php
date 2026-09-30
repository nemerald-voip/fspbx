{{-- email-template
version: 1.0.0
language: es-419
category: missed
subcategory: contact-center
format: html
layout: standard
subject: Llamada abandonada en {{ $attributes['queue_display'] }}
description: Notificación de llamada abandonada en el centro de contacto
--}}
@extends('emails.es-419.email_layout')

@section('content')
<h1>Llamada abandonada{{ $attributes['caller_id_number'] ? ' de '.$attributes['caller_id_number'] : '' }}.</h1>

<p>Una persona abandonó {{ $attributes['queue_display'] }} antes de que respondiera un agente.</p>

<ul>
    <li><strong>De:</strong> {{ $attributes['caller_display'] }}</li>
    <li><strong>Centro de contacto:</strong> {{ $attributes['queue_display'] }}</li>
    <li><strong>Motivo:</strong> {{ $attributes['departure_reason'] }}</li>
    @if (!empty($attributes['wait_duration']))
        <li><strong>Tiempo de espera:</strong> {{ $attributes['wait_duration'] }}</li>
    @endif
    <li><strong>ID de llamada:</strong> {{ $attributes['call_uuid'] }}</li>
</ul>

<p>Gracias,<br>El equipo de {{ config('app.name', 'FS PBX') }}</p>
@endsection
