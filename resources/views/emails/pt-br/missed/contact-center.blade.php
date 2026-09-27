{{-- email-template
version: 1.0.0
language: pt-br
category: missed
subcategory: contact-center
format: html
layout: standard
subject: Chamada abandonada em {{ $attributes['queue_display'] }}
description: Notificação de chamada abandonada na central de atendimento
--}}
@extends('emails.pt-br.email_layout')

@section('content')
<h1>Chamada abandonada{{ $attributes['caller_id_number'] ? ' de '.$attributes['caller_id_number'] : '' }}.</h1>

<p>Uma pessoa saiu de {{ $attributes['queue_display'] }} antes que um agente atendesse.</p>

<ul>
    <li><strong>De:</strong> {{ $attributes['caller_display'] }}</li>
    <li><strong>Central de Atendimento:</strong> {{ $attributes['queue_display'] }}</li>
    <li><strong>Motivo:</strong> {{ $attributes['departure_reason'] }}</li>
    @if (!empty($attributes['wait_duration']))
        <li><strong>Tempo de espera:</strong> {{ $attributes['wait_duration'] }}</li>
    @endif
    <li><strong>ID da Chamada:</strong> {{ $attributes['call_uuid'] }}</li>
</ul>

<p>Obrigado,<br>A equipe de {{ config('app.name', 'FS PBX') }}</p>
@endsection
