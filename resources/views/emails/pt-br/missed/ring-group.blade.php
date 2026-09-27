{{-- email-template
version: 1.0.0
language: pt-br
category: missed
subcategory: ring-group
format: html
layout: standard
subject: Chamada perdida para {{ $attributes['ring_group_display'] }}
description: Notificação de chamada perdida em um grupo de chamadas
--}}
@extends('emails.pt-br.email_layout')

@section('content')
<h1>Chamada perdida{{ $attributes['caller_id_number'] ? ' de ' . $attributes['caller_id_number'] : '' }}.</h1>

<p>Uma chamada para {{ $attributes['ring_group_display'] ?: 'seu grupo de chamadas' }} não foi atendida.</p>

<ul>
    <li><strong>De:</strong> {{ $attributes['caller_display'] ?: 'Chamador desconhecido' }}</li>
    <li><strong>Para:</strong> {{ $attributes['ring_group_display'] ?: 'Grupo de chamadas' }}</li>
    @if (!empty($attributes['destination_number']))
        <li><strong>Número discado:</strong> {{ $attributes['destination_number'] }}</li>
    @endif
</ul>

<p>Obrigado,<br>A equipe de {{ config('app.name', 'FS PBX') }}</p>
@endsection
