{{-- email-template
version: 1.0.0
language: pt-br
category: emergency
subcategory: call
format: html
layout: standard
subject: Chamada de emergência de {{ $attributes['caller'] }}
description: Notificação de chamada de emergência
--}}
@extends('emails.pt-br.email_layout')

@section('content')
<!-- Start Content -->

<h1>Notificação de chamada de emergência</h1>

<p>Foi feita uma chamada de emergência a partir do ramal <strong>{{ $attributes['caller'] }}</strong>.</p>

<p>Tome as medidas necessárias imediatamente.</p>

<p>Obrigado e cuide-se.</p>

@endsection
